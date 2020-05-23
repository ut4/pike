<?php

namespace Pike\Tests\Validation;

use PHPUnit\Framework\TestCase;
use Pike\Validation;

final class ValidationTest extends TestCase {
    public function testObjectValidatorHandlesNestedPaths() {
        $state = $this->setupPathHandlingTest();
        $this->addSpyRuleForPath('foo.bar', $state);
        $this->addSpyRuleForPath('foo.baz.bar', $state);
        $this->addSpyRuleForPath('foo.baz.notThere', $state);
        $this->callValidatorWith((object)[
            'foo' => (object)[
                'bar' => 'a',
                'baz' => (object)['bar' => 'b']
            ]
        ], $state);
        $this->verifyPassedTheseToSpyRule(['a', 'b', null], $state);
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testObjectValidatorHandlesWidlcardsForObjects() {
        $state = $this->setupPathHandlingTest();
        $this->addSpyRuleForPath('foo.*', $state);
        $this->callValidatorWith((object)[
            'foo' => (object)['a' => 'a', 'b' => 'b']
        ], $state);
        $this->verifyPassedTheseToSpyRule(['a', 'b'], $state);
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testObjectValidatorHandlesWidlcardsForObjectsInArray() {
        $state = $this->setupPathHandlingTest();
        $this->addSpyRuleForPath('foo.*.bar', $state);
        $this->addSpyRuleForPath('bar.*.bar.baz', $state);
        $this->addSpyRuleForPath('bar.*.bar.notThere', $state);
        $this->callValidatorWith((object)['foo' => [
            (object)['bar' => 'a'],
            (object)['bar' => 'b'],
        ], 'bar' => [
            (object)['bar' => (object)['baz' => 'c']],
            (object)['bar' => (object)['baz' => 'd']],
        ]], $state);
        $this->verifyPassedTheseToSpyRule(['a', 'b', 'c', 'd', null, null], $state);
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testObjectValidatorRejectsMissingOrUnlogicalPaths() {
        $v = Validation::makeObjectValidator();
        $v->rule('foo.*', 'type', 'int');
        $this->assertCount(1, $v->validate((object)['']));
        $this->assertCount(1, $v->validate((object)['foo'=>'not-an-object']));
        //
        $v2 = Validation::makeObjectValidator();
        $v2->rule('foo.*.bar', 'type', 'string');
        $this->assertCount(1, $v2->validate((object)['']));
        $this->assertCount(1, $v2->validate((object)['a'=>'b']));
        $this->assertCount(1, $v2->validate((object)['foo'=>'not-an-array-nor-object']));
        $this->assertCount(1, $v2->validate((object)['foo'=>['not-an-array-nor-object']]));
        $this->assertCount(1, $v2->validate((object)['foo'=>[(object)[]]]));
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testObjectValidatorAllowsOptionals() {
        $v = Validation::makeObjectValidator();
        $v->rule('foo?', 'identifier');
        $this->assertCount(0, $v->validate((object)[]));
        $this->assertCount(0, $v->validate((object)['foo' => null]));
        $this->assertCount(0, $v->validate((object)['foo' => '']));
        $this->assertCount(0, $v->validate((object)['foo' => []]));
        $this->assertCount(0, $v->validate((object)['foo' => 'bar']));
        $this->assertCount(1, $v->validate((object)['foo' => new \stdClass]));
        //
        $v = Validation::makeObjectValidator();
        $v->rule('foo.*.bar?', 'identifier');
        $this->assertCount(0, $v->validate((object)[]));
        $this->assertCount(0, $v->validate((object)['foo' => [(object)[]]]));
        $this->assertCount(0, $v->validate((object)['foo' => [(object)['bar' => null]]]));
        $this->assertCount(0, $v->validate((object)['foo' => [(object)['bar' => '']]]));
        $this->assertCount(0, $v->validate((object)['foo' => [(object)['bar' => []]]]));
        $this->assertCount(0, $v->validate((object)['foo' => [(object)['bar' => 'bar']]]));
        $this->assertCount(1, $v->validate((object)['foo' => [(object)['bar' => new \stdClass]]]));
    }
    private function setupPathHandlingTest() {
        $state = new \stdClass;
        $state->v = Validation::makeObjectValidator();
        $state->valsPassedToRule = [];
        Validation::registerRuleImpl('spyAsserter', function ($val) use ($state) {
            $state->valsPassedToRule[] = $val;
            return true;
        }, '...');
        $state->errors = [];
        return $state;
    }
    private function addSpyRuleForPath($path, $s) {
        $s->v->rule($path, 'spyAsserter');
    }
    private function callValidatorWith($input, $s) {
        $s->errors = $s->v->validate($input);
    }
    private function verifyPassedTheseToSpyRule($expectedValues, $s) {
        $this->assertCount(0, $s->errors);
        $this->assertEquals($expectedValues, $s->valsPassedToRule);
    }
}
