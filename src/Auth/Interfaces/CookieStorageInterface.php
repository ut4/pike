<?php

declare(strict_types=1);

namespace Pike\Auth\Interfaces;

interface CookieStorageInterface {
    /**
     * @param string $name e.g. 'foo'
     * @return ?string
     */
    public function getCookie(string $name): ?string;
    /**
     * @param string $cookieString e.g. 'name=foo;path=/'
     */
    public function storeCookie(string $cookieString): void;
}
