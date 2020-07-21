<?php

namespace Pike\TestUtils;

use Pike\{App, AppContext};

class AppHolder {
    /** @var \Pike\App */
    private $app;
    /** @var \Pike\AppContext */
    private $ctx;
    /**
     * @param \Pike\App $app
     * @param \Pike\AppContext $appCtx
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
