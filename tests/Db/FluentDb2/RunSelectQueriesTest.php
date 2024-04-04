<?php declare(strict_types=1);

namespace Pike\Tests\Db\FluentDb2;

use Pike\Db\NoDupeRowMapper;
use Pike\Interfaces\RowMapperInterface;
use Pike\PikeException;

final class RunSelectQueriesTest extends RunQueriesTestCase {
    public function testSelectUsesFunctionPassedDirectlyFoFetchAll(): void {
        $data = ["id" => 10, "title" => "Title10", "dev" => "Dev10"];
        $this->insertTestGame(strval($data["id"]), $data["title"], $data["dev"]);

        // Single arg / [fn]
        $rows1 = $this->fluentDb->select("games")
            ->fetchAll(fn(int $id, string $title, string $dev) => (object) [
                "id" => $id,
                "title" => $title,
                "dev" => $dev,
            ]);
        // Two args / [\PDO::FETCH_FUNC + fn]
        $rows2 = $this->fluentDb->select("games")
            ->fetchAll(\PDO::FETCH_FUNC, fn(int $id, string $title, string $dev) => (object) [
                "id" => $id,
                "title" => $title,
                "dev" => $dev,
            ]);

        $this->assertRowsContainsOnlyThis($data, $rows1);
        $this->assertRowsContainsOnlyThis($data, $rows2);
    }
    public function testSelectUsesFunctionPassedToFetchWith(): void {
        $data = ["id" => 11, "title" => "Title11", "dev" => "Dev11"];
        $this->insertTestGame(strval($data["id"]), $data["title"], $data["dev"]);

        // Single arg / [fn]
        $q = $this->fluentDb->select("games")
            ->fetchWith(fn(int $id, string $title, string $dev) => (object) [
                "id" => $id,
                "title" => $title,
                "dev" => $dev,
            ]);
        $rows1 = $q->fetchAll();
        // Two args / [\PDO::FETCH_FUNC + fn]
        $q2 = $this->fluentDb->select("games")
            ->fetchWith(\PDO::FETCH_FUNC, fn(int $id, string $title, string $dev) => (object) [
                "id" => $id,
                "title" => $title,
                "dev" => $dev,
            ]);
        $rows2 = $q2->fetchAll();

        $this->assertRowsContainsOnlyThis($data, $rows1);
        $this->assertRowsContainsOnlyThis($data, $rows2);
    }
    public function testSelectUsesCustomMapFn(): void {
        $this->insertTestGame("12", "Title12", "Dev12");

        $rows = $this->fluentDb->select("games")
            ->mapWith(function (object $in, int $rowNum, array $rows): ?object {
                $in->extra = "prop";
                return $in;
            })
            ->fetchAll(\PDO::FETCH_OBJ);

        $this->assertEquals($rows[0]->extra, "prop");
    }
    public function testSelectUsesCustomMapClass(): void {
        $this->insertTestGame("13", "Title13", "Dev13");

        $rows = $this->fluentDb->select("games")
            ->mapWith(new class () implements RowMapperInterface {
                public function mapRow(object $in, int $rowNum, array $rows): ?object {
                    $in->extra = "prop";
                    return $in;
                }
            })
            ->fetchAll(\PDO::FETCH_OBJ);

        $this->assertEquals($rows[0]->extra, "prop");
    }
    public function testSelectMapsRowsToObject(): void {
        $data = ["id" => "14", "title" => "Title14", "dev" => "Dev14"];
        $this->insertTestGame(...array_values($data));

        $rows = $this->fluentDb->select("games")->fetchAll(\PDO::FETCH_OBJ);

        $this->assertRowsContainsOnlyThis($data, $rows);
    }
    public function testSelectMapsRowsToClass(): void {
        $data = ["id" => "14", "title" => "Title14", "dev" => "Dev14"];
        $this->insertTestGame(...array_values($data));

        $rows = $this->fluentDb->select("games")->fetchAll(
            \PDO::FETCH_CLASS,
            TestEntityClass::class
        );

        $this->assertCount(1, $rows);
        $actualFromDb = $rows[0] ?? null;
        $this->assertTrue($actualFromDb instanceof TestEntityClass);
        $this->assertNotNull($actualFromDb);
        $this->assertEquals((int)$data["id"], $actualFromDb->id);
        $this->assertEquals($data["title"], $actualFromDb->title);
        $this->assertEquals($data["dev"], $actualFromDb->dev);
    }
    public function testSelectMapsRowsToAssociativeArray(): void {
        $this->insertTestGame("15", "Title15", "Dev15");

        $rows = $this->fluentDb->select("games")->fetchAll(/* null means an associative array */);

        $this->assertCount(1, $rows);
        $this->assertIsArray($rows[0]);
        $this->verifyGameEquals(["id" => "15", "title" => "Title15", "dev" => "Dev15"], $rows[0]);
    }
    public function testSelectWithNoDupeRowMapperMapsRowsOnlyOnce(): void {
        $this->insertTestGame("16", "Title16", "Dev16");
        $this->insertTestGame("17", "Title17", "Dev17");
        self::$db->exec("INSERT INTO platforms VALUES (?,?,?),(?,?,?),(?,?,?)",
                        ["10","Plat10","16",  "11","Plat11","16",
                         "12","Plat12","17"]);

        $rows = $this->fluentDb->select("games g")
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
            ->fetchAll(\PDO::FETCH_OBJ);

        $this->assertCount(2, $rows);
        usort($rows, fn($a, $b) => $a->platforms_title[-1] <=> $b->platforms_title[-1]);
        //
        $this->assertCount(2, $rows[0]->platforms);
        $this->assertEquals($rows[0]->platforms[0]->title, "Plat10");
        $this->assertEquals($rows[0]->platforms[1]->title, "Plat11");
        //
        $this->assertCount(1, $rows[1]->platforms);
        $this->assertEquals($rows[1]->platforms[0]->title, "Plat12");
    }
    public function testSelectThrowsAnExceptionIfFreeformSqlContainsComments(): void {
        $this->expectException(PikeException::class);
        $this->expectExceptionCode(PikeException::DOING_IT_WRONG);
        $this->expectExceptionMessage("Freeform sql contains unusual characters");
        $this->fluentDb->select("games")
            ->where("id=?-- injected comment", [1])
            ->fetchAll();
    }
    public function testSelectThrowsAnExceptionIfFreeformSqlContainsMultipleStatements(): void {
        $this->expectException(PikeException::class);
        $this->expectExceptionCode(PikeException::DOING_IT_WRONG);
        $this->expectExceptionMessage("Freeform sql contains unusual characters");
        $this->fluentDb->select("games")
            ->where("id=?; injected statement", [1])
            ->fetchAll();
    }
    private function assertRowsContainsOnlyThis(array $theThis, array $rows): void {
        $this->assertCount(1, $rows);
        $this->assertIsObject($rows[0]);
        $this->verifyGameEquals($theThis, (array) $rows[0]);
    }
}
