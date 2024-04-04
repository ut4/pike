<?php declare(strict_types=1);

namespace Pike\Db;

use Pike\{Db, PikeException};
use Pike\Interfaces\RowMapperInterface;

/**
 * @phpstan-type FetchFn \Closure(...mixed): (object|array|null)
 * @phpstan-type MapFn \Closure(object, int, object[]): (object|null)
 * @phpstan-type ConfigurationsMap object{fields?: [string], where?: [string, mixed[]], limit?: [int]}
 */
class FluentDb2 {
    /** @var \Pike\Db */
    protected $db;
    /** @var class-string */
    protected $QueryCls;
    /**
     * @param \Pike\Db $db
     * @param class-string $QueryCls = Query::class
     */
    public function __construct(Db $db, string $QueryCls = Query::class) {
        $this->db = $db;
        $this->QueryCls = $QueryCls;
    }
    /**
     * @param string $table
     * @param bool $orReplace = false
     * @return \Pike\Db\Query
     */
    public function insert(string $table, bool $orReplace = false): Query {
        return $this->instantiateQuery(function (object $configurations, ?string $return) use ($table, $orReplace) {
            $valuesConf = $configurations->values[0] ?? throw new PikeException("Nothing to insert");
            $fieldsConf = $configurations->fields[0] ?? [];
            $insertType = !$orReplace ? "INSERT" : "REPLACE";
            if (!self::hasManyItems($valuesConf)) {
                [$qList, $vals, $cols] = $this->db->makeInsertQParts($valuesConf, $fieldsConf);
                // @allow \Pike\PikeException
                $numRows = $this->db->exec("{$insertType} INTO {$this->generateTableName($table)} ({$cols}) VALUES ({$qList})",
                                           $vals);
            } else {
                [$qGroups, $vals, $cols] = $this->db->makeBatchInsertQParts($valuesConf, $fieldsConf);
                // @allow \Pike\PikeException
                $numRows = $this->db->exec("{$insertType} INTO {$this->generateTableName($table)} ({$cols}) VALUES {$qGroups}",
                                           $vals);
            }
            if (!$return || $return === "insertId")
                return $numRows ? $this->db->lastInsertId() : "";
            return $numRows;
        });
    }
    /**
     * @param string $table
     * @return \Pike\Db\Query
     */
    public function select(string $table): Query {
        return $this->instantiateQuery(function (bool $isFetchAll, object $configurations, /*...??*/$fetchConfig) use ($table) {
            $fieldsConf = $configurations->fields[0] ?? null;
            $whereConf = $configurations->where ?? [null, null];
            $cols = $fieldsConf ? self::createSelectCols($fieldsConf) : "*";
            $joins0 = array_map(fn($single) => "{$single[1]} JOIN {$single[0]}", $configurations->join ?? []);
            $joins = $joins0 ? self::getValidFreeformSql(implode("", $joins0)) : "";
            $whereSql = $whereConf[0] ? self::generateWhere($whereConf[0]) : "";
            $orderBy = $this->generateOrderBy($configurations->orderBy[0] ?? null);
            $groupBy = property_exists($configurations, "groupBy") ? (" GROUP BY " . self::getValidFreeformSql($configurations->groupBy[0])) : "";
            $limit = property_exists($configurations, "limit") ? (" LIMIT " . self::getValidFreeformSql($configurations->limit[0])) : "";
            $offset = property_exists($configurations, "offset") ? (" OFFSET " . self::getValidFreeformSql($configurations->offset[0])) : "";
            $sql = "SELECT {$cols} FROM {$this->generateTableName($table)}{$joins}{$whereSql}{$orderBy}{$groupBy}{$limit}{$offset}";

            $fetchWithConf = $configurations->fetchWith ?? $fetchConfig ?? [];
            if ($fetchWithConf && (($fetchWithConf[0] ?? null) instanceof \Closure))
                $fetchWithConf = [\PDO::FETCH_FUNC, $fetchWithConf[0]];

            $res1 = $this->db->fetchAll($sql, $whereConf[1], ...$fetchWithConf);
            $mapThing = $configurations->mapWith[0] ?? null;
            if ($mapThing instanceof \Closure) {
                $resMapped = [];
                for ($i = 0; $i < count($res1); ++$i) {
                    if (($c = $mapThing($res1[$i], $i, $res1)))
                        $resMapped[] = $c;
                }
            } elseif ($mapThing instanceof RowMapperInterface) {
                $resMapped = [];
                for ($i = 0; $i < count($res1); ++$i) {
                    if (($c = $mapThing->mapRow($res1[$i], $i, $res1)))
                        $resMapped[] = $c;
                }
            } else {
                $resMapped = $res1;
            }

            return $isFetchAll ? $resMapped : ($resMapped[0] ?? null);
        });
    }
    /**
     * @param string $table
     * @return \Pike\Db\Query
     */
    public function update(string $table): Query {
        return $this->instantiateQuery(function (object $configurations) use ($table) {
            $valuesConf = $configurations->values[0] ?? throw new PikeException("No data to update", PikeException::DOING_IT_WRONG);
            $item = !self::hasManyItems($valuesConf) ? $valuesConf : throw new PikeException("Updating multiple items not supported", PikeException::DOING_IT_WRONG);
            [$cols, $values] = $this->db->makeUpdateQParts($item, $configurations->fields[0] ?? []);
            $whereConf = $configurations->where ?? throw new PikeException("Updating without WHERE!", PikeException::DOING_IT_WRONG);
            $whereSql = self::generateWhere($whereConf[0]);
            return $this->db->exec(
                "UPDATE {$this->generateTableName($table)} SET {$cols}{$whereSql}",
                [...$values, ...$whereConf[1]]
            );
        });
    }
    /**
     * @param string $table
     * @return \Pike\Db\Query
     */
    public function delete(string $table): Query {
        return $this->instantiateQuery(function (object $configurations) use ($table) {
            $whereConf = $configurations->where ?? throw new PikeException("Deleting without WHERE!", PikeException::DOING_IT_WRONG);
            $whereSql = self::generateWhere($whereConf[0]);
            return $this->db->exec(
                "DELETE FROM {$this->generateTableName($table)}{$whereSql}",
                $whereConf[1]
            );
        });
    }
    /**
     * @return \Pike\Db 
     */
    public function getDb(): Db {
        return $this->db;
    }
    /**
     * @param \Closure(ConfigurationsMap, 'insertId'|'numRows'|null): string|\Closure(bool, ConfigurationsMap, ...mixed): string $handleExecuteOrFetch
     * @return \Pike\Db\Query
     */
    private function instantiateQuery(\Closure $handleExecuteOrFetch): Query {
        $configurations = new \stdClass;
        $Cls = $this->QueryCls;
        return new $Cls(static function (string $what, array $args) use ($configurations, $handleExecuteOrFetch) {
            if ($what === "execute")
                return $handleExecuteOrFetch($configurations, $args[0]);
            elseif ($what === "fetchAll" || $what === "fetchOne")
                return $handleExecuteOrFetch($what === "fetchAll", $configurations, $args);
            else {
                if ($what !== "join")
                    $configurations->{$what} = $args;
                else
                    $configurations->{$what}[] = $args;
            }
        });
    }
    /**
     * @param string $table
     * @return string
     */
    private function generateTableName(string $table): string {
        // 1. Transform "${p}table" -> "maybeprefix_table"
        $prefixified = $this->db->compileQuery($table);
        // 2. Return escaped
        return preg_replace("/[^A-Za-z0-9\$_ ]/", "", $prefixified);
    }
    /**
     * @param string $sql
     * @return string
     */
    private static function generateWhere(string $sql): string {
        return " WHERE " . self::getValidFreeformSql($sql);
    }
    /**
     * @param string|null $input
     * @return string
     */
    private function generateOrderBy(?string $input): string {
        if (!$input)
            return "";
        return " ORDER BY " . self::getValidFreeformSql(
            str_contains(strtolower($input), "random(") && $this->db->attr(\PDO::ATTR_DRIVER_NAME) === "mysql"
                ? str_replace(["om(", "OM("], "(", $input)
                : $input
        );
    }
    /**
     * @param string $sql
     * @return string
     * @throws \Pike\PikeException
     */
    private static function getValidFreeformSql(string $sql): string {
        // $sql (provided by a dev) normally never contains these substrings (`;`, `'`, `"` and `--`)
        if (preg_match("/(?:[;'\"]|--)/", $sql))
            throw new PikeException("Freeform sql contains unusual characters", PikeException::DOING_IT_WRONG);
        return $sql;
    }
    /**
     * @param string[] $columns
     * @return string
     */
    private static function createSelectCols(array $columns): string {
        return implode(", ", array_map(fn($col) =>
            preg_replace("/[^A-Za-z0-9$\\(\\)\\._'` ]/", "", $col)
        , $columns));
    }
    /**
     * @param object|array<int, object|array<string, mixed>>|array<string, mixed> $data
     */
    private static function hasManyItems($data): bool {
        return is_array($data) && ($data[0] ?? null);
    }
}

class Query {
    /** @var \Closure(string, array): \Pike\Db\Query|int|string|false|object|array{string, mixed}|null|array<int, object|array{string, mixed}> */
    private $onCall;
    public function __construct(\Closure $onCall) {
        $this->onCall = $onCall;
    }

    // Methods for all queries

    /**
     * @param string[] $fields
     * @return $this
     */
    public function fields(array $fields): Query {
        $this->onCall->__invoke("fields", [$fields]); return $this;
    }
    /**
     * @param string $sql
     * @param array<int mixed>|mixed $bindings = []
     * @return $this
     */
    public function where(string $sql, $bindings = []): Query {
        $this->onCall->__invoke("where", [$sql, is_array($bindings) ? $bindings : [$bindings]]); return $this;
    }
    /**
     * @param string $mongoExpr
     * @param array<string, mixed> $variables = []
     * @return $this
     */
    public function mongoWhere(string $mongoExpr, array $variables = []): Query {
        [$whereSql, $whereVals] = MongoFilters::fromString($mongoExpr)->toQParts();
        if ($whereSql) {
            // Substitute "$url" -> $variables->url etc.
            foreach ($variables as $name => $val) {
                foreach ($whereVals as $i => $val) {
                    if ($val === "\${$name}")
                        $whereVals[$i] = $val;
                }
            }
            $this->where(implode(" AND ", $whereSql), $whereVals);
        }
        return $this;
    }

    // Methods for select queries

    /**
     * @param string $statement
     * @return $this
     */
    public function join(string $statement): Query {
        $this->onCall->__invoke("join", [$statement, ""]); return $this;
    }
    /**
     * @param string $statement
     * @return $this
     */
    public function leftJoin(string $statement): Query {
        $this->onCall->__invoke("join", [$statement, " LEFT"]); return $this;
    }
    /**
     * @param string $column
     * @return $this
     */
    public function groupBy(string $column): Query {
        $this->onCall->__invoke("groupBy", [$column]); return $this;
    }
    /**
     * Examples:
     * ```php
     * $db->fetchWith(fn(int $col1, string $col2) => new Class($col1, $col2)));
     * $db->fetchWith(\PDO::FETCH_FUNC, fn(int $col1, string $col2) => new Class($col1, $col2)));
     * $db->fetchWith(\PDO::FETCH_CLASS, MyClass::class);
     * $db->fetchWith(\PDO::FETCH_OBJ);
     * ```
     * @param [FetchFn]|[int, FetchFn]|[int, ...mixed]|[int] ...$fetchConfig
     * @return $this
     */
    public function fetchWith(...$fetchConfig): Query {
        $this->onCall->__invoke("fetchWith", $fetchConfig); return $this;
    }
    /**
     * @param MapFn|RowMapperInterface $with
     * @return $this
     */
    public function mapWith(\Closure|RowMapperInterface $with): Query {
        $this->onCall->__invoke("mapWith", [$with]); return $this;
    }
    /**
     * @param string $column
     * @return $this
     */
    public function orderBy(string $column): Query {
        $this->onCall->__invoke("orderBy", [$column]); return $this;
        return $this;
    }
    /**
     * @param int|string ...$pieces
     * @return $this
     */
    public function limit(...$pieces): Query {
        $this->onCall->__invoke("limit", [implode(", ", $pieces)]); return $this;
    }
    /**
     * @param int $groupBy
     * @return $this
     */
    public function offset(int $groupBy): Query {
        $this->onCall->__invoke("offset", [$groupBy]); return $this;
    }
    /**
     * @template T
     * @param FetchFn|int|class-string ...$fetchConfig
     * @return array<int, T>
     */
    public function fetchAll(...$fetchConfig): array {
        return $this->onCall->__invoke("fetchAll", $fetchConfig);
    }
    /**
     * @template T
     * @param FetchFn|int|class-string ...$fetchConfig
     * @return T|null
     */
    public function fetch(...$fetchConfig): object|array|null {
        return $this->onCall->__invoke("fetchOne", $fetchConfig);
    }

    // Methods for insert and update queries

    /**
     * @param object|array<int, object>|array<string , mixed> $values
     * @return $this
     */
    public function values(object|array $values): Query {
        $this->onCall->__invoke("values", [$values]); return $this;
    }
    /**
     * @param 'insertId'|'numRows' $return = "insertId"
     * @return int|string|false
     */
    public function execute(string $return = null): int|string|false {
        return $this->onCall->__invoke("execute", [$return]);
    }
}
