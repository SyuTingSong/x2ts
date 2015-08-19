<?php

namespace x2ts\db;
/**
 * Class SqlBuilder
 * @package xts
 */
class SqlBuilder {
    /**
     * @var string
     */
    protected $sql='';

    /**
     * @var array
     */
    protected $params=array();

    /**
     * @var IDataBase
     */
    public $db=null;

    /**
     * @param IDataBase $db
     */
    public function __construct(IDataBase $db=null) {
        $this->db = $db;
    }

    /**
     * @param string $column
     * @param string $_ [optional]
     * @return SqlBuilder
     */
    public function select($column, $_=null) {
        $argc = func_num_args();
        $argv = func_get_args();
        $this->params = array();
        if($argc == 1) {
            $this->sql = "SELECT {$column} ";
        } else if($argc > 1) {
            $columns = implode('`,`', $argv);
            $this->sql = "SELECT `{$columns}` ";
        }
        return $this;
    }

    /**
     * @param string $table
     * @return SqlBuilder
     */
    public function update($table) {
        $this->sql = "UPDATE `$table` ";
        $this->params = array();
        return $this;
    }

    /**
     * @return SqlBuilder
     */
    public function delete() {
        $this->sql = "DELETE ";
        $this->params = array();
        return $this;
    }

    /**
     * @param string $table
     * @return SqlBuilder $this
     */
    public function insertInto($table) {
        $this->sql = "INSERT INTO `$table` ";
        $this->params = array();
        return $this;
    }

    /**
     * @param $table
     * @return SqlBuilder $this
     */
    public function insertIgnoreInto($table) {
        $this->sql = "INSERT IGNORE INTO `$table` ";
        $this->params = array();
        return $this;
    }

    /**
     * @param string|array $columns
     * @return SqlBuilder $this
     */
    public function columns($columns=array()) {
        if(is_array($columns)) {
            $this->sql .= '(`'. implode('`, `', $columns) . '`) ';
        } else if(is_string($columns)) {
            $this->sql .= "($columns) ";
        }
        return $this;
    }

    /**
     * @param array $bindings
     * @return SqlBuilder $this
     */
    public function values($bindings=array()) {
        $references = array();
        foreach($bindings as $k => $v) {
            $references[] = ":$k";
        }

        $this->sql .= 'VALUES ('.implode(', ', $references).') ';
        $this->params = array_merge($this->params, array_combine($references, array_values($bindings)));
        return $this;
    }
    /**
     * @param string $table
     * @return SqlBuilder
     */
    public function from($table) {
        $this->sql .= " FROM `$table` ";
        return $this;
    }

    /**
     * @param string $condition
     * @param array $params
     * @return SqlBuilder
     */
    public function where($condition, $params=array()) {
        $this->sql .= " WHERE $condition ";
        $this->params = array_merge($this->params, $params);
        return $this;
    }

    /**
     * @param array|string $exp
     * @param array $params
     * @return $this
     */
    public function onDupKeyUpdate($exp, $params=array()) {
        if(is_string($exp)) {
            $this->sql .= " ON DUPLICATE KEY UPDATE $exp ";
        } else if(is_array($exp)) {
            $columns = array();
            $params = array();
            foreach($exp as $k => $v) {
                $columns[] = "`$k`=:$k";
                $params[":$k"] = $v;
            }
            $this->sql .= "ON DUPLICATE KEY UPDATE ".implode(', ', $columns);
        }
        $this->params = array_merge($this->params, $params);
        return $this;
    }

    /**
     * @param int $offset
     * @param int $length
     * @return SqlBuilder
     */
    public function limit() {
        $argc = func_num_args();
        $argv = func_get_args();
        if($argc == 1) {
            $offset = 0;
            $length = $argv[0];
        } else if($argc == 2){
            $offset = $argv[0];
            $length = $argv[1];
        } else {
            return $this;
        }
        $this->sql .= "LIMIT $offset, $length";
        return $this;
    }

    /**
     * @param string|array $exp
     * @param array $params
     * @return SqlBuilder
     */
    public function set($exp, $params=array()) {
        if(is_string($exp)) {
            $this->sql .= " SET $exp ";
        } else if(is_array($exp)) {
            $columns = array();
            $params = array();
            foreach($exp as $k => $v) {
                $columns[] = "`$k`=:$k";
                $params[":$k"] = $v;
            }
            $this->sql .= "SET ".implode(', ', $columns);
        }
        $this->params = array_merge($this->params, $params);
        return $this;
    }

    /**
     * @param array $params
     * @return array
     * @throw OrangeException
     */
    public function query($params=array()) {
        $this->params = array_merge($this->params, $params);
        return $this->db->query($this->sql, $this->params);
    }

    /**
     * @return array
     */
    public function export() {
        return array(
            'sql' => $this->sql,
            'params' => $this->params,
        );
    }
}


