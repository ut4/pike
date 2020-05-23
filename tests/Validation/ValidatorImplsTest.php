<?php

namespace Pike\Tests\Validation;

use PHPUnit\Framework\TestCase;
use Pike\Validation;

final class ValidatorImplsTest extends TestCase {
    public function testTypeValidatorValidatesDataType() {
        $v = function() { return Validation::makeValueValidator(); };
        $this->assertNotEmpty($v()->rule('type', 'string')->validate([]));
        $this->assertEmpty($v()->rule('type', 'string')->validate('str'));
        $this->assertNotEmpty($v()->rule('type', 'int')->validate([]));
        $this->assertEmpty($v()->rule('type', 'int')->validate(1));
        $this->assertNotEmpty($v()->rule('type', 'array')->validate(1));
        $this->assertEmpty($v()->rule('type', 'array')->validate([]));
        $this->assertNotEmpty($v()->rule('type', 'bool')->validate([]));
        $this->assertEmpty($v()->rule('type', 'bool')->validate(true));
        $this->assertNotEmpty($v()->rule('type', 'float')->validate([]));
        $this->assertEmpty($v()->rule('type', 'float')->validate(1.2));
        $this->assertNotEmpty($v()->rule('type', 'object')->validate([]));
        $this->assertEmpty($v()->rule('type', 'object')->validate(new \stdClass));
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testMinLengthValidatorValidatesLength() {
        $v = function() { return Validation::makeValueValidator(); };
        $this->assertNotEmpty($v()->rule('minLength', 2)->validate('s'));
        $this->assertEmpty($v()->rule('minLength', 2)->validate('st'));
        $this->assertNotEmpty($v()->rule('minLength', 2)->validate([1]));
        $this->assertEmpty($v()->rule('minLength', 2)->validate([1,2]));
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testMaxLengthValidatorValidatesLength() {
        $v = function() { return Validation::makeValueValidator(); };
        $this->assertNotEmpty($v()->rule('maxLength', 2)->validate('str'));
        $this->assertEmpty($v()->rule('maxLength', 2)->validate('st'));
        $this->assertNotEmpty($v()->rule('maxLength', 2)->validate([1,2,3]));
        $this->assertEmpty($v()->rule('maxLength', 2)->validate([1,2]));
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testMinValidatorValidatesValue() {
        $v = function() { return Validation::makeValueValidator(); };
        $this->assertNotEmpty($v()->rule('min', 5)->validate(1));
        $this->assertNotEmpty($v()->rule('min', 5)->validate('1'));
        $this->assertNotEmpty($v()->rule('min', 5)->validate('foo'));
        $this->assertNotEmpty($v()->rule('min', 5)->validate([]));
        $this->assertEmpty($v()->rule('min', 5)->validate(6));
        $this->assertEmpty($v()->rule('min', 5)->validate('6.0'));
        $this->assertEmpty($v()->rule('min', 5)->validate(5));
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testMaxValidatorValidatesValue() {
        $v = function() { return Validation::makeValueValidator(); };
        $this->assertNotEmpty($v()->rule('max', 5)->validate(6));
        $this->assertNotEmpty($v()->rule('max', 5)->validate('6'));
        $this->assertNotEmpty($v()->rule('max', 5)->validate('foo'));
        $this->assertNotEmpty($v()->rule('max', 5)->validate([]));
        $this->assertEmpty($v()->rule('max', 5)->validate(2));
        $this->assertEmpty($v()->rule('max', 5)->validate('2.0'));
        $this->assertEmpty($v()->rule('max', 5)->validate(5));
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testInValidatorValidatesInclusion() {
        $v = function() { return Validation::makeValueValidator(); };
        $this->assertNotEmpty($v()->rule('in', [1, 2])->validate(6));
        $this->assertNotEmpty($v()->rule('in', [1, 2])->validate('foo'));
        $this->assertNotEmpty($v()->rule('in', [1, 2])->validate('2'));
        $this->assertEmpty($v()->rule('in', [1, 2])->validate(2));
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testIdentifierValidatorValidatesIdentifiers() {
        $v = function() { return Validation::makeValueValidator(); };
        $this->assertNotEmpty($v()->rule('identifier')->validate([]));
        $this->assertNotEmpty($v()->rule('identifier')->validate('Ab#'));
        $this->assertNotEmpty($v()->rule('identifier')->validate('Abä'));
        $this->assertNotEmpty($v()->rule('identifier')->validate('4_foo'));
        $this->assertEmpty($v()->rule('identifier')->validate('Abc'));
        $this->assertEmpty($v()->rule('identifier')->validate('Ab_c'));
        $this->assertEmpty($v()->rule('identifier')->validate('Ab5'));
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testMinValidatorValidatesPatterns() {
        $v = function() { return Validation::makeValueValidator(); };
        $pattern = '/foo|bar/';
        $this->assertNotEmpty($v()->rule('regexp', $pattern)->validate('ba'));
        $this->assertNotEmpty($v()->rule('regexp', $pattern)->validate('baz'));
        $this->assertNotEmpty($v()->rule('regexp', $pattern)->validate([]));
        $this->assertEmpty($v()->rule('regexp', $pattern)->validate('foo'));
        $this->assertEmpty($v()->rule('regexp', $pattern)->validate('bar'));
    }
}
