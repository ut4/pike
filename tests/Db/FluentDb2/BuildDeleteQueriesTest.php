<?php declare(strict_types=1);

namespace Pike\Tests\Db\FluentDb2;

use Pike\Db\FluentDb2;

final class BuildDeleteQueriesTest extends BuildQueriesTestCase {
    public function testBasicDelete(): void {
        [$actualSql, $actualParams] = self::buildQuery(fn(FluentDb2 $fluentDb2) =>
            $fluentDb2->delete("articles")
                ->where("publishedAt <= ?", strtotime("10 September 2000"))
                ->execute()
        );
        $this->assertEquals(
            "DELETE FROM articles WHERE publishedAt <= ?",
            $actualSql
        );
        $this->assertEquals(
            [strtotime("10 September 2000")],
            $actualParams
        );
    }
}
