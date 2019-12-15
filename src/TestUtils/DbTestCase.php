<?php

namespace Pike\TestUtils;

use PHPUnit\Framework\TestCase;

class DbTestCase extends TestCase {
    protected static $db = null;
    public static function getDb(array $config = null) {
        if (!$config) {
            if (!defined('TEST_CONFIG_DIR_PATH'))
                throw new \Exception('Can\'t make db without TEST_CONFIG_DIR_PATH . "config.php".');
            $config = require TEST_CONFIG_DIR_PATH . 'config.php';
        }
        if (!self::$db) {
            self::$db = new SingleConnectionDb($config);
            self::$db->open();
        } else {
            self::$db->setDatabase($config['db.database']);
            self::$db->setTablePrefix($config['db.tablePrefix']);
            if ($config['db.database']) {
                self::$db->exec('USE ' . $config['db.database'] . ';');
            }
        }
        self::$db->beginTransaction();
        return self::$db;
    }
    public static function tearDownAfterClass() {
        if (self::$db) {
            self::$db->rollback();
        }
    }
}
