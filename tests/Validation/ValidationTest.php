<?php

namespace Pike\Tests\Validation;

use PHPUnit\Framework\TestCase;
use Pike\Validation;

final class ValidationTest extends TestCase {
    public function testValidatorHandlesSimplePaths(): void {
        $this->validate('foo',  '{"foo":"valid"}',     ['valid'],     []);
        $this->validate('foo',  '{}',                  [null],        ["foo != `valid`"]);
        $this->validate('foo?', '{}',                  [],            []);
        $this->validate('foo?', '{"foo":null}',        [],            []);
        $this->validate('foo?', '{"foo":""}',          [''],          ["foo != `valid`"]);
        $this->validate('foo?', '{"foo":[]}',          [[]],          ["foo != `valid`"]);
        $this->validate('foo?', '{"foo":"not-valid"}', ['not-valid'], ["foo != `valid`"]);
    }
    public function testValidatorHandlesPropGetters(): void {
        $this->validate('foo.bar',  '{"foo":{"bar":"valid"}}',     ['valid'], []);
        $this->validate('foo.bar',  '{"foo":{}}',                  [null],    ["foo.bar != `valid`"]);
        $this->validate('foo?.bar', '{}',                          [],        []);
        $this->validate('foo.bar?', '{"foo":{"not-bar":"value"}}', [],        []);
        $this->validate('foo.bar',  '{"foo":"not-an-object"}',     [],        ['Expected `foo` to be an object']);
        $this->validate('foo.bar?', '{"foo":"p"}',                 [],        ['Expected `foo` to be an object']);
    }
    public function testValidatorHandlesWildcardGetters(): void {
        $this->validate('foo.*',  '{"foo":["not-valid","valid"]}', ['not-valid', 'valid'], ['foo.0 != `valid`']);
        $this->validate('foo.*',  '{"foo":[null]}',                [null],                 ['foo.0 != `valid`']);
        $this->validate('foo?.*', '{}',                            [],                     []);
        $this->validate('foo.*',  '{"foo":{"not":"an-array"}}',    [], ['Expected `foo` to be an array']);
    }
    public function testValidatorHandlesPropGetterPropGetters(): void {
        $this->validate('foo.bar.baz',  '{"foo":{"bar":{"baz":"valid"}}}',     ['valid'], []);
        $this->validate('foo.bar.baz',  '{"foo":{"bar":{}}}',                  [null],    ['foo.bar.baz != `valid`']);
        $this->validate('foo?.bar.baz', '{}',                                  [],        []);
        $this->validate('foo.bar?.baz', '{"foo":{"not-bar":"value"}}',         [],        []);
        $this->validate('foo.bar.baz?', '{"foo":{"bar":{"not-baz":"valid"}}}', [],        []);
        $this->validate('foo.bar.baz',  '{}',                                  [],        ['Expected `foo` to be an object']);
        $this->validate('foo.bar.baz',  '{"foo":"not-an-object"}',             [],        ['Expected `foo` to be an object']);
        $this->validate('foo.bar.baz',  '{"foo":{"bar":"not-an-object"}}',     [],        ['Expected `foo.bar` to be an object']);
    }
    public function testValidatorHandlesWildcardGetterPropGetters(): void {
        $this->validate('foo.*.bar',
                        '{"foo":[{"bar":"valid"},{"bar":"not-valid"}]}',
                        ['valid', 'not-valid'],
                        ['foo.1.bar != `valid`']);
        $this->validate('foo.*.bar', '{"foo":[{"bar":[]}]}',              [[]],          ['foo.0.bar != `valid`']);
        $this->validate('foo.*.bar', '{"foo":[{}]}',              [null],        ['foo.0.bar != `valid`']);
        $this->validate('foo?.*.bar', '{}',                               [],            []);
        $this->validate('foo?.*.bar', '{"foo":[]}',                       [],            []);
        $this->validate('foo.*.bar?', '{"foo":[{},{"bar":"not-valid"}]}', ['not-valid'], ['foo.1.bar != `valid`']);
        $this->validate('foo.*.bar?', '{"foo":[{"bar":""},{}]}',          [''],          ['foo.0.bar != `valid`']);
        $this->validate('foo.*.bar',  '{"foo":{"not":"an-array"}}',[],    ['Expected `foo` to be an array']);
        $this->validate('foo.*.bar',  '{}',                               [],            ['Expected `foo` to be an array']);
    }
    public function testValidatorHandlesWildarGetterPropGetterPropGetters(): void {
        $this->validate('foo.*.bar.baz',
                        '{"foo":[{"bar":{"baz":"not-valid"}},{"bar":{"not-baz":"valid"}}]}',
                        ['not-valid', null],
                        ['foo.0.bar.baz != `valid`',
                         'foo.1.bar.baz != `valid`']);
        $this->validate('foo.*.bar.baz',
                        '{"foo":[{"bar":{}}]}',
                        [null],
                        ['foo.0.bar.baz != `valid`']);
        $this->validate('foo.*.bar.baz?', '{"foo":[{"bar":{"not-baz":"value"}}]}', [], []);
        $this->validate('foo.*.bar.baz',  '{"foo":[{"bar":"not-an-object"}]}',     [], ['Expected `foo.0.bar` to be an object']);
        $this->validate('foo.*.bar.baz?', '{"foo":[{"bar":"not-an-object"}]}',     [], ['Expected `foo.0.bar` to be an object']);
        $this->validate('foo.*.bar.baz?', '{"foo":[{"bar":"not-an-object"}]}',     [], ['Expected `foo.0.bar` to be an object']);
    }
    public function testValidatorHandlesWildarGetterPropGetterWidlcardGetterPropGetters(): void {
        $this->validate('foo.*.bar.*.baz',
                        '{"foo":[{"bar":[{"baz":"valid"}]},{"bar":[{"baz":"not-valid"}]}]}',
                        ['valid', 'not-valid'],
                        ['foo.1.bar.0.baz != `valid`']);
        $this->validate('foo.*.bar.*.baz',
                        '{"foo":[{"bar":[{}]}]}',
                        [null],
                        ['foo.0.bar.0.baz != `valid`']);
        $this->validate('foo.*.bar.*.baz?',
                        '{"foo":[{"bar":[{"baz":"valid"},{}]},{"bar":[{},{"baz":"not-valid"}]}]}',
                        ['valid', 'not-valid'],
                        ['foo.1.bar.1.baz != `valid`']);
        $this->validate('foo.*.bar?.*.baz',
                        '{"foo":[{},{"bar":[{},{"baz":"not-valid"}]}]}',
                        [null, 'not-valid'],
                        ['foo.1.bar.0.baz != `valid`',
                         'foo.1.bar.1.baz != `valid`']);
        $this->validate('foo.*.bar.*.baz',
                        '{"foo":[{"bar":{"not":"an-array"}}]}',
                        [],
                        ['Expected `foo.0.bar` to be an array']);
        $this->validate('foo.*.bar.*.baz',
                        '{"foo":{"not":"an-array"}}',
                        [],
                        ['Expected `foo` to be an array']);
        $this->validate('foo.*.bar?.*.baz',
                        '{"foo":[{"bar":"not-an-array"}]}',
                        [],
                        ['Expected `foo.0.bar` to be an array']);
    }
    private function validate(string $rule,
                              string $value,
                              array $expectedPassages,
                              array $expectedErrors): void {
        $state = $this->setupPathHandlingTest();
        $this->addSpyRuleForPath($rule, $state);
        $this->callValidatorWith($value, $state);
        $this->verifyPassedTheseToSpyRule($expectedPassages, $state);
        $this->verifyHasTheseErrors($expectedErrors, $state);
    }
    private function setupPathHandlingTest(): \stdClass {
        $state = new \stdClass;
        $state->v = Validation::makeObjectValidator();
        $state->valsPassedToRule = [];
        Validation::registerRuleImpl('spyAsserter', function ($value) use ($state) {
            $state->valsPassedToRule[] = $value;
            return $value === 'valid';
        }, "%s != `valid`");
        $state->errors = [];
        return $state;
    }
    private function addSpyRuleForPath(string $path, \stdClass $s): void {
        $s->v->rule($path, 'spyAsserter');
    }
    private function callValidatorWith(string $input, \stdClass $s): void {
        $s->errors = $s->v->validate(json_decode($input));
    }
    private function verifyPassedTheseToSpyRule(array $expectedValues, \stdClass $s): void {
        $this->assertEquals($expectedValues, $s->valsPassedToRule);
    }
    private function verifyHasTheseErrors(array $expected, \stdClass $s): void {
        $this->assertEquals($expected, $s->errors);
    }
}
