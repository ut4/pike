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
    private $services;
    /**
     * @param \Pike\Auth\Internal\CachingServicesFactory $factory
     */
    public function __construct(CachingServicesFactory $factory) {
        $this->services = $factory;
    }
    /**
     * @param string $username
     * @param string $password
     * @param callable $serializeUserForSession = null fn(object $user): mixed
     * @return bool
     * @throws \Pike\PikeException
     */
    public function login(string $username,
                          string $password,
                          callable $serializeUserForSession = null): bool {
        // @allow \Pike\PikeException
        if (($user = $this->services->makeAuthService()->login($username, $password))) {
            $this->services->makeSession()->put('user', $serializeUserForSession
                ? call_user_func($serializeUserForSession, $user)
                : $user->id);
            return true;
        }
        return false;
    }
    /**
     * @return mixed|null
     */
    public function getIdentity() {
        return $this->services->makeSession()->get('user');
    }
    /**
     * @return bool
     */
    public function logout(): bool {
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
}
