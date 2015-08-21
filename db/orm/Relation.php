<?php

namespace x2ts\db\orm;

use x2ts\ICompilable;

abstract class Relation implements ICompilable{
    public $name;
    public $property;
    public $foreignModelName;
    public $foreignTableName;
    public $foreignTableField;

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
     * @return \x2ts\ICompilable
     */
    public static function __set_state($properties) {
        return new static($properties);
    }
}
