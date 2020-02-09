<?php

namespace Pike\Auth;

use Pike\Auth\Internal\CachingServicesFactory;

/**
 * Auth-moduulin julkinen API: sisältää metodit kuten isLoggedIn() ja login().
 */
class Authenticator {
    public const RESET_KEY_EXPIRATION_SECS = 60 * 60 * 2;
    public const INVALID_CREDENTIAL  = 201010;
    public const USER_ALREADY_EXISTS = 201011;
    public const FAILED_TO_SEND_MAIL = 201012;
    public const FAILED_TO_FORMAT_MAIL = 201013;
    public const CRYPTO_FAILURE = 201014;
    private $services;
    /**
     * @param \Pike\Auth\Internal\CachingServicesFactory $factory
     */
    public function __construct(CachingServicesFactory $factory) {
        $this->services = $factory;
    }
    /**
     * Palauttaa käyttäjän id:n mikäli käyttäjä on kirjautunut, muutoin null.
     *
     * @return string|null
     */
    public function getIdentity() {
        return $this->services->makeSession()->get('user');
    }
    /**
     * Asettaa käyttäjän $username kirjautuneeksi käyttäjäksi, tai heittää
     * RadExceptionin mikäli käyttäjää ei voitu hakea kannasta tai salasana ei
     * täsmännyt. Olettaa että parametrit on jo validoitu.
     *
     * @param string $username
     * @param string $password
     * @return bool
     * @throws \Pike\PikeException
     */
    public function login($username, $password) {
        return $this->services->makeUserManager()->login($username, $password);
    }
    /**
     * Kirjaa käyttäjän ulos poistamalla käyttäjän tiedot sessiosta.
     *
     * @return bool
     */
    public function logout() {
        $this->services->makeSession()->destroy();
        return true;
    }
    /**
     * ...
     *
     * @param string $usernameOrEmail
     * @param fn({id: string, username: string, email: string, passwordHash: string, resetKey: string, resetRequestedAt: int} $user, string $resetKey, {fromAddress: string, fromName?: string, toAddress: string, toName?: string, subject: string, body: string} $settingsOut): void $makeEmailSettings
     * @return bool
     * @throws \Pike\PikeException
     */
    public function requestPasswordReset($usernameOrEmail, callable $makeEmailSettings) {
        return $this->services->makeUserManager()
            ->requestPasswordReset($usernameOrEmail, $makeEmailSettings);
    }
    /**
     * ...
     *
     * @param string $key
     * @param string $email
     * @param string $newPassword
     * @return bool
     * @throws \Pike\PikeException
     */
    public function finalizePasswordReset($key, $email, $newPassword) {
        return $this->services->makeUserManager()
            ->finalizePasswordReset($key, $email, $newPassword);
    }
}
