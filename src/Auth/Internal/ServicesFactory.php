<?php

declare(strict_types=1);

namespace Pike\Auth\Internal;

use Pike\Auth\Crypto;
use Pike\Auth\Interfaces\CookieStorageInterface;
use Pike\Interfaces\{SessionInterface, UserRepositoryInterface};

class ServicesFactory {
    /** @var callable($this): \Pike\Interfaces\UserRepositoryInterface */
    private $makeUserRepositoryFn;
    /** @var callable($this): \Pike\Interfaces\SessionInterface */
    private $makeSessionFn;
    /** @var callable($this): \Pike\Auth\Interfaces\CookieStorageInterface */
    private $makeCookieStorageFn;
    /** @var bool */
    private $doUseRememberMe;
    /** @var ?\Pike\Auth\Crypto */
    private $crypto;
    /** @var ?\Pike\Interfaces\SessionInterface */
    private $cachedSession;
    /** @var ?\Pike\Auth\Internal\CookieManager */
    private $cachedCookieManager;
    /**
     * @param callable($this): \Pike\Interfaces\UserRepositoryInterface $makeUserRepositoryFn
     * @param callable($this): \Pike\Interfaces\SessionInterface $makeSessionFn
     * @param callable($this): \Pike\Auth\Interfaces\CookieStorageInterface $makeCookieStorageFn
     * @param bool $doUseRememberMe
     * @param ?\Pike\Auth\Crypto $crypto = null
     */
    public function __construct(callable $makeUserRepositoryFn,
                                callable $makeSessionFn,
                                callable $makeCookieStorageFn,
                                bool $doUseRememberMe,
                                ?Crypto $crypto = null) {
        $this->makeUserRepositoryFn = $makeUserRepositoryFn;
        $this->makeSessionFn = $makeSessionFn;
        $this->makeCookieStorageFn = $makeCookieStorageFn;
        $this->doUseRememberMe = $doUseRememberMe;
        $this->crypto = $crypto;
        $this->cachedCookieManager = null;
    }
    /**
     * @return \Pike\Interfaces\UserRepositoryInterface
     */
    public function makeUserRepository(): UserRepositoryInterface {
        return call_user_func($this->makeUserRepositoryFn, $this);
    }
    /**
     * @return \Pike\Interfaces\SessionInterface
     */
    public function makeSession(): SessionInterface {
        if (!$this->cachedSession)
            $this->cachedSession = call_user_func($this->makeSessionFn, $this);
        return $this->cachedSession;
    }
    /**
     * @return ?\Pike\Auth\Internal\RememberMe
     */
    public function makeRememberMe(): ?RememberMe {
        return $this->doUseRememberMe
            ? new RememberMe($this->makeUserRepository(),
                             $this->makeCookieManager(),
                             $this->makeCrypto())
            : null;
    }
    /**
     * @return \Pike\Auth\Crypto
     */
    public function makeCrypto(): Crypto {
        return $this->crypto ?? new Crypto;
    }
    /**
     * @return \Pike\Auth\Internal\CookieManager
     */
    public function makeCookieManager(): CookieManager {
        if (!$this->cachedCookieManager)
            $this->cachedCookieManager = new CookieManager($this->makeCookieStorage());
        return $this->cachedCookieManager;
    }
    /**
     * @return \Pike\Auth\Interfaces\CookieStorageInterface
     */
    public function makeCookieStorage(): CookieStorageInterface {
        return call_user_func($this->makeCookieStorageFn, $this);
    }
}
