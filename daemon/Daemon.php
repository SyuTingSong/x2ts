<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2016/6/7
 * Time: 上午11:19
 */

namespace x2ts\daemon;


use swoole_process;
use x2ts\Component;
use x2ts\ExtensionNotLoadedException;
use x2ts\Toolkit;

if (!extension_loaded('swoole')) {
    throw new ExtensionNotLoadedException('The x2ts\daemon\Daemon required extension swoole has not been loaded yet');
}

class Daemon extends Component {
    protected static $_conf = [
        'workerNum'     => 1,
        'autoRestart'   => false,
        'daemonize'     => false,
        'name'          => '',
        'onWorkerStart' => null,
    ];
    private $workerNum;
    private $autoRestart;
    private $daemonize;
    private $name;
    private $onWorkerStart;
    private $workers = [];

    /**
     * Daemon constructor.
     * @param array $settings
     */
    public function __construct(array $settings = []) {
        foreach ($this->conf as $key => $value) {
            $this->$key = $settings[$key] ?? $value;
        }
        parent::__construct();
    }

    public function onWorkerStart(callable $callback) {
        $this->onWorkerStart = $callback;
        return $this;
    }

    public function _workerStartDelegate(swoole_process $worker) {
        if ($this->name)
            @swoole_set_process_name($this->name . ': worker');
        Toolkit::log($this->name . ' worker start', X_LOG_NOTICE);
        return call_user_func($this->onWorkerStart, $worker);
    }

    public function _signalChild() {
        Toolkit::log('[' . posix_getpid() . '] Receive SIGCHLD', X_LOG_NOTICE);
        while ($p = swoole_process::wait(false)) {
            unset($this->workers[$p['pid']]);
            if ($this->autoRestart) {
                Toolkit::log('Auto restart', X_LOG_NOTICE);
                $worker = new swoole_process([$this, '_workerStartDelegate']);
                if ($worker->start())
                    $this->workers[$worker->pid] = $worker;
                else
                    Toolkit::log(swoole_strerror(swoole_errno()), X_LOG_ERROR, 'daemon');
            } elseif (count($this->workers) === 0) {
                Toolkit::log('All child processes killed, exit', X_LOG_NOTICE);
                exit(0);
            }
        }
    }

    public function _signalTerm() {
        Toolkit::log('Receive SIGTERM', X_LOG_NOTICE);
        $this->autoRestart = false;
        foreach ($this->workers as $worker) {
            swoole_process::kill($worker->pid, SIGQUIT);
        }
    }

    public function run(callable $onWorkerStart = null) {
        if (is_callable($onWorkerStart)) {
            $this->onWorkerStart = $onWorkerStart;
        }
        Toolkit::log($this->name . ' master start', X_LOG_NOTICE);
        if ($this->daemonize) {
            swoole_process::daemon(true);
            Toolkit::log('Daemonized', X_LOG_NOTICE);
        }
        swoole_process::signal(SIGTERM, [$this, '_signalTerm']);
        swoole_process::signal(SIGCHLD, [$this, '_signalChild']);
        if ($this->name)
            @swoole_set_process_name($this->name . ': master');
        for ($i = 0; $i < $this->workerNum; $i++) {
            $worker = new swoole_process([$this, '_workerStartDelegate']);
            if ($worker->start())
                $this->workers[$worker->pid] = $worker;
        }
    }
}