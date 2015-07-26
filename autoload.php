<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 15/3/26
 * Time: 上午11:54
 */

defined('X_LIB_ROOT') or define('X_LIB_ROOT', dirname(__DIR__));
define('X_LOG_DEBUG', 0);
define('X_LOG_NOTICE', 1);
define('X_LOG_WARNING', 2);
define('X_LOG_ERROR', 3);
defined('X_LOG_LEVEL') or define('X_LOG_LEVEL', X_LOG_DEBUG);
define('X_RUNTIME_ROOT', __DIR__ . DIRECTORY_SEPARATOR . 'runtime');

$__x2ts_autoload_not_exist_classes = [];
spl_autoload_register(function ($className) {
    global $__x2ts_autoload_not_exist_classes;
    if (isset($__x2ts_autoload_not_exist_classes[$className]))
        return;
    $file = X_LIB_ROOT . DIRECTORY_SEPARATOR
        . (DIRECTORY_SEPARATOR == '\\' ? $className : str_replace('\\', DIRECTORY_SEPARATOR, $className))
        . '.php';
    if (is_file($file)) {
        include_once($file);
    } else {
        $__x2ts_autoload_not_exist_classes[$className] = true;
    }
});
