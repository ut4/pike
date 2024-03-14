<?php declare(strict_types=1);

namespace Pike\Tests\Db\FluentDb2;

use Pike\Db\FluentDb2;

final class BuildInsertQueriesTest extends BuildQueriesTestCase {
    public function testInsertOrReplace(): void {
        [$actualSql, $actualParams] = self::buildQuery(fn(FluentDb2 $fluentDb2) =>
            $fluentDb2->insert("articles", orReplace: true)
                ->values((object) ["id" => "1", "title" => "Title"])
                ->execute()
        );
        $this->assertEquals(
            "REPLACE INTO articles (`id`,`title`) VALUES (?,?)",
            $actualSql
        );
        $this->assertEquals(
            ["1", "Title"],
            $actualParams
        );
    }
    public function testInsertSelectedFields(): void {
        [$actualSql, $actualParams] = self::buildQuery(fn(FluentDb2 $fluentDb2) =>
            $fluentDb2->insert("articles")
                ->values((object) ["id" => "1", "title" => "Title", "text" => "..."])
                ->fields(["title", "text"])
                ->execute()
        );
        $this->assertEquals(
            "INSERT INTO articles (`title`,`text`) VALUES (?,?)",
            $actualSql
        );
        $this->assertEquals(
            ["Title", "..."],
            $actualParams
        );
    }
}
