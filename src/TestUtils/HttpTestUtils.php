<?php

namespace Pike\TestUtils;

use Auryn\Injector;
use Pike\Response;
use Pike\FileSystemInterface;
use Pike\Auth\Authenticator;
use Pike\Db;

trait HttpTestUtils {
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
     * @param \Pike\Request $req
     * @param \Pike\Response $res
     * @param callable $createApp
     * @param object $ctx = null
     * @param \Callable $alterInjectorFn = null ($injector: \Auryn\Injector): void
     */
    public function sendRequest($req, $res, $createApp, $ctx = null, $alterInjectorFn = null) {
        if (!$ctx) {
            $ctx = (object)['db' => null, 'fs' => null];
        }
        if (!isset($ctx->db)) {
            $ctx->db = DbTestCase::getDb();
        }
        if (!isset($ctx->fs)) {
            $ctx->fs = $this->createMock(FileSystemInterface::class);
            $ctx->fs->method('readDir')->willReturn([]); // plugins
        }
        if (!isset($ctx->auth)) {
            $ctx->auth = $this->createMock(Authenticator::class);
            $ctx->auth->method('getIdentity')->willReturn('1');
        }
        $app = call_user_func($createApp, require TEST_CONFIG_DIR_PATH. 'config.php', $ctx);
        $injector = new Injector();
        $injector->delegate(Response::class, function() use ($res) { return $res; });
        $injector->alias(Db::class, SingleConnectionDb::class);
        if ($alterInjectorFn) $alterInjectorFn($injector);
        $app->handleRequest($req, $injector);
    }
    /**
     * @param \Pike\Request $req
     * @param callable $createApp
     * @param object $state
     */
    public function sendResponseBodyCapturingRequest($req, $createApp, $state) {
        $res = $this->createMock(MutedResponse::class);
        $res->expects($this->once())
            ->method('json')
            ->with($this->callback(function ($actualResponse) use ($state) {
                $state->actualResponseBody = json_encode($actualResponse);
                return true;
            }))
            ->willReturn($res);
        return $this->sendRequest($req, $res, $createApp, $state->ctx ?? null);
    }
}
