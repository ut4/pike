<?php

namespace Pike\TestUtils;

use Pike\{App, Request, Response};

class ResponseSpyingAppBuilder {
    /** @var \Pike\App */
    protected $app;
    /**
     * @param \Pike\App $app
     */
    public function __construct(App $app) {
        $this->app = $app;
        $this->app->getDi()->alias(Response::class, MutedSpyingResponse::class);
    }
    /**
     * @param \Pike\App $app
     */
    public function getApp(): App {
        return $this->app;
    }
    /**
     * @param \Pike\Request|string $reqOrPath
     * @param string $method = 'GET'
     * @param ?object $body = new \stdClass
     * @param ?object $files = new \stdClass
     * @param ?array $serverVars = null
     * @param ?array $queryVars = null
     * @param ?array $cookies = null
     * @return \Pike\TestUtils\MutedSpyingResponse
     */
    public function sendRequest($reqOrPath,
                                string $method = 'GET',
                                ?object $body = null,
                                ?object $files = null,
                                ?array $serverVars = null,
                                ?array $queryVars = null,
                                ?array $cookies = null): MutedSpyingResponse {
        $req = !($reqOrPath instanceof Request)
            ? new Request($reqOrPath, $method, $body, $files, $serverVars, $queryVars, $cookies)
            : $reqOrPath;
        $res = new MutedSpyingResponse;
        $this->app->handleRequest($req, null, $res);
        return $res;
    }
}
