<?php

namespace Pike\TestUtils;

use Pike\{AppConfig, AppContext, Db, ServiceDefaults};
use Pike\Auth\Authenticator;

/**
 * Replaces `new App(..., function (ServiceDetaults $defaults) { // this })` when
 * running tests.
 */
final class ServiceDefaultsForTests extends ServiceDefaults {
    /** @var \Closure */
    private $makeMockSessionFn;
    /** @var \Closure */
    private $getConfigFn;
    /**
     * @param \Pike\AppContext $ctx
     * @param \Closure $makeMockSessionFn
     * @param \Closure $getConfigFn
     */
    public function __construct(AppContext $ctx,
                                \Closure $makeMockSessionFn,
                                \Closure $getConfigFn) {
        parent::__construct($ctx);
        $this->makeMockSessionFn = $makeMockSessionFn;
        $this->getConfigFn = $getConfigFn;
    }
    /**
     * @inheritdoc
     */
    public function makeConfig($config = null): AppConfig {
        return $this->ctx->config ?? new AppConfig($config ?? $this->getConfigFn->__invoke());
    }
    /**
     * @inheritdoc
     */
    public function makeDb(): Db {
        if (isset($this->ctx->db)) return $this->ctx->db;
        $cfg = $this->ctx->config ?? null;
        return DbTestCase::setGetDb($cfg instanceof AppConfig
            ? (array) $this->ctx->config->getVals()
            : null);
    }
    /**
     * @inheritdoc
     */
    public function makeAuth(): Authenticator {
        if (isset($this->ctx->auth)) return $this->ctx->auth;
        return new Authenticator(
            function ($_factory) { },
            $this->makeMockSessionFn,
            function ($_factory) { },
            '',   // $userRoleCookieName
            false // doUseRememberMe
        );
    }
}
