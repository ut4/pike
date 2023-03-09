<?php declare(strict_types=1);

namespace Pike\Db;

use Pike\{DbUtils, PikeException, Validation};

final class MongoFilters {
    /** @var array<string, array{0: string, 1: mixed}> */ 
    private array $filters; 
    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    private function __construct(array $filters) {
        $this->filters = $filters;
    }
    /**
     * @return array<string, array{0: string, 1: mixed}> ["<colName>" => [<filterType>, <value>]...]
     */
    public function getFilters(): array {
        return $this->filters;
    }
    /**
     * @param string $filters
     * @return \Pike\Db\MongoFilters
     * @throws \Pike\PikeException
     */
    public static function fromString(string $filters): MongoFilters {
        $input = json_decode($filters, false, 512, JSON_THROW_ON_ERROR);
        if (!is_object($input)) throw new PikeException("Filters must by an object",
                                                        PikeException::BAD_INPUT);
        $filters = [];
        foreach ($input as $colName => $filter) {
            if (!is_object($filter))
                throw new PikeException("Filter must by an object",
                                        PikeException::BAD_INPUT);
            if (!Validation::isIdentifier(str_replace(".", "", $colName)))
                throw new PikeException("Filter columnName name `{$colName}` is not" .
                                        " valid columnName expression",
                                        PikeException::BAD_INPUT);
            $filterType = (new \ArrayIterator($filter))->key();
            if (in_array($filterType, ["\$eq", "\$in", "\$startsWith", "\$contains", "\$gt"])) {
                if ($filterType === "\$in" && !is_array($filter->{$filterType}))
                    throw new PikeException("\$in value must be an array",
                                            PikeException::BAD_INPUT);
                $filters[$colName] = [$filterType, $filter->{$filterType}];
            } else
                throw new PikeException("Unsupported filter type `{$filterType}`",
                                        PikeException::BAD_INPUT);
        }
        return new self($filters);
    }
    /**
     * @return array{0: string[], 1: mixed[]} [<whereSql>, <whereVals>]
     */
    public function toQParts(): array {
        $whereSql = [];
        $whereVals = [];
        foreach ($this->filters as $colName => [$filterType, $value]) {
            $pcs = explode(".", $colName, 2);
            $col = count($pcs) === 1 ? DbUtils::columnify($pcs[0]) : ("{$pcs[0]}." . DbUtils::columnify($pcs[1]));
            if ($filterType === "\$eq") {
                $whereSql[] = "{$col} = ?";
                $whereVals[] = $value;
            } elseif ($filterType === "\$in" && $value) {
                $whereSql[] = "{$col} IN (".implode(",",array_fill(0,count($value),"?")).")";
                $whereVals = array_merge($whereVals, $value);
            } elseif ($filterType === "\$startsWith") {
                $whereSql[] = "{$col} LIKE ?";
                $whereVals[] = "{$value}%";
            } elseif ($filterType === "\$contains") {
                $whereSql[] = "{$col} LIKE ?";
                $whereVals[] = "%{$value}%";
            } elseif ($filterType === "\$gt") {
                $whereSql[] = "{$col} > ?";
                $whereVals[] = $value;
            }
        }
        return [$whereSql, $whereVals];
    }
    /**
     * @param string $columnName
     * @return bool
     */
    public function hasFilter(string $columnName): bool {
        return $this->getFilter($columnName) !== null;
    }
    /**
     * @param string $columnName
     * @return ?array{0: string, 1: mixed} [<filterType>, <value>]|null
     */
    public function getFilter(string $columnName): ?array {
        return $this->filters[$columnName] ?? null;
    }
}
