<?php declare(strict_types=1);

namespace Pike\Db;

use Pike\Interfaces\RowMapperInterface;

/**
 * Maps the result set, and passes its each entry to `$this->doMapRow()` only once.
 */
abstract class NoDupeMapper implements RowMapperInterface {
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
     * @param object $obj
     * @param int $rowNum
     * @param object[] $rows
     * @return ?object
     */
    public function mapRow(object $obj, int $rowNum, array $rows): ?object {
        $pkMaybeStrInt = $obj->{$this->pkKey} ?? null;
        if (!$pkMaybeStrInt) {
            return null;
        }
        $pk = "_{$pkMaybeStrInt}";
        if (($this->keys[$pk] ?? null === 1)) {
            return null;
        }
        $collected = $this->doMapRow($obj, $rowNum, $rows);
        $this->keys[$pk] = 1;
        return $collected;
    }
    /**
     * @param object $obj
     * @param int $rowNum
     * @param object[] $rows
     * @return ?object
     */
    abstract function doMapRow(object $obj, int $rowNum, array $rows): ?object;
}
