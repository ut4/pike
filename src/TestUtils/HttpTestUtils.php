<?php

namespace Pike\TestUtils;

use Pike\App;

trait HttpTestUtils {
    /**
     * @param ?\Pike\App $app = null
     * @return \Pike\TestUtils\ResponseSpyingAppBuilder
     */
    public function buildApp(App $app = null): ResponseSpyingAppBuilder {
        $app = $app ?? new App;
        return new ResponseSpyingAppBuilder($app);
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
