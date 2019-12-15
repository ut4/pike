<?php

namespace Pike\TestUtils;

use \Pike\Db;

class SingleConnectionDb extends Db {
    private $connectionOpened = false;
    public function open() {
        if ($this->connectionOpened) return;
        parent::open();
        $this->connectionOpened = true;
    }
    public function getDatabase() {
        return $this->database;
    }
    public function setDatabase($databaseName) {
        $this->database = $databaseName;
    }
    public function getTablePrefix() {
        return $this->tablePrefix;
    }
    public function setTablePrefix($tablePrefix) {
        $this->tablePrefix = $tablePrefix;
    }
}
