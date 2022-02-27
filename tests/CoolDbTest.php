<?php declare(strict_types=1);

namespace Pike\Tests;

use Pike\CoolDb;
use Pike\Db\NoDupeMapper;
use Pike\Interfaces\RowMapperInterface;
use Pike\TestUtils\DbTestCase;

final class CoolDbTest extends DbTestCase {
    /** @var \Pike\CoolDb */
    protected $coolDb;
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
        $this->coolDb = new CoolDb(self::$db);
    }
    protected function tearDown(): void {
        // Note: no parent::tearDown();
        self::$db->exec("DROP TABLE IF EXISTS platforms");
        self::$db->exec("DROP TABLE IF EXISTS games");
    }
    public function testInsertWithSingleObjInsertsAllProps(): void {
        $input = (object) ["id" => "1", "title" => "Title", "dev" => "Dev"];
        $insertId = $this->coolDb->insert("games")
            ->values($input)
            ->execute();
        //
        $this->assertEquals("1", $insertId);
        $actuallyInserted = self::$db->fetchOne("SELECT * FROM games");
        $this->verifyGameEquals((array) $input, $actuallyInserted);
    }
    public function testInsertWithSingleObjInsertsSelectedProps(): void {
        $insertId = $this->coolDb->insert("games")
            ->values((object) ["id" => "2", "title" => "Title2", "dev" => "Expected to be ignored"])
            ->fields(["id", "title"])
            ->execute();
        //
        $this->assertEquals("2", $insertId);
        $actuallyInserted = self::$db->fetchOne("SELECT * FROM games");
        $this->assertEquals("2", $actuallyInserted["id"], "Shouldn't ignore `id`");
        $this->assertEquals("Title2", $actuallyInserted["title"], "Shouldn't ignore `id`");
        $this->assertEquals(null, $actuallyInserted["dev"], "Should ignore `dev`");
    }



    public function testSelectMapsRowsToObject(): void {
        $this->insertTestGame("3", "Title3", "Dev3");
        //
        $rows = $this->coolDb->select("games", "\stdClass")->fetchAll();
        //
        $this->assertCount(1, $rows);
        $this->assertIsObject($rows[0]);
        $this->verifyGameEquals(["id" => "3", "title" => "Title3", "dev" => "Dev3"], (array) $rows[0]);
    }
    public function testSelectMapsRowsToAssociativeArray(): void {
        $this->insertTestGame("3", "Title3", "Dev3");
        //
        $rows = $this->coolDb->select("games"/* null means an associative array */)->fetchAll();
        //
        $this->assertCount(1, $rows);
        $this->assertIsArray($rows[0]);
        $this->verifyGameEquals(["id" => "3", "title" => "Title3", "dev" => "Dev3"], $rows[0]);
    }
    public function testSelectUsesCustomMapper(): void {
        $this->insertTestGame("9", "Title9", "Dev9");
        //
        $rows = $this->coolDb->select("games", "\stdClass")
            ->mapWith(new class () implements RowMapperInterface {
                public function mapRow(object $in, int $rowNum, array $rows): ?object {
                    $in->extra = "prop";
                    return $in;
                }
            })
            ->fetchAll();
        //
        $this->assertEquals($rows[0]->extra, "prop");
    }
    public function testSelectWithNoDupeMapperMapsRowsOnlyOnce(): void {
        $this->insertTestGame("7", "Title7", "Dev7");
        $this->insertTestGame("8", "Title8", "Dev8");
        self::$db->exec("INSERT INTO platforms VALUES (?,?,?),(?,?,?),(?,?,?)",
                        ["1","Plat1","7",  "2","Plat2","7",
                         "3","Plat3","8"]);
        //
        $rows = $this->coolDb->select("games g", "stdClass")
            ->fields(["g.id", "g.title", "p.title AS platforms_title", "p.gameId AS platforms_gameId"])
            ->leftJoin("platforms p ON (g.id = p.gameId)")
            ->mapWith(new class() extends NoDupeMapper {
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
        //
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
        $this->insertTestGame("4", "Title4", "Dev4");
        //
        $numAffected = $this->coolDb->update("games")
            ->values((object) ["title" => "Title4 2", "dev" => "Dev4 2"])
            ->where("id = ?", ["4"])
            ->execute();
        //
        $this->assertEquals(1, $numAffected);
        $actuallyUpdated = self::$db->fetchOne("SELECT * FROM games");
        $this->verifyGameEquals(["id" => "4", "title" => "Title4 2", "dev" => "Dev4 2"], $actuallyUpdated);
    }
    public function testUpdateUpdatesSelectedProps(): void {
        $this->insertTestGame("5", "Title5", "Original");
        //
        $numAffected = $this->coolDb->update("games")
            ->values((object) ["title" => "Title5 2", "dev" => "Expected to be ignored"])
            ->fields(["title"])
            ->where("id = ?", ["5"])
            ->execute();
        //
        $this->assertEquals(1, $numAffected);
        $actuallyUpdated = self::$db->fetchOne("SELECT * FROM games WHERE id=?", ["5"]);
        $this->assertEquals("Title5 2", $actuallyUpdated["title"], "Shouldn't ignore `title`");
        $this->assertEquals("Original", $actuallyUpdated["dev"], "Should ignore `dev`");
    }


    public function testDeleteDeletesRow(): void {
        $this->insertTestGame("6", "Title6", "Dev6");
        //
        $numAffected = $this->coolDb->delete("games")
            ->where("id = ?", ["6"])
            ->execute();
        //
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
