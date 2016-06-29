<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2016/5/27
 * Time: 下午4:04
 */

namespace x2ts\rpc;

use AMQPChannel;
use AMQPConnection;
use AMQPEnvelope;
use AMQPExchange;
use AMQPQueue;
use AMQPQueueException;
use Throwable;
use X;
use x2ts\Component;
use x2ts\ExtensionNotLoadedException;
use x2ts\Toolkit;

if (!extension_loaded('amqp')) {
    throw new ExtensionNotLoadedException('The x2ts\rpc\RPC required extension amqp has not been loaded yet');
}
if (!extension_loaded('msgpack')) {
    throw new ExtensionNotLoadedException('The x2ts\rpc\RPC required extension msgpack has not been loaded yet');
}

/**
 * Class RPC
 * @package x2ts\rpc
 *
 * @property-read AMQPChannel $serverChannel
 * @property-read AMQPChannel $clientChannel
 */
class RPC extends Component {
    protected static $_conf = [
        'connection' => [
            'host'            => 'localhost',
            'port'            => 5672,
            'login'           => 'guest',
            'password'        => 'guest',
            'vhost'           => '/',
            'read_timeout'    => 30,
            'write_timeout'   => 30,
            'connect_timeout' => 30,
        ],
        'persistent' => false,
        'maxRequest' => 500,
    ];
    private $_serverChannel;
    private $_clientChannel;
    private $_serverExchange;
    private $package;
    private $callbacks;

    public function __construct(string $package = 'common') {
        $this->callbacks = [];
        $this->setPackage($package);
        parent::__construct();
    }

    private function getServerExchange() {
        if (!$this->_serverExchange instanceof AMQPExchange) {
            $this->_serverExchange = new AMQPExchange($this->serverChannel);
        }
        return $this->_serverExchange;
    }

    public function setPackage(string $package):RPC {
        $this->package = "rpc.$package";
        return $this;
    }

    private function getConnection(array $conf, bool $persistent = false):AMQPConnection {
        $connection = new AMQPConnection($conf);
        if ($persistent) {
            $connection->pconnect();
        } else {
            $connection->connect();
        }
        return $connection;
    }

    protected function getServerChannel() {
        if (!$this->_serverChannel instanceof AMQPChannel) {
            $conf = $this->conf['connection'];
            $conf['read_timeout'] = 0;

            $this->_serverChannel = new AMQPChannel($this->getConnection(
                $conf,
                (bool) $this->conf['persistent']
            ));
            $this->_serverChannel->setPrefetchCount(1);
        }
        return $this->_serverChannel;
    }

    protected function getClientChannel() {
        if (!$this->_clientChannel instanceof AMQPChannel) {
            $this->_clientChannel = new AMQPChannel($this->getConnection(
                $this->conf['connection'],
                $this->conf['persistent']
            ));
            $this->_clientChannel->setPrefetchCount(1);
        }
        return $this->_clientChannel;
    }

    private function checkPackage() {
        try {
            $q = new AMQPQueue($this->clientChannel);
            $q->setFlags(AMQP_DURABLE | AMQP_PASSIVE);
            $q->setName($this->package);
            $q->declareQueue();
        } catch (AMQPQueueException $ex) {
            throw new PackageNotFoundException();
        }
    }

    public function call(string $name, ...$args) {
        $this->checkPackage();
        return (new Request($this->clientChannel, $this->package, $name, $args))->send()->getResponse();
    }

    public function callVoid(string $name, ...$args):void {
        $this->checkPackage();
        (new Request($this->clientChannel, $this->package, $name, $args))->send();
    }

    public function asyncCall(string $name, ...$args):Response {
        $this->checkPackage();
        return (new Request($this->clientChannel, $this->package, $name, $args))->send();
    }

    public function register(string $name, callable $method = null):RPC {
        if ($method === null) {
            $this->callbacks[$name] = $name;
        } else {
            $this->callbacks[$name] = $method;
        }
        return $this;
    }

    protected $requestCounter = 0;

    /**
     * @param AMQPEnvelope $msg
     * @param AMQPQueue $q
     * @return bool
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     */
    public function _onRequest(AMQPEnvelope $msg, AMQPQueue $q) {
        $GLOBALS['_rpc_server_shutdown'] = [$this->getServerExchange(), $msg, $q];
        error_clear_last();
        $callInfo = msgpack_unpack($msg->getBody());
        $error = error_get_last();
        if (!empty($error)) {
            $payload = [
                'error'     => $error,
                'exception' => [
                    'class'  => 'PacketFormatException',
                    'args'   => [
                        'Request packet format error: ' . $error['message'],
                        PacketFormatException::REQUEST,
                    ],
                    'result' => null,
                ],
            ];
            Toolkit::log($payload['exception']['args'][0], X_LOG_WARNING);
            goto reply;
        }
        Toolkit::trace($callInfo);

        try {
            if (array_key_exists($callInfo['name'], $this->callbacks)) {
                error_clear_last();
                $r = call_user_func_array($this->callbacks[$callInfo['name']], $callInfo['args']);
                if ($callInfo['void']) {
                    goto finish;
                }
                $payload = [
                    'error'     => error_get_last(),
                    'exception' => null,
                    'result'    => $r,
                ];
            } else {
                $payload = [
                    'error'     => 'Specified RPC function not exist',
                    'exception' => [
                        'class' => 'UnregisteredFunctionException',
                        'args'  => ["Specified RPC function {$this->package}.{$callInfo['name']} is unregistered."],
                    ],
                    'result'    => null,
                ];
                Toolkit::log($payload['exception']['args'][0], X_LOG_WARNING);
            }
        } catch (Throwable $e) {
            $message = get_class($e) . ' thrown in remote file "' . $e->getFile()
                . '" (line: ' . $e->getLine() . ') with code ' . $e->getCode() .
                ' message: ' . $e->getMessage() . "\n\nRemote Call stack:\n"
                . $e->getTraceAsString();
            $payload = [
                'error'     => error_get_last(),
                'exception' => [
                    'class' => 'RPCException',
                    'args'  => [$message],
                ],
                'result'    => null,
            ];
            Toolkit::log($payload['exception']['args'][0], X_LOG_WARNING);
        }

        reply:
        $this->getServerExchange()->publish(
            msgpack_pack($payload),
            $msg->getReplyTo(),
            AMQP_NOPARAM,
            ['correlation_id' => $msg->getCorrelationId()]
        );

        finish:
        $q->ack($msg->getDeliveryTag());
        if ($this->conf['maxRequest']) {
            if (++$this->requestCounter > $this->conf['maxRequest']) {
                Toolkit::log(
                    'Max request limit exceed. Exit the rpc loop',
                    X_LOG_NOTICE
                );
                error_clear_last();
                return false; // exit consume loop
            }
        }
        return true;
    }

    private function register_rpc_server_shutdown_function() {
        register_shutdown_function(function () {
            $error = error_get_last();
            if (empty($error)) {
                return;
            }
            if ($error['type'] & (
                    E_ALL &
                    ~E_NOTICE &
                    ~E_WARNING &
                    ~E_DEPRECATED &
                    ~E_USER_NOTICE &
                    ~E_USER_WARNING &
                    ~E_USER_DEPRECATED &
                    ~E_STRICT
                )
            ) {
                $payload = [
                    'error'     => $error,
                    'exception' => [
                        'class' => 'RPCException',
                        'args'  => [
                            "An error \"{$error['message']}\" in remote file "
                            . "\"{$error['file']}\" (line: {$error['line']}) "
                            . 'cause the rpc worker down. Please report this '
                            . 'issue to the rpc server administrator as soon '
                            . 'as possible.',
                            $error['type'],
                        ],
                    ],
                    'result'    => null,
                ];
                Toolkit::log($payload['exception']['args'][0], X_LOG_ERROR);

                /**
                 * @var AMQPExchange $e
                 * @var AMQPEnvelope $m
                 * @var AMQPQueue $q
                 */
                list ($e, $m, $q) = $GLOBALS['_rpc_server_shutdown'];
                if ($e instanceof AMQPExchange) {
                    $e->publish(
                        msgpack_pack($payload),
                        $m->getReplyTo(),
                        AMQP_NOPARAM,
                        ['correlation_id' => $m->getCorrelationId()]
                    );
                    $q->reject($m->getDeliveryTag());
                }
            } else {
                Toolkit::log($error['message'], X_LOG_NOTICE);
            }
        });
    }

    public function listen() {
        X::trace('listen start');
        $queue = new AMQPQueue($this->serverChannel);
        $queue->setName($this->package);
        $queue->setFlags(AMQP_DURABLE);
        $queue->declareQueue();
        $this->register_rpc_server_shutdown_function();
        $queue->consume([$this, '_onRequest']);
    }

    public function __reconstruct($package) {
        $this->setPackage($package);
    }
}

