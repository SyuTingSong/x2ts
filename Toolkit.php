<?php

namespace x2ts;

abstract class Toolkit {
    /**
     * @param $var
     * @throws UncompilableException
     * @return string
     */
    public static function compile($var) {
        if (is_resource($var)) {
            throw new UncompilableException("Resource cannot be compiled to PHP code");
        } else if (is_object($var) && !$var instanceof ICompilable) {
            throw new UncompilableException("The object is no compilable");
        }
        return var_export($var, true);
    }

    /**
     * @param array $dst
     * @param array $src
     * @return array
     */
    public static function &override(&$dst, $src) {
        foreach ($src as $key => $value) {
            if (is_int($key)) {
                $dst[] = $value;
            } else if (!isset($dst[$key])) {
                $dst[$key] = $value;
            } else if (is_array($dst[$key]) && is_array($value)) {
                self::override($dst[$key], $value);
            } else {
                $dst[$key] = $value;
            }
        }
        return $dst;
    }

    private static $camelCase = array();

    /**
     * Convert $name to camelCase
     * @param string $name
     * @param boolean $Pascal
     * @return string
     */
    public static function toCamelCase($name, $Pascal = false) {
        $p = $Pascal ? 'true' : 'false';
        if (!isset(self::$camelCase[$p][$name])) {
            $r = self::extractWords($name);
            $r = ucwords($r);
            if (!$Pascal)
                $r = lcfirst($r);
            $r = strtr($r, array(' ' => ''));
            self::$camelCase[$p][$name] = $r;
        }
        return self::$camelCase[$p][$name];
    }

    /**
     * Convert $name to snake_case
     * @param string $name
     * @param boolean $Upper_First_Letter
     * @return string
     */
    public static function to_snake_case($name, $Upper_First_Letter = false) {
        $r = self::extractWords($name);
        if ($Upper_First_Letter)
            $r = ucwords($r);
        $r = strtr($r, array(' ' => '_'));
        return $r;
    }


    /**
     * @param $word
     * @return mixed|string
     */
    public static function pluralize($word) {
        $plural = array(
            '/(quiz)$/i' => '$1zes',
            '/^(ox)$/i' => '$1en',
            '/([m|l])ouse$/i' => '$1ice',
            '/(matr|vert|ind)ix|ex$/i' => '$1ices',
            '/(x|ch|ss|sh)$/i' => '$1es',
            '/([^aeiouy]|qu)ies$/i' => '$1y',
            '/([^aeiouy]|qu)y$/i' => '$1ies',
            '/(hive)$/i' => '$1s',
            '/(?:([^f])fe|([lr])f)$/i' => '$1$2ves',
            '/sis$/i' => 'ses',
            '/([ti])um$/i' => '$1a',
            '/(buffal|tomat)o$/i' => '$1oes',
            '/(bu)s$/i' => '$1ses',
            '/(alias|status)/i' => '$1es',
            '/(octop|vir)us$/i' => '$1i',
            '/(ax|test)is$/i' => '$1es',
            '/s$/i' => 's',
            '/$/' => 's'
        );

        $uncountable = array(
            'equipment',
            'information',
            'rice',
            'money',
            'species',
            'series',
            'fish',
            'sheep'
        );

        $irregular = array(
            'person' => 'people',
            'man' => 'men',
            'child' => 'children',
            'sex' => 'sexes',
            'move' => 'moves',
            'leaf' => 'leaves',
        );

        foreach ($uncountable as $_uncountable) {
            if (substr(strtolower($word), -strlen($_uncountable)) == $_uncountable) {
                return $word;
            }
        }

        foreach ($irregular as $_singular => $_plural) {
            $length = strlen($_singular);
            if (substr($word, -$length) == $_singular) {
                return substr($word, 0, -$length) . $_plural;
            }
        }

        foreach ($plural as $search => $replacement) {
            if (($r = preg_replace($search, $replacement, $word)) != $word) {
                return $r;
            }
        }
        return false;
    }

    public static $logFile;

    public static function log($msg, $logLevel = X_LOG_DEBUG, $category = 'app') {
        $logLevelName = array('debug', 'notice', 'warning', 'error');
        $logLevelColor = array("\x1B[35m", "\x1B[32m", "\x1B[33m", "\x1B[31m");
        if ($logLevel >= X_LOG_LEVEL) {
            if (!is_resource(self::$logFile)) {
                self::$logFile = fopen(X_RUNTIME_ROOT.'/app.log', 'a+');
            }
            if (is_callable($msg)) {
                $logMessage = call_user_func($msg);
            } elseif (is_array($msg) || is_object($msg)) {
                ob_start();
                var_dump($msg);
                $logMessage = ob_get_contents();
                ob_end_clean();
            } else {
                $logMessage = strval($msg);
            }
            fprintf(
                self::$logFile, "%s[%s][%s][%s]%s\x1B[0m\n",
                $logLevelColor[$logLevel],
                date('c'),
                $logLevelName[$logLevel],
                $category,
                $logMessage
            );
        }
    }

    public static function trace($msg, $traceIndex = 1) {
        if (X_LOG_DEBUG >= X_LOG_LEVEL) {
            $trace = debug_backtrace();
            $class = isset($trace[$traceIndex]['class']) ? $trace[$traceIndex]['class'] : 'FUNC';
            $func = $trace[$traceIndex]['function'];
            self::log($msg, X_LOG_DEBUG, "$class::$func");
        }
    }


    /**
     * @param string $name
     * @return string
     */
    private static function extractWords($name) {
        if (strpos($name, '_') === false) {
            $r = preg_replace('#[A-Z]#', ' $0', lcfirst($name));
        } else {
            $r = strtr($name, array('_' => ' '));
        }
        $r = strtolower(trim($r));
        return $r;
    }
}
