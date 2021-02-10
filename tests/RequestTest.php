<?php

namespace Pike\Tests;

use PHPUnit\Framework\TestCase;
use Pike\Request;

final class RequestTest extends TestCase {
    public function testHeaderReturnsHeaders(): void {
        $req = new Request('', 'GET', null, null, [
            'HTTP_HOST' => 'localhost',
            'HTTP_X_REQUESTED_BY' => 'foo',
            'CONTENT_TYPE' => 'application/json',
            'CONTENT_LENGTH' => '38',
        ]);
        $this->assertEquals('localhost', $req->header('host'));
        $this->assertEquals('localhost', $req->header('Host'));
        $this->assertEquals('foo', $req->header('x-requested-by'));
        $this->assertEquals('foo', $req->header('X-Requested-By'));
        $this->assertEquals('application/json', $req->header('content-type'));
        $this->assertEquals('application/json', $req->header('Content-Type'));
        $this->assertEquals('38', $req->header('content-length'));
        $this->assertEquals('38', $req->header('Content-Length'));
        //
        $this->assertEquals(null, $req->header('foo'));
        $this->assertEquals('-', $req->header('SERVER_ADMIN', '-'));
    }
    public function testQueryVarReturnsQueryVariables(): void {
        $req = new Request('', 'GET', null, null, null, ['foo' => 'bar']);
        $this->assertEquals('bar', $req->queryVar('foo'));
        //
        $this->assertEquals(null, $req->queryVar('fos'));
        $this->assertEquals('-', $req->queryVar('fos', '-'));
    }
}
