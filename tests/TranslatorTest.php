<?php

namespace Pike\Tests;

use PHPUnit\Framework\TestCase;
use Pike\Translator;

class TranslatorTest extends TestCase {
    public function testHandlesPercentCharacters(): void {
        $string = 'VAT 12%% %d';
        $translator = new Translator([$string => $string]);
        $this->assertEquals('VAT 12% 1', $translator->t($string, 1));
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testReplacesSprintfPlaceholders(): void {
        $string = 'foo %s %d';
        $translator = new Translator([$string => $string]);
        $this->assertEquals('foo bar 1', $translator->t($string, 'bar', 1));
        //
        $string2 = 'not registered %s %d';
        $this->assertEquals('not registered bar 1', $translator->t($string2, 'bar', 1));
    }
}
