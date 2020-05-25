<?php

namespace Pike\TestUtils;

use Pike\App;
use Pike\AppContext;

class AppHolder {
    /** @var \Pike\App */
    private $app;
    /** @var \Pike\AppContext */
    private $ctx;
    /**
     * @param \Pike\App $app
     * @param object $appCtx {router: \Pike\Router, ...}
     */
    public function __construct(App $app, AppContext $appCtx) {
        $this->app = $app;
        $this->ctx = $appCtx;
    }
    /**
     * @return \Pike\App
     */
    public function getApp() {
        return $this->app;
    }
    /**
     * @return \Pike\AppContext
     */
    public function getAppCtx() {
        return $this->ctx;
    }
}
