<?php

namespace Pike\TestUtils;

class AppHolder {
    private $app;
    private $ctx;
    /**
     * @param \Pike\App $app
     * @param object $appCtx
     */
    public function __construct($app, $appCtx) {
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
     * @return object
     */
    public function getAppCtx() {
        return $this->ctx;
    }
}
