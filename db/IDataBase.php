<?php

namespace x2ts\db;

interface IDataBase {
    /**
     * Query an SQL statement with parameters and returns the result set
     *
     * @param $sql
     * @param array $params
     * @throws DataBaseException
     * @return array
     */
    public function query($sql, $params=array());

    /**
     * Execute an SQL statement and returns the row num it affected
     *
     * @param string $sql
     * @throws DataBaseException
     * @return int
     */
    public function execute($sql);

    /**
     * Returns the auto increment ID created by the last insert statement
     * @return int
     */
    public function getLastInsertId();

    /**
     * Returns the affected row count by the last statement
     * @return int
     */
    public function getAffectedRows();

    /**
     * Initiates a transaction
     *
     * @return bool
     */
    public function startTransaction();

    /**
     * Commits the transaction
     *
     * @return bool
     */
    public function commit();

    /**
     * Roll back the transaction
     *
     * @return bool
     */
    public function rollback();
}