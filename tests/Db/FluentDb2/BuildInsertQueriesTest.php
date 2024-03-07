<?php declare(strict_types=1);

namespace Pike\Tests\Db\FluentDb2;

use Pike\Db;
use Pike\Db\{FluentDb2};
use PHPUnit\Framework\TestCase;

final class BuildInsertQueriesTest extends TestCase {
    public function testInsertOrReplace(): void {
        [$actualSql, $actualParams] = self::buildQuery(fn($fluentDb2) =>
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
        [$actualSql, $actualParams] = self::buildQuery(fn($fluentDb2) =>
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
    private static function buildQuery(\Closure $doTheQ): array {
        $mutedSpyingDb = new MutedSpyingDb([]);
        $fluentDb2 = new FluentDb2($mutedSpyingDb);
        $_ = $doTheQ($fluentDb2);
        return [$mutedSpyingDb->executedQuery, $mutedSpyingDb->executedParams];
    }
}

final class MutedSpyingDb extends Db {
    public string $executedQuery = "";
    public array $executedParams = [];
    public function fetchAll(string $query,
                             array $params = null,
                             ...$fetchConfig): array {
        $this->executedQuery = $query;
        $this->executedParams = $params ?? [];
        return [];
    }
    public function exec(string $query, array $params = null): int {
        $this->executedQuery = $query;
        $this->executedParams = $params ?? [];
        return 1;
    }
    public function lastInsertId(): string {
        return "";
    }
}
