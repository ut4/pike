<?php

namespace Pike\TestUtils;

use Auryn\Injector;
use Pike\{App, Db, Response};
use Pike\Interfaces\SessionInterface;

trait HttpTestUtils {
    /**
     * @param callable(): \Pike\App $factory
     * @param ?\Closure $userDefinedAlterDi = null callable(\Auryn\Injector $di): void
     */
    public function makeApp(callable $factory,
                            ?\Closure $userDefinedAlterDi = null): ResponseSpyingApp {
        $app = call_user_func($factory);
        if (!($app instanceof App))
            throw new \UnexpectedValueException('$factory must return an instance of \Pike\App');
        //
        $app->setServiceInstantiator(fn($ctx) => new ServiceDefaultsForTests($ctx, function ($_factory) {
            $mockSession = $this->createMock(SessionInterface::class);
            $mockSession->method('get')->with('user')->willReturn((object) ['id' => '1', 'role' => 1]);
            return $mockSession;
        }, function () {
            return self::setGetConfig();
        }));
        //
        $app->getModules()[] = new class($userDefinedAlterDi) {
            private $alterDiFn;
            public function __construct(?\Closure $userDefinedAlterDi = null) {
                $this->alterDiFn = $userDefinedAlterDi;
            }
            public function alterDi(Injector $di): void {
                $di->alias(Response::class, MutedSpyingResponse::class);
                $di->alias(Db::class, SingleConnectionDb::class);
                if ($this->alterDiFn) $this->alterDiFn->__invoke($di);
            }
        };
        return new ResponseSpyingApp($app);
    }
    /**
     * @return \Pike\TestUtils\MutedSpyingResponse
     */
    public function makeSpyingResponse() {
        return new MutedSpyingResponse;
    }
    /**
     * @param string|object|array $expected
     * @param \Pike\TestUtils\MutedSpyingResponse|string|\stdClass $actual
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
     * @param \Pike\TestUtils\MutedSpyingResponse $spyingResponse
     */
    public function verifyResponseMetaEquals(int $expectedStatusCode,
                                             string $expectedContentType,
                                             MutedSpyingResponse $spyingResponse): void {
        $this->assertEquals($expectedStatusCode, $spyingResponse->getActualStatusCode());
        $this->assertEquals($expectedContentType, $spyingResponse->getActualContentType());
    }
}
