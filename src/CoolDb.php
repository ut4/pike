<?php declare(strict_types=1);

namespace Pike;

use Envms\FluentPDO\Exception;
use Envms\FluentPDO\Queries\Select;
use Envms\FluentPDO\Query;

class CoolDb {
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
     * @return \Pike\MyInsert 
     */
    public function insert(string $tableName): MyInsert {
        return new MyInsert($this->compileTableName($tableName), $this->db);
    }
    /**
     * @param string $tableName
     * @param ?class-string $toClass = null
     * @return \Pike\MySelect
     */
    public function select(string $tableName, ?string $toClass = null): MySelect {
        $out = (new MyQuery($this->db->getPdo()))->from($this->compileTableName($tableName));
        if ($toClass)
            $out->asObject($toClass);
        // else use default assoc
        return $out;
    }
    /**
     * @param string $tableName
     * @return \Pike\MyUpdate 
     */
    public function update(string $tableName): MyUpdate {
        return new MyUpdate($this->compileTableName($tableName), $this->db);
    }
    /**
     * @param string $tableName
     * @return \Pike\MyDelete 
     */
    public function delete(string $tableName): MyDelete {
        return new MyDelete($this->compileTableName($tableName), $this->db);
    }
    /**
     * @return \Pike\Db 
     */
    public function getDb(): Db {
        return $this->db;
    }
    /**
     * @param string $input
     * @return string "[[prefix]]drop table" -> "`myprefix_drop_table`"
     */
    private function compileTableName(string $input): string {
        return $this->db->columnify($this->db->compileQuery($input));
    }
}

class MyQuery extends Query {
    /**
     * @inheritdoc
     */
    public function from(?string $table = null, ?int $primaryKey = null): Select {
        $this->setTableName($table);
        $table = $this->getFullTableName();
        $query = new MySelect($this, $table);
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
    /**
     * @param string[] $columns
     * @param bool $overrideDefault = true
     * @return $this
     */
    public function fields(array $columns, bool $overrideDefault = true): Select {
        return $this->select($columns, $overrideDefault);
    }
    /**
     * @inheritdoc
     */
    public function fetchAll($index = "", $selectOnly = "") {
        return $this->processAndGetResults(parent::fetchAll($index, $selectOnly));
    }
    /**
     * @inheritdoc
     */
    public function fetch(?string $column = null, int $cursorOrientation = \PDO::FETCH_ORI_NEXT) {
        return $this->processAndGetResults([parent::fetch($column, $cursorOrientation)])[0] ?? null;
    }
    /**
     * todo 
     */
    private function processAndGetResults($rows) {
        if (!$rows) return $rows;
        return $rows;
    }
    /**
     * Get query string
     *
     * @param bool $formatted - Return formatted query
     *
     * @return string
     * @throws Exception
     */
    public function getQuery2(bool $formatted = true): string {
        unset($this->clauses["SELECT"]);
        unset($this->clauses["FROM"]);
        return $this->getQuery($formatted);
    }
}

abstract class InsertUpdate {
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
     * assume is valid
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
     */
    public function execute(): string {
        if (!$this->theValues)
            throw new PikeException("No data to update", PikeException::BAD_INPUT);
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
    private $internalWhere = null;
    /**
     * @inheritdoc
     */
    public function values($values): MyUpdate {
        if (is_array($values)) throw new PikeException("Updating multiple objects not supported",
                                                       PikeException::BAD_INPUT);
      	return parent::values($values);
    }
    /**
     * 
     */
    public function where($condition, $parameters = [], $separator = "AND"): MyUpdate {
        if (!$this->internalWhere)
            $this->internalWhere = new MySelect(new MyQuery($this->db->getPdo()), "ignore");
        $this->internalWhere->where($condition, $parameters, $separator);
        return $this;
    }
    /**
     * @return int $numAffectedRows
     * @throws \Pike\PikeException
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
	protected $db;
    /** @var string */
    protected $tableName;
    /** @var ?\Envms\FluentPDO\Queries\Select */
    private $internalWhere = null;
    /**
     * assume is valid
     */
	public function __construct(string $tableName, Db $db) {
	    $this->db = $db;
	    $this->tableName = $tableName;
	}
    /**
     * 
     */
    public function where($condition, $parameters = [], $separator = "AND"): MyDelete {
        if (!$this->internalWhere)
            $this->internalWhere = new MySelect(new MyQuery($this->db->getPdo()), "ignore");
        $this->internalWhere->where($condition, $parameters, $separator);
        return $this;
    }
    /**
     * @return int $numAffectedRows
     * @throws \Pike\PikeException
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
