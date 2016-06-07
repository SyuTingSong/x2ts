<?php

namespace x2ts;

use InvalidArgumentException;
use ReflectionClass;
use x2ts\db\orm\Model;

/**
 * Class ComponentFactory
 * @package x2ts
 * @method static app\Router router()
 * @method static db\IDataBase db()
 * @method static db\orm\Model model($modelName)
 * @method static cache\MCache cache()
 * @method static cache\CCache cc()
 * @method static view\Hail view()
 * @method static rpc\RPC rpc(string $package = null)
 */
abstract class ComponentFactory extends Component {
    protected static $_conf = array(
        'component' => array(
            'router' => array(
                'class' => '\\x2ts\\app\\Router',
                'singleton' => true,
                'conf' => array(
                    'defaultAction' => '/index',
                    'actionSuffix' => 'Action',
                    'actionSuffixOmissible' => false,
                    'baseUri' => '/',
                ),
            ),
            'db' => array(
                'class' => '\\x2ts\\db\\SQLite',
                'singleton' => true,
                'conf' => array(
                    'filename' => X_RUNTIME_ROOT . '/sqlite.db'
                ),
            ),
            'model' => array(
                'class' => '\x2ts\db\orm\Model',
                'singleton' => false,
                'conf' => array(
                    'tablePrefix' => '',
                    'dbId' => 'db',
                    'enableCacheByDefault' => false,
                    'schemaConf' => array(
                        'schemaCacheId' => 'cc',
                        'useSchemaCache' => false,
                        'schemaCacheDuration' => 0,
                    ),
                    'cacheConf' => array(
                        'cacheId' => 'cache',
                        'duration' => 60,
                    ),
                )
            ),
            'cache' => array(
                'class' => '\\x2ts\\MCache',
                'singleton' => true,
                'conf' => array(
                    'host' => 'localhost',
                    'port' => 11211,
                    'persistent' => true,
                    'keyPrefix' => '',
                ),
            ),
            'cc' => array(
                'class' => '\\x2ts\\CICache',
                'singleton' => true,
                'conf' => array(
                    'cacheDir' => X_RUNTIME_ROOT . '/cache',
                ),
            ),
            'view' => array(
                'class' => '\\x2ts\\view\\Hail',
                'singleton' => true,
                'conf' => array(
                    'tpl_dir' => X_PROJECT_ROOT . '/protected/view',
                    'tpl_ext' => 'html',
                    'compile_dir' => X_RUNTIME_ROOT . '/compiled_template',
                    'enable_clip' => false,
                    'cacheId' => 'cc', // string to cache component id or false to disable cache
                    'cacheDuration' => 60, // page cache duration, second
                )
            ),
            'rpc' => array(
                'class' => '\\x2ts\\rpc\\RPC',
                'singleton' => true,
                'conf' => array(
                    'connection' => [
                        'host' => 'localhost',
                        'port' => 5672,
                        'login' => 'guest',
                        'password' => 'guest',
                        'vhost' => '/',
                        'read_timeout' => 0,
                        'write_timeout' => 0,
                        'connect_timeout' => 0,
                    ],
                    'persistent' => false,
                )
            ),
        ),
    );
    private static $_singletons;

    /**
     * @param $componentId
     * @return bool|Component
     */
    public static function getComponent($componentId) {
        return self::__callStatic($componentId, array());
    }

    public static function log($msg, $level = X_LOG_DEBUG, $category = 'app') {
        Toolkit::log($msg, $level, $category);
    }

    public static function trace($msg) {
        Toolkit::trace($msg, 3);
    }


    /**
     * @param string $name
     * @param array $args
     * @return \x2ts\IComponent
     * @throws \x2ts\ComponentNotFoundException
     */
    public static function __callStatic($name, $args) {
        if (isset(self::$_conf['component'][$name])) {
            $desc = self::$_conf['component'][$name];
            if ($desc['singleton'] === false)
                return self::getInstance($desc['class'], $args, $desc['conf']);
            if (!self::$_singletons[$name] instanceof IComponent)
                self::$_singletons[$name] = self::getInstance($desc['class'], $args, $desc['conf']);
            elseif (method_exists(self::$_singletons[$name], '__reconstruct'))
                self::$_singletons[$name]->__reconstruct(...$args);
            return self::$_singletons[$name];
        }
        throw new ComponentNotFoundException("The specified component $name cannot be found in configurations");
    }

    /**
     * @param string $class
     * @param array $args
     * @param array $conf
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
