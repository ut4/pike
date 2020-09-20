<?php

namespace Pike\Tests\Extensions\Validation;

use PHPUnit\Framework\TestCase;
use Pike\Validation;

final class SafeHTMLValidatorTest extends TestCase {
    public function testRejectsHtmlWithDefaultSettings(): void {
        $v = function() { return Validation::makeValueValidator(); };
        $this->assertNotEmpty($v()->rule('safeHtml')->validate('<script>xss()</script>'));
        $this->assertNotEmpty($v()->rule('safeHtml')->validate('<img onerror="xss()">'));
        $this->assertNotEmpty($v()->rule('safeHtml')->validate('<p data-not-whitelisted="bar"></p>'));
        $this->assertNotEmpty($v()->rule('safeHtml')->validate('<p onerror=xss()>'));
        $this->assertNotEmpty($v()->rule('safeHtml')->validate('<in%vali d<<<!>'));
        $this->assertNotEmpty($v()->rule('safeHtml')->validate('<?php echo "xss"; ?>'));
        $this->assertNotEmpty($v()->rule('safeHtml')->validate('<?xml version="1.0" encoding="UTF-8"?><foo>val</foo>'));
        $this->assertEmpty($v()->rule('safeHtml')->validate('<h1>foo</h1><p>bar</p>'));
        $this->assertEmpty($v()->rule('safeHtml')->validate('<h1 id="h1">foo</h1><p>👌</p>'));
        //
        $this->assertNotEmpty($v()->rule('safeHtml')->validate('<SCRIPT>xss()</SCRIPT>'));
        $this->assertNotEmpty($v()->rule('safeHtml')->validate('<img ONERROR="xss()">'));
        $this->assertEmpty($v()->rule('safeHtml')->validate('<H1>foo</H1><P>bar</P>'));
        $this->assertEmpty($v()->rule('safeHtml')->validate('<h1 ID="h1">foo</h1>'));
    }
    public function testAcceptsOnlySpecifiedTagNames(): void {
        $v = function() { return Validation::makeValueValidator(); };
        $this->assertNotEmpty($v()->rule('safeHtml', ['span', 'br'])->validate('<p>foo</p>'));
        $this->assertNotEmpty($v()->rule('safeHtml', ['span', 'br'])->validate('<span>foo</span><p>foo</p>'));
        $this->assertEmpty($v()->rule('safeHtml', ['span', 'br'])->validate('<span>foo<br></span>'));
        $this->assertEmpty($v()->rule('safeHtml', ['span', 'br'])->validate('<br id=\'foo\'>'));
        //
        $this->assertEmpty($v()->rule('safeHtml', ['span', 'br'])->validate('<SPAN>foo<BR></SPAN>'));
        $this->assertEmpty($v()->rule('safeHtml', ['span', 'br'])->validate('<BR id=\'foo\'>'));
    }
    public function testAcceptsOnlySpecifiedAttributes(): void {
        $v = function() { return Validation::makeValueValidator(); };
        $this->assertNotEmpty($v()->rule('safeHtml', [], ['title', 'data-a'])->validate('<p id="foo">foo</p>'));
        $this->assertNotEmpty($v()->rule('safeHtml', [], ['title', 'data-a'])->validate('<h1>foo</h1><p data-title="bat">foo</p>'));
        $this->assertEmpty($v()->rule('safeHtml', [], ['title', 'data-a'])->validate('<p title="foo">foo</p>'));
        $this->assertEmpty($v()->rule('safeHtml', [], ['title', 'data-a'])->validate('<p title="foo" data-a="bar">foo</p>'));
        $this->assertEmpty($v()->rule('safeHtml', [], ['title', 'data-a'])->validate('<p>foo</p>'));
        //
        $this->assertEmpty($v()->rule('safeHtml', [], ['title', 'data-a'])->validate('<p TITLE="foo">foo</p>'));
        $this->assertEmpty($v()->rule('safeHtml', [], ['title', 'data-a'])->validate('<p TITLE="foo" DATA-A="bar">foo</p>'));
    }
}
