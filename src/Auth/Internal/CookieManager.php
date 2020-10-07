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
     * @param ?int $expiration = null null = istunnon ajan, >0 = absoluuttinen aika, unixtime
     */
    public function addCookieConfig(string $name,
                                    string $value,
                                    ?int $expiration = null): void {
        $e = $expiration !== null ? (';' . self::makeCookieExpiresKeyPair($expiration)) : '';
        $this->configs[] = "{$name}={$value};path=/{$e}";
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
        $e = self::makeCookieExpiresKeyPair(1);
        $this->configs[] = "{$name}=-;path=/;{$e}";
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
