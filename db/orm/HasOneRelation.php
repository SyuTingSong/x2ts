<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 15/8/18
 * Time: 下午3:34
 */

namespace x2ts\db\orm;


use x2ts\Toolkit;

class HasOneRelation extends Relation {
    /**
     * @param Model  $model
     * @param string $condition [optional]
     * @param array  $params    [optional]
     * @param int    $offset    [optional]
     * @param int    $limit     [optional]
     *
     * @return array
     */
    public function fetchRelated(
        Model $model,
        $condition = null,
        $params = [],
        $offset = null,
        $limit = null
    ) {
        Toolkit::trace('HasOneRelation fetch');
        $condition = $this->foreignTableField . '=:_fk' .
            ((null === $condition || '' === $condition) ?
                '' : " AND $condition");
        $params = array_merge($params, [
            ':_fk' => $model->properties[$this->property],
        ]);
        return Model::getInstance($this->foreignModelName)
            ->one($condition, $params);
    }
}