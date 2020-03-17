<?php

namespace Pike\Auth\Internal;

use Pike\Auth\Crypto;
use Pike\PikeException;
use Pike\Auth\Authenticator;
use Pike\Validation;

class AuthService {
    private $persistence;
    private $crypto;
    private $lastErrReason;
    /**
     * @param \Pike\Auth\Internal\UserRepository $persistence
     * @param \Pike\Auth\Crypto $crypto
     */
    public function __construct(UserRepository $persistence, Crypto $crypto) {
        $this->persistence = $persistence;
        $this->crypto = $crypto;
    }
    /**
     * @param string $username
     * @param string $password
     * @return \stdClass
     * @throws \Pike\PikeException
     */
    public function login($username, $password) {
        // @allow \Pike\PikeException
        $user = $this->persistence->getUser('username = ?', [$username]);
        if (!$user)
            throw new PikeException('User not found',
                                    Authenticator::INVALID_CREDENTIAL);
        if (!$this->crypto->verifyPass($password, $user->passwordHash))
            throw new PikeException('Invalid password',
                                    Authenticator::INVALID_CREDENTIAL);
        return $user;
    }
    /**
     * @param string $usernameOrEmail
     * @param callable $makeEmailSettings fn({id: string, username: string, email: string, passwordHash: string, resetKey: string, resetRequestedAt: int} $user, string $resetKey, {fromAddress: string, fromName?: string, toAddress: string, toName?: string, subject: string, body: string} $settingsOut): void
     * @param \Pike\Auth\Internal\PhpMailerMailer $mailer
     * @return bool
     * @throws \Pike\PikeException
     */
    public function requestPasswordReset($usernameOrEmail,
                                         callable $makeEmailSettings,
                                         $mailer) {
        // @allow \Pike\PikeException
        $user = $this->persistence->getUser('username = ? OR email = ?',
                                            [$usernameOrEmail, $usernameOrEmail]);
        if (!$user)
            throw new PikeException('User not found',
                                    Authenticator::INVALID_CREDENTIAL);
        try {
            $key = $this->crypto->genRandomToken();
        } catch (\Exception $e) {
            throw new PikeException("Failed to generate reset key: {$e->getMessage()}",
                                    Authenticator::CRYPTO_FAILURE);
        }
        // @allow \Pike\PikeException
        $emailSettings = $this->makeResetPassEmailSettings($makeEmailSettings,
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
            if (!$this->persistence->updateUser($data, '`id` = ?', [$user->id]))
                throw new PikeException('Failed to insert resetInfo',
                                        PikeException::FAILED_DB_OP);
            if (!$mailer->sendMail($emailSettings))
                throw new PikeException('Failed to send mail: ' .
                                        $mailer->getLastError()->getMessage(),
                                        Authenticator::FAILED_TO_SEND_MAIL);
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
    public function finalizePasswordReset($key, $email, $newPassword) {
        // 1. Hae resetointidata tietokannasta
        // @allow \Pike\PikeException
        $user = $this->persistence->getUser('resetKey = ?', [$key]);
        $this->lastErrReason = null;
        // 2. Validoi avain ja email
        if (!$user)
            $this->lastErrReason = 'Reset key didn\'t exist';
        elseif (time() > $user->resetRequestedAt +
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
        // @allow \Pike\PikeException
        if (!$this->persistence->updateUser($data, '`id` = ?', [$user->id]))
            throw new PikeException('Failed to clear resetInfo',
                                    PikeException::FAILED_DB_OP);
        return true;
    }
    /**
     * @param mixed $userId
     * @param string $newPassword
     * @return bool
     * @throws \Pike\PikeException
     */
    public function updatePassword($userId, $newPassword) {
        // @allow \Pike\PikeException
        $filters = ['`id` = ?', [$userId]];
        if (!($user = $this->persistence->getUser(...$filters)))
            throw new PikeException('User not found',
                                    Authenticator::INVALID_CREDENTIAL);
        //
        if (!($user->passwordHash = $this->crypto->hashPass($newPassword)))
            throw new PikeException('Failed to hash a password',
                                    Authenticator::CRYPTO_FAILURE);
        //
        $data = (object) ['passwordHash' => $user->passwordHash];
        // @allow \Pike\PikeException
        if (!$this->persistence->updateUser($data, ...$filters))
            throw new PikeException('Failed to update user',
                                    PikeException::FAILED_DB_OP);
        return true;
    }
    /**
     * @throws \Pike\PikeException
     */
    private function makeResetPassEmailSettings($userDefinedMakeEmailSettings,
                                                $user,
                                                $resetKey) {
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
            ->rule('toAddress', 'type', 'string')
            ->rule('subject', 'type', 'string')
            ->rule('body', 'type', 'string')
            ->rule('fromName?', 'type', 'string')
            ->rule('toName?', 'type', 'string')
            ->validate($settings);
        if ($errors)
            throw new PikeException(implode(', ', $errors),
                                    Authenticator::FAILED_TO_FORMAT_MAIL);
        return $settings;
    }
}
