<?php

declare(strict_types=1);

namespace Pike\Auth\Defaults;

use Pike\AppContext;
use Pike\Auth\Interfaces\CookieStorageInterface;

class DefaultCookieStorage implements CookieStorageInterface {
    /** @var \Pike\AppContext */
    private $ctx;
    /**
     * @param \Pike\AppContext $ctx
     */
    public function __construct(AppContext $ctx) {
        $this->ctx = $ctx;
    }
    /**
     * @param string $name
     * @return ?string
     */
    public function getCookie(string $name): ?string {
        return $this->ctx->req->cookie($name);
    }
    /**
     * @param string $cookieString
     */
    public function storeCookie(string $cookieString): void {
        $this->ctx->res->header('Set-Cookie', $cookieString, false);
    }
}
