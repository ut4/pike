<?php

namespace Pike\Tests;

use PHPUnit\Framework\TestCase;
use Pike\{App, AppContext, Request, TestUtils\HttpTestUtils};

final class AppTest extends TestCase {
    use HttpTestUtils;
    public function testHandleRequestStopsAtFirstMiddleware() {
        $req = new Request('/foo', 'GET');
        $res = $this->makeSpyingResponse();
        $app = $this->makeApp(function ($config, $ctx, $makeInjector) {
            return App::create([TestModule::class, TestModule2::class],
                               $config,
                               $ctx,
                               $makeInjector);
        });
        $this->sendRequest($req, $res, $app);
        //
        $this->assertEquals('Not allowed', $res->getActualBody());
        $this->assertFalse(TestController::$method1Called);
        $this->assertFalse(TestController::$method2Called);
    }
}

abstract class TestModule {
    public static function init(AppContext $ctx): void {
        $ctx->router->map('GET', '/foo', [TestController::class, 'method']);
        $ctx->router->on('*', function ($_req, $res, $_next) {
            $res->status(400)->plain('Not allowed');
        });
    }
}

abstract class TestModule2 {
    public static function init(AppContext $ctx): void {
        $ctx->router->map('GET', '/bar', [TestController::class, 'method2']);
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
