<?php

declare(strict_types=1);

namespace Pike\TestUtils;

use Pike\{Db, PikeException};
use PHPUnit\Framework\TestCase;

abstract class DbTestCase extends TestCase {
    /** @var ?\Pike\Db */
    protected static $db = null;
    /**
     * @inheritdoc
     */
    protected function setUp(): void {
        self::setGetDb();
        self::$db->beginTransaction();
    }
    /**
     * @inheritdoc
     */
    protected function tearDown(): void {
        self::$db->rollBack();
    }
    /**
     * @return array<string, mixed>
     */
    public static function getDbConfig(): array {
        throw new PikeException("MyDbTestCase must implement getDbConfig()",
                                PikeException::BAD_INPUT);
    }
    /**
     * @return array<string, mixed>|null $config = null
     * @return \Pike\Db
     */
    public static function setGetDb(?array $config = null): Db {
        if (!self::$db) {
            self::$db = new SingleConnectionDb([]);
            self::createOrOpenTestDb($config ?? static::getDbConfig());
        }
        return self::$db;
    }
    /**
     * @param array<string, mixed> $config
     */
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
    /**
     * @param string $initFilePath
     * @return string|array<int, string>
     * @throws \Pike\PikeException
     */
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
    /**
     * @param string|array<int, string> $initSql
     * @inheritdoc
     */
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
