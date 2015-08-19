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

    /**
     * @param array $properties
     * @return \xts\Compilable
     */
    public static function __set_state($properties) {
        return new static($properties);
    }
}
