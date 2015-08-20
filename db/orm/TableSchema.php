<?php

namespace x2ts\db\orm;

use x2ts\Component;
use x2ts\db\IDataBase;
use x2ts\db\MySQL;
use x2ts\Toolkit;
use x2ts\ComponentFactory;


/**
 * Class Table
 * @package xts
 * @property-read array $columns
 * @property-read array $columnNames
 * @property-read array $keys
 * @property-read array $relations
 * @property-read \x2ts\cache\ICache $cache
 */
abstract class TableSchema extends Component {
    protected static $_conf = array(
        'schemaCacheId' => 'cc',
        'useSchemaCache' => false,
        'schemaCacheDuration' => 0,
    );

    /**
     * @var boolean
     */
    public $useCache=false;

    /**
     * @var array
     */
    protected static $tables = array();
    /**
     * @var MySQL
     */
    protected $db;
    /**
     * @var string
     */
    protected $name='';

    /**
     * @return array
     */
    public function getColumns() {
        return static::$tables[$this->name]['columns'];
    }

    /**
     * @return array
     */
    public function getColumnNames() {
        return array_keys(static::$tables[$this->name]['columns']);
    }

    /**
     * @return array
     */
    public function getKeys() {
        return static::$tables[$this->name]['keys'];
    }

    /**
     * @return array
     */
    public function getRelations() {
        return static::$tables[$this->name]['relations'];
    }

    public function getCache() {
        return ComponentFactory::getComponent($this->conf['schemaCacheId']);
    }

    public abstract function load();

    public function getHash() {
        return get_class($this->db) . '/' . $this->db->getDbName() . '/' . $this->name;
    }

    public function init() {
        if(!isset(static::$tables[$this->name])) {
            if($this->conf['useSchemaCache']) {
                $key = $this->getHash();
                $tableSchema = $this->cache->get($key);
                if($tableSchema instanceof TableSchema) {
                    static::$tables[$this->name] = $tableSchema;
                    return;
                }
            }
            $this->load();
        }
    }

    /**
     * @param string $name
     * @param IDataBase $db
     */
    public function __construct($name, IDataBase $db) {
        $this->name = $name;
        $this->db = $db;
        $this->init();
    }
}
