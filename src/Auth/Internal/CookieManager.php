<?php

declare(strict_types=1);

namespace Pike\Auth\Internal;

use Pike\AppContext;

class CookieManager {
    /** @var \Pike\AppContext */
    private $ctx;
    /** @var mixed[] */
    private $configs;
    /**
     * @param \Pike\AppContext $ctx
     */
    public function __construct(AppContext $ctx) {
        $this->ctx = $ctx;
        $this->configs = [];
    }
    /**
     * @param string $name
     * @param string $value
     * @param ?int $expiration = null null = istunnon ajan, >0 = absoluuttinen aika, unixtime
     */
    public function putCookie(string $name,
                              string $value,
                              ?int $expiration = null): void {
        $e = $expiration !== null ? (';' . self::makeCookieExpiresKeyPair($expiration)) : '';
        $this->configs[] = ["{$name}={$value};path=/{$e}", false];
    }
    /**
     * @param string $name
     * @return ?string
     */
    public function getCookie(string $name): ?string {
        return $this->ctx->req->cookie($name);
    }
    /**
     * @param string $cookie
     */
    public function clearCookie(string $name): void {
        $e = self::makeCookieExpiresKeyPair(1);
        $this->configs[] = ["{$name}=-;path=/;{$e}", false];
    }
    /**
     * Kirjoittaa asetetut keksit $this->ctx->req-olioon.
     */
    public function commitCookies(): void {
        foreach ($this->configs as $c)
            $this->ctx->res->header('Set-Cookie', ...$c);
    }
    /**
     * @param int $unixTime
     * @return string
     */
    private static function makeCookieExpiresKeyPair(int $unixTime): string {
        return 'expires=' . gmdate('D, d M Y H:i:s', $unixTime) . ' GMT';
    }
}
