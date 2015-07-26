<?php

namespace x2ts\db\orm;

abstract class Relation {
    public $originTable;
    public $originColumn;
    public $foreignTable;
    public $foreignColumn;
}
