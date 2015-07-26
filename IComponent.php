<?php
namespace x2ts;

interface IComponent {
    /**
     * @param string $name
     * @return mixed
     */
    public function __get($name);

    /**
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function __set($name, $value);

    /**
     * @param string $name
     * @return boolean
     */
    public function __isset($name);

    /**
     * @param array|null $conf
     * @return void|array
     */
    public static function conf($conf = null);

    /**
     * @return array
     */
    public function getConf();
}