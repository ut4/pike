<?php

namespace Pike\TestUtils;

use Pike\{App, Request, Response};

class ResponseSpyingApp {
    /** @var \Pike\App */
    private $app;
    /**
     * @param \Pike\App $app
     */
    public function __construct(App $app) {
        $this->app = $app;
    }
    /**
     * @param \Pike\App $app
     */
    public function getApp(): App {
        return $this->app;
    }
    /**
     * @param \Pike\Request $req
     * @param ?\Pike\Response $res = null
     * @return \Pike\Response
     */
    public function sendRequest(Request $req, ?Response $res = null): Response {
        if (!$res) $res = new MutedSpyingResponse;
        $this->app->handleRequest($req, null, $res);
        return $res;
    }
}
