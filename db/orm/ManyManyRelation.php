<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2016/7/28
 * Time: 下午10:40
 */

namespace x2ts\db\orm;


use x2ts\Toolkit;

class ManyManyRelation extends Relation {
    public $relationTableName;

    public $relationTableFieldThis;

    public $relationTableFieldThat;

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
        $condition = "`{$this->relationTableName}`.`{$this->relationTableFieldThis}`=:_fk" .
            (null === $condition || '' === $condition ?
                '' : " AND $condition");
        $params = array_merge(
            [':_fk' => $model->properties[$this->property]],
            $params
        );

        return Model::getInstance($this->foreignModelName)->sql(
            <<<SQL
SELECT
  `{$this->foreignTableName}`.*
FROM
  `{$this->relationTableName}` INNER JOIN `{$this->foreignTableName}`
ON
  `{$this->relationTableName}`.`{$this->relationTableFieldThat}` = `{$this->foreignTableName}`.`{$this->foreignTableField}`
WHERE $condition
SQL
            ,
            $params
        );
    }
}