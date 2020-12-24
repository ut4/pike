<?php

declare(strict_types=1);

namespace Pike\Auth;

use Pike\Entities\User;
use Pike\Interfaces\{MailerInterface, UserRepositoryInterface};
use Pike\{PhpMailerMailer, PikeException, Validation};

/**
 * Autentikaatiomoduulin julkinen käyttäjänhallinta-API, toiminnallisuuksiin pääsee
 * käsiksi $auth->getAccountManager()->toiminnallisuus();
 */
final class AccountManager {
    /** @var \Pike\Interfaces\UserRepositoryInterface */
    private $persistence;
    /** @var \Pike\Auth\Crypto */
    private $crypto;
    /** @var ?callable(): \Pike\Interfaces\MailerInterface */
    private $makeMailerFn;
    /**
     * @param \Pike\Interfaces\UserRepositoryInterface $userRepo
     * @param \Pike\Auth\Crypto $crypto
     * @param ?callable(): \Pike\Interfaces\MailerInterface $makeMailerFn = null
     */
    public function __construct(UserRepositoryInterface $userRepo,
                                Crypto $crypto,
                                ?callable $makeMailerFn = null) {
        $this->persistence = $userRepo;
        $this->crypto = $crypto;
        $this->makeMailerFn = $makeMailerFn;
    }
    /**
     * @param string $username
     * @param string $email
     * @param string $password
     * @param callable(\Pike\Entities\User $user, string $activationKey, object $emailSettings): void $makeEmailSettings
     * @param int $role = \Pike\Auth\ACL::ROLE_LAST
     * @return string The return value of $this->persistence->createUser()
     * @throws \Pike\PikeException
     */
    public function requestNewAccount(string $username,
                                      string $email,
                                      string $password,
                                      callable $makeEmailSettings,
                                      int $role = ACL::ROLE_LAST): string {
        // @allow \Pike\PikeException
        if ($this->persistence->getUserByColumn('username', $username))
            throw new PikeException('User already exists',
                                    Authenticator::USER_ALREADY_EXISTED);
        //
        $user = new User;
        // @allow \Exception
        $user->id = $this->crypto->guidv4();
        $user->username = $username;
        $user->email = $email;
        $user->accountStatus = Authenticator::ACCOUNT_STATUS_UNACTIVATED;
        // @allow \Pike\PikeException
        $user->passwordHash = $this->crypto->hashPass($password);
        $user->role = $role;
        // @allow \Pike\PikeException
        $key = $this->crypto->genRandomToken(32);
        // @allow \Pike\PikeException
        $emailSettings = $this->makeEmailSettings($makeEmailSettings, $user, $key);
        $mailer = $this->makeMailer();
        return $this->persistence->runInTransaction(function () use ($key,
                                                                     $user,
                                                                     $mailer,
                                                                     $emailSettings) {
            $user->activationKey = $key;
            $user->accountCreatedAt = time();
            // @allow \Pike\PikeException
            $insertId = $this->persistence->createUser($user);
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
     * @throws \Pike\PikeException
     */
    public function activateAccount(string $activationKey): void {
        // 1. Hae resetointidata tietokannasta
        // @allow \Pike\PikeException
        $user = $this->persistence->getUserByColumn('activationKey', $activationKey);
        // 2. Validoi avain ja käyttäjä
        if (!$user)
            throw new PikeException('Invalid reset credential',
                                    Authenticator::CREDENTIAL_WAS_INVALID);
        if (time() > $user->accountCreatedAt +
                     Authenticator::ACTIVATION_KEY_EXPIRATION_SECS) {
        // 2.1 Avain vanhentunut, poista käyttäjä
            // @allow \Pike\PikeException
            if (!$this->persistence->deleteUserByUserId($user->id))
                throw new PikeException('Failed to delete stray user',
                                        PikeException::FAILED_DB_OP);
            throw new PikeException('Activation key had expired',
                                    Authenticator::KEY_HAD_EXPIRED);
        }
        // 3. Ok, aktivoi tili
        $user->activationKey = null;
        $user->accountStatus = Authenticator::ACCOUNT_STATUS_ACTIVATED;
        // @allow \Pike\PikeException
        if (!$this->persistence->updateUserByUserId($user,
            ['activationKey', 'accountStatus'], $user->id))
            throw new PikeException('Failed to clear activation info',
                                    PikeException::FAILED_DB_OP);
    }
    /**
     * @param string $usernameOrEmail
     * @param callable(\Pike\Entities\User $user, string $resetKey, object $emailSettings): void $makeEmailSettings
     * @throws \Pike\PikeException
     */
    public function requestPasswordReset(string $usernameOrEmail,
                                         callable $makeEmailSettings): void {
        // @allow \Pike\PikeException
        $user = $this->persistence->getUserByColumn('usernameOrEmail', $usernameOrEmail);
        if (!$user)
            throw new PikeException('User not found or not activated',
                                    Authenticator::CREDENTIAL_WAS_INVALID);
        if ($user->accountStatus !== Authenticator::ACCOUNT_STATUS_ACTIVATED)
            throw new PikeException('Expected accountStatus to be ACTIVATED',
                                    Authenticator::ACCOUNT_STATUS_WAS_UNEXPECTED);
        // @allow \Pike\PikeException
        $key = $this->crypto->genRandomToken(32);
        // @allow \Pike\PikeException
        $emailSettings = $this->makeEmailSettings($makeEmailSettings, $user, $key);
        $mailer = $this->makeMailer();
        // @allow \Pike\PikeException
        $this->persistence->runInTransaction(function () use ($key,
                                                              $emailSettings,
                                                              $user,
                                                              $mailer) {
            $user->resetKey = $key;
            $user->resetRequestedAt = time();
            $user->loginId = null;
            $user->loginIdValidatorHash = null;
            $user->loginData = null;
            // @allow \Pike\PikeException
            if (!$this->persistence->updateUserByUserId($user, ['resetKey', 'resetRequestedAt',
                'loginId', 'loginIdValidatorHash' ,'loginData'], $user->id))
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
    }
    /**
     * @param string $key
     * @param string $email
     * @param string $newPassword
     * @throws \Pike\PikeException
     */
    public function finalizePasswordReset(string $key,
                                          string $email,
                                          string $newPassword): void {
        // 1. Hae resetointidata tietokannasta
        // @allow \Pike\PikeException
        $user = $this->persistence->getUserByColumn('resetKey', $key);
        // 2. Validoi avain ja email
        if (!$user || $user->email !== $email)
            throw new PikeException('Invalid reset credential',
                                    Authenticator::CREDENTIAL_WAS_INVALID);
        if (time() > $user->resetRequestedAt +
                     Authenticator::RESET_KEY_EXPIRATION_SECS) {
            throw new PikeException('Reset key had expired',
                                    Authenticator::KEY_HAD_EXPIRED);
        }
        // 3. Päivitä uusi salasana + tyhjennä resetointiData
        // @allow \Pike\PikeException
        $user->passwordHash = $this->crypto->hashPass($newPassword);
        $user->resetKey = null;
        $user->resetRequestedAt = 0;
        // @allow \Pike\PikeException
        if (!$this->persistence->updateUserByUserId($user, ['passwordHash', 'resetKey', 'resetRequestedAt'], $user->id))
            throw new PikeException('Failed to clear reset info',
                                    PikeException::FAILED_DB_OP);
    }
    /**
     * @param string $userId
     * @param string $newPassword
     * @throws \Pike\PikeException
     */
    public function updatePassword(string $userId, string $newPassword): void {
        throw new PikeException('Not implemented yo');
    }
    /**
     * @param callable(\Pike\Entities\User $user, string $activationOrResetKey, object $emailSettings): void $userDefinedMakeEmailSettings
     * @param \Pike\Entities\User $user
     * @param string $resetKey
     * @return \stdClass
     * @throws \Pike\PikeException
     */
    private function makeEmailSettings(callable $userDefinedMakeEmailSettings,
                                       User $user,
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
    /**
     * @return \Pike\Interfaces\MailerInterface
     */
    private function makeMailer(): MailerInterface {
        return !$this->makeMailerFn
            ? new PhpMailerMailer
            : call_user_func($this->makeMailerFn);
    }
}
