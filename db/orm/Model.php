<?php

namespace x2ts\db\orm;

use ArrayAccess;
use BadMethodCallException;
use IteratorAggregate;
use JsonSerializable;
use x2ts\Component;
use x2ts\ComponentFactory;
use x2ts\db\IDataBase;
use x2ts\db\MySQL;
use x2ts\db\SqlBuilder;
use x2ts\IAssignable;
use x2ts\MethodNotImplementException;
use x2ts\ObjectIterator;
use x2ts\Toolkit;

/**
 * Class Model
 *
 * @package x2ts
 * @property array              $modified
 * @property-read array         $properties
 * @property-read TableSchema   $tableSchema
 * @property-read MySQL         $db
 * @property-read string        $modelName
 * @property-read bool          $isNewRecord
 * @property-read mixed         $oldPK
 * @property-read mixed         $pk
 * @property-read string        $pkName
 * @property-read string        $tableName
 * @property-read array         $relations
 * @property-read SqlBuilder    $builder
 * @property-read IModelManager $modelManager
 */
class Model extends Component implements
    ArrayAccess,
    IteratorAggregate,
    JsonSerializable,
    IAssignable {
    const INSERT_NORMAL = 0;
    const INSERT_IGNORE = 1;
    const INSERT_UPDATE = 2;

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
        'manager'              => array(
            'class' => '\x2ts\db\orm\DirectModelManager',
            'conf'  => [],
        ),
    );

    /**
     * @var SqlBuilder
     */
    private $_builder;

    public function getSqlBuilder() {
        return $this->_builder;
    }

    public function __sleep() {
        return array('_modelName', '_properties', '_modified');
    }

    public function __wakeUp() {
        $this->_builder = new SqlBuilder($this->db);
    }

    /**
     * @param string $modelName
     *
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
    }

    /**
     * @var \ReflectionMethod
     */
    protected $_reflectionMMGetter;

    public function getModelManager():IModelManager {
        if (null === $this->_reflectionMMGetter) {
            $this->_reflectionMMGetter = (new \ReflectionClass($this->conf['manager']['class']))
                ->getMethod('getInstance');
        }
        return $this->_reflectionMMGetter->invoke(null, $this, $this->conf['manager']['conf']);
    }

    /**
     * @param mixed $pk
     *
     * @return null|Model
     */
    public function load($pk) {
        return $this->modelManager->load($pk);
    }

    /**
     * @param int $scenario [optional]
     *
     * @return $this
     */
    public function save($scenario = Model::INSERT_NORMAL) {
        return $this->modelManager->save($scenario);
    }

    /**
     * @param int $pk
     *
     * @return int
     */
    public function remove($pk = null) {
        return $this->modelManager->remove($pk);
    }

    /**
     * @param string $condition
     * @param array  $params
     *
     * @return null|Model
     */
    public function one(string $condition = null, array $params = []) {
        return $this->modelManager->one($condition, $params);
    }

    /**
     * @param string $condition
     * @param array  $params
     * @param int    $offset
     * @param int    $limit
     *
     * @return array
     */
    public function many($condition = null, $params = array(), $offset = null, $limit = null) {
        return $this->modelManager->many($condition, $params, $offset, $limit);
    }

    /**
     * @param string $condition
     * @param array  $params
     *
     * @return int|bool
     */
    public function count($condition = null, $params = array()) {
        return $this->modelManager->count($condition, $params);
    }

    /**
     * @param string $sql
     * @param array  $params
     *
     * @return array
     * @throws \x2ts\db\DataBaseException
     */
    public function sql($sql, $params = array()) {
        return $this->modelManager->sql($sql, $params);
    }

    protected function setupOne($properties) {
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

    /**
     * @param array $properties
     *
     * @return array|Model
     */
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

    /**
     * @param null|int    $duration
     * @param null|string $key
     *
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
        } else if (array_key_exists($name, $this->relations)) {
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
     * @param        $name
     * @param string $condition
     * @param array  $params
     * @param int    $offset
     * @param int    $limit
     *
     * @return Model|array|null
     */
    protected function loadRelationObj(
        string $name,
        string $condition = null,
        array $params = [],
        int $offset = 0,
        int $limit = 200
    ) {
        return $this->modelManager->loadRelationObj($name, $condition, $params, $offset, $limit);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Whether a offset exists
     *
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     *
     * @param mixed $offset <p>
     *                      An offset to check for.
     *                      </p>
     *
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
     *
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     *
     * @param mixed $offset <p>
     *                      The offset to retrieve.
     *                      </p>
     *
     * @return mixed Can return all value types.
     */
    public function offsetGet($offset) {
        return $this->__get($offset);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to set
     *
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     *
     * @param mixed $offset <p>
     *                      The offset to assign the value to.
     *                      </p>
     * @param mixed $value  <p>
     *                      The value to set.
     *                      </p>
     *
     * @return void
     */
    public function offsetSet($offset, $value) {
        $this->__set($offset, $value);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to unset
     *
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     *
     * @param mixed $offset <p>
     *                      The offset to unset.
     *                      </p>
     *
     * @throws MethodNotImplementException
     * @return void
     */
    public function offsetUnset($offset) {
        throw new MethodNotImplementException("Model properties are maintained by database schema. You cannot unset any of them");
    }

    /**
     * @param array $array
     *
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
     * @param string $name  The name of the property to be set
     * @param mixed  $value The value of the property
     *
     * @return int|bool Returns the number of changed properties, or false if
     *                  $name is invalid
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
     *
     * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
     * @return \Traversable An instance of an object implementing
     *                      <b>Iterator</b> or
     *       <b>Traversable</b>
     */
    public function getIterator() {
        return new ObjectIterator($this, $this->getExportProperties());
    }

    protected static $_export = [];

    protected function getExportProperties() {
        $base = array_keys($this->_properties);
        $within = [];
        $without = [];
        foreach (static::$_export as $act) {
            $act = trim($act);
            if ($act[0] === '+') {
                $within[] = trim(substr($act, 1));
            } elseif ($act[0] === '-') {
                $without[] = trim(substr($act, 1));
            } else {
                $within[] = $act;
            }
        }

        return array_diff(array_merge($base, $within), $without);
    }

    /**
     * (PHP 5 &gt;= 5.4.0)<br/>
     * Specify data which should be serialized to JSON
     *
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     *       which is a value of any type other than a resource.
     */
    public function jsonSerialize() {
        $jsonArray = array();
        foreach ($this as $key => $value) {
            $jsonArray[$key] = $value;
        }
        return $jsonArray;
    }
}
