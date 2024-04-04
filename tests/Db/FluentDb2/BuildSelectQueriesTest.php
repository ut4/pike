<?php declare(strict_types=1);

namespace Pike\Tests\Db\FluentDb2;

use Pike\Db\FluentDb2;

final class BuildSelectQueriesTest extends BuildQueriesTestCase {
    public function testBasicSelect(): void {
        [$actualSql, $actualParams] = self::buildQuery(fn(FluentDb2 $fluentDb2) =>
            $fluentDb2->select("articles")
                ->fields(["id", "title", "publishedAt as published"])
                ->where("publishedAt <= ?", strtotime("10 September 2000"))
                ->fetchAll()
        );
        $this->assertEquals(
            "SELECT id, title, publishedAt as published FROM articles WHERE publishedAt <= ?",
            $actualSql
        );
        $this->assertEquals(
            [strtotime("10 September 2000")],
            $actualParams
        );
    }
    public function testSelectWithoutFields(): void {
        [$actualSql, $actualParams] = self::buildQuery(fn(FluentDb2 $fluentDb2) =>
            $fluentDb2->select("articles")
                ->where("1=1")
                ->fetchAll()
        );
        $this->assertEquals("SELECT * FROM articles WHERE 1=1", $actualSql);
        $this->assertEquals([], $actualParams);
    }
    public function testSelectWithoutWhere(): void {
        [$actualSql, $actualParams] = self::buildQuery(fn(FluentDb2 $fluentDb2) =>
            $fluentDb2->select("articles")
                ->fetchAll()
        );
        $this->assertEquals("SELECT * FROM articles", $actualSql);
        $this->assertEquals([], $actualParams);
    }
    public function testSelectWithManyFilters(): void {
        $time = time();
        [$actualSql, $actualParams] = self::buildQuery(fn(FluentDb2 $fluentDb2) =>
            $fluentDb2->select("articles")
                ->where("publishedAt > ?", [$time])
                ->orderBy("publishedAt DESC")
                ->limit(5)
                ->fetchAll()
        );
        $this->assertEquals(
            "SELECT *" .
            " FROM articles" .
            " WHERE publishedAt > ?" .
            " ORDER BY publishedAt DESC" .
            " LIMIT 5",
            $actualSql
        );
        $this->assertEquals(
            [$time],
            $actualParams
        );
    }
    public function testMongoWhere(): void {
        [$actualSql, $actualParams] = self::buildQuery(fn(FluentDb2 $fluentDb2) =>
            $fluentDb2->select("pages")
                ->mongoWhere(json_encode([
                    "slug" => ["\$startsWith" => "/slug"],
                    "categories" => ["\$contains" => "\"42\""]
                ]))
                ->fetchAll()
        );
        $this->assertEquals(
            "SELECT * FROM pages WHERE" .
            " `slug` LIKE ? AND `categories` LIKE ?",
            $actualSql
        );
        $this->assertEquals(
            ["/slug%", "%\"42\"%"],
            $actualParams
        );
    }
    public function testSubQueryInSelectField(): void {
        [$actualSql, $actualParams] = self::buildQuery(fn(FluentDb2 $fluentDb2) =>
            $fluentDb2->select("outerTable o")
                ->fields(["o.id", "(SELECT SUM(smthing) FROM otherTable) AS innerField"])
                ->fetchAll()
        );
        $this->assertEquals(
            "SELECT o.id, (SELECT SUM(smthing) FROM otherTable) AS innerField" .
            " FROM outerTable o",
            $actualSql
        );
        $this->assertEquals(
            [],
            $actualParams
        );
    }
    public function testOrderBy(): void {
        foreach ([
            "id DESC",
            "rank ASC, name ASC",
            "random(42)",
        ] as $input) {
            [$actualSql, $actualParams] = self::buildQuery(fn(FluentDb2 $fluentDb2) =>
                $fluentDb2->select("articles")
                    ->orderBy($input)
                    ->fetchAll()
            );
            $this->assertEquals(
                "SELECT * FROM articles ORDER BY {$input}",
                $actualSql
            );
            $this->assertEquals(
                [],
                $actualParams
            );
        }
    }
    public function testSelectNormalizesRandFunc(): void {
        [$actualSql, $actualParams] = self::buildQuery(fn(FluentDb2 $fluentDb2) =>
            $fluentDb2->select("articles")
                ->orderBy("colName random()")
                ->fetchAll(),
            ["db.driver" => "mysql"]
        );
        $this->assertEquals("SELECT * FROM articles ORDER BY colName rand()", $actualSql);
        $this->assertEquals([], $actualParams);
    }
    public function testLimitRange(): void {
        [$actualSql, $actualParams] = self::buildQuery(fn(FluentDb2 $fluentDb2) =>
            $fluentDb2->select("articles")
                ->limit(20, 10)
                ->fetchAll()
        );
        $this->assertEquals(
            "SELECT * FROM articles LIMIT 20, 10",
            $actualSql
        );
        $this->assertEquals(
            [],
            $actualParams
        );
    }
    public function testSimpleJoin(): void {
        [$actualSql, $actualParams] = self::buildQuery(fn(FluentDb2 $fluentDb2) =>
            $fluentDb2->select("articles a")
                ->fields(["a.title", "au.name"])
                ->join("users as au ON au.id = a.userId")
                ->fetchAll()
        );
        $this->assertEquals(
            "SELECT a.title, au.name" .
            " FROM articles a" .
            " JOIN users as au ON au.id = a.userId",
            $actualSql
        );
        $this->assertEquals(
            [],
            $actualParams
        );
    }
    public function testLeftJoins(): void {
        [$actualSql, $actualParams] = self::buildQuery(fn(FluentDb2 $fluentDb2) =>
            $fluentDb2->select("articles a")
                ->fields(["a.title", "p.`key` AS `propKey`", "b.field"])
                ->leftJoin("props p ON (1)")
                ->leftJoin("another b ON (1)")
                ->fetchAll()
        );
        $this->assertEquals(
            "SELECT a.title, p.`key` AS `propKey`, b.field" .
            " FROM articles a" .
            " LEFT JOIN props p ON (1)" .
            " LEFT JOIN another b ON (1)",
            $actualSql
        );
        $this->assertEquals(
            [],
            $actualParams
        );
    }
    public function testLiternalsAndFuncNamesInFields(): void {
        [$actualSql, $actualParams] = self::buildQuery(fn(FluentDb2 $fluentDb2) =>
            $fluentDb2->select("payments")
                ->fields(["'literal' AS field", "SUM(amount)"])
                ->where("invoice_id=?", 4)
                ->fetchAll()
        );
        $this->assertEquals(
            "SELECT 'literal' AS field, SUM(amount) FROM payments WHERE invoice_id=?",
            $actualSql
        );
        $this->assertEquals(
            [4],
            $actualParams
        );
    }
    public function testFuncCommasInFields(): void {
        [$actualSql, $actualParams] = self::buildQuery(fn(FluentDb2 $fluentDb2) =>
            $fluentDb2->select("payments")
                ->fields(["payments.amount"])
                ->where("payments.invoice_id = ?", 2)
                ->fetchAll()
        );
        $this->assertEquals(
            "SELECT payments.amount FROM payments WHERE payments.invoice_id = ?",
            $actualSql
        );
        $this->assertEquals(
            [2],
            $actualParams
        );
    }
    public function testTablePrefix(): void {
        [$actualSql, $actualParams] = self::buildQuery(fn(FluentDb2 $fluentDb2) =>
            $fluentDb2->select("\${p}table")->fetchAll()
        , ["db.tablePrefix" => "mypref_"]);
        $this->assertEquals(
            "SELECT * FROM mypref_table",
            $actualSql
        );
        $this->assertEquals(
            [],
            $actualParams
        );
    }
}
