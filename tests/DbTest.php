<?php

namespace Pike\Tests;

use Pike\TestUtils\DbTestCase;

class MyEntity {
    public $columnName;
}

class DbTest extends DbTestCase {
    public static function getDbConfig(): array {
        return require PIKE_TEST_CONFIG_FILE_PATH;
    }


    public function testFetchOneWithFetchClassDoesNotReturnAssocArray() {
        $testQ = "SELECT 'foo' AS columnName";
        $actual = self::$db->fetchOne($testQ,
                                      null,
                                      \PDO::FETCH_CLASS,
                                      MyEntity::class);
        $this->assertInstanceOf(MyEntity::class, $actual);
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testFetchAllWithFetchClassDoesNotReturnAssocArrays() {
        $testQ = "SELECT 'foo' AS columnName";
        $actual = self::$db->fetchAll($testQ,
                                      null,
                                      \PDO::FETCH_CLASS,
                                      MyEntity::class);
        $this->assertInstanceOf(MyEntity::class, $actual[0]);
    }
}
