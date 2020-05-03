<?php

declare(strict_types=1);

namespace Pike\TestUtils;

use Pike\Db;

abstract class DbTestCase extends ConfigProvidingTestCase {
    protected static $db = null;
    protected function setUp(): void {
        if (!self::$db) self::getDb();
        self::$db->beginTransaction();
    }
    protected function tearDown(): void {
        self::$db->rollback();
    }
    public static function getDb(array $config = null): Db {
        if (!self::$db) {
            self::$db = new SingleConnectionDb([]);
            self::createOrOpenTestDb($config !== null ? $config : static::getAppConfig());
        }
        return self::$db;
    }
    private static function createOrOpenTestDb(array $config): void {
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
