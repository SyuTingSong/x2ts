<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2016/7/12
 * Time: 下午8:31
 */

namespace x2ts\validator;


class HexadecimalValidator extends IntegerValidator {
    public function __construct($var, $shell = null) {
        $this->shell = $this;
        if ($shell instanceof Validator) {
            $this->shell = $shell;
        }

        if (is_int($var) || ctype_xdigit($var) || (strpos($var, '0x') === 0 && ctype_xdigit(substr($var, 2)))) {
            $this->_unsafeVar = intval($var, 16);
        } else {
            $this->_unsafeVar = $var;
            $this->_isValid = false;
        }
    }
}
