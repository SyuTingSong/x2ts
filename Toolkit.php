<?php

namespace x2ts;

use SimpleXMLIterator;

abstract class Toolkit {
    /**
     * @param $var
     *
     * @throws UncompilableException
     * @return string
     */
    public static function compile($var) {
        if (is_resource($var)) {
            throw new UncompilableException('Resource cannot be compiled to PHP code');
        } else if (is_object($var) && !$var instanceof ICompilable) {
            throw new UncompilableException('The object is no compilable');
        }
        return var_export($var, true);
    }

    /**
     * @param array $dst
     * @param array $src
     *
     * @return array
     */
    public static function &override(&$dst, $src) {
        foreach ($src as $key => $value) {
            if (is_int($key)) {
                $dst[] = $value;
            } else if (!array_key_exists($key, $dst)) {
                $dst[$key] = $value;
            } else if (is_array($value) && is_array($dst[$key])) {
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
     *
     * @param string  $name
     * @param boolean $Pascal
     *
     * @return string
     */
    public static function toCamelCase($name, $Pascal = false) {
        $p = $Pascal ? 'true' : 'false';
        if (!isset(self::$camelCase[$p][$name])) {
            $r = self::extractWords($name);
            $r = ucwords($r);
            if (!$Pascal) {
                $r = lcfirst($r);
            }
            $r = strtr($r, array(' ' => ''));
            self::$camelCase[$p][$name] = $r;
        }
        return self::$camelCase[$p][$name];
    }

    /**
     * Convert $name to snake_case
     *
     * @param string  $name
     * @param boolean $Upper_First_Letter
     *
     * @return string
     */
    public static function to_snake_case($name, $Upper_First_Letter = false) {
        $r = self::extractWords($name);
        if ($Upper_First_Letter) {
            $r = ucwords($r);
        }
        $r = strtr($r, array(' ' => '_'));
        return $r;
    }

    /**
     * @param $word
     *
     * @return mixed|string
     */
    public static function pluralize($word) {
        if ('' === $word || null === $word) {
            return false;
        }
        $plural = array(
            '/(quiz)$/i'               => '$1zes',
            '/^(ox)$/i'                => '$1en',
            '/([m|l])ouse$/i'          => '$1ice',
            '/(matr|vert|ind)ix|ex$/i' => '$1ices',
            '/(x|ch|ss|sh)$/i'         => '$1es',
            '/([^aeiouy]|qu)ies$/i'    => '$1y',
            '/([^aeiouy]|qu)y$/i'      => '$1ies',
            '/(hive)$/i'               => '$1s',
            '/(?:([^f])fe|([lr])f)$/i' => '$1$2ves',
            '/sis$/i'                  => 'ses',
            '/([ti])um$/i'             => '$1a',
            '/(buffal|tomat)o$/i'      => '$1oes',
            '/(bu)s$/i'                => '$1ses',
            '/(alias|status)/i'        => '$1es',
            '/(octop|vir)us$/i'        => '$1i',
            '/(ax|test)is$/i'          => '$1es',
            '/s$/i'                    => 's',
        );

        $uncountableNouns = [
            'air',
            'anger',
            'beauty',
            'equipment',
            'evidence',
            'fish',
            'information',
            'knowledge',
            'love',
            'money',
            'news',
            'research',
            'rice',
            'safety',
            'series',
            'sheep',
            'species',
            'sugar',
            'tea',
            'water',
        ];

        $irregular = [
            'child'  => 'children',
            'leaf'   => 'leaves',
            'man'    => 'men',
            'move'   => 'moves',
            'person' => 'people',
            'sex'    => 'sexes',
        ];

        $lowerWord = strtolower($word);
        foreach ($uncountableNouns as $noun) {
            if (substr($lowerWord, -strlen($noun)) === $noun) {
                return $word . '_list';
            }
        }

        foreach ($irregular as $_singular => $_plural) {
            $length = strlen($_singular);
            if (substr($lowerWord, -$length) === $_singular) {
                return substr($word, 0, -$length) . $_plural;
            }
        }

        foreach ($plural as $search => $replacement) {
            if (($r = preg_replace($search, $replacement, $word)) !== $word) {
                return $r;
            }
        }
        return $word . 's';
    }

    public static $logFile;

    public static $pid;

    public static function log($msg, $logLevel = X_LOG_DEBUG, $category = '', $traceIndex = 1) {
        $logLevelName = array('debug', 'notice', 'warning', 'error');
        $logLevelColor = array("\x1B[35m", "\x1B[32m", "\x1B[33m", "\x1B[31m");
        if ($logLevel >= X_LOG_LEVEL) {
            if (!is_resource(self::$logFile)) {
                self::$logFile = fopen(X_RUNTIME_ROOT . '/app.log', 'a+');
            }
            if ($msg instanceof \Throwable) {
                $logMessage = sprintf(
                    "%s is thrown at %s(%d) with message: %s\nCall stack:\n%s",
                    get_class($msg),
                    $msg->getFile(),
                    $msg->getLine(),
                    $msg->getMessage(),
                    $msg->getTraceAsString()
                );
            } elseif (is_callable($msg)) {
                $logMessage = call_user_func($msg);
            } elseif (!is_string($msg)) {
                ob_start();
                /** @noinspection ForgottenDebugOutputInspection */
                var_dump($msg);
                $logMessage = ob_get_contents();
                ob_end_clean();
            } else {
                $logMessage = (string) $msg;
            }
            if ($category === '') {
                $trace = debug_backtrace();
                if ($traceIndex < count($trace)) {
                    $class = $trace[$traceIndex]['class'] ?? 'FUNC';
                    $func = $trace[$traceIndex]['function'];
                    $category = "$class::$func";
                } else {
                    $category = 'GLOBAL';
                }
            }
            fprintf(
                self::$logFile, "%s[%s][%s][%d][%s]%s\x1B[0m\n",
                $logLevelColor[$logLevel],
                date('c'),
                $logLevelName[$logLevel],
                self::$pid ?? (self::$pid = posix_getpid()),
                $category,
                $logMessage
            );
        }
    }

    public static function trace($msg, $traceIndex = 2) {
        if (X_LOG_DEBUG >= X_LOG_LEVEL) {
            self::log($msg, X_LOG_DEBUG, '', $traceIndex);
        }
    }

    public static function random_chars(int $length): string {
        return substr(
            str_replace(['+', '/', '='], '', base64_encode(
                random_bytes($length << 1)
            )),
            0,
            $length
        );
    }

    /**
     * @param string $name
     *
     * @return string
     */
    private static function extractWords($name) {
        if (strpos($name, '_') === false) {
            $r = preg_replace('#[A-Z]#', ' $0', $name);
        } else {
            $r = strtr($name, array('_' => ' '));
        }
        $r = strtolower(ltrim($r));
        return $r;
    }
}
