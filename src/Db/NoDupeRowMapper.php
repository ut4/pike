<?php declare(strict_types=1);

namespace Pike\Db;

use Pike\Interfaces\RowMapperInterface;

/**
 * Maps the result set, and passes its each entry to `$this->doMapRow()` exactly only.
 */
abstract class NoDupeRowMapper implements RowMapperInterface {
    /** @var array<string, string> */
    private $keys;
    /** @var string */
    private $pkKey;
    /**
     * @param ?string $pkKey = null
     */
    public function __construct(?string $pkKey = null) {
        $this->keys = [];
        $this->pkKey = $pkKey ?? "id";
    }
    /**
     * @param object $row
     * @param int $rowNum
     * @param object[] $rows
     * @return ?object
     */
    public function mapRow(object $row, int $rowNum, array $rows): ?object {
        $pkMaybeStrInt = $row->{$this->pkKey} ?? null;
        if (!$pkMaybeStrInt) {
            return null;
        }
        $pk = "_{$pkMaybeStrInt}";
        if (($this->keys[$pk] ?? null === 1)) {
            return null;
        }
        $collected = $this->doMapRow($row, $rowNum, $rows);
        $this->keys[$pk] = "mapped";
        return $collected;
    }
    /**
     * @param object $obj
     * @param int $rowNum
     * @param object[] $rows
     * @return ?object
     */
    abstract function doMapRow(object $obj, int $rowNum, array $rows): ?object;
    /**
     * @param object[] $rows
     * @param \Closure $with
     * @param ?string $pkKey = null
     * @param ?array $initial = null
     * @return \ArrayObject|array
     */
    public static function collectOnce(array $rows,
                                       \Closure $with,
                                       ?string $pkKey = null,
                                       ?array $initial = null) {
        $keys = [];
        $to = $initial ?? new \ArrayObject;
        if (!$pkKey) $pkKey = "id";
        //
        foreach ($rows as $i => $row) {
            $pk = $row->{$pkKey};
            if (!$row->{$pkKey} || array_key_exists($pk, $keys))
                continue;
            if (($entity = $with($row, $i)) !== null)
                $to[] = $entity;
            $keys[$pk] = "mapped";
        }
        //
        return $to;
    }
}
