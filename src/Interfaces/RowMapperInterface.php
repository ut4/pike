<?php

declare(strict_types=1);

namespace Pike\Interfaces;

interface RowMapperInterface {
    /**
     * @param object $row One entry from the result set (select->fetch|fetchAll())
     * @param int $rowNum
     * @param object[] $rows The complete result set
     * @return object|null Mapped/mutated $row
     */
    public function mapRow(object $row, int $rowNum, array $rows): ?object;
}
