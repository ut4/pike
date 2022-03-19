<?php

declare(strict_types=1);

namespace Pike\Auth;

use Pike\Auth\Internal\ServicesFactory;
use Pike\Entities\User;
use Pike\Interfaces\SessionInterface;
use Pike\PikeException;

/**
 * Autentikaatiomoduulin julkinen API: sisältää metodit kuten login() ja
 * getIdentity(). Käyttäjänhallintatoiminnallisuudet löytyy $auth->
 * getAccountManager() -luokasta.
 */
final class Authenticator {
    public const ACTIVATION_KEY_EXPIRATION_SECS = 60 * 60 * 24;
    public const RESET_KEY_EXPIRATION_SECS = 60 * 60 * 2;
    //
    public const ACCOUNT_STATUS_ACTIVATED   = 0;
    public const ACCOUNT_STATUS_UNACTIVATED = 1;
    public const ACCOUNT_STATUS_BANNED      = 2;
    //
    public const CREDENTIAL_WAS_INVALID = 201010;
    public const USER_ALREADY_EXISTED   = 201011;
    public const FAILED_TO_FORMAT_MAIL  = 201012;
    public const FAILED_TO_SEND_MAIL    = 201013;
    public const KEY_HAD_EXPIRED        = 201014;
    public const ACCOUNT_STATUS_WAS_UNEXPECTED = 201015;
    /** @var \Pike\Auth\Internal\ServicesFactory */
    private $services;
    /** @var ?string */
    private $userRoleCookieName;
    /**
     * @param callable(\Pike\Auth\Internal\ServicesFactory): \Pike\Interfaces\UserRepositoryInterface $makeUserRepositoryFn
     * @param callable(\Pike\Auth\Internal\ServicesFactory): \Pike\Interfaces\SessionInterface $makeSessionFn
     * @param callable(\Pike\Auth\Internal\ServicesFactory): \Pike\Auth\Interfaces\CookieStorageInterface $makeCookieStorageFn
     * @param string $userRoleCookieName = 'maybeLoggedInUserRole'
     * @param bool $doUseRememberMe = true
     * @param ?\Pike\Auth\Crypto $crypto = null
     */
    public function __construct(callable $makeUserRepositoryFn,
                                callable $makeSessionFn,
                                callable $makeCookieStorageFn,
                                string $userRoleCookieName = 'maybeLoggedInUserRole',
                                bool $doUseRememberMe = true,
                                Crypto $crypto = null) {
        $this->services = new ServicesFactory($makeUserRepositoryFn,
                                              $makeSessionFn,
                                              $makeCookieStorageFn,
                                              $doUseRememberMe,
                                              $crypto);
        $this->userRoleCookieName = strlen($userRoleCookieName) ? $userRoleCookieName : null;
    }
    /**
     * @param string $usernameOrEmail
     * @param string $password
     * @param ?callable $convertUserToSessionData = null fn(\Pike\Entities\User): object
     * @throws \Pike\PikeException
     */
    public function login(string $usernameOrEmail,
                          string $password,
                          ?callable $convertUserToSessionData = null): void {
        // @allow \Pike\PikeException
        $user = $this->services->makeUserRepository()
            ->getUserByColumn('usernameOrEmail', $usernameOrEmail);
        if (!$user)
            throw new PikeException('User not found or not activated',
                                    Authenticator::CREDENTIAL_WAS_INVALID);
        if (!$this->services->makeCrypto()->verifyPass($password, $user->passwordHash))
            throw new PikeException('Invalid password',
                                    Authenticator::CREDENTIAL_WAS_INVALID);
        if ($user->accountStatus !== Authenticator::ACCOUNT_STATUS_ACTIVATED)
            throw new PikeException('Expected accountStatus to be ACTIVATED',
                                    Authenticator::ACCOUNT_STATUS_WAS_UNEXPECTED);
        // @allow \Pike\PikeException
        $this->putUserToSession($user, $convertUserToSessionData);
    }
    /**
     * @param string $userId
     * @param ?callable $convertUserToSessionData = null fn(\Pike\Entities\User): object
     * @throws \Pike\PikeException
     */
    public function loginByUserId(string $userId,
                                  ?callable $convertUserToSessionData = null): void {
        // @allow \Pike\PikeException
        $user = $this->services->makeUserRepository()->getUserByColumn('id', $userId);
        if (!$user)
            throw new PikeException('User not found or not activated',
                                    Authenticator::CREDENTIAL_WAS_INVALID);
        if ($user->accountStatus !== Authenticator::ACCOUNT_STATUS_ACTIVATED)
            throw new PikeException('Expected accountStatus to be ACTIVATED',
                                    Authenticator::ACCOUNT_STATUS_WAS_UNEXPECTED);
        // @allow \Pike\PikeException
        $this->putUserToSession($user, $convertUserToSessionData);
    }
    /**
     * @return mixed|null
     * @throws \Pike\PikeException
     */
    public function getIdentity() {
        if (($data = $this->getAndOpenSession()->get('user')) ||
            !($rememberMe = $this->services->makeRememberMe()))
            return $data;
        if (($serializedSessionData = $rememberMe->getLogin())) {
            $sessionData = unserialize($serializedSessionData);
            $this->getAndOpenSession()->put('user', $sessionData);
            return $sessionData;
        }
        return null;
    }
    /**
     * @throws \Pike\PikeException
     */
    public function logout(): void {
        if ($this->userRoleCookieName)
            $this->services->makeCookieManager()->addClearCookieConfig($this->userRoleCookieName);
        if (($rememberMe = $this->services->makeRememberMe()))
            // @allow \Pike\PikeException
            $rememberMe->clearLogin();
        $this->getAndOpenSession()->destroy();
    }
    /**
     * @param ?callable(): \Pike\Interfaces\MailerInterface $makeMailerFn = null
     * @return \Pike\Auth\AccountManager
     */
    public function getAccountManager(callable $makeMailerFn = null): AccountManager {
        return new AccountManager($this->services->makeUserRepository(),
                                  $this->services->makeCrypto(),
                                  $makeMailerFn);
    }
    /**
     * @return string
     */
    public function getPerSessionCsrfToken(): string {
        return $this->getAndOpenSession()->get('csrfToken') ??
            $this->issuePerSessionCsrfToken();
    }
    /**
     * @return string
     */
    public function issuePerSessionCsrfToken(): string {
        $token = $this->services->makeCrypto()->genRandomToken();
        $this->getAndOpenSession()->put('csrfToken', $token);
        return $token;
    }
    /**
     */
    public function postProcess(): void {
        if ($this->userRoleCookieName || $this->services->makeRememberMe())
            $this->services->makeCookieManager()->commitCookieConfigs();
    }
    /**
     * @return \Pike\Interfaces\SessionInterface
     */
    private function getAndOpenSession(): SessionInterface {
        $sess = $this->services->makeSession();
        $sess->start();
        return $sess;
    }
    /**
     * @param \Pike\Entities\User $user
     * @param ?callable $convertUserToSessionData = null fn(\Pike\Entities\User): object
     */
    private function putUserToSession(User $user,
                                      ?callable $convertUserToSessionData = null): void {
        $sessionData = $convertUserToSessionData
            ? call_user_func($convertUserToSessionData, $user)
            : (object) ['id' => $user->id];
        if ($sessionData === null)
            throw new PikeException('convertUserToSessionData mustn\'t return null',
                                    PikeException::BAD_INPUT);
        //
        $this->getAndOpenSession()->put('user', $sessionData);
        //
        if ($this->userRoleCookieName)
            $this->services->makeCookieManager()
                ->addCookieConfig($this->userRoleCookieName, strval($user->role));
        //
        if ($rememberMe = $this->services->makeRememberMe())
            // @allow \Pike\PikeException
            $rememberMe->putLogin($user->id, serialize($sessionData));
    }
}
