<?php

namespace x2ts\db\orm;

use x2ts\ICompilable;

class Column implements ICompilable {
    public $name='';
    public $type='int';
    public $defaultValue='';
    public $canBeNull = false;
    public $isPK = false;
    public $isUQ = false;

    public function __construct($array=null) {
        if(is_array($array)) {
            foreach($array as $key => $value) {
                if(property_exists($this, $key)) {
                    $this->$key = $value;
                }
            }
        }
    }
    
    public function isInt():bool {
        return in_array(
            $this->type,
            ['bigint', 'int', 'mediumint', 'smallint', 'tinyint']
            ,true
        );
    }

    public function isFloat():bool {
        return in_array(
            $this->type,
            array('decimal', 'float', 'real', 'double'),
            true
        );
    }
    /**
     * @param array $properties
     * @return \x2ts\ICompilable
     */
    public static function __set_state($properties) {
        return new static($properties);
    }
}
