<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 15/8/18
 * Time: 下午4:22
 */

namespace x2ts\db\orm;


use x2ts\TConfig;
use x2ts\TGetterSetter;
use x2ts\ComponentFactory;
use x2ts\Toolkit;

class CachedModel {
    use TConfig;
    use TGetterSetter;

    protected static $_conf = array();

    /**
     * @var Model
     */
    protected $_model;

    protected $_duration;

    protected $_key;

    /**
     * @var \x2ts\cache\ICache
     */
    protected $_cache;

    public function __construct($model, $duration, $key) {
        $this->_model = $model;
        $this->_duration = is_null($duration) ? $this->conf['duration'] : $duration;
        $this->_key = $key;
        $this->_cache = ComponentFactory::getComponent($this->conf['cacheId']);
    }

    /**
     * @param $pk
     * @return null|Model
     */
    public function load($pk) {
        $key = is_null($this->_key) ?
            md5("load|{$this->_model->tableName}|{$pk}") : $this->_key;
        $o = $this->_cache->get($key);
        if ($o instanceof Model) {
            return $this->_model->setup($o->properties);
        } else {
            $o = $this->_model->load($pk);
            if ($o instanceof Model) {
                $this->_cache->set($key, $o, $this->_duration);
                return $o;
            } else {
                return null;
            }
        }
    }

    public function save($scenario = Model::INSERT_NORMAL) {
        $r = $this->_model->save($scenario);
        $pk = $this->_model->pk;
        $key = is_null($this->_key) ?
            md5("load|{$this->_model->tableName}|{$pk}") : $this->_key;
        $this->_cache->set($key, $this->_model, $this->_duration);
        return $r;
    }

    public function remove($pk = null) {
        if (is_null($pk)) {
            $pk = $this->_model->getPK();
        }
        $this->_model->remove($pk);
        $key = is_null($this->_key) ?
            md5("load|{$this->_model->tableName}|{$pk}") : $this->_key;
        $this->_cache->remove($key);
    }

    public function one($condition = null, $params = array(), $clone = false) {
        $key = is_null($this->_key) ?
            md5("one|{$this->_model->tableName}|{$condition}|" . serialize($params)) : $this->_key;
        $pk = $this->_cache->get($key);
        if ($pk) {
            if ($clone) {
                $o = clone $this->_model;
                $r = $o->cache($this->_duration)->load($pk);
            } else {
                $r = $this->load($pk);
            }
            if ($r instanceof Model) {
                return $r;
            }
        }
        $r = $this->_model->one($condition, $params, $clone);
        if ($r instanceof Model) {
            $this->_cache->set($key, $r->getPK(), $this->_duration);
        }
        return $r;
    }

    public function many($condition = null, $params = array(), $offset = null, $limit = null) {
        $key = is_null($this->_key) ?
            md5("many|{$this->_model->tableName}|{$condition}|" . serialize($params))
            . "|{$offset}|{$limit}" : $this->_key;

        $r = $this->_cache->get($key);
        if (is_array($r)) {
            $models = array();
            foreach ($r as $pk) {
                $model = clone $this->_model;
                $model->cache($this->_duration)->load($pk);
                if ($model instanceof Model) {
                    $models[] = $model;
                }
            }
            if (!empty($models)) {
                return $models;
            }
        }
        $models = $this->_model->many($condition, $params, $offset, $limit);
        $pks = array();
        /** @var Model $model */
        foreach ($models as $model) {
            $pks[] = $model->getPK();
        }
        $this->_cache->set($key, $pks, $this->_duration);
        return $models;
    }

    public function count($condition = null, $params = array()) {
        $key = is_null($this->_key) ?
            md5("count|{$this->_model->tableName}|{$condition}|" . serialize($params)) : $this->_key;
        $r = $this->_cache->get($key);
        if ($r === false) {
            $r = $this->_model->count($condition, $params);
            $this->_cache->set($key, $r, $this->_duration);
        }
        return $r;
    }

    public function sql($sql, $params = array()) {
        $key = is_null($this->_key) ?
            md5("sql|{$this->_model->tableName}|{$sql}|" . serialize($params)) : $this->_key;
        $r = $this->_cache->get($key);
        if (is_array($r)) {
            $models = array();
            foreach ($r as $pk) {
                $model = clone $this->_model;
                $model->cache($this->_duration)->load($pk);
                if ($model)
                    $models[] = $model;
            }
            if (count($models)) return $models;
        }
        $models = $this->_model->sql($sql, $params);
        $pks = array();
        foreach ($models as $model) {
            $pks[] = $model->getPK();
        }
        $this->_cache->set($key, $pks, $this->_duration);
        return $models;
    }
}