<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 15/8/10
 * Time: 下午7:25
 */

namespace x2ts;

trait TConfig {
    protected $_confHash = null;

    /**
     * @param array|null $conf
     * @return void
     */
    public static function conf($conf = null) {
        Toolkit::override(static::$_conf, $conf);
    }

    /**
     * @return array
     */
    public function getConf() {
        if ($this->_confHash &&
            array_key_exists(
                $this->_confHash,
                Configuration::$configuration
            )
        ) {
            return Configuration::$configuration[$this->_confHash];
        }
        return static::$_conf;
    }

    private function saveHashedConfig() {
        $this->_confHash = md5(serialize(static::$_conf));
        Configuration::$configuration[$this->_confHash] = static::$_conf;
    }
}