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
     * @return ?string $serializedSessionData
     */
    public function getLogin(): ?string {
        [$loginIdToken, $loginIdValidatorToken] = $this->getAndParseCookie();
        // @allow \Pike\PikeException
        $user = $this->persistence->getUserByColumn('loginId', $loginIdToken);
        if (!$user || !$user->loginIdValidatorHash) return null;
        if (hash_equals($user->loginIdValidatorHash,
                        $this->crypto->hash('sha256', $loginIdValidatorToken)))
            return $user->loginData;
        else
            $this->clearPersistentLoginData($user->id);
        return null;
    }
    /**
     * @param string $userId
     * @param string $serializedSessionData
     */
    public function putLogin(string $userId, string $serializedSessionData): void {
        $updated = new User;
        $updated->loginId = $this->crypto->genRandomToken();
        $validatorToken = $this->crypto->genRandomToken();
        $updated->loginIdValidatorHash = $this->crypto->hash('sha256', $validatorToken);
        $updated->loginData = $serializedSessionData;
        // @allow \Pike\PikeException
        $this->persistence->updateUserByUserId($updated,
            ['loginId', 'loginIdValidatorHash', 'loginData'], $userId);
        //
        $this->cookieManager->addCookieConfig(self::COOKIE_NAME,
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
            $this->clearPersistentLoginData($user->id);
        $this->cookieManager->addClearCookieConfig(self::COOKIE_NAME);
    }
    /**
     * @return string[] [<loginIdToken>, <loginIdValidatorToken>]
     */
    private function getAndParseCookie(): array {
        $loginTokens = $this->cookieManager->getCookie(self::COOKIE_NAME);
        return $loginTokens ? explode(':', $loginTokens) : ['', ''];
    }
    /**
     * @param string $userId
     */
    private function clearPersistentLoginData(string $userId): void {
        $user = new User;
        $user->loginId = null;
        $user->loginIdValidatorHash = null;
        $user->loginData = null;
        // @allow \Pike\PikeException
        $this->persistence->updateUserByUserId($user,
            ['loginId', 'loginIdValidatorHash', 'loginData'],
            $userId);
    }
}
