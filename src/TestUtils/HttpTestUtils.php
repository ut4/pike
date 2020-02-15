<?php

namespace Pike\TestUtils;

use Auryn\Injector;
use Pike\Response;
use Pike\Auth\Authenticator;
use Pike\Db;
use Pike\FileSystem;
use Pike\Auth\Crypto;
use PHPUnit\Framework\MockObject\MockObject;

trait HttpTestUtils {
    /**
     * Luo uuden \Pike\App:n kutsumalla $factoryä passaten sille testiympäistöön
     * configuroidut $config, ja $ctx -objektit. Luo automaattisesti $ctx->db,
     * ja $ctx->auth (käytä makeApp(..., ..., ['db' => 'none', 'auth' => 'none'])
     * mikäli et tarvitse niitä).
     *
     * @param fn(array $config, object $ctx, callable? $makeInjector): \Pike\App $factory
     * @param array|string|null $config = null
     * @param object|array $ctx = null
     * @param fn(\Auryn\Injector $injector): void $alterInjector = null
     * @return \Pike\TestUtils\AppHolder
     */
    public function makeApp(callable $factory,
                            $config = null,
                            $ctx = null,
                            \Closure $alterInjector = null) {
        if (!($ctx instanceof \stdClass)) {
            if (!is_array($ctx)) $ctx = new \stdClass;
            else $ctx = $ctx ? (object)$ctx : new \stdClass;
        }
        if (!isset($ctx->auth)) {
            $ctx->auth = $this->createMock(Authenticator::class);
            $ctx->auth->method('getIdentity')->willReturn((object)['id' => '1', 'role' => 0]);
        }
        if (!isset($ctx->db)) {
            $ctx->db = DbTestCase::getDb(!is_string($config) ? $config : require $config);
        }
        return new AppHolder(
            call_user_func($factory, $config, $ctx, function () use ($ctx, $alterInjector) {
                $injector = new Injector();
                $injector->alias(Db::class, SingleConnectionDb::class);
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
     * @param mixed $expectedBody
     * @param string $expectedStatus = 200
     * @param string $expectedContentType = 'json'
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    public function createMockResponse($expectedBody,
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
    public function sendRequest($req,
                                $res,
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
    public function sendResponseBodyCapturingRequest($req,
                                                     $res,
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
