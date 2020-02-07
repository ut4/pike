<?php

namespace Pike\Auth;

use Pike\PikeException;

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
    private $crypto;
    private $services;
    private $lastErrReason;
    /**
     * @param \Pike\Auth\Crypto $crypto
     * @param \Pike\Auth\CachingServicesFactory $factory
     */
    public function __construct(Crypto $crypto, CachingServicesFactory $factory) {
        $this->crypto = $crypto;
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
        $user = $this->services->makeUserRepository()->getUser('username = ?',
                                                               [$username]);
        if (!$user)
            throw new PikeException('User not found', self::INVALID_CREDENTIAL);
        if (!$this->crypto->verifyPass($password, $user->passwordHash))
            throw new PikeException('Invalid password', self::INVALID_CREDENTIAL);
        $this->services->makeSession()->put('user', $user->id);
        return true;
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
        $persistence = $this->services->makeUserRepository();
        $user = $persistence->getUser('username = ? OR email = ?',
                                      [$usernameOrEmail, $usernameOrEmail]);
        if (!$user)
            throw new PikeException('User not found', self::INVALID_CREDENTIAL);
        try {
            $key = $this->crypto->genRandomToken();
        } catch (\Exception $_) {
            throw new PikeException('Failed to generate reset key',
                                    self::CRYPTO_FAILURE);
        }
        // @allow \Pike\PikeException
        $emailSettings = $this->makeResetPassEmailSettings($makeEmailSettings,
            $user, $key);
        // @allow \Pike\PikeException
        $persistence->runInTransaction(function () use ($persistence,
                                                        $key,
                                                        $emailSettings,
                                                        $user) {
            $data = new \stdClass;
            $data->resetKey = $key;
            $data->resetRequestedAt = time();
            if (!$persistence->updateUser($data, 'id = ?', [$user->id]))
                throw new PikeException('Failed to insert resetInfo',
                                        PikeException::FAILED_DB_OP);
            $mailer = $this->services->makeMailer();
            if (!$mailer->sendMail($emailSettings))
                throw new PikeException('Failed to send mail: ' .
                                        $mailer->getLastError()->getMessage(),
                                        self::FAILED_TO_SEND_MAIL);
        });
        return true;
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
        // 1. Hae resetointidata tietokannasta
        $persistence = $this->services->makeUserRepository();
        $user = $persistence->getUser('resetKey = ?', [$key]);
        $this->lastErrReason = null;
        // 2. Validoi avain ja email
        if (!$user)
            $this->lastErrReason = 'Reset key didn\'t exist';
        elseif (time () > $user->resetRequestedAt + self::RESET_KEY_EXPIRATION_SECS)
            $this->lastErrReason = 'Reset key had expired';
        elseif ($user->email !== $email)
            $this->lastErrReason = 'Email didn\'t match';
        if ($this->lastErrReason)
            throw new PikeException('Invalid reset credential',
                                    self::INVALID_CREDENTIAL);
        //
        if (!($user->passwordHash = $this->crypto->hashPass($newPassword)))
            throw new PikeException('Failed to hash a password',
                                    self::CRYPTO_FAILURE);
        // 3. Päivitä uusi salasana + tyhjennä resetointiData
        $data = new \stdClass;
        $data->passwordHash = $user->passwordHash;
        $data->resetKey = null;
        $data->resetRequestedAt = null;
        if (!$persistence->updateUser($data, 'id = ?', [$user->id]))
            throw new PikeException('Failed to clear resetInfo',
                                    PikeException::FAILED_DB_OP);
        return true;
    }
    /**
     * @throws \Pike\PikeException
     */
    private function makeResetPassEmailSettings($userDefinedMakeEmailSettings,
                                                $user,
                                                $resetKey) {
        $settings = new \stdClass();
        $settings->fromAddress = '';
        $settings->fromName = '';
        $settings->toAddress = $user->email;
        $settings->toName = $user->username;
        $settings->subject = '';
        $settings->body = '';
        call_user_func($userDefinedMakeEmailSettings, $user, $resetKey, $settings);
        //
        $errors = [];
        foreach (['fromAddress', 'toAddress', 'subject', 'body'] as $key) {
            if (!isset($settings->$key) || !is_string($settings->$key))
                $errors[] = "mailSettings->{$key} is required.";
        }
        foreach (['fromName', 'toName'] as $optional) {
            if (isset($settings->$optional) || !is_string($settings->$optional))
                $errors[] = "mailSettings->{$key} must be a string.";
        }
        if ($errors)
            throw new PikeException(implode(', ', $errors),
                                    self::FAILED_TO_FORMAT_MAIL);
        return $settings;
    }
}
