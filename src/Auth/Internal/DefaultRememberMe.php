<?php

declare(strict_types=1);

namespace Pike\Auth\Internal;

use Pike\Auth\{AbstractUserRepository, Crypto};

/**
 * https://paragonie.com/blog/2015/04/secure-authentication-php-with-long-term-persistence#title.2.1
 */
class DefaultRememberMe {
    protected const COOKIE_NAME = 'loginTokens';
    /** @var \Pike\Auth\AbstractUserRepository */
    protected $persistence;
    /** @var \Pike\Auth\Crypto */
    protected $crypto;
    /** @var \Pike\Auth\Internal\CookieManager */
    protected $cookieManager;
    /**
     * @param \Pike\Auth\AbstractUserRepository $persistence
     * @param \Pike\Auth\Internal\CookieManager $cookieManager
     * @param \Pike\Auth\Crypto $crypto
     */
    public function __construct(AbstractUserRepository $persistence,
                                CookieManager $cookieManager,
                                Crypto $crypto) {
        $this->persistence = $persistence;
        $this->cookieManager = $cookieManager;
        $this->crypto = $crypto;
    }
    /**
     * @return ?object
     */
    public function getLogin(): ?object {
        [$loginIdToken, $loginIdValidatorToken] = $this->getAndParseCookie();
        // @allow \Pike\PikeException
        $user = $this->persistence->getUserByColumn('loginId', $loginIdToken);
        if (!$user || !$user->loginIdValidatorHash) return null;
        if (hash_equals($user->loginIdValidatorHash,
                        $this->crypto->hash('sha256', $loginIdValidatorToken)))
            return $user;
        else
            $this->clearPersistentLoginData($user);
        return null;
    }
    /**
     * @param object $user
     * @param string $loginData
    */
    public function putLogin(object $user, string $loginData): void {
        $selectorToken = $this->crypto->genRandomToken();
        $validatorToken = $this->crypto->genRandomToken();
        // @allow \Pike\PikeException
        $this->persistence->updateUserByUserId((object) [
            'loginId' => $selectorToken,
            'loginIdValidatorHash' => $this->crypto->hash('sha256', $validatorToken),
            'loginData' => $loginData,
        ], $user->id);
        //
        $this->cookieManager->putCookie(static::COOKIE_NAME,
                                        "{$selectorToken}:{$validatorToken}",
                                        strtotime('+6 months'));
    }
    /**
     */
    public function clearLogin(): void {
        [$loginIdToken, $loginIdValidatorToken] = $this->getAndParseCookie();
        if (!$loginIdToken)
            return;
        // @allow \Pike\PikeException
        if (($user = $this->persistence->getUserByColumn('loginId', $loginIdToken)))
            $this->clearPersistentLoginData($user);
        $this->cookieManager->clearCookie(static::COOKIE_NAME);
    }
    /**
     * @return string[] [<loginIdToken>, <loginIdValidatorToken>]
     */
    protected function getAndParseCookie(): array {
        $loginTokens = $this->cookieManager->getCookie(static::COOKIE_NAME);
        return $loginTokens ? explode(':', $loginTokens) : ['', ''];
    }
    /**
     * @param object $user
     */
    protected function clearPersistentLoginData(object $user): void {
        // @allow \Pike\PikeException
        $this->persistence->updateUserByUserId((object) [
            'loginId' => null,
            'loginIdValidatorHash' => null,
            'loginData' => null,
        ], $user->id);
    }
}
