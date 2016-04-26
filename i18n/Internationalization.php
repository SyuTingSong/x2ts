<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2016/3/22
 * Time: 下午4:37
 */

namespace x2ts\i18n;

use x2ts\Component;

class Internationalization extends Component {
    protected static $_conf = array(
        'default' => 'En',
    );
    protected static $messages = array();

    public static function getInstance($lang = null) {
        if (!empty($lang)) {
            $class = "\\lang\\$lang";
            return new $class();
        }
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            preg_match_all(
                '#([\w\-, ]+); ?q=([01]\.\d)#',
                $_SERVER['HTTP_ACCEPT_LANGUAGE'],
                $m,
                PREG_SET_ORDER
            );
            usort($m, function ($a, $b) {
                $a = floatval($a[2]) * 100;
                $b = floatval($b[2]) * 100;
                return $b - $a;
            });
            $langs = array();
            foreach ($m as $item) {
                $group = explode(',', $item[1]);
                foreach ($group as $lang) {
                    $lang = trim($lang);
                    if (!empty($lang))
                        $langs[] = str_replace('-', '', ucfirst($lang));
                }
            }
            foreach ($langs as $lang) {
                $class = '\\lang\\' . $lang;
                if (class_exists($class)) {
                    return new $class();
                }
            }
        }

        $class = '\\lang\\' . static::$_conf['default'];
        return new $class();
    }
}