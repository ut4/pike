<?php declare(strict_types=1);

namespace Pike\Tests\Db\FluentDb2;

final class RunUpdateQueriesTest extends RunQueriesTestCase {
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
}
