<?php

namespace x2ts\cache;
use x2ts\Component;
use x2ts\Toolkit;
use x2ts\MethodNotImplementException;

/**
 * Class CCache
 * @package x2ts\cache
 */
class CCache extends Component implements ICache {
    protected static $_conf = array(
        'cacheDir' => '.',
    );

    public function init() {
        Toolkit::trace('CCache init');
        if (!is_dir(static::$_conf['cacheDir']))
            mkdir(static::$_conf['cacheDir'], 0777, true);
    }

    private function key2file($key) {
        return self::$_conf['cacheDir'] . DIRECTORY_SEPARATOR . rawurlencode($key) . '.php';
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function get($key) {
        $file = $this->key2file($key);
        if (is_file($file)) {
            $r = require($file);
            if (!empty($r) && $key === $r['key'] && ($r['expiration'] == 0 || time() <= $r['expiration'])) {
                Toolkit::trace("CCache hit $key");
                return $r['data'];
            }
        }
        Toolkit::trace("CCache miss $key");
        return false;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int $duration
     * @return void
     */
    public function set($key, $value, $duration) {
        Toolkit::trace("CCache set $key");
        $file = $this->key2file($key);
        $content = array(
            'key' => $key,
            'expiration' => $duration > 0 ? time() + $duration : 0,
            'data' => $value,
        );
        $phpCode = '<?php return ' . Toolkit::compile($content) . ';';
        if (function_exists('opcache_invalidate'))
            opcache_invalidate($file, true);
        file_put_contents($file, $phpCode, LOCK_EX);
    }

    /**
     * @param string $key
     * @return boolean
     */
    public function remove($key) {
        Toolkit::trace("CCache remove $key");
        $file = $this->key2file($key);
        if (is_file($file)) {
            unlink($file);
            return true;
        }
        return false;
    }

    /**
     * @throws MethodNotImplementException
     * @return void
     */
    public function flush() {
        throw new MethodNotImplementException("CCache not supports flush");
    }
}
