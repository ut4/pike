<?php

declare(strict_types=1);

namespace Pike\Auth\Internal;

use Pike\Auth\Interfaces\CookieStorageInterface;

/**
 * Luokka, jonka tehtävänä on luoda asetettavat cookiet, ja passata ne tallentajalle
 * $this->cookieStorage.
 */
final class CookieManager {
    /** @var \Pike\Auth\Interfaces\CookieStorageInterface */
    private $cookieStorage;
    /** @var string[] */
    private $configs;
    /**
     * @param \Pike\Auth\Interfaces\CookieStorageInterface $cookieStorage
     */
    public function __construct(CookieStorageInterface $cookieStorage) {
        $this->cookieStorage = $cookieStorage;
        $this->configs = [];
    }
    /**
     * @param string $name
     * @param string $value
     * @param ?int $expiration = null null = session length, > 0 = specific absolute unix timestamp
     * @param ?bool $httpOnly = true
     */
    public function addCookieConfig(string $name,
                                    string $value,
                                    ?int $expiration = null,
                                    ?bool $httpOnly = true): void {
        $exp  = $expiration !== null ? ('; ' . self::makeCookieExpiresKeyPair($expiration)) : '';
        $nojs = $httpOnly            ? ('; HttpOnly')                                       : '';
        $this->configs[] = "{$name}={$value}; path=/; SameSite=Strict{$exp}{$nojs}";
    }
    /**
     * @param string $name
     * @return ?string
     */
    public function getCookie(string $name): ?string {
        return $this->cookieStorage->getCookie($name);
    }
    /**
     * @param string $name
     */
    public function addClearCookieConfig(string $name): void {
        $jan_1_1970 = 0;
        $exp = self::makeCookieExpiresKeyPair($jan_1_1970);
        $this->configs[] = "{$name}=-; path=/; SameSite=Strict; {$exp}";
    }
    /**
     * Kirjoittaa asetetut keksit $this->ctx->req-olioon.
     */
    public function commitCookieConfigs(): void {
        array_walk($this->configs, [$this->cookieStorage, 'storeCookie']);
    }
    /**
     * @param int $unixTime
     * @return string
     */
    private static function makeCookieExpiresKeyPair(int $unixTime): string {
        return 'expires=' . gmdate('D, d M Y H:i:s', $unixTime) . ' GMT';
    }
}
