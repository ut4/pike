<?php

namespace Pike\TestUtils;

use PHPUnit\Framework\TestCase;

class DbTestCase extends TestCase {
    protected static $db = null;
    protected static $dbConfig = null;
    protected function setUp() {
        if (!self::$db) self::getDb();
        self::$db->beginTransaction();
    }
    protected function tearDown() {
        self::$db->rollback();
    }
    public static function getDb() {
        if (!self::$db) {
            $config = self::$dbConfig;
            if (!$config) {
                if (!defined('TEST_CONFIG_DIR_PATH'))
                    throw new \Exception('Can\'t make db without TEST_CONFIG_' .
                                         'DIR_PATH . "config.php".');
                $config = require TEST_CONFIG_DIR_PATH . 'config.php';
            }
            self::$db = new SingleConnectionDb([]);
            self::createOrOpenTestDb($config);
        }
        return self::$db;
    }
    private function createOrOpenTestDb($config) {
        $databaseName = $config['db.database'];
        $config['db.database'] = '';
        self::$db->setConfig($config);
        self::$db->open();
        //
        if (self::$db->fetchOne(
            'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA' .
            ' WHERE SCHEMA_NAME = ?',
            [$databaseName]
        )) {
            self::$db->exec("USE {$databaseName}");
        } else {
            if (!file_exists($config['db.schemaInitFilePath'])) {
                throw new \Exception('Can\'t create batabase without $confi' .
                                     'g[\'db.schemaInitFilePath\'].');
            }
            self::$db->exec("CREATE DATABASE {$databaseName}");
            self::$db->exec("USE {$databaseName}");
            self::$db->exec(file_get_contents($config['db.schemaInitFilePath']));
        }
        $config['db.database'] = $databaseName;
        self::$db->setConfig($config);
    }
}
