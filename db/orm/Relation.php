<?php

namespace x2ts\db\orm;

use x2ts\ICompilable;
use x2ts\Toolkit;

abstract class Relation implements ICompilable {
    public $name;

    public $property;

    public $foreignModelName;

    public $foreignTableName;

    public $foreignTableField;

    public function __construct($array = []) {
        foreach ($array as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * @param array $properties
     *
     * @return \x2ts\ICompilable
     */
    public static function __set_state($properties) {
        return new static($properties);
    }

    /**
     * @param Model  $model
     * @param string $condition [optional]
     * @param array  $params    [optional]
     * @param int    $offset    [optional]
     * @param int    $limit     [optional]
     *
     * @return array
     */
    public abstract function fetchRelated(
        Model $model,
        $condition = null,
        $params = [],
        $offset = null,
        $limit = null
    );
}
