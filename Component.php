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
    protected static $_confObject;

    /**
     * @param array|null $conf
     * @return void|\stdClass
     */
    public static function conf($conf = null) {
        if (!is_null($conf) && is_array($conf))
            Toolkit::override(static::$_conf, $conf);
        if (empty(static::$_confObject))
            static::$_confObject = json_decode(json_encode(static::$_conf));
        return static::$_confObject;
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