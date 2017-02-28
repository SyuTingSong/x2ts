<?php

namespace x2ts;

/**
 * Class Component
 *
 * @package x2ts
 * @property-read array $conf
 */
abstract class Component implements IComponent {
    use TGetterSetter;
    use TConfig;

    /**
     * @static
     * @var array
     */
    protected static $_conf;

    public function __construct() {
        $this->saveHashedConfig();
        if (method_exists($this, 'init')) {
            call_user_func(array($this, 'init'));
        }
    }
}