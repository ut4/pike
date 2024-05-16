<?php

declare(strict_types=1);

namespace Pike\Auth\Internal;

use Pike\Auth\{Authenticator, Crypto};
use Pike\Entities\User;
use Pike\Interfaces\UserRepositoryInterface;
use Pike\Request;

/**
 * https://paragonie.com/blog/2015/04/secure-authentication-php-with-long-term-persistence#title.2.1
 */
final class RememberMe {
    private const COOKIE_NAME = 'loginTokens';
    /** @var \Pike\Interfaces\UserRepositoryInterface */
    private $persistence;
    /** @var \Pike\Auth\Crypto */
    private $crypto;
    /** @var \Pike\Auth\Internal\CookieManager */
    private $cookieManager;
    /** @var \Pike\Request */
    private $request;
    /**
     * @param \Pike\Interfaces\UserRepositoryInterface $persistence
     * @param \Pike\Auth\Internal\CookieManager $cookieManager
     * @param \Pike\Auth\Crypto $crypto
     * @param \Pike\Request $request
     */
    public function __construct(UserRepositoryInterface $persistence,
                                CookieManager $cookieManager,
                                Crypto $crypto,
                                Request $request) {
        $this->persistence = $persistence;
        $this->cookieManager = $cookieManager;
        $this->crypto = $crypto;
        $this->request = $request;
    }
    /**
     * @return ?string $serializedSessionData
     */
    public function getLogin(): ?string {
        [$loginId, $loginIdValidatorWithoutIP] = $this->getAndParseCookie();
        // @allow \Pike\PikeException
        $user = $this->persistence->getUserByColumn('loginId', $loginId);
        if (!$user || !$user->loginIdValidatorHash) return null;
        //
        if ($user->accountStatus !== Authenticator::ACCOUNT_STATUS_ACTIVATED) {
            return $this->wipePersistentLoginDataAndReturn($user->id);
        }
        $loginIdValidatorWithIP = "{$loginIdValidatorWithoutIP}{$this->request->attr('REMOTE_ADDR')}";
        if (!hash_equals($user->loginIdValidatorHash,
                         $this->crypto->hash('sha256', $loginIdValidatorWithIP))) {
            return $this->wipePersistentLoginDataAndReturn($user->id);
        }
        // ok
        return $user->loginData;
    }
    /**
     * @param string $userId
     * @param string $serializedSessionData
     */
    public function putLogin(string $userId, string $serializedSessionData): void {
        $updated = new User;
        $updated->loginId = $this->crypto->genRandomToken();
        $loginIdValidatorWithoutIP = $this->crypto->genRandomToken();
        $loginIdValidatorWithIP = "{$loginIdValidatorWithoutIP}{$this->request->attr('REMOTE_ADDR')}";
        $updated->loginIdValidatorHash = $this->crypto->hash('sha256', $loginIdValidatorWithIP);
        $updated->loginData = $serializedSessionData;
        // @allow \Pike\PikeException
        $this->persistence->updateUserByUserId($updated,
            ['loginId', 'loginIdValidatorHash', 'loginData'], $userId);
        //
        $this->cookieManager->addCookieConfig(self::COOKIE_NAME,
            "{$updated->loginId}:{$loginIdValidatorWithoutIP}",
            strtotime('+6 months'));
    }
    /**
     */
    public function clearLogin(): void {
        [$loginId, $_loginIdValidatorWithoutIP] = $this->getAndParseCookie();
        if (!$loginId)
            return;
        // @allow \Pike\PikeException
        if (($user = $this->persistence->getUserByColumn('loginId', $loginId)))
            $this->clearPersistentLoginData($user->id);
        $this->cookieManager->addClearCookieConfig(self::COOKIE_NAME);
    }
    /**
     * @return string[] [<loginId>, <loginIdValidatorWithoutIP>]
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
    /**
     * @param string $userId
     * @return null
     */
    private function wipePersistentLoginDataAndReturn(string $userId) {
        $this->clearPersistentLoginData($userId);
        return null;
    }
}
