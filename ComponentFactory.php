<?php

namespace x2ts;

use InvalidArgumentException;
use ReflectionClass;
use stdClass;
use x2ts\app\Action;

/**
 * Class ComponentFactory
 *
 * @package x2ts
 * @method static app\Router router()
 * @method static db\IDataBase db()
 * @method static db\orm\Model model($modelName)
 * @method static cache\MCache cache()
 * @method static cache\CCache cc()
 * @method static Token token(string $token = '')
 * @method static Session session()
 * @method static view\Hail view()
 * @method static rpc\RPC rpc(string $package = null)
 * @method static daemon\Daemon daemon(array $settings = [])
 * @method static Utils utils()
 * @method static validator\Validator validator(array $vars)
 * @method static event\Bus bus()
 */
abstract class ComponentFactory extends Component {
    /**
     * @var array
     */
    protected static $_conf = [
        'component' => [
            'bus'       => [
                'class'     => event\Bus::class,
                'singleton' => true,
                'conf'      => [],
            ],
            'router'    => [
                'class'     => app\Router::class,
                'singleton' => true,
                'conf'      => [
                    'defaultAction'         => '/index',
                    'actionSuffix'          => 'Action',
                    'actionSuffixOmissible' => false,
                    'baseUri'               => '/',
                    'fallbackActionClass'   => false,
                ],
            ],
            'db'        => [
                'class'     => db\SQLite::class,
                'singleton' => true,
                'conf'      => [
                    'filename' => X_RUNTIME_ROOT . '/sqlite.db',
                ],
            ],
            'model'     => [
                'class'     => db\orm\Model::class,
                'singleton' => false,
                'conf'      => [
                    'namespace'   => 'model',
                    'tablePrefix' => '',
                    'dbId'        => 'db',
                    'schemaConf'  => [
                        'schemaCacheId'       => 'cc',
                        'useSchemaCache'      => true,
                        'schemaCacheDuration' => 300,
                    ],
                    'manager'     => [
                        'class' => db\orm\DirectModelManager::class,
                        'conf'  => [],
                    ],
                ],
            ],
            'cache'     => [
                'class'     => cache\MCache::class,
                'singleton' => true,
                'conf'      => [
                    'host'       => 'localhost',
                    'port'       => 11211,
                    'persistent' => true,
                    'keyPrefix'  => '',
                ],
            ],
            'cc'        => [
                'class'     => cache\CCache::class,
                'singleton' => true,
                'conf'      => [
                    'cacheDir' => X_RUNTIME_ROOT . '/cache',
                ],
            ],
            'token'     => [
                'class'     => Token::class,
                'singleton' => false,
                'conf'      => [
                    'saveComponentId' => 'cache',
                    'saveKeyPrefix'   => 'tok_',
                    'tokenLength'     => 16,
                    'autoSave'        => true,
                    'expireIn'        => 300,
                ],
            ],
            'session'   => [
                'class'     => Session::class,
                'singleton' => false,
                'conf'      => [
                    'saveComponentId' => 'cache',
                    'saveKeyPrefix'   => 'session_',
                    'tokenLength'     => 16,
                    'autoSave'        => true,
                    'expireIn'        => 604800,
                    'cookie'          => [
                        'name'     => 'X_SESSION_ID',
                        'expireIn' => null,
                        'path'     => '/',
                        'domain'   => null,
                        'secure'   => null,
                        'httpOnly' => true,
                    ],
                ],
            ],
            'view'      => [
                'class'     => view\Hail::class,
                'singleton' => true,
                'conf'      => [
                    'tpl_dir'       => X_PROJECT_ROOT . '/protected/view',
                    'tpl_ext'       => 'html',
                    'compile_dir'   => X_RUNTIME_ROOT . '/compiled_template',
                    'enable_clip'   => false,
                    'cacheId'       => 'cc', // string to cache component id or false to disable cache
                    'cacheDuration' => 60, // page cache duration, second
                ],
            ],
            'rpc'       => [
                'class'     => rpc\RPC::class,
                'singleton' => true,
                'conf'      => [
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
                ],
            ],
            'daemon'    => [
                'class'     => daemon\Daemon::class,
                'singleton' => false,
                'conf'      => [
                    'workerNum'     => 1,
                    'autoRestart'   => false,
                    'daemonize'     => false,
                    'name'          => '',
                    'onWorkerStart' => null,
                    'pidFile'       => X_RUNTIME_ROOT . '/daemon.pid',
                    'lockFile'      => X_RUNTIME_ROOT . '/daemon.lock',
                    'user'          => '',
                    'group'         => '',
                ],
            ],
            'utils'     => [
                'class'     => Utils::class,
                'singleton' => true,
                'conf'      => [
                    'ldap' => [
                        'host'           => 'localhost',
                        'port'           => 389,
                        'dn_base'        => 'ou=staffs,dc=example,dc=com',
                        'auth_key'       => 'uid',
                        'admin_dn'       => 'cn=admin,dc=example,dc=com',
                        'admin_password' => '',
                    ],
                ],
            ],
            'validator' => [
                'class'     => validator\Validator::class,
                'singleton' => false,
                'conf'      => [
                    'encoding' => 'UTF-8',
                    'autoTrim' => true,
                ],
            ],
        ],
    ];

    /**
     * @var array
     */
    private static $_singletons = [];

    /**
     * @var stdClass
     */
    protected static $_confObject;

    /**
     * @param array|string|null $conf
     *
     * @return array|stdClass
     */
    public static function conf($conf = null) {
        if (null !== $conf) {
            if (is_array($conf)) {
                Toolkit::override(static::$_conf, $conf);
                return static::$_conf;
            } else if (is_string($conf)) {
                return static::$_conf[$conf] ?? null;
            }
        }
        if (!static::$_confObject instanceof stdClass) {
            static::$_confObject = json_decode(json_encode(static::$_conf));
        }
        return static::$_confObject;
    }

    /**
     * @param $componentId
     *
     * @return bool|Component
     * @throws \x2ts\ComponentNotFoundException
     */
    public static function getComponent($componentId) {
        /** @noinspection ImplicitMagicMethodCallInspection */
        return self::__callStatic($componentId, array());
    }

    public static function log($msg, $level = X_LOG_DEBUG, $category = '', $traceIndex = 2) {
        Toolkit::log($msg, $level, $category, $traceIndex);
    }

    public static function trace($msg, $traceIndex = 3) {
        Toolkit::trace($msg, $traceIndex);
    }

    /**
     * @param Action $action
     *
     * @return Action
     * @throws ActionNotBindingException
     */
    public static function action($action = null): Action {
        if ($action instanceof Action) {
            self::$_singletons['action'] = $action;
            return $action;
        } else if (self::$_singletons['action'] instanceof Action) {
            return self::$_singletons['action'];
        } else {
            throw new ActionNotBindingException('action is available after routing');
        }
    }

    /**
     * @param string $name
     * @param array  $args
     *
     * @return \x2ts\IComponent
     * @throws \InvalidArgumentException
     * @throws \x2ts\ComponentNotFoundException
     */
    public static function __callStatic($name, $args) {
        if (isset(self::$_conf['component'][$name])) {
            $desc = self::$_conf['component'][$name];
            if ($desc['singleton'] === false) {
                return self::getInstance($desc['class'], $args, $desc['conf']);
            }
            if (!isset(self::$_singletons[$name]) || !self::$_singletons[$name] instanceof IComponent) {
                self::$_singletons[$name] = self::getInstance($desc['class'], $args, $desc['conf']);
            } elseif (method_exists(self::$_singletons[$name], '__reconstruct')) {
                self::$_singletons[$name]->__reconstruct(...$args);
            }
            return self::$_singletons[$name];
        }
        throw new ComponentNotFoundException("The specified component $name cannot be found in configurations");
    }

    /**
     * @param string $class
     * @param array  $args
     * @param array  $conf
     *
     * @throws InvalidArgumentException
     * @return \x2ts\IComponent
     */
    public static function getInstance($class, $args = array(), $conf = array()) {
        $rfClass = new ReflectionClass($class);
        if ($rfClass->isSubclassOf('x2ts\IComponent')) {
            $rfClass->getMethod("conf")->invoke(null, $conf);
            if ($rfClass->hasMethod('getInstance')) {
                return $rfClass->getMethod('getInstance')->invokeArgs(null, $args);
            }
            return $rfClass->newInstanceArgs($args);
        }
        throw new InvalidArgumentException("class $class is not an instance of x2ts\\IComponent");
    }
}
