<?php declare(strict_types=1);

namespace Pike\Db;

use Envms\FluentPDO\{Query};
use Envms\FluentPDO\Queries\Select;
use Pike\{Db, PikeException};
use Pike\Interfaces\RowMapperInterface;

class FluentDb {
    /** @var \Pike\Db */
    protected $db;
    /** @var string */
    protected $tablePrefix;
    /**
     * @param \Pike\Db $db
     */
    public function __construct(Db $db) {
        $this->db = $db;
        $this->tablePrefix = $db->getTablePrefix();
    }
    /**
     * @param string $tableName
     * @return \Pike\Db\MyInsert 
     */
    public function insert(string $tableName): MyInsert {
        return new MyInsert($this->db->compileQuery($tableName), $this->db);
    }
    /**
     * @param string $tableName
     * @param ?class-string $toClass = null
     * @return \Pike\Db\MySelect
     */
    public function select(string $tableName, ?string $toClass = null): MySelect {
        $out = (new MyQuery($this->db->getPdo()))
            ->from($this->db->compileQuery($tableName), null, $this->db);
        if ($toClass)
            $out->asObject($toClass);
        // else use default assoc
        return $out;
    }
    /**
     * @param string $tableName
     * @return \Pike\Db\MyUpdate 
     */
    public function update(string $tableName): MyUpdate {
        return new MyUpdate($this->db->compileQuery($tableName), $this->db);
    }
    /**
     * @param string $tableName
     * @return \Pike\Db\MyDelete 
     */
    public function delete(string $tableName): MyDelete {
        return new MyDelete($this->db->compileQuery($tableName), $this->db);
    }
    /**
     * @return \Pike\Db 
     */
    public function getDb(): Db {
        return $this->db;
    }
}

class MyQuery extends Query {
    /**
     * @param ?string  $table = null      - db table name
     * @param ?int     $primaryKey = null - return one row by primary key
     * @param \Pike\Db $db = null
     * @return \Pike\Db\MySelect
     */
    public function from(?string $table = null,
                         ?int $primaryKey = null,
                         Db $db = null): Select {
        $this->setTableName($table);
        $table = $this->getFullTableName();
        $query = new MySelect($this, $table, $db);
        if ($primaryKey !== null) {
            $tableTable = $query->getFromTable();
            $tableAlias = $query->getFromAlias();
            $primaryKeyName = $this->structure->getPrimaryKey($tableTable);
            $query = $query->where("$tableAlias.$primaryKeyName", $primaryKey);
        }
        return $query;
    }
}

class MySelect extends Select {
    /** @var ?\Pike\Db */
    private $db;
    /** @var ?\Pike\Interfaces\RowMapperInterface */
    private $mapper;
    /** @var ?callable(string): string */
    private $finalQueryMutator;
    /**
     * @param \Envms\FluentPDO\Query $fluent
     * @param string                 $from
     * @param ?\Pike\Db              $db = null
     */
    function __construct(Query $fluent, $from, ?Db $db = null) {
        parent::__construct($fluent, $from);
        $this->db = $db;
        $this->mapper = null;
        $this->finalQueryMutator = null;
    }
    /**
     * @param string[] $columns
     * @param bool $overrideDefault = true
     * @return $this
     */
    public function fields(array $columns, bool $overrideDefault = true): Select {
        return $this->select($columns, $overrideDefault);
    }
    /**
     * @param \Pike\Interfaces\RowMapperInterface $mapper
     * @return $this
     */
    public function mapWith(RowMapperInterface $mapper): Select {
        $this->mapper = $mapper;
        return $this;
    }
    /**
     * @inheritdoc
     */
    public function leftJoin($statement): Select {
        return self::join("leftJoin", $statement);
    }
    /**
     * @inheritdoc
     */
    public function rightJoin($statement): Select {
        return self::join("leftJoin", $statement);
    }
    /**
     * @inheritdoc
     */
    public function innerJoin($statement): Select {
        return self::join("leftJoin", $statement);
    }
    /**
     * @inheritdoc
     */
    public function outerJoin($statement): Select {
        return self::join("leftJoin", $statement);
    }
    /**
     * @inheritdoc
     */
    public function fullJoin($statement): Select {
        return self::join("leftJoin", $statement);
    }
    /**
     * @inheritdoc
     */
    public function orderBy(string $column): Select {
        $isSqlite = $this->db->attr(\PDO::ATTR_DRIVER_NAME) === "sqlite";
        if ($isSqlite) {
            if (str_contains($column, "rand(")) $column = str_replace("rand(", "random(", $column);
            if (str_contains($column, "RAND(")) $column = str_replace("RAND(", "RANDOM(", $column);
        } else {
            if (str_contains($column, "random(")) $column = str_replace("random(", "rand(", $column);
            if (str_contains($column, "RANDOM(")) $column = str_replace("RANDOM(", "RAND(", $column);
        }
        return parent::orderBy($column);
    }
    /**
     * @param string $which "leftJoin"|"rightJoin"|"innerJoin"|"outerJoin"|"fullJoin"
     * @param string $statement
     * @return $this
     */
    private function join(string $which, string $statement): Select {
        if (!$this->db) throw new PikeException("select->db not set");
        return parent::$which($this->db->compileQuery($statement));
    }
    /**
     * Get query string
     *
     * @param bool $formatted = true - Return formatted query
     * @return string
     */
    public function getQuery2(bool $formatted = true): string {
        unset($this->clauses["SELECT"]);
        unset($this->clauses["FROM"]);
        return $this->getQuery($formatted);
    }
    /**
     * @param string $index = ""      - specify index column. Allows for data organization by field using 'field[]'
     * @param string $selectOnly = "" - select columns which could be fetched
     * @return array<int, object|array<string, mixed>>
     */
    public function fetchAll($index = "", $selectOnly = "") {
        return $this->processAndGetResults(parent::fetchAll($index, $selectOnly));
    }
    /**
     * @param ?string $column = null - column name or empty string for the whole row
     * @param int    $cursorOrientation = \PDO::FETCH_ORI_NEXT
     * @return object|array<string, mixed>|null
     */
    public function fetch(?string $column = null, int $cursorOrientation = \PDO::FETCH_ORI_NEXT) {
        return $this->processAndGetResults([parent::fetch($column, $cursorOrientation)])[0] ?? null;
    }
    /**
     * @param callable(string): string $fn
     * @return $this
     */
    public function mutateQWith(callable $fn): Select {
        $this->finalQueryMutator = $fn;
        return $this;
    }
    /**
     * @param string $mongoExpr
     * @param array<string, mixed> $variables = []
     * @return $this
     */
    public function mongoWhere(string $mongoExpr, array $variables = []): Select {
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
    /**
     * @inheritdoc
     */
    protected function buildQuery() {
        $out = parent::buildQuery();
        return !$this->finalQueryMutator
            ? $out
            : $this->db->compileQuery(call_user_func($this->finalQueryMutator, $out));
    }
    /**
     * @param array<int, object|array<string, mixed>> $rows
     * @return array<int, object|array<string, mixed>>
     */
    private function processAndGetResults(array $rows): array {
        if (!$rows || !($mapper = $this->mapper))
            return $rows;
        //
        if (!is_object($rows[0])) {
            if (!$rows[0]) return [];
            throw new PikeException("Mappers only supports objects i.e. select(..., MyObject::class)",
                                    PikeException::BAD_INPUT);
        }
        //
        $filtered = [];
        for ($i = 0; $i < count($rows); ++$i) {
            $mapped = $mapper->mapRow($rows[$i], $i, $rows);
            if ($mapped) $filtered[] = $mapped;
        }
        return $filtered;
    }
}

abstract class InsertUpdate {
    /** @var \Pike\Db */
    protected $db;
    /** @var string */
    protected $tableName;
    /** @var object[] */
    protected $theValues = [];
    /** @var bool */
    protected $hasManyValues = false;
    /** @var string[] */
    protected $onlyTheseFields = [];
    /**
     * @param string $tableName Assume is valid
     * @param \Pike\Db $db
     */
    public function __construct(string $tableName, Db $db) {
        $this->db = $db;
        $this->tableName = $tableName;
    }
    /**
     * @param object|array $values
     * @return static
     */
    public function values($values): InsertUpdate {
        $this->theValues = is_object($values) ? [$values] : $values;
        $this->hasManyValues = count($this->theValues) > 1;
        return $this;
    }
    /**
     * @param string[] $columns
     * @return static
     */
    public function fields(array $columns): InsertUpdate {
        if ($columns) $this->onlyTheseFields = $columns;
        return $this;
    }
}

class MyInsert extends InsertUpdate {
    /**
     * @return string $lastInsertId or ""
     * @throws \Pike\PikeException If there's no data to insert
     */
    public function execute(): string {
        if (!$this->theValues)
            throw new PikeException("No data to insert", PikeException::BAD_INPUT);
        if (!$this->hasManyValues) {
            [$qList, $vals, $cols] = $this->db->makeInsertQParts($this->theValues[0], $this->onlyTheseFields);
            // @allow \Pike\PikeException
            $numRows = $this->db->exec("INSERT INTO {$this->tableName} ({$cols}) VALUES ({$qList})",
                                       $vals);
        } else {
            [$qGroups, $vals, $cols] = $this->db->makeBatchInsertQParts($this->theValues, $this->onlyTheseFields);
            // @allow \Pike\PikeException
            $numRows = $this->db->exec("INSERT INTO {$this->tableName} ({$cols}) VALUES {$qGroups}",
                                       $vals);
        }
        return $numRows ? $this->db->lastInsertId() : "";
    }
}

class MyUpdate extends InsertUpdate {
    /** @var ?\Envms\FluentPDO\Queries\Select */
    protected $internalWhere = null;
    /**
     * @inheritdoc
     */
    public function values($values): MyUpdate {
        if (is_array($values)) throw new PikeException("Updating multiple objects not supported",
                                                       PikeException::BAD_INPUT);
          return parent::values($values);
    }
    /**
     * @param string|array $condition  - possibly containing ? or :name (PDO syntax)
     * @param mixed        $parameters
     * @param string       $separator - should be AND or OR
     * @return $this
     */
    public function where($condition, $parameters = [], $separator = "AND"): MyUpdate {
        if (!$this->internalWhere)
            $this->internalWhere = new MySelect(new MyQuery($this->db->getPdo()), "ignore");
        $this->internalWhere->where($condition, $parameters, $separator);
        return $this;
    }
    /**
     * @return int $numAffectedRows
     * @throws \Pike\PikeException If there's no data to update or "where" isn't set
     */
    public function execute(): int {
        if (!$this->theValues)
            throw new PikeException("No data to update", PikeException::BAD_INPUT);
        if (!$this->internalWhere)
            throw new PikeException("Updating without WHERE!", PikeException::BAD_INPUT);
        [$columns, $values] = $this->db->makeUpdateQParts($this->theValues[0], $this->onlyTheseFields);
        return $this->db->exec(
            "UPDATE {$this->tableName} SET {$columns} {$this->internalWhere->getQuery2(false)}",
            array_merge($values, $this->internalWhere->getParameters())
        );
    } 
}

class MyDelete {
    /** @var \Pike\Db */
    protected $db;
    /** @var string */
    protected $tableName;
    /** @var ?\Envms\FluentPDO\Queries\Select */
    private $internalWhere = null;
    /**
     * @param string $tableName Assume is valid
     * @param \Pike\Db $db
     */
    public function __construct(string $tableName, Db $db) {
        $this->db = $db;
        $this->tableName = $tableName;
    }
    /**
     * @param string|array $condition  - possibly containing ? or :name (PDO syntax)
     * @param mixed        $parameters
     * @param string       $separator - should be AND or OR
     * @return $this
     */
    public function where($condition, $parameters = [], $separator = "AND"): MyDelete {
        if (!$this->internalWhere)
            $this->internalWhere = new MySelect(new MyQuery($this->db->getPdo()), "ignore");
        $this->internalWhere->where($condition, $parameters, $separator);
        return $this;
    }
    /**
     * @return int $numAffectedRows
     * @throws \Pike\PikeException If "where" isn't set
     */
    public function execute(): int {
        if (!$this->internalWhere)
            throw new PikeException("Deleting without WHERE!", PikeException::BAD_INPUT);
        return $this->db->exec(
            "DELETE FROM {$this->tableName} {$this->internalWhere->getQuery2(false)}",
            $this->internalWhere->getParameters()
        );
    } 
}
