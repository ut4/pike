<?php

declare(strict_types=1);

namespace Pike\Auth;

use Pike\Entities\User;
use Pike\PikeException;

/**
 * Autentikaatiomoduulin julkinen API: sisältää metodit kuten login() ja
 * getIdentity(). Käyttäjänhallintatoiminnallisuudet löytyy $auth->
 * makeAccountManager() -luokasta.
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
    /** @var \Pike\Auth\ServicesFactory */
    private $services;
    /** @var ?string */
    private $userRoleCookieName;
    /**
     * @param callable(\Pike\Auth\ServicesFactory): \Pike\Interfaces\UserRepositoryInterface $makeUserRepositoryFn
     * @param callable(\Pike\Auth\ServicesFactory): \Pike\Interfaces\SessionInterface $makeSessionFn
     * @param callable(\Pike\Auth\ServicesFactory): \Pike\Auth\Interfaces\CookieStorageInterface $makeCookieStorageFn
     * @param string $userRoleCookieName = 'maybeLoggedInUserRole'
     * @param bool $doUseRememberMe = true
     * @param \Pike\Auth\Crypto $crypto = null
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
     * @param string $username
     * @param string $password
     * @param ?callable $serializeUserForSession = null
     * @throws \Pike\PikeException
     */
    public function login(string $username,
                          string $password,
                          ?callable $serializeUserForSession = null): void {
        // @allow \Pike\PikeException
        $user = $this->services->makeUserRepository()
            ->getUserByColumn('username', $username);
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
        $this->putUserToSession($user, $serializeUserForSession);
    }
    /**
     * @return ?object
     * @throws \Pike\PikeException
     */
    public function getIdentity(): ?object {
        if (($data = $this->services->makeSession()->get('user')) ||
            !($rememberMe = $this->services->makeRememberMe()))
            return $data;
        // todo rememberMe
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
        $this->services->makeSession()->destroy();
    }
    /**
     * @return \Pike\Auth\AccountManager
     */
    public function makeAccountManager(): AccountManager {
        return new AccountManager($this->services->makeUserRepository(),
                                  $this->services->makeCrypto());
    }
    /**
     */
    public function postProcess(): void {
        if ($this->userRoleCookieName)
            $this->services->makeCookieManager()->commitCookieConfigs();
    }
    /**
     * @param \Pike\Entities\User $user
     * @param ?callable $serializeUserForSession = null
     */
    private function putUserToSession(User $user,
                                      ?callable $serializeUserForSession = null): void {
        $sessionData = $serializeUserForSession
            ? call_user_func($serializeUserForSession, $user)
            : (object) ['id' => $user->id];
        $this->services->makeSession()->put('user', $sessionData);
        //
        if ($this->userRoleCookieName)
            $this->services->makeCookieManager()
                ->addCookieConfig($this->userRoleCookieName, strval($user->role));
        //
        if ($rememberMe = $this->services->makeRememberMe())
            // @allow \Pike\PikeException
            $rememberMe->putLogin($user, serialize($sessionData));
    }
}
