<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 15/8/10
 * Time: 下午7:25
 */

namespace x2ts;

trait TConfig {
    protected static $_conf=array();

    /**
     * @param array|null $conf
     * @return void|array
     */
    public static function conf($conf = null) {
        Toolkit::override(static::$_conf, $conf);
    }

    /**
     * @return array
     */
    public function getConf() {
        return static::$_conf;
    }
}