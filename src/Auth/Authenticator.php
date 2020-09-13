<?php

declare(strict_types=1);

namespace Pike\Auth;

use Pike\Auth\Internal\CachingServicesFactory;

/**
 * Autentikaatiomoduulin julkinen API: sisältää metodit kuten login() ja
 * getIdentity().
 */
class Authenticator {
    public const ACTIVATION_KEY_EXPIRATION_SECS = 60 * 60 * 24;
    public const RESET_KEY_EXPIRATION_SECS = 60 * 60 * 2;
    public const INVALID_CREDENTIAL  = 201010;
    public const USER_ALREADY_EXISTS = 201011;
    public const FAILED_TO_SEND_MAIL = 201012;
    public const FAILED_TO_FORMAT_MAIL = 201013;
    public const CRYPTO_FAILURE = 201014;
    public const EXPIRED_KEY = 201015;
    public const UNEXPECTED_ACCOUNT_STATUS = 201016;
    public const ACCOUNT_STATUS_ACTIVATED = 0;
    public const ACCOUNT_STATUS_UNACTIVATED = 1;
    public const ACCOUNT_STATUS_BANNED = 2;
    /** @var \Pike\Auth\Internal\CachingServicesFactory */
    protected $services;
    /** @var ?string */
    protected $userRoleCookieName;
    /**
     * @param \Pike\Auth\Internal\CachingServicesFactory $factory
     * @param string $userRoleCookieName = 'maybeLoggedInUserRole' Disabloi asettamalla tyhjä merkkijono
     */
    public function __construct(CachingServicesFactory $factory,
                                string $userRoleCookieName = 'maybeLoggedInUserRole') {
        $this->services = $factory;
        $this->userRoleCookieName = strlen($userRoleCookieName) ? $userRoleCookieName : null;
    }
    /**
     */
    public function postProcess(): void {
        if ($this->userRoleCookieName)
            $this->services->makeCookieManager()->commitCookies();
    }
    /**
     * @param string $username
     * @param string $password
     * @param ?callable $serializeUserForSession = null fn(object $user): mixed
     * @return bool
     * @throws \Pike\PikeException
     */
    public function login(string $username,
                          string $password,
                          ?callable $serializeUserForSession = null): bool {
        $authService = $this->services->makeAuthService();
        // @allow \Pike\PikeException
        if (($user = $authService->login($username, $password))) {
            $this->putUserToSession($user, $serializeUserForSession);
            return true;
        }
        return false;
    }
    /**
     * @param string $userId
     * @param ?callable $serializeUserForSession = null fn(object $user): mixed
     * @return bool
     * @throws \Pike\PikeException
     */
    public function loginByUserId(string $userId,
                                  ?callable $serializeUserForSession = null): bool {
        $authService = $this->services->makeAuthService();
        // @allow \Pike\PikeException
        if (($user = $authService->loginByUserId($userId))) {
            $this->putUserToSession($user, $serializeUserForSession);
            return true;
        }
        return false;
    }
    /**
     * @return mixed|null
     */
    public function getIdentity() {
        if (($user = $this->services->makeSession()->get('user')) ||
            !($rememberMe = $this->services->makeRememberMe()))
            return $user;
        if (// @allow \Pike\PikeException
            ($user = $rememberMe->getLogin())) {
            $sessionData = unserialize($user->loginData);
            $this->services->makeSession()->put('user', $sessionData);
            return $sessionData;
        }
        return null;
    }
    /**
     * @return bool
     */
    public function logout(): bool {
        if ($this->userRoleCookieName)
            $this->services->makeCookieManager()->clearCookie($this->userRoleCookieName);
        if (($rememberMe = $this->services->makeRememberMe()))
            // @allow \Pike\PikeException
            $rememberMe->clearLogin();
        $this->services->makeSession()->destroy();
        return true;
    }
    /**
     * @param string $username
     * @param string $email
     * @param string $password
     * @param int $role
     * @param callable $makeEmailSettings fn({id: string, username: string, email: string, passwordHash: string, role: int, activationKey: string, accountCreatedAt: int, resetKey: string, resetRequestedAt: int, accountStatus: int} $user, string $activationKey, {fromAddress: string, fromName?: string, toAddress: string, toName?: string, subject: string, body: string} $settingsOut): void
     * @return string
     * @throws \Pike\PikeException
     */
    public function requestNewAccount(string $username,
                                      string $email,
                                      string $password,
                                      int $role,
                                      callable $makeEmailSettings): string {
        // @allow \Pike\PikeException
        return $this->services->makeAuthService()
            ->createUnactivatedUser($username, $email, $password, $role,
                                    $makeEmailSettings,
                                    $this->services->makeMailer());
    }
    /**
     * @param string $activationKey
     * @return bool
     * @throws \Pike\PikeException
     */
    public function activateAccount(string $activationKey): bool {
        // @allow \Pike\PikeException
        return $this->services->makeAuthService()
            ->activateUser($activationKey);
    }
    /**
     * @param string $usernameOrEmail
     * @param callable $makeEmailSettings fn({id: string, username: string, email: string, passwordHash: string, role: int, activationKey: string, accountCreatedAt: int, resetKey: string, resetRequestedAt: int, accountStatus: int} $user, string $resetKey, {fromAddress: string, fromName?: string, toAddress: string, toName?: string, subject: string, body: string} $settingsOut): void
     * @return bool
     * @throws \Pike\PikeException
     */
    public function requestPasswordReset(string $usernameOrEmail,
                                         callable $makeEmailSettings): bool {
        // @allow \Pike\PikeException
        return $this->services->makeAuthService()
            ->requestPasswordReset($usernameOrEmail,
                                   $makeEmailSettings,
                                   $this->services->makeMailer());
    }
    /**
     * @param string $key
     * @param string $email
     * @param string $newPassword
     * @return bool
     * @throws \Pike\PikeException
     */
    public function finalizePasswordReset(string $key,
                                          string $email,
                                          string $newPassword): bool {
        // @allow \Pike\PikeException
        return $this->services->makeAuthService()
            ->finalizePasswordReset($key, $email, $newPassword);
    }
    /**
     * @param string $userId
     * @param string $newPassword
     * @return bool
     * @throws \Pike\PikeException
     */
    public function updatePassword(string $userId, string $newPassword): bool {
        // @allow \Pike\PikeException
        return $this->services->makeAuthService()
            ->updatePassword($userId, $newPassword);
    }
    /**
     * @param object $user
     * @param ?callable $serializeUserForSession = null
     */
    private function putUserToSession(object $user,
                                      ?callable $serializeUserForSession = null): void {
        $sessionData = $serializeUserForSession
            ? call_user_func($serializeUserForSession, $user)
            : (object) ['id' => $user->id];
        $this->services->makeSession()->put('user', $sessionData);
        //
        if ($this->userRoleCookieName)
            $this->services->makeCookieManager()
                ->putCookie($this->userRoleCookieName, strval($user->role));
        //
        if ($rememberMe = $this->services->makeRememberMe())
            // @allow \Pike\PikeException
            $rememberMe->putLogin($user, serialize($sessionData));
    }
}
