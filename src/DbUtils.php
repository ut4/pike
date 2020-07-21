<?php

declare(strict_types=1);

namespace Pike;

class DbUtils {
    /**
     * @param object|array $data ['col1' => 'val1', 'col2' => 'val2']
     * @param string[] $columnsToInclude = []
     * @return array ['?,?', ['val1', 'val2'], '`col1`,`col2`']
     */
    public static function makeInsertQParts($data, array $columnsToInclude = []): array {
        $qList = [];
        $values = [];
        $cols = [];
        foreach ($data as $key => $val) {
            if ($columnsToInclude && !in_array($key, $columnsToInclude, true)) continue;
            $qList[] = '?';
            $values[] = $val;
            $cols[] = self::columnify($key);
        }
        return [implode(',', $qList), $values, implode(',', $cols)];
    }
    /**
     * @param array<object|array> $data [['col1' => 'val11', 'col2' => 'val21'], ['col1' => 'val12', 'col2' => 'val22']]
     * @param string[] $columnsToInclude = []
     * @return array ['(?,?),(?,?)', ['val1', 'val2', 'val3', 'val4'], '`col1`,`col2`']
     */
    public static function makeBatchInsertQParts(array $data, array $columnsToInclude = []): array {
        $qLists = [];
        $values = [];
        $cols = '';
        foreach ($data as $item) {
            [$qList, $currentValues, $currentCols] = self::makeInsertQParts($item, $columnsToInclude);
            if ($cols && $currentCols !== $cols)
                throw new PikeException('Insert items must have identical columns',
                                        PikeException::BAD_INPUT);
            $qLists[] = "({$qList})";
            $values = array_merge($values, $currentValues);
            $cols = $currentCols;
        }
        return [implode(',', $qLists), $values, $cols];
    }
    /**
     * @param object|array $data ['col1' => 'val1', 'col2' => 'val2']
     * @param string[] $columnsToInclude = []
     * @return array ['`col1`=?,`col2`=?', ['val1', 'val2']]
     */
    public static function makeUpdateQParts($data, array $columnsToInclude = []): array {
        $colPairs = [];
        $values = [];
        foreach ($data as $key => $val) {
            if ($columnsToInclude && !in_array($key, $columnsToInclude, true)) continue;
            $colPairs[] = self::columnify($key) . '=?';
            $values[] = $val;
        }
        return [implode(',', $colPairs), $values];
    }
    /**
     * In: '$foo %&" _bar'
     * Out: '`$foo_bar`'
     *
     * @param string $columnNameCandidate
     * @return string
     */
    public static function columnify(string $columnNameCandidate): string {
        return '`' . preg_replace('/[^A-Za-z0-9$_]/', '', $columnNameCandidate) . '`';
    }
}
