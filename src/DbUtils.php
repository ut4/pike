<?php

declare(strict_types=1);

namespace Pike;

class DbUtils {
    /**
     * In: ['col1' => 'val1', 'col2' => 'val2']
     * Out: ['?,?', ['val1', 'val2'], '`col1`,`col2`']
     *
     * @param object|array $data
     */
    public static function makeInsertBinders($data): array {
        $qs = [];
        $values = [];
        $cols = [];
        foreach ($data as $key => $val) {
            $qs[] = '?';
            $values[] = $val;
            $cols[] = self::columnify($key);
        }
        return [implode(',', $qs), $values, implode(',', $cols)];
    }
    /**
     * In: ['col1' => 'val1', 'col2' => 'val2']
     * Out: ['`col1`=?,`col2`=?', ['val1', 'val2']]
     *
     * @param object|array $data
     */
    public static function makeUpdateBinders($data): array {
        $colPairs = [];
        $values = [];
        foreach ($data as $key => $val) {
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
