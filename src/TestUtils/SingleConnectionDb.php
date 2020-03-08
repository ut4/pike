<?php

namespace Pike\TestUtils;

use \Pike\Db;

class SingleConnectionDb extends Db {
    private $connectionOpened = false;
    private $currentDatabaseName;
    public function open() {
        if ($this->connectionOpened) return;
        parent::open();
        $this->connectionOpened = true;
    }
    public function setConfig($config) {
        parent::setConfig($config);
        $this->currentDatabaseName = $this->config['db.database'] ?? '';
    }
    public function getCurrentDatabaseName() {
        return $this->currentDatabaseName;
    }
    public function setCurrentDatabaseName($databaseName) {
        $this->currentDatabaseName = $databaseName;
    }
    public function getTablePrefix() {
        return $this->tablePrefix;
    }
    public function setTablePrefix($tablePrefix) {
        $this->tablePrefix = $tablePrefix;
    }
}
