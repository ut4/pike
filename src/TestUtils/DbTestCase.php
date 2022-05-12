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
     * @param array<string, mixed>|null $config = null
     * @return \Pike\Db
     */
    public static function setGetDb(?array $config = null): Db {
        if (!self::$db) {
            self::$db = new SingleConnectionDb([]);
            self::createOrOpenTestDb($config ?? static::getDbConfig(), self::$db);
        }
        return self::$db;
    }
    public static function openAndPopulateTestDb(array $config, Db $db, ?array $extraInitStatements = null): void {
        self::createOrOpenTestDb($config, $db, $extraInitStatements);
    }
    /**
     * @param array<string, mixed> $config
     */
    private static function createOrOpenTestDb(array $config, Db $db, ?array $extraInitStatements = null): void {
        $initFilePath = $config['db.schemaInitFilePath'] ?? '';
        if (!$initFilePath) {
            $db->setConfig($config);
            $db->open();
            return;
        }
        //
        $initSql = self::getInitSql($initFilePath);
        if (is_array($initSql) && $extraInitStatements)
            $initSql = array_merge($initSql, $extraInitStatements);
        $isSqlite = ($config['db.driver'] ?? '') === 'sqlite';
        $db->setConfig($isSqlite
            ? $config
            // Open !sqlite databases without a selected database/schema
            : array_merge($config, ['db.database' => '']));
        $db->open();
        //
        if ($isSqlite) {
            self::populateDb($initSql, $db);
            return;
        }
        //
        $databaseName = $config['db.database'];
        if ($db->fetchOne(
            'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA' .
            ' WHERE SCHEMA_NAME = ?',
            [$databaseName]
        )) {
            $db->exec("USE {$databaseName}");
            return;
        }
        $db->exec("CREATE DATABASE {$databaseName}");
        $db->exec("USE {$databaseName}");
        $db->setConfig($config);
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
    private static function populateDb($initSql, Db $db): void {
        if (is_array($initSql)) {
            $db->exec("BEGIN");
            foreach ($initSql as $stmt)
                $db->exec($stmt);
            $db->exec("COMMIT");
        } else {
            $db->exec($initSql);
        }
    }
}
