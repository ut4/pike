<?php

namespace Pike\Tests;

use PHPUnit\Framework\TestCase;
use Pike\DbUtils;

final class DbUtilsTest extends TestCase {
    public function testMakeInsertBindersReturnsPlaceholdersAndValues() {
        $data = (object) ['col1' => 'val1', 'col2' => 'val2'];
        $expected = ['?,?', array_values((array) $data), '`col1`,`col2`'];
        $this->assertEquals($expected, DbUtils::makeInsertBinders($data));
        $this->assertEquals($expected, DbUtils::makeInsertBinders((array) $data));
    }

    ////////////////////////////////////////////////////////////////////////////


    public function testMakeUpdateBinderReturnsColumnsAndValues() {
        $data = (object) ['col1' => 'val1', 'col2' => 'val2'];
        $expected = ['`col1`=?,`col2`=?', array_values((array) $data)];
        $this->assertEquals($expected, DbUtils::makeUpdateBinders($data));
        $this->assertEquals($expected, DbUtils::makeUpdateBinders((array) $data));
    }
}
