<?php

namespace Pike\TestUtils;

use Auryn\Injector;
use Pike\Response;
use Pike\Auth\Authenticator;
use Pike\Db;
use Pike\FileSystem;
use Pike\Auth\Crypto;
use PHPUnit\Framework\MockObject\MockObject;
use Pike\App;
use Pike\AppContext;
use Pike\Request;

trait HttpTestUtils {
    /**
     * @param callable $factory fn(array $config, \Pike\AppContext $ctx, callable? $makeInjector): \Pike\App
     * @param array|string|null $config = null
     * @param \Pike\AppContext|class-string $ctx = null
     * @param callable $alterInjector = null fn(\Auryn\Injector $injector): void
     * @return \Pike\TestUtils\AppHolder
     */
    public function makeApp(callable $factory,
                            $config = null,
                            $ctx = null,
                            callable $alterInjector = null) {
        if ($config === null && $this instanceof ConfigProvidingTestCase) {
            $config = $this->getAppConfig();
        }
        if (!$ctx || is_string($ctx)) {
            $Cls = !$ctx ? AppContext::class : $ctx;
            $ctx = new $Cls(['db' => App::MAKE_AUTOMATICALLY,
                             'auth' => App::MAKE_AUTOMATICALLY]);
        }
        if (($ctx->serviceHints['db'] ?? null) === App::MAKE_AUTOMATICALLY) {
            $ctx->db = DbTestCase::getDb(!is_string($config) ? $config : require $config);
        }
        if (($ctx->serviceHints['auth'] ?? null) === App::MAKE_AUTOMATICALLY) {
            $ctx->auth = $this->createMock(Authenticator::class);
            $ctx->auth->method('getIdentity')->willReturn((object)['id' => '1', 'role' => 1]);
        }
        return new AppHolder(
            call_user_func($factory, $config, $ctx, function () use ($ctx, $alterInjector) {
                $injector = new Injector();
                $injector->alias(Db::class, SingleConnectionDb::class);
                if ($ctx->auth instanceof MockObject)
                    $injector->delegate(Authenticator::class, function () use ($ctx) { return $ctx->auth; });
                if (isset($ctx->fs))
                    $injector->delegate(FileSystem::class, function () use ($ctx) { return $ctx->fs; });
                if (isset($ctx->crypto))
                    $injector->delegate(Crypto::class, function () use ($ctx) { return $ctx->crypto; });
                if (isset($ctx->res) &&
                    ($ctx->res instanceof MutedResponse ||
                     $ctx->res instanceof MockObject))
                    $injector->delegate(Response::class, function () use ($ctx) { return $ctx->res; });
                if ($alterInjector)
                    $alterInjector($injector);
                return $injector;
            }),
            $ctx
        );
    }
    /**
     * @param mixed $expectedBody = null
     * @param int $expectedStatus = 200
     * @param string $expectedContentType = 'json'
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    public function createMockResponse($expectedBody = null,
                                       $expectedStatus = 200,
                                       $expectedContentType = 'json') {
        $stub = $this->createMock(MutedResponse::class);
        if ($expectedStatus === 200) {
            $stub->method('status')
                ->willReturn($stub);
        } else {
            $stub->expects($this->atLeastOnce())
                ->method('status')
                ->with($this->equalTo($expectedStatus))
                ->willReturn($stub);
        }
        if (!$expectedBody) return $stub;
        //
        $bodyExpection = null;
        if (is_object($expectedBody) &&
            property_exists($expectedBody, 'actualResponseBody')) {
            $state = $expectedBody;
            $bodyExpection = $this->callback(function ($actualResponse) use ($state, $expectedContentType) {
                $state->actualResponseBody = $expectedContentType !== 'json'
                    ? $actualResponse
                    : json_encode($actualResponse);
                return true;
            });
        } else {
            $bodyExpection = is_string($expectedBody)
                ? $this->equalTo($expectedBody)
                : $expectedBody;
        }
        $stub->expects($this->once())
            ->method($expectedContentType)
            ->with($bodyExpection)
            ->willReturn($stub);
        return $stub;
    }
    /**
     * @param object $captureTo
     * @param int $expectedStatus = 200
     * @param string $expectedContentType = 'json'
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    public function createBodyCapturingMockResponse($captureTo,
                                                    $expectedStatus = 200,
                                                    $expectedContentType = 'json') {
        return $this->createMockResponse($captureTo, $expectedStatus, $expectedContentType);
    }
    /**
     * Esimerkki:
     * ```
     * $req = new Request('/api/foo', 'GET');
     * $res = $this->createMockResponse(['expected response'], 200);
     * $app = $this->makeApp('\My\App::create', $this->getAppConfig());
     * $this->sendRequest($req, $res, $app);
     * ```
     *
     * @param \Pike\Request $req
     * @param \Pike\Response $res
     * @param \Pike\TestUtils\AppHolder $appHolder
     */
    public function sendRequest(Request $req,
                                MockObject $res,
                                AppHolder $appHolder) {
        $appHolder->getAppCtx()->res = $res;
        $appHolder->getApp()->handleRequest($req);
    }
    /**
     * @param string|object|array $expected
     * @param string|\stdClass $actual
     */
    public function verifyResponseBodyEquals($expected, $actual): void {
        $expected = is_string($expected) ? $expected : json_encode($expected);
        $actual = is_string($actual) ? $actual : $actual->actualResponseBody;
        $this->assertEquals($expected, $actual);
    }
}
