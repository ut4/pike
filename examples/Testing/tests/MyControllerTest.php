<?php

declare(strict_types=1);

namespace Me\Testing\Tests;

use PHPUnit\Framework\TestCase;
use Me\Testing\MyApp;
use Pike\{AppContext, Request, TestUtils\HttpTestUtils};

class MyControllerTest extends TestCase {
    use HttpTestUtils;
    public function testSomeRouteReturnsFoo(): void {
    // 1. Luo applikaatio-olio
        $config = [];
        $ctx = new AppContext;
        $app = $this->makeApp([MyApp::class, 'create'], $config, $ctx);
    // 2. Luo olio testattavalle reitille
        $body = null;
        $files = null;
        $serverVars = null;
        $req = new Request('/some-route', 'GET', $body, $files, $serverVars);
    // 3. Luo olio vastaukselle
        $res = $this->makeSpyingResponse();
    // 4. Suorita testi
        $this->sendRequest($req, $res, $app);
    // 5. Assertoi
        $this->assertEquals(200, $res->getActualStatusCode());
        $this->assertEquals(json_encode((object) ['message' => 'foo']),
                            $res->getActualBody());
        $this->assertEquals('application/json', $res->getActualContentType());
    }
    public function testAnotherRouteReturnsBar(): void {
    // 1. Luo applikaatio-olio
        $config = [];
        $ctx = new AppContext;
        $app = $this->makeApp([MyApp::class, 'create'], $config, $ctx);
    // 2. Luo olio testattavalle reitille
        $req = new Request('/another-route', 'GET');
    // 3. Luo olio vastaukselle
        $res = $this->makeSpyingResponse();
    // 4. Suorita pyyntö
        $this->sendRequest($req, $res, $app);
    // 5. Assertoi
        $expected = json_encode((object) ['message' => 'bar']);
        $this->assertEquals($expected, $res->getActualBody());
    }
}
