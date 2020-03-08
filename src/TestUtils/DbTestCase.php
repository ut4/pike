<?php

namespace Pike\TestUtils;

abstract class DbTestCase extends ConfigProvidingTestCase {
    protected static $db = null;
    protected function setUp() {
        if (!self::$db) self::getDb();
        self::$db->beginTransaction();
    }
    protected function tearDown() {
        self::$db->rollback();
    }
    public static function getDb(array $config = null) {
        if (!self::$db) {
            self::$db = new SingleConnectionDb([]);
            self::createOrOpenTestDb($config ?? static::getAppConfig());
        }
        return self::$db;
    }
    private static function createOrOpenTestDb($config) {
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
