<?php

declare(strict_types=1);

namespace Me\Testing\Tests;

use PHPUnit\Framework\TestCase;
use Me\Testing\MyApp;
use Pike\AppContext;
use Pike\Request;
use Pike\TestUtils\HttpTestUtils;

class MyControllerTest extends TestCase {
    use HttpTestUtils;
    public function testSomeRouteReturnsFoo(): void {
    // 2. Luo applikaatio-olio
        $config = [];
        $ctx = new AppContext;
        $app = $this->makeApp([MyApp::class, 'create'], $config, $ctx);
    // 3. Luo olio testattavalle reitille
        $body = null;
        $files = null;
        $serverVars = null;
        $req = new Request('/some-route', 'GET', $body, $files, $serverVars);
    // 4. Luo olio odotetulle vastaukselle
        $expectedBody = (object) ['message' => 'foo'];
        $expectedStatusCode = 200;
        $expectedContentType = 'json';
        $res = $this->createMockResponse($expectedBody,
                                         $expectedStatusCode,
                                         $expectedContentType);
    // 5. Suorita testi
        $this->sendRequest($req, $res, $app);
    }
    public function testAnotherRouteReturnsBar(): void {
    // 2. Luo applikaatio-olio
        $config = [];
        $ctx = new AppContext;
        $app = $this->makeApp([MyApp::class, 'create'], $config, $ctx);
    // 3. Luo olio testattavalle reitille
        $req = new Request('/another-route', 'GET');
    // 4. Luo olio tulokselle
        $state = (object) ['actualResponseBody' => null];
        $res = $this->createBodyCapturingMockResponse($state);
    // 5. Suorita pyyntö
        $this->sendRequest($req, $res, $app);
    // 6. Assertoi
        $this->assertIsString($state->actualResponseBody);
        $expected = json_encode((object) ['message' => 'bar']);
        $this->assertEquals($expected, $state->actualResponseBody);
    }
}
