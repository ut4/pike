<?php

declare(strict_types=1);

namespace Pike\Auth;

use Pike\Auth\{CookieManager, Crypto};
use Pike\Entities\User;
use Pike\Interfaces\UserRepositoryInterface;

/**
 * https://paragonie.com/blog/2015/04/secure-authentication-php-with-long-term-persistence#title.2.1
 */
final class RememberMe {
    private const COOKIE_NAME = 'loginTokens';
    /** @var \Pike\Interfaces\UserRepositoryInterface */
    private $persistence;
    /** @var \Pike\Auth\Crypto */
    private $crypto;
    /** @var \Pike\Auth\CookieManager */
    private $cookieManager;
    /**
     * @param \Pike\Interfaces\UserRepositoryInterface $persistence
     * @param \Pike\Auth\CookieManager $cookieManager
     * @param \Pike\Auth\Crypto $crypto
     */
    public function __construct(UserRepositoryInterface $persistence,
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
     * @param \Pike\Entities\User $user
     * @param string $loginData
    */
    public function putLogin(User $user, string $loginData): void {
        $updated = new User;
        $updated->loginId = $this->crypto->genRandomToken();
        $validatorToken = $this->crypto->genRandomToken();
        $updated->loginIdValidatorHash = $this->crypto->hash('sha256', $validatorToken);
        $updated->loginData = $loginData;
        // @allow \Pike\PikeException
        $this->persistence->updateUserByUserId($updated,
            ['loginId', 'loginIdValidatorHash', 'loginData'], $user->id);
        //
        $this->cookieManager->addCookieConfig(static::COOKIE_NAME,
            "{$updated->loginId}:{$validatorToken}",
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
        $this->cookieManager->addClearCookieConfig(static::COOKIE_NAME);
    }
    /**
     * @return string[] [<loginIdToken>, <loginIdValidatorToken>]
     */
    private function getAndParseCookie(): array {
        $loginTokens = $this->cookieManager->getCookie(static::COOKIE_NAME);
        return $loginTokens ? explode(':', $loginTokens) : ['', ''];
    }
    /**
     * @param \Pike\Entities\User $user
     */
    private function clearPersistentLoginData(User $user): void {
        $user->loginId = null;
        $user->loginIdValidatorHash = null;
        $user->loginData = null;
        // @allow \Pike\PikeException
        $this->persistence->updateUserByUserId($user,
            ['loginId', 'loginIdValidatorHash', 'loginData'],
            $user->id);
    }
}
