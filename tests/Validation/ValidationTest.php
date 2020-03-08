<?php

namespace Pike\Tests\Validation;

use PHPUnit\Framework\TestCase;
use Pike\Validation;

final class ValidationTest extends TestCase {
    public function testObjectValidatorHandlesNestedPaths() {
        $s = $this->setupPathHandlingTest();
        $this->addSpyRuleForPath('foo.bar', $s);
        $this->addSpyRuleForPath('foo.baz.bar', $s);
        $this->addSpyRuleForPath('foo.baz.notThere', $s);
        $this->callValidatorWith((object)[
            'foo' => (object)[
                'bar' => 'a',
                'baz' => (object)['bar' => 'b']
            ]
        ], $s);
        $this->verifyPassedTheseToSpyRule(['a', 'b', null], $s);
    }

    ////////////////////////////////////////////////////////////////////////////

    public function testObjectValidatorHandlesWidlcardsForObjects() {
        $s = $this->setupPathHandlingTest();
        $this->addSpyRuleForPath('foo.*', $s);
        $this->callValidatorWith((object)[
            'foo' => (object)['a' => 'a', 'b' => 'b']
        ], $s);
        $this->verifyPassedTheseToSpyRule(['a', 'b'], $s);
    }

    ////////////////////////////////////////////////////////////////////////////

    public function testObjectValidatorHandlesWidlcardsForObjectsInArray() {
        $s = $this->setupPathHandlingTest();
        $this->addSpyRuleForPath('foo.*.bar', $s);
        $this->addSpyRuleForPath('bar.*.bar.baz', $s);
        $this->addSpyRuleForPath('bar.*.bar.notThere', $s);
        $this->callValidatorWith((object)['foo' => [
            (object)['bar' => 'a'],
            (object)['bar' => 'b'],
        ], 'bar' => [
            (object)['bar' => (object)['baz' => 'c']],
            (object)['bar' => (object)['baz' => 'd']],
        ]], $s);
        $this->verifyPassedTheseToSpyRule(['a', 'b', 'c', 'd', null, null], $s);
    }

    ////////////////////////////////////////////////////////////////////////////

    public function testObjectValidatorAllowsOptionals() {
        $v = Validation::makeObjectValidator();
        $v->rule('foo?', 'identifier');
        $this->assertCount(0, $v->validate((object)[]));
        $this->assertCount(0, $v->validate((object)['foo' => '']));
        $this->assertCount(1, $v->validate((object)['foo' => new \stdClass]));
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
