<?php declare(strict_types=1);

namespace Pike\Tests\Db\FluentDb2;

final class RunInsertQueriesTest extends RunQueriesTestCase {
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

    private function verifyGameEquals(array $expected, ?array $actualFromDb): void {
        $this->assertNotNull($actualFromDb);
        $this->assertEquals($expected["id"], $actualFromDb["id"]);
        $this->assertEquals($expected["title"], $actualFromDb["title"]);
        $this->assertEquals($expected["dev"], $actualFromDb["dev"]);
    }
}
