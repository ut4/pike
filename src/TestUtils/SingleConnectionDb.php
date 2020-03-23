<?php

declare(strict_types=1);

namespace Pike\TestUtils;

use \Pike\Db;

class SingleConnectionDb extends Db {
    private $connectionOpened = false;
    private $currentDatabaseName;
    public function open(): bool {
        if ($this->connectionOpened) return true;
        $ok = parent::open();
        $this->connectionOpened = true;
        return $ok;
    }
    public function setConfig($config): void {
        parent::setConfig($config);
        $this->currentDatabaseName = $this->config['db.database'] ?? '';
    }
    public function getCurrentDatabaseName(): string {
        return $this->currentDatabaseName;
    }
    public function setCurrentDatabaseName(string $databaseName): void {
        $this->currentDatabaseName = $databaseName;
    }
    public function getTablePrefix(): string {
        return $this->tablePrefix;
    }
    public function setTablePrefix(string $tablePrefix): void {
        $this->tablePrefix = $tablePrefix;
    }
}
