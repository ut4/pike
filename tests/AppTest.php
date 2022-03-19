<?php

namespace Pike\Tests;

use PHPUnit\Framework\TestCase;
use Pike\{App, Request, Router};
use Pike\TestUtils\{HttpTestUtils, MutedSpyingResponse};

final class AppTest extends TestCase {
    use HttpTestUtils;
    public function tearDown(): void {
        TestController::$method1Called = false;
        TestController::$method2Called = false;
    }
    public function testHandleRequestStopsAtFirstMiddlewareThatDoesNotCallNext(): void {
        $req = new Request('/foo', 'GET');
        $modules = [new TestModule(true), new TestModule2];
        $res = $this->sendTestRequest($req, $modules);
        //
        $this->assertEquals('Not allowed', $res->getActualBody());
        $this->assertTrue($modules[0]->middlewareCalled);
        $this->assertFalse($modules[1]->middlewareCalled);
        $this->verifyDidNotExecuteAnyControllers();
    }
    public function testHandleRequestStopsAtFirstMiddlewareEvenIfNoRequestHasBeenSent(): void {
        $req = new Request('/foo', 'GET');
        $modules = [new TestModule(false), new TestModule2];
        $_res = $this->sendTestRequest($req, $modules);
        //
        $this->assertTrue($modules[0]->middlewareCalled);
        $this->assertFalse($modules[1]->middlewareCalled);
        $this->verifyDidNotExecuteAnyControllers();
    }
    private function sendTestRequest(Request $req, array $modules): MutedSpyingResponse {
        return $this->buildApp((new App)->setModules($modules))->sendRequest($req);
    }
    private function verifyDidNotExecuteAnyControllers(): void {
        $this->assertFalse(TestController::$method1Called);
        $this->assertFalse(TestController::$method2Called);
    }
}

final class TestModule {
    private $doSendRequest;
    public $middlewareCalled = false;
    public function __construct(bool $doSendRequest) {
        $this->doSendRequest = $doSendRequest;
    }
    public function init(Router $router): void {
        $router->map('GET', '/foo', [TestController::class, 'method']);
        $router->on('*', function ($_req, $res, $_next) {
            $this->middlewareCalled = true;
            if ($this->doSendRequest)
                $res->status(400)->plain('Not allowed');
            // Note: no $next() call here
        });
    }
}

final class TestModule2 {
    public $middlewareCalled = false;
    public function init(Router $router): void {
        $router->map('GET', '/bar', [TestController::class, 'method2']);
        $router->on('*', function ($_req, $res, $_next) {
            $this->middlewareCalled = true;
        });
    }
}

final class TestController {
    public static $method1Called = false;
    public static $method2Called = false;
    public function method(): void {
        self::$method1Called = true;
    }
    public function method2(): void {
        self::$method2Called = true;
    }
}
