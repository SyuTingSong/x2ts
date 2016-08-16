<?php
namespace x2ts\test;
use x2ts\ComponentFactory;

define('X_PROJECT_ROOT', __DIR__);
define('X_RUNTIME_ROOT', __DIR__ . '/runtime');
define('X_DEBUG', true);

ini_set('display_errors', X_DEBUG ? 'On' : 'Off');

require_once dirname(__DIR__) . '/autoload.php';
/**
 * Class ComponentFactory
 * @method static \x2ts\db\Redis redis()
 * @method static \lang\Zh intl(string $lang = null)
 * @method static \x2ts\db\MySQL db()
 */
class XTS extends ComponentFactory {}
