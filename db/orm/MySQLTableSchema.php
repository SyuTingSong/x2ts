<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 15/8/18
 * Time: 下午3:44
 */

namespace x2ts\db\orm;


use x2ts\Toolkit;

class MySQLTableSchema extends TableSchema {
    protected static $tables = array();

    public function load() {
        $db = $this->db;
        $cols = $db->query(
            "SELECT * FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA`=:s AND `TABLE_NAME`=:n",
            array(
                ':s' => $db->getDbName(),
                ':n' => $this->name,
            )
        );
        $columns = array();
        $keys = array();
        $relations = array();
        foreach($cols as $col) {
            $column = new Column();
            $column->name = $col['COLUMN_NAME'];
            $column->type = $col['DATA_TYPE'];
            $column->canBeNull = $col['IS_NULLABLE'] == 'YES';
            if(is_null($col['COLUMN_DEFAULT']))
                $column->defaultValue = $column->canBeNull?null:$this->nullToDefault($column->type);
            else
                $column->defaultValue = $col['COLUMN_DEFAULT'];
            if($col['COLUMN_KEY'] == 'PRI') {
                $column->isPK = true;
                $keys['PK'] = $col['COLUMN_NAME'];
            }
            if($col['COLUMN_KEY'] == 'UNI') {
                $column->isUQ = true;
                $keys['UQ'][] = $col['COLUMN_NAME'];
            }
            if($col['COLUMN_KEY'] == 'MUL') {
                $keys['MU'][] = $col['COLUMN_NAME'];
            }
            $columns[$column->name] = $column;
        }
        $rels = $db->query(
            'SELECT * FROM `information_schema`.`KEY_COLUMN_USAGE` WHERE `TABLE_SCHEMA`=:s AND `TABLE_NAME`=:n AND `REFERENCED_TABLE_NAME` IS NOT NULL',
            array(
                ':s' => $db->getDbName(),
                ':n' => $this->name,
            )
        );
        foreach($rels as $rel) {
            $relation = new BelongToRelation();
            $relation->property = $rel['COLUMN_NAME'];
            $relation->foreignTableName = $rel['REFERENCED_TABLE_NAME'];
            $relation->foreignModelName = $rel['REFERENCED_TABLE_NAME'];
            $relation->foreignTableField = $rel['REFERENCED_COLUMN_NAME'];
            if(strrpos($relation->property, '_id')) {
                $relation->name = substr($relation->property, 0, strlen($relation->property) - 3);
            } else {
                $relation->name = $relation->foreignTableName;
            }
            $relations[$relation->name] = $relation;
        }
        $rels = $db->query(
            'SELECT c.TABLE_NAME, c.COLUMN_NAME, c.COLUMN_KEY FROM information_schema.KEY_COLUMN_USAGE AS kcu INNER JOIN information_schema.COLUMNS AS c USING (TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME) WHERE TABLE_SCHEMA=:s AND REFERENCED_TABLE_NAME=:n',
            array(
                ':s' => $db->getDbName(),
                ':n' => $this->name,
            )
        );
        foreach($rels as $rel) {
            if($rel['COLUMN_KEY'] == 'MUL') {
                $relation = new HasManyRelation();
            } else if($rel['COLUMN_KEY'] == 'PRI' || $rel['COLUMN_KEY'] == 'UNI'){
                $relation = new HasOneRelation();
            } else {
                continue;
            }
            $relation->foreignTableName = $rel['TABLE_NAME'];
            $relation->foreignModelName = $rel['TABLE_NAME'];
            $relation->foreignTableField = $rel['COLUMN_NAME'];
            $relation->name = ($relation instanceof HasManyRelation) ?
                Toolkit::pluralize($rel['TABLE_NAME']):$rel['TABLE_NAME'];
            $relations[$relation->name] = $relation;
        }
        static::$tables[$this->name] = array(
            'columns' => $columns,
            'keys' => $keys,
            'relations' => $relations,
        );
        if($this->conf['useSchemaCache']) {
            $key = $this->getHash();
            $this->cache->set($key, static::$tables[$this->name], $this->conf['schemaCacheDuration']);
        }
    }
    private function nullToDefault($type) {
        switch($type) {
            case 'varchar':
            case 'char':
            case 'text':
                return '';
            case 'date':
                return '1970-01-01';
            case 'datetime':
                return '1970-01-01 00:00:00';
            case 'time':
                return '00:00:00';
            case 'int':
            case 'bigint':
            case 'smallint':
            case 'tinyint':
            case 'float':
            case 'decimal':
                return 0;
            default:
                return '';
        }
    }
}