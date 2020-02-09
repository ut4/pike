<?php

namespace Pike\Auth\Internal;

use Pike\Auth\Crypto;
use Pike\PikeException;
use Pike\Auth\Authenticator;

class UserManager {
    private $persistence;
    private $crypto;
    private $services;
    private $lastErrReason;
    /**
     * @param \Pike\Auth\Internal\UserRepository $persistence
     * @param \Pike\Auth\Crypto $crypto
     * @param \Pike\Auth\Internal\CachingServicesFactory $services
     */
    public function __construct(UserRepository $persistence,
                                Crypto $crypto,
                                CachingServicesFactory $services) {
        $this->persistence = $persistence;
        $this->crypto = $crypto;
        $this->services = $services;
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
        $user = $this->persistence->getUser('username = ?', [$username]);
        if (!$user)
            throw new PikeException('User not found',
                                    Authenticator::INVALID_CREDENTIAL);
        if (!$this->crypto->verifyPass($password, $user->passwordHash))
            throw new PikeException('Invalid password',
                                    Authenticator::INVALID_CREDENTIAL);
        $this->services->makeSession()->put('user', $user->id);
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
        $user = $this->persistence->getUser('username = ? OR email = ?',
                                            [$usernameOrEmail, $usernameOrEmail]);
        if (!$user)
            throw new PikeException('User not found',
                                    Authenticator::INVALID_CREDENTIAL);
        try {
            $key = $this->crypto->genRandomToken();
        } catch (\Exception $_) {
            throw new PikeException('Failed to generate reset key',
                                    Authenticator::CRYPTO_FAILURE);
        }
        // @allow \Pike\PikeException
        $emailSettings = $this->makeResetPassEmailSettings($makeEmailSettings,
            $user, $key);
        // @allow \Pike\PikeException
        $this->persistence->runInTransaction(function () use ($key,
                                                            $emailSettings,
                                                            $user) {
            $data = new \stdClass;
            $data->resetKey = $key;
            $data->resetRequestedAt = time();
            if (!$this->persistence->updateUser($data, 'id = ?', [$user->id]))
                throw new PikeException('Failed to insert resetInfo',
                                        PikeException::FAILED_DB_OP);
            $mailer = $this->services->makeMailer();
            if (!$mailer->sendMail($emailSettings))
                throw new PikeException('Failed to send mail: ' .
                                        $mailer->getLastError()->getMessage(),
                                        Authenticator::FAILED_TO_SEND_MAIL);
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
        $user = $this->persistence->getUser('resetKey = ?', [$key]);
        $this->lastErrReason = null;
        // 2. Validoi avain ja email
        if (!$user)
            $this->lastErrReason = 'Reset key didn\'t exist';
        elseif (time () > $user->resetRequestedAt +
                          Authenticator::RESET_KEY_EXPIRATION_SECS)
            $this->lastErrReason = 'Reset key had expired';
        elseif ($user->email !== $email)
            $this->lastErrReason = 'Email didn\'t match';
        if ($this->lastErrReason)
            throw new PikeException('Invalid reset credential',
                                    Authenticator::INVALID_CREDENTIAL);
        //
        if (!($user->passwordHash = $this->crypto->hashPass($newPassword)))
            throw new PikeException('Failed to hash a password',
                                    Authenticator::CRYPTO_FAILURE);
        // 3. Päivitä uusi salasana + tyhjennä resetointiData
        $data = new \stdClass;
        $data->passwordHash = $user->passwordHash;
        $data->resetKey = null;
        $data->resetRequestedAt = null;
        if (!$this->persistence->updateUser($data, 'id = ?', [$user->id]))
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
                $errors[] = "mailSettings->{$key} is required";
        }
        foreach (['fromName', 'toName'] as $optional) {
            if (isset($settings->$optional) && !is_string($settings->$optional))
                $errors[] = "mailSettings->{$optional} must be a string";
        }
        if ($errors)
            throw new PikeException(implode(', ', $errors),
                                    Authenticator::FAILED_TO_FORMAT_MAIL);
        return $settings;
    }
}