<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2016/10/21
 * Time: 下午4:56
 */

namespace x2ts\db\orm;


interface IModelManager {
    /**
     * @param mixed $pk
     *
     * @return int
     */
    public function remove($pk = null);

    /**
     * @param int $scenario [optional]
     *
     * @return Model
     */
    public function save($scenario = Model::INSERT_NORMAL);

    /**
     * @param mixed $pk
     *
     * @return null|Model
     */
    public function load($pk);

    /**
     * @param string   $condition
     * @param array    $params
     * @param null|int $offset
     * @param null|int $limit
     *
     * @return array
     */
    public function many($condition = null, $params = array(), $offset = null, $limit = null);

    /**
     * @param string $condition
     * @param array  $params
     *
     * @return null|Model
     */
    public function one(string $condition = null, array $params = []);

    /**
     * @param string $sql
     * @param array  $params
     *
     * @return array
     * @throws \x2ts\db\DataBaseException
     */
    public function sql($sql, $params = array());

    /**
     * @param string $condition
     * @param array  $params
     *
     * @return int|bool
     */
    public function count($condition = null, $params = array());

    public function loadRelationObj(
        string $name,
        string $condition = null,
        array $params = [],
        int $offset = 0,
        int $limit = 200
    );
}