<?php

declare(strict_types=1);

namespace Pike\TestUtils;

use Pike\Db;

abstract class DbTestCase extends ConfigProvidingTestCase {
    /** @var ?\Pike\Db */
    protected static $db = null;
    protected function setUp(): void {
        self::setGetDb();
        self::$db->beginTransaction();
    }
    protected function tearDown(): void {
        self::$db->rollBack();
    }
    public static function setGetDb(array $config = null): Db {
        if (!self::$db) {
            self::$db = new SingleConnectionDb([]);
            self::createOrOpenTestDb($config ?? self::setGetConfig());
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
            if (!($config['db.schemaInitFilePath'] ?? '')) {
                throw new \Exception('Can\'t create batabase without $confi' .
                                     'g[\'db.schemaInitFilePath\'].');
            }
            if (!file_exists($config['db.schemaInitFilePath'])) {
                throw new \Exception('Failed to read file `'.
                                     $config['db.schemaInitFilePath'] .'`.');
            }
            self::$db->exec("CREATE DATABASE {$databaseName}");
            self::$db->exec("USE {$databaseName}");
            self::$db->exec(file_get_contents($config['db.schemaInitFilePath']));
        }
        $config['db.database'] = $databaseName;
        self::$db->setConfig($config);
    }
}
