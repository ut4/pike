<?php declare(strict_types=1);

namespace Pike\Tests;

use Pike\Db\{FluentDb, NoDupeRowMapper};
use Pike\Interfaces\RowMapperInterface;
use Pike\TestUtils\DbTestCase;

class TestState extends \stdClass {}

final class FluentDbTest extends DbTestCase {
    /** @var \Pike\Db\FluentDb */
    protected $fluentDb;
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
        $this->fluentDb = new FluentDb(self::$db);
    }
    protected function tearDown(): void {
        // Note: no parent::tearDown();
        self::$db->exec("DROP TABLE IF EXISTS platforms");
        self::$db->exec("DROP TABLE IF EXISTS games");
    }
    public static function getDbConfig(): array {
        return require PIKE_TEST_CONFIG_FILE_PATH;
    }
    public function testInsertWithSingleObjInsertsAllProps(): void {
        // -- Setup test ----
        $input = (object) ["id" => "1", "title" => "Title", "dev" => "Dev"];

        // -- Invoke insert single -feature ----
        $insertId = $this->fluentDb->insert("games")
            ->values($input)
            ->execute();

        // -- Verify inserted single value fully ----
        $this->assertEquals("1", $insertId);
        $actuallyInserted = self::$db->fetchOne("SELECT * FROM games");
        $this->verifyGameEquals((array) $input, $actuallyInserted);
    }
    public function testInsertWithSingleObjInsertsSelectedProps(): void {
        // -- Setup test ----
        $input = (object) ["id" => "2", "title" => "Title2", "dev" => "Expected to be ignored"];

        // -- Invoke insert single -feature ----
        $insertId = $this->fluentDb->insert("games")
            ->values($input)
            ->fields(["id", "title"])
            ->execute();

        // -- Verify inserted only selected fields ----
        $this->assertEquals("2", $insertId);
        $actuallyInserted = self::$db->fetchOne("SELECT * FROM games");
        $this->assertEquals("2", $actuallyInserted["id"], "Shouldn't ignore `id`");
        $this->assertEquals("Title2", $actuallyInserted["title"], "Shouldn't ignore `id`");
        $this->assertEquals(null, $actuallyInserted["dev"], "Should ignore `dev`");
    }
    public function testInsertWithManyObjsInsertsAllProps(): void {
        // -- Setup test ----
        $input = [(object) ["id" => "10", "title" => "Title10", "dev" => "Dev10"],
                  (object) ["id" => "11", "title" => "Title11", "dev" => "Dev11"]];

        // -- Invoke insert multiple values -feature ----
        $insertId = $this->fluentDb->insert("games")
            ->values($input)
            ->execute();

        // -- Verify inserted all objects fully ----
        $this->assertGreaterThanOrEqual(10, (int) $insertId);
        /** @var array[] */
        $actuallyInserted = self::$db->fetchAll("SELECT * FROM games WHERE `id`>='10'" .
                                                " ORDER BY `id` ASC");
        $this->assertCount(2, $actuallyInserted);
        $this->verifyGameEquals((array) $input[0], $actuallyInserted[0]);
        $this->verifyGameEquals((array) $input[1], $actuallyInserted[1]);
    }
    public function testInsertWithManyObjsInsertsSelectedProps(): void {
        // -- Setup test ----
        $input = [(object) ["id" => "12", "title" => "Title12", "dev" => "Expected to be ignored"],
                  (object) ["id" => "13", "title" => "Title13", "dev" => "Expected to be ignored"]];

        // -- Invoke insert multiple values -feature ----
        $this->fluentDb->insert("games")
            ->fields(["id", "title"])
            ->values($input)
            ->execute();

        // -- Verify inserted all objects using only selected fields ----
        /** @var array[] */
        $actuallyInserted = self::$db->fetchAll("SELECT * FROM games WHERE `id`>='12'" .
                                                " ORDER BY `id` ASC");
        $this->assertEquals("12", $actuallyInserted[0]["id"], "Shouldn't ignore `id`");
        $this->assertEquals("Title12", $actuallyInserted[0]["title"], "Shouldn't ignore `id`");
        $this->assertEquals(null, $actuallyInserted[0]["dev"], "Should ignore `dev`");
        $this->assertEquals("13", $actuallyInserted[1]["id"], "Shouldn't ignore `id`");
        $this->assertEquals("Title13", $actuallyInserted[1]["title"], "Shouldn't ignore `id`");
        $this->assertEquals(null, $actuallyInserted[1]["dev"], "Should ignore `dev`");
    }



    public function testSelectMapsRowsToObject(): void {
        // -- Setup test ----
        $this->insertTestGame("3", "Title3", "Dev3");

        // -- Invoke select -feature ----
        $rows = $this->fluentDb->select("games", "\stdClass")->fetchAll();

        // -- Verify returned filtered rows ----
        $this->assertCount(1, $rows);
        $this->assertIsObject($rows[0]);
        $this->verifyGameEquals(["id" => "3", "title" => "Title3", "dev" => "Dev3"], (array) $rows[0]);
    }
    public function testSelectMapsRowsToAssociativeArray(): void {
        // -- Setup test ----
        $this->insertTestGame("3", "Title3", "Dev3");

        // -- Invoke select -feature without the toClass-argument ----
        $rows = $this->fluentDb->select("games"/* null means an associative array */)->fetchAll();

        // -- Verify returned filtered rows ----
        $this->assertCount(1, $rows);
        $this->assertIsArray($rows[0]);
        $this->verifyGameEquals(["id" => "3", "title" => "Title3", "dev" => "Dev3"], $rows[0]);
    }
    public function testSelectUsesCustomMapper(): void {
        // -- Setup test ----
        $this->insertTestGame("9", "Title9", "Dev9");

        // -- Invoke select -feature using a custom row mapper ----
        $rows = $this->fluentDb->select("games", "\stdClass")
            ->mapWith(new class () implements RowMapperInterface {
                public function mapRow(object $in, int $rowNum, array $rows): ?object {
                    $in->extra = "prop";
                    return $in;
                }
            })
            ->fetchAll();

        // -- Verify returned filtered rows ----
        $this->assertEquals($rows[0]->extra, "prop");
    }
    public function testSelectWithNoDupeRowMapperMapsRowsOnlyOnce(): void {
        // -- Setup test ----
        $this->insertTestGame("7", "Title7", "Dev7");
        $this->insertTestGame("8", "Title8", "Dev8");
        self::$db->exec("INSERT INTO platforms VALUES (?,?,?),(?,?,?),(?,?,?)",
                        ["1","Plat1","7",  "2","Plat2","7",
                         "3","Plat3","8"]);

        // -- Invoke select -feature using a custom row mapper wrapped to NuDupeRowMapper ----
        $rows = $this->fluentDb->select("games g", "stdClass")
            ->fields(["g.id", "g.title", "p.title AS platforms_title", "p.gameId AS platforms_gameId"])
            ->leftJoin("platforms p ON (g.id = p.gameId)")
            ->mapWith(new class() extends NoDupeRowMapper {
                public function doMapRow(object $obj, int $rowNum, array $rows): ?object {
                    $obj->platforms = [];
                    foreach ($rows as $obj2) {
                        if ($obj2->platforms_gameId === $obj->id)
                            $obj->platforms[] = (object) ["title" => $obj2->platforms_title];
                    }
                    return $obj;
                }
            })
            ->fetchAll();

        // -- Verify mapped rows correctly ----
        $this->assertCount(2, $rows);
        usort($rows, fn($a, $b) => $a->platforms_title[-1] <=> $b->platforms_title[-1]);
        //
        $this->assertCount(2, $rows[0]->platforms);
        $this->assertEquals($rows[0]->platforms[0]->title, "Plat1");
        $this->assertEquals($rows[0]->platforms[1]->title, "Plat2");
        //
        $this->assertCount(1, $rows[1]->platforms);
        $this->assertEquals($rows[1]->platforms[0]->title, "Plat3");
    }


    public function testUpdateUpdatesAllProps(): void {
        // -- Setup test ----
        $this->insertTestGame("4", "Title4", "Dev4");

        // -- Invoke update -feature ----
        $numAffected = $this->fluentDb->update("games")
            ->values((object) ["title" => "Title4 2", "dev" => "Dev4 2"])
            ->where("id = ?", ["4"])
            ->execute();

        // -- Verify overwrote matched rows fully ----
        $this->assertEquals(1, $numAffected);
        $actuallyUpdated = self::$db->fetchOne("SELECT * FROM games");
        $this->verifyGameEquals(["id" => "4", "title" => "Title4 2", "dev" => "Dev4 2"], $actuallyUpdated);
    }
    public function testUpdateUpdatesSelectedProps(): void {
        // -- Setup test ----
        $this->insertTestGame("5", "Title5", "Original");

        // -- Invoke update -feature ----
        $numAffected = $this->fluentDb->update("games")
            ->values((object) ["title" => "Title5 2", "dev" => "Expected to be ignored"])
            ->fields(["title"])
            ->where("id = ?", ["5"])
            ->execute();

        // -- Verify overwrote matched rows using only selected fields ----
        $this->assertEquals(1, $numAffected);
        $actuallyUpdated = self::$db->fetchOne("SELECT * FROM games WHERE id=?", ["5"]);
        $this->assertEquals("Title5 2", $actuallyUpdated["title"], "Shouldn't ignore `title`");
        $this->assertEquals("Original", $actuallyUpdated["dev"], "Should ignore `dev`");
    }


    public function testDeleteDeletesRow(): void {
        // -- Setup test ----
        $this->insertTestGame("6", "Title6", "Dev6");

        // -- Invoke delete -feature ----
        $numAffected = $this->fluentDb->delete("games")
            ->where("id = ?", ["6"])
            ->execute();

        // -- Verify deleted matched rows ----
        $this->assertEquals(1, $numAffected);
        $actuallyDeleted = self::$db->fetchOne("SELECT * FROM games");
        $this->assertNull($actuallyDeleted);
    }


    private function insertTestGame(string $id, string $title, ?string $dev): void {
        self::$db->exec("INSERT INTO games VALUES (?,?,?)", [$id, $title, $dev]);
    }
    private function verifyGameEquals(array $expected, ?array $actualFromDb): void {
        $this->assertNotNull($actualFromDb);
        $this->assertEquals($expected["id"], $actualFromDb["id"]);
        $this->assertEquals($expected["title"], $actualFromDb["title"]);
        $this->assertEquals($expected["dev"], $actualFromDb["dev"]);
    }
}
