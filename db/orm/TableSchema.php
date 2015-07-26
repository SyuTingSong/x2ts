<?php

namespace x2ts\db\orm;

use x2ts\db\IDataBase;
use x2ts\TGetterSetter;

abstract class TableSchema {
    use TGetterSetter;
    /**
     * @param string $tableName
     * @param IDataBase $db
     */
    public function __construct($tableName, $db) {}

    public abstract function getColumns();

    public abstract function getIndexes();

    public abstract function getRelations();
}