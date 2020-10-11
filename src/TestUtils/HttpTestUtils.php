<?php

namespace Pike\TestUtils;

use Auryn\Injector;
use PHPUnit\Framework\MockObject\MockObject;
use Pike\{App, AppContext, Db, FileSystem, Request, Response};
use Pike\Auth\{Authenticator, Crypto};
use Pike\Interfaces\SessionInterface;

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
            $ctx->auth = new Authenticator(
                function ($_factory) { },
                function ($_factory) {
                    $mockSession = $this->createMock(SessionInterface::class);
                    $mockSession->method('get')->with('user')->willReturn((object) ['id' => '1', 'role' => 1]);
                    return $mockSession;
                },
                function ($_factory) { },
                '',   // $userRoleCookieName
                false // doUseRememberMe
            );
        }
        return new AppHolder(
            call_user_func($factory, $config, $ctx, function () use ($ctx, $alterInjector) {
                $injector = new Injector();
                $injector->alias(Db::class, SingleConnectionDb::class);
                if (isset($ctx->auth))
                    $injector->delegate(Authenticator::class, function () use ($ctx) { return $ctx->auth; });
                if (isset($ctx->fs))
                    $injector->delegate(FileSystem::class, function () use ($ctx) { return $ctx->fs; });
                if (isset($ctx->crypto))
                    $injector->delegate(Crypto::class, function () use ($ctx) { return $ctx->crypto; });
                if (isset($ctx->res) &&
                    ($ctx->res instanceof MutedSpyingResponse ||
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
     * @return \Pike\TestUtils\MutedSpyingResponse
     */
    public function makeSpyingResponse() {
        return new MutedSpyingResponse;
    }
    /**
     * Esimerkki:
     * ```
     * $req = new Request('/api/foo', 'GET');
     * $res = $this->makeSpyingResponse();
     * $app = $this->makeApp('\My\App::create', $this->getAppConfig());
     * $this->sendRequest($req, $res, $app);
     * $this->assertEquals(200, $res->getActualStatusCode());
     * $this->assertEquals(json_encode(['expected response']), $res->getActualBody());
     * ```
     *
     * @param \Pike\Request $req
     * @param \Pike\Response $res
     * @param \Pike\TestUtils\AppHolder $appHolder
     */
    public function sendRequest(Request $req, $res, AppHolder $appHolder) {
        $appHolder->getAppCtx()->res = $res;
        $appHolder->getApp()->handleRequest($req);
    }
    /**
     * @param string|object|array $expected
     * @param \Pike\TestUtils\SpyingResponse|string|\stdClass $actual
     */
    public function verifyResponseBodyEquals($expected, $actual): void {
        $expected = is_string($expected) ? $expected : json_encode($expected);
        if ($actual instanceof MutedSpyingResponse)
            $actual = $actual->getActualBody();
        elseif (is_object($actual))
            $actual = $actual->actualResponseBody;
        $this->assertEquals($expected, $actual);
    }
    /**
     * @param int $expectedStatusCode 200 etc.
     * @param string $expectedContentType 'application/json' etc.
     * @param \Pike\TestUtils\SpyingResponse $spyingResponse
     */
    public function verifyResponseMetaEquals(int $expectedStatusCode,
                                             string $expectedContentType,
                                             MutedSpyingResponse $spyingResponse): void {
        $this->assertEquals($expectedStatusCode, $spyingResponse->getActualStatusCode());
        $this->assertEquals($expectedContentType, $spyingResponse->getActualContentType());
    }
}
