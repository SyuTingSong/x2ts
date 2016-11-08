<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2016/10/18
 * Time: 下午12:50
 */

namespace x2ts\db\orm;


use x2ts\ComponentFactory;
use x2ts\db\Redis;
use x2ts\Toolkit;

/**
 * Class RedisCachedModelManager
 *
 * @package x2ts\db\orm
 */
final class RedisCachedModelManager implements IModelManager {
    /**
     * @var Model
     */
    private $model;

    /**
     * @var array
     */
    private $conf = [
        'redisId'  => 'redis',
        'duration' => [
            'pool'  => 3600,
            'many'  => 300,
            'count' => 300,
            'one'   => 300,
        ],
    ];

    protected static $instance;

    /**
     * @param Model $model
     * @param array $conf
     *
     * @return RedisCachedModelManager
     */
    public static function getInstance(Model $model, array $conf = []) {
        if (null === self::$instance) {
            self::$instance = new RedisCachedModelManager();
        }
        self::$instance->model = $model;
        self::$instance->conf = [
            'redisId'  => 'redis',
            'duration' => [
                'pool'  => 3600,
                'many'  => 300,
                'one'   => 300,
                'count' => 300,
            ],
        ];
        Toolkit::override(self::$instance->conf, $conf);
        return self::$instance;
    }

    public function redis():Redis {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return ComponentFactory::getComponent($this->conf['redisId']);
    }

    /**
     * @param mixed $pk
     *
     * @return null|Model
     */
    public function load($pk) {
        Toolkit::trace("Redis cached load $pk");
        $key = $this->getPoolKey($pk);
        $model = @unserialize($this->redis()->get($key));

        if ($model instanceof Model) {
            if ($model->isNewRecord || count($model->modified) > 0) {
                Toolkit::log(
                    "The cache of {$model->tableName}-{$model->pk} is polluted",
                    X_LOG_WARNING
                );
                return $this->loadFromDb($pk);
            }
            return $this->model->setup($model->properties);
        }
        return $this->loadFromDb($pk);
    }

    /**
     * @param int $scenario [optional]
     *
     * @return Model
     */
    public function save($scenario = Model::INSERT_NORMAL) {
        Toolkit::trace('Redis cached save');
        $result = DirectModelManager::getInstance($this->model)
            ->save($scenario);
        $this->removeAllRelatedCache();
        return $result;
    }

    /**
     * @param mixed $pk
     *
     * @return int
     */
    public function remove($pk = null) {
        Toolkit::trace('Redis cached remove');
        $result = DirectModelManager::getInstance($this->model)->remove($pk);
        $this->removeAllRelatedCache();
        return $result;
    }

    /**
     * @param string   $condition
     * @param array    $params
     * @param null|int $offset
     * @param null|int $limit
     *
     * @return Model[]
     */
    public function many($condition = null, $params = array(), $offset = null, $limit = null) {
        Toolkit::trace('Redis cached many');
        $key = $this->getManyKey($condition, $params, $offset, $limit);
        /** @var array $pks */
        $pks = @unserialize($this->redis()->get($key));
        if (is_array($pks)) {
            return $this->pks2models($pks);
        } else {
            return $this->manySet($key,
                DirectModelManager::getInstance($this->model)
                    ->many($condition, $params, $offset, $limit)
            );
        }
    }

    /**
     * @param string $condition
     * @param array  $params
     *
     * @return null|Model
     */
    public function one(string $condition = null, array $params = []) {
        Toolkit::trace('Redis cached one');
        $key = $this->getOneKey($condition, $params);
        $pk = $this->redis()->get($key);
        if ($pk) {
            return $this->load($pk);
        } else {
            $model = DirectModelManager::getInstance($this->model)
                ->one($condition, $params);
            if ($model) {
                $this->set(
                    $key,
                    $model->pk,
                    $this->conf['duration']['one']
                );
                $this->poolSet($model);
            }
            return $model;
        }
    }

    /**
     * @param string $sql
     * @param array  $params
     *
     * @return array
     * @throws \x2ts\db\DataBaseException
     */
    public function sql($sql, $params = array()) {
        $key = $this->getSqlKey($sql, $params);
        $pks = @unserialize($this->redis()->get($key));
        if ($pks) {
            return $this->pks2models($pks);
        } else {
            return $this->manySet($key,
                DirectModelManager::getInstance($this->model)
                    ->sql($sql, $params)
            );
        }
    }

    /**
     * @param string $condition
     * @param array  $params
     *
     * @return int|bool
     */
    public function count($condition = null, $params = array()) {
        $key = $this->getCountKey($condition, $params);
        $count = $this->redis()->get($key);
        if (!is_int($count) && !ctype_digit($count)) {
            $count = DirectModelManager::getInstance($this->model)
                ->count($condition, $params);
            $this->set(
                $key,
                $count,
                $this->conf['duration']['count']
            );
        }
        return $count;
    }

    protected function getPoolKey($pk = null) {
        if (null === $pk) {
            $pk = $this->model->pk;
        }
        return "rmc:p:{$this->model->db->dbName}:{$this->model->tableName}:{$pk}";
    }

    protected function getManyKey($condition, $params, $offset, $limit) {
        $p = serialize($params);
        $md5 = md5("$condition:$p:$offset:$limit");
        return "rmc:m:{$this->model->db->dbName}:{$this->model->tableName}:{$md5}";
    }

    protected function getOneKey($condition, $params) {
        $p = serialize($params);
        $md5 = md5("$condition:$p");
        return "rmc:o:{$this->model->db->dbName}:{$this->model->tableName}:{$md5}";
    }

    protected function getSqlKey($sql, $params) {
        $p = serialize($params);
        $md5 = md5("$sql:$p");
        return "rmc:m:{$this->model->db->dbName}:{$this->model->tableName}:{$md5}";
    }

    protected function getCountKey($condition, $params) {
        $p = serialize($params);
        $md5 = md5("$condition:$p");
        return "rmc:c:{$this->model->db->dbName}:{$this->model->tableName}:{$md5}";
    }

    /**
     * @param Model $model
     *
     * @return $this
     */
    private function poolSet(Model $model) {
        $this->redis()->set(
            $this->getPoolKey($model->pk),
            serialize($model),
            $this->conf['duration']['pool']
        );
        return $this;
    }

    /**
     * @param array $pks
     *
     * @return array
     */
    private function pks2models($pks):array {
        $pool_keys = array_map(function ($pk) {
            return $this->getPoolKey($pk);
        }, $pks);
        $serialized_models = $this->redis()->mget($pool_keys);
        $length = count($pks);
        $models = [];
        for ($i = 0; $i < $length; $i++) {
            $pk = $pks[$i];
            $serialized_model = $serialized_models[$i];
            if (false === $serialized_model) {
                $model = clone $this->load($pk);
            } else {
                $model = unserialize($serialized_models[$i]);
            }
            $models[] = $model;
        }
        return $models;
    }

    /**
     * @param string  $key
     *
     * @param Model[] $models
     *
     * @return Model[]
     */
    private function manySet($key, $models) {
        if (0 === count($models)) {
            return $models;
        }
        $pks = array_map(function (Model $model) {
            $this->poolSet($model);
            return $model->pk;
        }, $models);
        $this->set(
            $key,
            serialize($pks),
            $this->conf['duration']['many']
        );
        return $models;
    }

    private function loadFromDb($pk) {
        if (DirectModelManager::getInstance($this->model)->load($pk)) {
            $this->poolSet($this->model);
            return $this->model;
        }
        return null;
    }

    private function set($key, $value, $duration = 0) {
        $this->redis()->sAdd($this->group(), $key);
        $this->redis()->set($key, $value, $duration);
    }

    private function group() {
        return "rmcg:{$this->model->db->dbName}:{$this->model->tableName}:";
    }

    public function removeAllRelatedCache() {
        $groupKey = $this->group();
        $keysInGroup = $this->redis()->sMembers($groupKey);
        if (!$keysInGroup) {
            $keysInGroup = [];
        }
        $num = $this->redis()->del($this->getPoolKey(), ...$keysInGroup);
        $this->redis()->del($groupKey);
        Toolkit::trace("$num keys removed");
        return $num;
    }
}