<?php declare(strict_types=1);

namespace Pike\Db;

use Pike\{Db, PikeException};

/**
 * @phpstan-type FetchFn \Closure(...mixed): (object|array|null)
 * @phpstan-type MapFn \Closure(object, int, object[]): (object|null)
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
        return $this->newQuery(function (object $configurations, ?string $return) use ($table, $orReplace) {
            $valuesConf = $configurations->values[0] ?? throw new PikeException("Nothing to insert");
            $fieldsConf = $configurations->fields[0] ?? [];
            $insertType = !$orReplace ? "INSERT" : "REPLACE";
            if (is_object($valuesConf) || !($valuesConf[0] ?? null)) {
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
        return $this->newQuery(function (bool $isFetchAll, object $configurations, /*...??*/$fetchConfig) use ($table) {
            //
        });
    }
    /**
     * @param string $table
     * @return \Pike\Db\Query
     */
    public function update(string $table): Query {
        return $this->newQuery(function (object $configurations/*, ?string $return*/) use ($table) {
            //
        });
    }
    /**
     * @param string $table
     * @return \Pike\Db\Query
     */
    public function delete(string $table): Query {
        return $this->newQuery(function (object $configurations) use ($table) {
            $whereConf = $configurations->where ?? throw new PikeException("Deleting without WHERE!", PikeException::DOING_IT_WRONG);
            $whereSql = self::generateWhereSql($whereConf[0]);
            return $this->db->exec(
                "DELETE FROM {$this->generateTableName($table)}{$whereSql}",
                $whereConf[1]
            );
        });
    }
    /**
     * @param todo $onExec
     * @return \Pike\Db\Query
     */
    private function newQuery(\Closure $onExec): Query {
        $configurations = new \stdClass;
        $Cls = $this->QueryCls;
        return new $Cls(static function (string $what, array $args) use ($configurations, $onExec) {
            if ($what === "execute")
                return $onExec($configurations, $args[0]);
            else
                $configurations->{$what} = $args;
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
    /**
     * @param string $sql
     * @return string
     */
    private static function generateWhereSql(string $sql): string {
        return " WHERE " . self::getValidFreeformSql($sql);
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
}

class Query {
    /** @var \Closure(string, array): \Pike\Db\Query|int|string|false */
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
     * @param array<int mixed>|mixed $bindings
     * @return $this
     */
    public function where(string $sql, $bindings): Query {
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
