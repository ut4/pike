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
use Pike\Request;

trait HttpTestUtils {
    /**
     * @param callable $factory fn(array $config, object $ctx, callable? $makeInjector): \Pike\App
     * @param array|string|null $config = null
     * @param object $ctx = null
     * @param callable $alterInjector = null fn(\Auryn\Injector $injector): void
     * @return \Pike\TestUtils\AppHolder
     */
    public function makeApp(callable $factory,
                            $config = null,
                            $ctx = null,
                            \Closure $alterInjector = null) {
        if ($config === null && $this instanceof ConfigProvidingTestCase) {
            $config = $this->getAppConfig();
        }
        if (!is_object($ctx)) {
            $defaultCtx = (object) ['db' => App::MAKE_AUTOMATICALLY,
                                    'auth' => App::MAKE_AUTOMATICALLY];
            if (!is_array($ctx)) $ctx = $defaultCtx;
            else $ctx = $ctx ? (object)$ctx : $defaultCtx;
        }
        if (($ctx->auth ?? null) === App::MAKE_AUTOMATICALLY) {
            $ctx->auth = $this->createMock(Authenticator::class);
            $ctx->auth->method('getIdentity')->willReturn((object)['id' => '1', 'role' => 1]);
        }
        if (($ctx->db ?? null) === App::MAKE_AUTOMATICALLY) {
            $ctx->db = DbTestCase::getDb(!is_string($config) ? $config : require $config);
        }
        return new AppHolder(
            call_user_func($factory, $config, $ctx, function () use ($ctx, $alterInjector) {
                $injector = new Injector();
                $injector->alias(Db::class, SingleConnectionDb::class);
                if (($ctx->auth ?? null) instanceof MockObject)
                    $injector->delegate(Authenticator::class, function () use ($ctx) { return $ctx->auth; });
                if (isset($ctx->fs))
                    $injector->delegate(FileSystem::class, function () use ($ctx) { return $ctx->fs; });
                if (isset($ctx->crypto))
                    $injector->delegate(Crypto::class, function () use ($ctx) { return $ctx->crypto; });
                if (($ctx->res instanceof MutedResponse) ||
                    ($ctx->res instanceof MockObject))
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
     * @param string $expectedStatus = 200
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
        if ($expectedBody !== null)
            $stub->expects($this->once())
                ->method($expectedContentType)
                ->with(is_string($expectedBody)
                    ? $this->equalTo($expectedBody)
                    : $expectedBody)
                ->willReturn($stub);
        return $stub;
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
     * Esimerkki:
     * ```
     * $req = new Request('/api/foo', 'GET');
     * $res = $this->createMock(\Pike\Response::class);
     * $app = $this->makeApp('\My\App::create', $this->getAppConfig());
     * $state = (object)['actualResponseBody' => null];
     * $this->sendResponseBodyCapturingRequest($req, $res, $app);
     * $this->assertEquals();
     * ```
     *
     * @param \Pike\Request $req
     * @param \Pike\Response $res
     * @param \Pike\TestUtils\AppHolder $appHolder
     * @param object $state
     */
    public function sendResponseBodyCapturingRequest(Request $req,
                                                     MockObject $res,
                                                     AppHolder $appHolder,
                                                     $state) {
        $res->expects($this->once())
            ->method('json')
            ->with($this->callback(function ($actualResponse) use ($state) {
                $state->actualResponseBody = json_encode($actualResponse);
                return true;
            }))
            ->willReturn($res);
        $this->sendRequest($req, $res, $appHolder);
    }
}
