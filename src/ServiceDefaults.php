<?php declare(strict_types=1);

namespace Pike;

use Pike\Auth\Authenticator;
use Pike\Auth\Defaults\DefaultCookieStorage;
use Pike\Defaults\DefaultUserRepository;

/**
 * Provides default services for `new App(..., function (ServiceDefaults $defaults) { // here })`.
 */
class ServiceDefaults {
    /** @var \Pike\AppContext */
    private $ctx;
    /**
     * @param \Pike\AppContext $ctx
     */
    public function __construct(AppContext $ctx) {
        $this->ctx = $ctx;
    }
    /**
     * @return object\array\null $config
     * @return \Pike\AppConfig
     */
    public function makeConfig($config = null): AppConfig {
        return new AppConfig($config ?? new \stdClass);
    }
    /**
     * @return \Pike\Db
     */
    public function makeDb(): Db {
        return new Db($this->ctx->config->getVals());
    }
    /**
     * @return \Pike\Auth\Authenticator
     */
    public function makeAuth(): Authenticator {
        return new Authenticator(
            // @phan-suppress-next-line PhanUndeclaredProperty
            function ($_factory) { return new DefaultUserRepository($this->ctx->db); },
            function ($_factory) { return new NativeSession(); },
            function ($_factory) { return new DefaultCookieStorage($this->ctx); },
            'maybeLoggedInUserRole',
            true // doUseRememberMe
        );
    }
}
