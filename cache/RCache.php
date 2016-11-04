<?php

namespace x2ts\cache;

use Redis;
use x2ts\Component;
use x2ts\Toolkit;

/**
 * Class RCache
 *
 * @package x2ts
 * @property-read Redis $cache
 */
class RCache extends Component implements ICache {
    /**
     * @var Redis $_cache
     */
    protected $_cache;

    /**
     * @varStatic array
     */
    protected static $_conf = array(
        'host'           => 'localhost',
        'port'           => 6379, //int, 6379 by default
        'timeout'        => 0, //float, value in seconds, default is 0 meaning unlimited
        'persistent'     => false, //bool, false by default
        'persistentHash' => 'rcache',//identity for the requested persistent connection
        'database'       => 0, //number, 0 by default
        'auth'           => null, //string, null by default
        'keyPrefix'      => '',
    );

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function get($key) {
        $s = $this->cache->get($key);
        if ($s === false) {
            Toolkit::trace("RCache Miss '$key'");
            return false;
        } else {
            Toolkit::trace("RCache Hit '$key'");
            return is_numeric($s) ? $s : unserialize($s);
        }
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @param int    $duration
     *
     * @return void
     */
    public function set($key, $value, $duration = 0) {
        Toolkit::trace("RCache Set $key");
        $s = is_numeric($value) ? $value : serialize($value);
        $this->cache->set($key, $s, $duration);
    }

    /**
     * @param string $key
     *
     * @return boolean
     */
    public function remove($key) {
        Toolkit::trace("RCache remove '$key'");
        $this->cache->delete($key);
    }

    /**
     * @return void
     */
    public function flush() {
        Toolkit::trace("RCache flush");
        $this->cache->flushDB();
    }

    /**
     * @param string $key
     * @param int    $step
     *
     * @return int
     */
    public function inc($key, $step = 1) {
        Toolkit::trace("RCache inc '$key' by $step");
        if ($step > 1) {
            return $this->cache->incrBy($key, $step);
        } else {
            return $this->cache->incr($key);
        }
    }

    /**
     * @param string $key
     * @param int    $step
     *
     * @return int
     */
    public function dec($key, $step = 1) {
        Toolkit::trace("RCache dec '$key' by $step");
        if ($step > 1) {
            return $this->cache->decrBy($key, $step);
        } else {
            return $this->cache->decr($key);
        }
    }

    /**
     * @return Redis
     */
    public function getCache() {
        if (!$this->_cache instanceof Redis) {
            Toolkit::trace("RCache init");
            $this->_cache = new Redis();
            if (static::$_conf['persistent']) {
                $this->_cache->pconnect(
                    static::$_conf['host'],
                    static::$_conf['port'],
                    static::$_conf['timeout'],
                    static::$_conf['persistentHash']
                );
            } else {
                $this->_cache->connect(static::$_conf['host'], static::$_conf['port'], static::$_conf['timeout']);
            }
            if (static::$_conf['auth']) {
                $this->_cache->auth(static::$_conf['auth']);
            }
            if (static::$_conf['database']) {
                $this->_cache->select(static::$_conf['database']);
            }
            if (static::$_conf['keyPrefix']) {
                $this->_cache->setOption(Redis::OPT_PREFIX, static::$_conf['keyPrefix']);
            }
        }
        return $this->_cache;
    }
}
