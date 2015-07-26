<?php

namespace x2ts\db\orm;

use x2ts\Component;
use x2ts\db\DataBaseException;

class Model extends Component {

    /**
     * @param string $modelName
     * @return \x2ts\db\orm\Model
     */
    public function pick($modelName) {

    }

    /**
     * @return bool
     */
    public function getIsNewRecord() {

    }

    /**
     * @param mixed $id
     * @param bool $clone
     * @throws DataBaseException
     * @return $this
     */
    public function load($id, $clone=false) {

    }

    public function save() {

    }
}
