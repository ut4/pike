<?php declare(strict_types=1);

namespace Pike\Tests\Db\FluentDb2;

final class RunDeleteQueriesTest extends RunQueriesTestCase {
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
}
