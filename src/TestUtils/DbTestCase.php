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
        $initFilePath = $config['db.schemaInitFilePath'] ?? '';
        if (!$initFilePath) {
            self::$db->setConfig($config);
            self::$db->open();
            return;
        }
        //
        $initSql = self::getInitSql($initFilePath);
        $isSqlite = ($config['db.driver'] ?? '') === 'sqlite';
        self::$db->setConfig($isSqlite
            ? $config
            // Open !sqlite databases without a selected database/schema
            : array_merge($config, ['db.database' => '']));
        self::$db->open();
        //
        if ($isSqlite) {
            self::populateDb($initSql);
            return;
        }
        //
        $databaseName = $config['db.database'];
        if (self::$db->fetchOne(
            'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA' .
            ' WHERE SCHEMA_NAME = ?',
            [$databaseName]
        )) {
            self::$db->exec("USE {$databaseName}");
            return;
        }
        self::$db->exec("CREATE DATABASE {$databaseName}");
        self::$db->exec("USE {$databaseName}");
        self::$db->setConfig($config);
        self::populateDb($initSql);
    }
    private static function getInitSql(string $initFilePath) {
        $contents = file_get_contents($initFilePath);
        //
        if (strpos($contents, '<?php') !== 0) {
            return $contents;
        }
        if (is_array(($statements = require $initFilePath))) {
            return $statements;
        }
        throw new \Exception('$config[\'db.schemaInitFilePath\'] must contain <sql>' .
                             ' or <?php return [<sql>...]');
    }
    private static function populateDb($initSql): void {
        if (is_array($initSql)) {
            self::$db->exec("BEGIN");
            foreach ($initSql as $stmt)
                self::$db->exec($stmt);
            self::$db->exec("COMMIT");
        } else {
            self::$db->exec($initSql);
        }
    }
}
