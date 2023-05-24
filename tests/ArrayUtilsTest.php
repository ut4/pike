<?php

namespace Pike\Tests;

use PHPUnit\Framework\TestCase;
use Pike\ArrayUtils;

final class ArrayUtilsTest extends TestCase {
    public function testFindReturnsValues() {
        $plainArray = ['val1', 'val2'];
        $arrayObject = new \ArrayObject($plainArray);
        $this->assertEquals($plainArray[0], ArrayUtils::find($plainArray, fn($v) => $v === 'val1'));
        $this->assertEquals('default', ArrayUtils::find($plainArray, fn() => false, 'default'));
        $this->assertEquals($arrayObject[0], ArrayUtils::find($arrayObject, fn($v) => $v === 'val1'));
        $this->assertEquals('default', ArrayUtils::find($arrayObject, fn() => false, 'default'));
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testFindByKeyReturnsObjectOrAssoc() {
        $assocs = [['prop' => 'val1'], ['prop' => 'val2']];
        $objects = array_map(function ($a) { return (object) $a; }, $assocs);
        $this->assertEquals($objects[0], ArrayUtils::findByKey($objects, 'val1', 'prop'));
        $this->assertEquals($assocs[0], ArrayUtils::findByKey($assocs, 'val1', 'prop'));
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testFilterByKeyReturnsArrayOfObjectsOrAssocs() {
        $assocs = [['prop' => 'val1'], ['prop' => 'val2'], ['prop' => 'val2']];
        $objects = array_map(function ($a) { return (object) $a; }, $assocs);
        $this->assertEquals(array_slice($objects, 1),
                            ArrayUtils::filterByKey($objects, 'val2', 'prop'));
        $this->assertEquals(array_slice($assocs, 1),
                            ArrayUtils::filterByKey($assocs, 'val2', 'prop'));
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testFindIndexByKeyReturnsObjectOrAssocIndex() {
        $assocs = [['prop' => 'val1'], ['prop' => 'val2']];
        $objects = array_map(function ($a) { return (object) $a; }, $assocs);
        $this->assertEquals(0, ArrayUtils::findIndexByKey($objects, 'val1', 'prop'));
        $this->assertEquals(0, ArrayUtils::findIndexByKey($assocs, 'val1', 'prop'));
    }
}
