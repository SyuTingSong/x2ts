<?php

namespace x2ts;

/**
 * Class Component
 * @package x2ts
 * @property-read array $conf
 */
abstract class Component implements IComponent {
    use TGetterSetter;

    /**
     * @static
     * @var array
     */
    protected static $_conf;

    /**
     * @param array|null $conf
     * @return void|array
     */
    public static function conf($conf = null) {
        if (!is_null($conf) && is_array($conf))
            Toolkit::override(static::$_conf, $conf);
        return static::$_conf;
    }

    /**
     * @return array
     */
    public function getConf() {
        return static::$_conf;
    }

    public function __construct() {
        if (method_exists($this, 'init')) {
            call_user_func(array($this, 'init'));
        }
    }
}