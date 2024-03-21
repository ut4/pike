<?php declare(strict_types=1);

namespace Pike\Tests\Db\FluentDb2;

use Pike\Db\{FluentDb2};
use Pike\TestUtils\DbTestCase;

abstract class RunQueriesTestCase extends DbTestCase {
    /** @var \Pike\Db\FluentDb2 */
    protected FluentDb2 $fluentDb;
    protected function setUp(): void {
        parent::setUp();
        self::$db->exec("CREATE TABLE games (" .
            "id INT AUTO_INCREMENT, " .
            "title TEXT, " .
            "dev VARCHAR(24) DEFAULT NULL, " .
            "PRIMARY KEY (id)" .
        ")");
        self::$db->exec("CREATE TABLE platforms (" .
            "id INT AUTO_INCREMENT, " .
            "title TEXT, " .
            "gameId INT, " .
            "PRIMARY KEY (id)" .
        ")");
        $this->fluentDb = new FluentDb2(self::$db);
    }
    protected function tearDown(): void {
        // Note: no parent::tearDown();
        self::$db->exec("DROP TABLE IF EXISTS platforms");
        self::$db->exec("DROP TABLE IF EXISTS games");
    }
    public static function getDbConfig(): array {
        return require PIKE_TEST_CONFIG_FILE_PATH;
    }
    protected function insertTestGame(string $id, string $title, ?string $dev): void {
        self::$db->exec("INSERT INTO games VALUES (?,?,?)", [$id, $title, $dev]);
    }
    protected function verifyGameEquals(array $expected, ?array $actualFromDb): void {
        $this->assertNotNull($actualFromDb);
        $this->assertEquals($expected["id"], $actualFromDb["id"]);
        $this->assertEquals($expected["title"], $actualFromDb["title"]);
        $this->assertEquals($expected["dev"], $actualFromDb["dev"]);
    }
}
