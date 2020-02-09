<?php

namespace Pike;

class DbUtils {
    /**
     * In: ['col1' => 'val1', 'col2' => 'val2']
     * Out: ['`col1`=?,`col2`=?', ['val1', 'val2']]
     *
     * @param object|array $data
     */
    public static function makeUpdateBinders($data) {
        $colPairs = [];
        $values = [];
        foreach ($data as $key => $val) {
            $colPairs[] = "`{$key}`=?";
            $values[] = $val;
        }
        return [implode(',', $colPairs), $values];
    }
}
