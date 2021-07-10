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


    public function testMakeInsertQPartsFiltersColumns() {
        $data = (object) ['col1' => 'val1', 'col2' => 'val2', 'col3' => 'val3'];
        $cols = ['col2', 'col1', 'irrelevant'];
        $expected = ['?,?', ['val1', 'val2'], '`col1`,`col2`'];
        $this->assertEquals($expected, DbUtils::makeInsertQParts($data, $cols));
        //
        $data = (object) ['col1' => 'val1', 'irrelevant' => 'val2'];
        $cols = ['col1'];
        $expected = ['?', ['val1'], '`col1`'];
        $this->assertEquals($expected, DbUtils::makeInsertQParts($data, $cols));
        //
        $this->assertEquals(['', [], ''], DbUtils::makeInsertQParts($data, ['col4']));
        $this->assertEquals(['', [], ''], DbUtils::makeInsertQParts([], $cols));
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


    public function testMakeBatchInsertQPartsFiltersColumns() {
        $data = [(object) ['col1' => 'val1', 'col2' => 'val2', 'col3' => 'val3'],
                 (object) ['col1' => 'val4', 'col2' => 'val5', 'col3' => 'val6']];
        $cols = ['col2', 'col3'];
        $expected = ['(?,?),(?,?)',
                     ['val2', 'val3', 'val5', 'val6'],
                     '`col2`,`col3`'];
        $this->assertEquals($expected, DbUtils::makeBatchInsertQParts($data, $cols));
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testMakeUpdateQPartsReturnsColumnsAndValues() {
        $data = (object) ['col1' => 'val1', 'col2' => 'val2'];
        $expected = ['`col1`=?,`col2`=?', array_values((array) $data)];
        $this->assertEquals($expected, DbUtils::makeUpdateQParts($data));
        $this->assertEquals($expected, DbUtils::makeUpdateQParts((array) $data));
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testMakeUpdateQPartsFiltersColumns() {
        $data = (object) ['col1' => 'val1', 'col2' => 'val2', 'col3' => 'val3'];
        $cols = ['col2', 'col3'];
        $expected = ['`col2`=?,`col3`=?', ['val2', 'val3']];
        $this->assertEquals($expected, DbUtils::makeUpdateQParts($data, $cols));
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testMakeUpdateQPartsSanitizesColumns() {
        $data = (object) ['#€%col$_1' => 'val1'];
        $expected = ['`col$_1`=?', array_values((array) $data)];
        $this->assertEquals($expected, DbUtils::makeUpdateQParts($data));
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testMakeInsertQPartsAllowsNumericArrays() {
        $data = ['a', 'b'];
        $expected = ['?,?', $data, '`0`,`1`'];
        $this->assertEquals($expected, DbUtils::makeInsertQParts($data));
    }
}
