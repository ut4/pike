<?php

namespace Pike\Tests;

use PHPUnit\Framework\TestCase;
use Pike\DbUtils;

final class DbUtilsTest extends TestCase {
    public function testMakeInsertQPartsReturnsPlaceholdersAndValues() {
        $data = (object) ['col1' => 'val1', 'col2' => 'val2'];
        $expected = ['?,?', array_values((array) $data), '`col1`,`col2`'];
        $this->assertEquals($expected, DbUtils::makeInsertQParts($data));
        $this->assertEquals($expected, DbUtils::makeInsertQParts((array) $data));
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testMakeInsertQPartsSanitizesColumns() {
        $data = (object) ['col_1\' drop table;' => 'val1'];
        $expected = ['?', array_values((array) $data), '`col_1droptable`'];
        $this->assertEquals($expected, DbUtils::makeInsertQParts($data));
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testMakeBatchInsertQPartsReturnsPlaceholdersAndValues() {
        $data = [(object) ['col1' => 'val1', 'col2' => 'val2'],
                 (object) ['col1' => 'val3', 'col2' => 'val4']];
        $expected = ['(?,?),(?,?)',
                     array_merge(array_values((array) $data[0]),
                                 array_values((array) $data[1])),
                     '`col1`,`col2`'];
        $this->assertEquals($expected, DbUtils::makeBatchInsertQParts($data));
        $this->assertEquals($expected, DbUtils::makeBatchInsertQParts((array) $data));
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testMakeBatchInsertQPartsSanitizesColumns() {
        $data = [(object) ['col_1\' drop table;' => 'val1']];
        $expected = ['(?)', array_values((array) $data[0]), '`col_1droptable`'];
        $this->assertEquals($expected, DbUtils::makeBatchInsertQParts($data));
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testMakeUpdateQPartsReturnsColumnsAndValues() {
        $data = (object) ['col1' => 'val1', 'col2' => 'val2'];
        $expected = ['`col1`=?,`col2`=?', array_values((array) $data)];
        $this->assertEquals($expected, DbUtils::makeUpdateQParts($data));
        $this->assertEquals($expected, DbUtils::makeUpdateQParts((array) $data));
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testMakeUpdateQPartsSanitizesColumns() {
        $data = (object) ['#€%col$_1' => 'val1'];
        $expected = ['`col$_1`=?', array_values((array) $data)];
        $this->assertEquals($expected, DbUtils::makeUpdateQParts($data));
    }
}
