<?php declare(strict_types=1);

namespace Pike\Tests\Db\FluentDb2;

use Pike\Db;
use Pike\Db\{FluentDb2};
use PHPUnit\Framework\TestCase;

abstract class BuildQueriesTestCase extends TestCase {
    protected static function buildQuery(\Closure $doTheQuery, array $dbConfig = []): array {
        $mutedSpyingDb = new MutedSpyingDb($dbConfig);
        $fluentDb2 = new FluentDb2($mutedSpyingDb);
        $_ = $doTheQuery($fluentDb2);
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
    public function attr(int $attr, $value = null) {
        if ($attr === \PDO::ATTR_DRIVER_NAME && !$value) return $this->config["db.driver"] ?? "sqlite";
    }
}
