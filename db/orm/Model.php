<?php

namespace x2ts\db\orm;

use ArrayAccess;
use BadMethodCallException;
use IteratorAggregate;
use JsonSerializable;
use x2ts\Component;
use x2ts\db\IDataBase;
use x2ts\db\SqlBuilder;
use x2ts\IAssignable;
use x2ts\MethodNotImplementException;
use x2ts\ObjectIterator;
use x2ts\Toolkit;
use x2ts\ComponentFactory;

/**
 * Class Model
 * @package x2ts
 * @property-read array $properties
 * @property-read TableSchema $tableSchema
 * @property-read IDataBase $db
 * @property-read string $modelName
 * @property-read bool $isNewRecord
 * @property-read mixed $oldPK
 * @property-read mixed $pk
 * @property-read string $pkName
 * @property-read string $tableName
 * @property-read array $modified
 * @property-read array $relations
 * @property-read SqlBuilder $builder
 */
class Model extends Component implements
    ArrayAccess,
    IteratorAggregate,
    JsonSerializable,
    IAssignable {
    const INSERT_NORMAL = 0;
    const INSERT_IGNORE = 1;
    const INSERT_UPDATE = 2;
    const INSERT_REPLACE = 3;
    /**
     * @var bool
     */
    protected $_isNewRecord = true;
    /**
     * @var mixed
     */
    protected $_oldPK = null;
    /**
     * @var array
     */
    protected $_properties = array();
    /**
     * @var array
     */
    protected $_modified = array();
    /**
     * @var array
     */
    protected $_relationObjects = array();
    /**
     * @var TableSchema
     */
    protected $_tableSchema;
    /**
     * @var string
     */
    protected $_modelName;
    /**
     * @var string
     */
    protected $_tableName;
    /**
     * @var array
     */
    public static $_conf = array(
        'tablePrefix'          => '',
        'dbId'                 => 'db',
        'enableCacheByDefault' => false,
        'schemaConf'           => array(
            'schemaCacheId'       => 'cc',
            'useSchemaCache'      => false,
            'schemaCacheDuration' => 0,
        ),
        'cacheConf'            => array(
            'cacheId'  => 'cache',
            'duration' => 60,
        ),
    );
    /**
     * @var SqlBuilder
     */
    private $_builder;

    public function __sleep() {
        return array('_modelName', '_properties', '_modified');
    }

    public function __wakeUp() {
        $this->_builder = new SqlBuilder($this->db);
    }

    /**
     * @param string $modelName
     * @return Model
     */
    public static function getInstance($modelName) {
        $className = "\\model\\" . Toolkit::toCamelCase($modelName, true);
        if (class_exists($className)) {
            return new $className($modelName);
        } else {
            return new Model($modelName);
        }
    }

    public static function conf($conf = array()) {
        parent::conf($conf);
        TableSchema::conf(static::$_conf['schemaConf']);
        CachedModel::conf(static::$_conf['cacheConf']);
    }

    /**
     * @param mixed $pk
     * @return null|Model
     */
    public function load($pk) {
        $r = $this->_builder->select('*')
            ->from($this->tableName)
            ->where("`{$this->pkName}`=:pk")
            ->query(array(':pk' => $pk));
        if (count($r)) {
            return $this->setupOne($r[0]);
        }
        return null;
    }

    /**
     * @param int $scenario [optional]
     * @return $this
     * @throws MethodNotImplementException
     */
    public function save($scenario = Model::INSERT_NORMAL) {
        $pkName = $this->pkName;
        if ($this->isNewRecord) {
            $pk = 0;
            switch ($scenario) {
                case Model::INSERT_NORMAL:
                    $this->_builder
                        ->insertInto($this->tableName)
                        ->columns($this->tableSchema->columnNames)
                        ->values($this->_properties)
                        ->query();
                    $pk = $this->db->getLastInsertId();
                    if (empty($pk) && !empty($this->pk)) {
                        $pk = $this->pk;
                    }
                    break;
                case Model::INSERT_IGNORE:
                    $this->builder
                        ->insertIgnoreInto($this->tableName)
                        ->columns($this->tableSchema->columnNames)
                        ->values($this->_properties)
                        ->query();
                    $pk = $this->db->getLastInsertId();
                    break;
                case Model::INSERT_UPDATE:
                    $this->_builder
                        ->insertInto($this->tableName)
                        ->columns($this->tableSchema->columnNames)
                        ->values($this->_properties)
                        ->onDupKeyUpdate($this->_modified)
                        ->query();
                    $pk = $this->db->getLastInsertId();
                    break;
                case Model::INSERT_REPLACE:
                    throw new MethodNotImplementException('Using REPLACE is dangerous! It may brokes the foreign key constraint. So x2ts ORM NOT implement replace support');
            }
            if ($pk) {
                $this->load($pk);
            }
        } else if (0 !== count($this->_modified)) {
            $this->_builder
                ->update($this->tableName)
                ->set($this->_modified)
                ->where("`$pkName`=:_table_pk", array(
                    ':_table_pk' => $this->oldPK,
                ))
                ->query();
            $this->_modified = array();
        }
        return $this;
    }

    /**
     * @param int $pk
     * @return int
     */
    public function remove($pk = null) {
        if (is_null($pk)) {
            $pk = $this->getPK();
        }
        $this->_builder
            ->delete()
            ->from($this->tableName)
            ->where("`{$this->pkName}`=:pk", array(':pk' => $pk,))
            ->query();
        return $this->db->getAffectedRows();
    }

    /**
     * @param string $condition
     * @param array $params
     * @param boolean $clone
     * @return null|Model
     */
    public function one(string $condition = null, array $params = [], $clone = false) {
        $this->_builder->select('*')
            ->from($this->tableName);
        if (!empty($condition)) {
            $this->_builder->where($condition, $params);
        }
        $r = $this->_builder->limit(1)
            ->query();
        if (!is_array($r) or 0 === count($r)) {
            return null;
        }
        $one = $clone ? clone $this : $this;
        return $one->setupOne($r[0]);
    }

    /**
     * @param string $condition
     * @param array $params
     * @param null|int $offset
     * @param null|int $limit
     * @return array
     */
    public function many($condition = null, $params = array(), $offset = null, $limit = null) {
        $this->_builder
            ->select('*')
            ->from($this->tableName);

        if (!empty($condition)) {
            $this->_builder->where($condition, $params);
        }

        if (!is_null($offset)) {
            if (is_null($limit)) {
                $this->_builder->limit($offset);
            } else {
                $this->_builder->limit($offset, $limit);
            }
        }
        $r = $this->_builder->query();
        if (!is_array($r) or 0 === count($r)) {
            return array();
        } else {
            return $this->setup($r);
        }
    }

    /**
     * @param string $condition
     * @param array $params
     * @return int|bool
     */
    public function count($condition = null, $params = array()) {
        $this->_builder->select('COUNT(*)')
            ->from($this->tableName);
        if (null !== $condition) {
            $this->_builder->where($condition, $params);
        }
        $r = $this->_builder->query();
        if (!is_array($r)) {
            return false;
        }
        return (int) reset($r[0]);
    }

    private function setupOne($properties) {
        $pkName = $this->pkName;
        /** @var Column $column */
        foreach ($this->tableSchema->columns as $column) {
            if (!array_key_exists($column->name, $properties)) {
                continue;
            }

            if (null !== $properties[$column->name]) {
                if ($column->isInt()) {
                    $this->_properties[$column->name] =
                        (int) $properties[$column->name];
                } else if ($column->isFloat()) {
                    $this->_properties[$column->name] =
                        (float) $properties[$column->name];
                } else {
                    $this->_properties[$column->name] =
                        $properties[$column->name];
                }
            } else {
                $this->_properties[$column->name] = null;
            }
        }
        $this->_modified = array();
        $this->_oldPK = isset($this->_properties[$pkName]) ? $this->_properties[$pkName] : null;
        $this->_isNewRecord = !isset($this->_oldPK);
        return $this;
    }

    public function setup($properties) {
        if (is_array(reset($properties))) {
            $modelList = array();
            foreach ($properties as $p) {
                $o = clone $this;
                $o->setupOne($p);
                $modelList[] = $o;
            }
            return $modelList;
        } else {
            return $this->setupOne($properties);
        }
    }

    public function sql($sql, $params = array()) {
        $r = $this->db->query($sql, $params);
        if (empty($r)) {
            return array();
        } else {
            return $this->setup($r);
        }
    }

    /**
     * @param null|int $duration
     * @param null|string $key
     * @return CachedModel
     * @internal param callable $callback
     */
    public function cache($duration = null, $key = null) {
        if (is_string($duration) && is_null($key) && !is_numeric($duration)) {
            $key = $duration;
            $duration = null;
        }
        return new CachedModel($this, $duration, $key);
    }

    /**
     * @return SqlBuilder
     */
    public function getBuilder() {
        return $this->_builder;
    }

    /**
     * @param string $modelName
     */
    public function __construct($modelName = null) {
        $this->_builder = new SqlBuilder($this->db);
        $this->_modelName = $modelName ?? Toolkit::toCamelCase(
                basename(str_replace('\\', '/', get_class($this))),
                true
            );
        parent::__construct();
    }

    protected function init() {
        $columns = $this->getTableSchema()->getColumns();
        foreach ($columns as $column) {
            $this->_properties[$column->name] = $column->defaultValue;
        }
        $this->_modified = array();
    }

    /**
     * @return string
     */
    public function getModelName() {
        return $this->_modelName;
    }

    /**
     * @return string
     */
    public function getTableName() {
        if (empty($this->_tableName)) {
            $this->_tableName = $this->conf['tablePrefix'] . Toolkit::to_snake_case($this->_modelName);
        }
        return $this->_tableName;
    }

    /**
     * @return array
     */
    public function getProperties() {
        return $this->_properties;
    }

    public function setProperties($array) {
        foreach ($array as $key => $value) {
            $this->_propertySet($key, $value);
        }
    }

    /**
     * @return array
     */
    public function getModified() {
        return $this->_modified;
    }

    /**
     * @param array $mod
     */
    public function setModified($mod) {
        $this->_modified = $mod;
    }

    /**
     * @return bool
     */
    public function getIsNewRecord() {
        return $this->_isNewRecord;
    }

    /**
     * @return mixed
     */
    public function getOldPK() {
        return $this->_oldPK;
    }

    /**
     * @return mixed
     */
    public function getPK() {
        return $this->_properties[$this->getPKName()];
    }

    public function getPKName() {
        return $this->tableSchema->keys['PK'];
    }

    public function getRelations() {
        return $this->tableSchema->relations;
    }

    /**
     * @throws MissingPrimaryKeyException
     * @return TableSchema
     */
    public function getTableSchema() {
        if (null === $this->_tableSchema) {
            /** @var TableSchema $schema */
            $this->_tableSchema = new MySQLTableSchema($this->tableName, $this->db);
            $keys = $this->_tableSchema->getKeys();
            if (empty($keys['PK']))
                throw new MissingPrimaryKeyException("Table {$this->tableName} does not have the Primary Key. It cannot be initialized as an Model");
        }
        return $this->_tableSchema;
    }

    /**
     * @return IDataBase
     */
    protected function getDb() {
        return ComponentFactory::getComponent($this->conf['dbId']);
    }

    public function __get($name) {
        if ($name === 'conf') {
            return static::$_conf;
        }
        $getter = Toolkit::toCamelCase("get $name");
        $snakeName = Toolkit::to_snake_case($name);
        if (method_exists($this, $getter)) {
            return $this->$getter();
        } else if (array_key_exists($name, $this->_properties)) {
            return $this->_properties[$name];
        } else if (array_key_exists($snakeName, $this->_properties)) {
            return $this->_properties[$snakeName];
        } else if (array_key_exists($name, $this->relations)
            && !$this->relations[$name] instanceof HasManyRelation
        ) { // Use method to load HasManyRelation
            if (!array_key_exists($name, $this->_relationObjects)) {
                $this->_relationObjects[$name] = $this->loadRelationObj($name);
            }
            return $this->_relationObjects[$name];
        }
        return null;
    }

    public function __set($name, $value) {
        $setter = Toolkit::toCamelCase("set $name");
        if (method_exists($this, $setter)) {
            $this->$setter($value);
        } else {
            $this->_propertySet($name, $value);
        }
    }

    public function __isset($name) {
        return array_key_exists($name, $this->_properties)
        || array_key_exists($name, $this->relations)
        || ($getter = Toolkit::toCamelCase("get $name")) && method_exists($this, $getter);
    }

    public function __call($name, $args) {
        if (array_key_exists($name, $this->relations)) {
            array_unshift($args, $name);
            return call_user_func_array(array($this, 'loadRelationObj'), $args);
        }
        throw new BadMethodCallException("Call to undefined method $name");
    }

    /**
     * @param $name
     * @param string $condition
     * @param array $params
     * @param int $offset
     * @param int $limit
     * @return Model|array|null
     */
    protected function loadRelationObj(
        string $name,
        string $condition = null,
        array $params = [],
        int $offset = 0,
        int $limit = 200
    ) {
        Toolkit::trace('Loading relation object');
        /** @var Relation $relation */
        $relation = $this->relations[$name];
        $model = Model::getInstance($relation->foreignModelName);
        switch (true) {
            case $relation instanceof BelongToRelation:
                $pk = $this->properties[$relation->property];
                Toolkit::trace("Load belonging object {$relation->name} with PK value is {$pk}");
                return $model->load($pk);
            case $relation instanceof HasOneRelation:
                $c = $relation->foreignTableField . '=:_fid';
                if ($condition) {
                    $c .= " AND $condition";
                }
                $p = array_merge([':_fid' => $this->pk], $params);
                Toolkit::trace("Load relation object {$relation->name}");
                return $model->one($c, $p);
            case $relation instanceof HasManyRelation:
                $c = $relation->foreignTableField . '=:_fid';
                if ($condition) {
                    $c .= " AND $condition";
                }
                $p = array_merge([':_fid' => $this->pk], $params);
                Toolkit::trace("Load relation objects {$relation->name}");
                return $model->many($c, $p, $offset, $limit);
        }
        return null;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     */
    public function offsetExists($offset) {
        return $this->__isset($offset);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     */
    public function offsetGet($offset) {
        return $this->__get($offset);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     */
    public function offsetSet($offset, $value) {
        $this->__set($offset, $value);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @throws MethodNotImplementException
     * @return void
     */
    public function offsetUnset($offset) {
        throw new MethodNotImplementException("Model properties are maintained by database schema. You cannot unset any of them");
    }

    /**
     * @param array $array
     * @return $this
     */
    public function assign(array $array) {
        if ($this->isNewRecord && !empty($array[$this->pkName])) {
            $this->load($array[$this->pkName]);
        }

        foreach ($array as $key => $value) {
            $this->__set($key, $value);
        }
        return $this;
    }

    /**
     * @param string $name The name of the property to be set
     * @param mixed $value The value of the property
     * @return int|bool Returns the number of changed properties, or false if $name is invalid
     */
    protected function _propertySet($name, $value) {
        if (array_key_exists($name, $this->_properties)) {
            if ($this->_properties[$name] !== $value) {
                $this->_properties[$name] = $value;
                $this->_modified[$name] = $value;
                return 1;
            } else {
                return 0;
            }
        }
        return false;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Retrieve an external iterator
     * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
     * @return \Traversable An instance of an object implementing <b>Iterator</b> or
     * <b>Traversable</b>
     */
    public function getIterator() {
        return new ObjectIterator($this, $this->getExportProperties());
    }

    protected function getExportProperties() {
        return array_keys($this->_properties);
    }

    /**
     * (PHP 5 &gt;= 5.4.0)<br/>
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     */
    public function jsonSerialize() {
        $jsonArray = array();
        foreach ($this as $key => $value) {
            $jsonArray[$key] = $value;
        }
        return $jsonArray;
    }
}
