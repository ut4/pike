<?php

declare(strict_types=1);

namespace Pike\Auth\Internal;

use Pike\Auth\AbstractUserRepository;
use Pike\Auth\Crypto;
use Pike\PikeException;
use Pike\Auth\Authenticator;
use Pike\Validation;

final class AuthService {
    private $persistence;
    private $crypto;
    /**
     * @param \Pike\Auth\AbstractUserRepository $persistence
     * @param \Pike\Auth\Crypto $crypto
     */
    public function __construct(AbstractUserRepository $persistence, Crypto $crypto) {
        $this->persistence = $persistence;
        $this->crypto = $crypto;
    }
    /**
     * @param string $username
     * @param string $password
     * @return object
     * @throws \Pike\PikeException
     */
    public function login(string $username, string $password): object {
        // @allow \Pike\PikeException
        $user = $this->persistence->getUserByUsername($username);
        if (!$user)
            throw new PikeException('User not found or not activated',
                                    Authenticator::INVALID_CREDENTIAL);
        if (!$this->crypto->verifyPass($password, $user->passwordHash))
            throw new PikeException('Invalid password',
                                    Authenticator::INVALID_CREDENTIAL);
        if ($user->accountStatus !== Authenticator::ACCOUNT_STATUS_ACTIVATED)
            throw new PikeException('Expected accountStatus to be ACTIVATED',
                                    Authenticator::UNEXPECTED_ACCOUNT_STATUS);
        return $user;
    }
    /**
     * @param string $username
     * @param string $email
     * @param string $password
     * @param int $role
     * @param callable $makeEmailSettings fn({id: string, username: string, email: string, passwordHash: string, role: int, activationKey: string, accountCreatedAt: int, resetKey: string, resetRequestedAt: int, accountStatus: int} $user, string $activationKey, {fromAddress: string, fromName?: string, toAddress: string, toName?: string, subject: string, body: string} $settingsOut): void
     * @param \Pike\Auth\Internal\AbstractMailer $mailer
     * @return string $insertId
     * @throws \Pike\PikeException|\Exception
     */
    public function createUnactivatedUser(string $username,
                                          string $email,
                                          string $password,
                                          int $role,
                                          callable $makeEmailSettings,
                                          AbstractMailer $mailer): string {
        // @allow \Pike\PikeException
        if ($this->persistence->getUserByUsernameOrEmail($username, $email))
            throw new PikeException('User already exists',
                                    Authenticator::USER_ALREADY_EXISTS);
        //
        $data = (object) [
            // @allow \Exception
            'id' => $this->crypto->guidv4(),
            'username' => $username,
            'email' => $email,
            // @allow \Pike\PikeException
            'passwordHash' => $this->crypto->hashPass($password),
            'role' => $role,
            'activationKey' => '',
            'accountCreatedAt' => '',
        ];
        // @allow \Pike\PikeException
        $key = $this->crypto->genRandomToken(32);
        // @allow \Pike\PikeException
        $emailSettings = $this->makeEmailSettings($makeEmailSettings, $data, $key);
        return $this->persistence->runInTransaction(function () use ($key,
                                                                     $data,
                                                                     $mailer,
                                                                     $emailSettings) {
            $data->activationKey = $key;
            $data->accountCreatedAt = time();
            // @allow \Pike\PikeException
            $insertId = $this->persistence->putUser($data);
            try {
                $mailer->sendMail($emailSettings);
            } catch (\Exception $e) {
                throw new PikeException("Failed to send mail: {$e->getMessage()}",
                                        Authenticator::FAILED_TO_SEND_MAIL,
                                        $e);
            }
            return $insertId;
        });
    }
    /**
     * @param string $activationKey
     * @return bool
     * @throws \Pike\PikeException
     */
    public function activateUser(string $activationKey): bool {
        // 1. Hae resetointidata tietokannasta
        // @allow \Pike\PikeException
        $user = $this->persistence->getUserByActivationKey($activationKey);
        // 2. Validoi avain ja käyttäjä
        if (!$user)
            throw new PikeException('Invalid reset credential',
                                    Authenticator::INVALID_CREDENTIAL);
        if (time() > $user->accountCreatedAt +
                     Authenticator::ACTIVATION_KEY_EXPIRATION_SECS) {
        // 2.1 Avain vanhentunut, poista käyttäjä
            // @allow \Pike\PikeException
            if (!$this->persistence->deleteUserByUserId($user->id))
                throw new PikeException('Failed to delete stray user',
                                        PikeException::FAILED_DB_OP);
            throw new PikeException('Activation key had expired',
                                    Authenticator::EXPIRED_KEY);
        }
        // 3. Ok, aktivoi tili
        $data = new \stdClass;
        $data->activationKey = null;
        $data->accountStatus = Authenticator::ACCOUNT_STATUS_ACTIVATED;
        // @allow \Pike\PikeException
        if (!$this->persistence->updateUserByUserId($data, $user->id))
            throw new PikeException('Failed to clear activation info',
                                    PikeException::FAILED_DB_OP);
        return true;
    }
    /**
     * @param string $usernameOrEmail
     * @param callable $makeEmailSettings fn({id: string, username: string, email: string, passwordHash: string, role: int, activationKey: string, accountCreatedAt: int, resetKey: string, resetRequestedAt: int, accountStatus: int} $user, string $resetKey, {fromAddress: string, fromName?: string, toAddress: string, toName?: string, subject: string, body: string} $settingsOut): void
     * @param \Pike\Auth\Internal\AbstractMailer $mailer
     * @return bool
     * @throws \Pike\PikeException
     */
    public function requestPasswordReset(string $usernameOrEmail,
                                         callable $makeEmailSettings,
                                         AbstractMailer $mailer): bool {
        // @allow \Pike\PikeException
        $user = $this->persistence->getUserByUsernameOrEmail($usernameOrEmail,
                                                             $usernameOrEmail);
        if (!$user)
            throw new PikeException('User not found or not activated',
                                    Authenticator::INVALID_CREDENTIAL);
        if ($user->accountStatus !== Authenticator::ACCOUNT_STATUS_ACTIVATED)
            throw new PikeException('Expected accountStatus to be ACTIVATED',
                                    Authenticator::UNEXPECTED_ACCOUNT_STATUS);
        // @allow \Pike\PikeException
        $key = $this->crypto->genRandomToken(32);
        // @allow \Pike\PikeException
        $emailSettings = $this->makeEmailSettings($makeEmailSettings,
            $user, $key);
        // @allow \Pike\PikeException
        $this->persistence->runInTransaction(function () use ($key,
                                                              $emailSettings,
                                                              $user,
                                                              $mailer) {
            $data = new \stdClass;
            $data->resetKey = $key;
            $data->resetRequestedAt = time();
            // @allow \Pike\PikeException
            if (!$this->persistence->updateUserByUserId($data, $user->id))
                throw new PikeException('Failed to insert reset info',
                                        PikeException::FAILED_DB_OP);
            try {
                $mailer->sendMail($emailSettings);
            } catch (\Exception $e) {
                throw new PikeException("Failed to send mail: {$e->getMessage()}",
                                        Authenticator::FAILED_TO_SEND_MAIL,
                                        $e);
            }
        });
        return true;
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
        // 1. Hae resetointidata tietokannasta
        // @allow \Pike\PikeException
        $user = $this->persistence->getUserByResetKey($key);
        // 2. Validoi avain ja email
        if (!$user || $user->email !== $email)
            throw new PikeException('Invalid reset credential',
                                    Authenticator::INVALID_CREDENTIAL);
        if (time() > $user->resetRequestedAt +
                     Authenticator::RESET_KEY_EXPIRATION_SECS) {
            throw new PikeException('Reset key had expired',
                                    Authenticator::EXPIRED_KEY);
        }
        // 3. Päivitä uusi salasana + tyhjennä resetointiData
        $data = new \stdClass;
        // @allow \Pike\PikeException
        $data->passwordHash = $this->crypto->hashPass($newPassword);
        $data->resetKey = null;
        $data->resetRequestedAt = 0;
        // @allow \Pike\PikeException
        if (!$this->persistence->updateUserByUserId($data, $user->id))
            throw new PikeException('Failed to clear reset info',
                                    PikeException::FAILED_DB_OP);
        return true;
    }
    /**
     * @param mixed $userId
     * @param string $newPassword
     * @return bool
     * @throws \Pike\PikeException
     */
    public function updatePassword(string $userId, string $newPassword): bool {
        // @allow \Pike\PikeException
        if (!($user = $this->persistence->getUserByUserId($userId)))
            throw new PikeException('User not found',
                                    Authenticator::INVALID_CREDENTIAL);
        //
        $data = new \stdClass;
        // @allow \Pike\PikeException
        $data->passwordHash = $this->crypto->hashPass($newPassword);
        // @allow \Pike\PikeException
        if (!$this->persistence->updateUserByUserId($data, $userId))
            throw new PikeException('Failed to update user',
                                    PikeException::FAILED_DB_OP);
        return true;
    }
    /**
     * @throws \Pike\PikeException
     */
    private function makeEmailSettings(callable $userDefinedMakeEmailSettings,
                                       object $user,
                                       string $resetKey): \stdClass {
        $settings = new \stdClass;
        $settings->fromAddress = '';
        $settings->fromName = '';
        $settings->toAddress = $user->email;
        $settings->toName = $user->username;
        $settings->subject = '';
        $settings->body = '';
        call_user_func($userDefinedMakeEmailSettings, $user, $resetKey, $settings);
        //
        $errors = (Validation::makeObjectValidator())
            ->rule('fromAddress', 'type', 'string')
            ->rule('fromAddress', 'minLength', 3)
            ->rule('toAddress', 'type', 'string')
            ->rule('toAddress', 'minLength', 3)
            ->rule('subject', 'type', 'string')
            ->rule('subject', 'minLength', 1)
            ->rule('body', 'type', 'string')
            ->rule('body', 'minLength', 1)
            ->rule('fromName?', 'type', 'string')
            ->rule('toName?', 'type', 'string')
            ->validate($settings);
        if ($errors)
            throw new PikeException(implode(', ', $errors),
                                    Authenticator::FAILED_TO_FORMAT_MAIL);
        return $settings;
    }
}
