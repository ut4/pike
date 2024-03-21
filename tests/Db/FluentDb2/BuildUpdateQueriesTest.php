<?php declare(strict_types=1);

namespace Pike\Tests\Db\FluentDb2;

use Pike\Db\FluentDb2;

final class BuildUpdateQueriesTest extends BuildQueriesTestCase {
    public function testInsertOrReplace(): void {
        [$actualSql, $actualParams] = self::buildQuery(fn(FluentDb2 $fluentDb2) =>
            $fluentDb2->update("Pages")
                ->values((object) ["title" => "New title"])
                ->where("`id` = ?", "abcd")
                ->execute()
        );
        $this->assertEquals(
            "UPDATE Pages SET `title`=? WHERE `id` = ?",
            $actualSql
        );
        $this->assertEquals(
            ["New title", "abcd"],
            $actualParams
        );
    }
}
