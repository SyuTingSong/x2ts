<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2016/7/12
 * Time: 下午9:11
 */

namespace x2ts\validator;


class DecimalValidator extends IntegerValidator {
    public function __construct($var, $shell = null) {
        if ($shell instanceof Validator) {
            $this->shell = $shell;
        } else {
            $this->shell = $this;
        }

        if (is_int($var) || ctype_digit($var) || ($var[0] === '-' && ctype_digit(substr($var, 1)))) {
            $this->_unsafeVar = (int) $var;
        } else {
            $this->_unsafeVar = $var;
            $this->_isValid = false;
        }
    }
}
