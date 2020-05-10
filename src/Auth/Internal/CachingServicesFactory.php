<?php

declare(strict_types=1);

namespace Pike\Auth\Internal;

use Pike\Db;
use Pike\NativeSession;
use Pike\Auth\Crypto;
use Pike\SessionInterface;

/**
 * Tarjoilee Authenticator-luokalle sen tarvitsemia palveluja.
 */
class CachingServicesFactory {
    private $session;
    private $mailer;
    private $authService;
    private $makeUserRepositoryFn;
    /**
     * @param callable $makeUserRepositoryFn
     * @param \Pike\Auth\Internal\AbstractMailer $mailer = null
     */
    public function __construct(callable $makeUserRepositoryFn,
                                AbstractMailer $mailer = null) {
        $this->makeUserRepositoryFn = $makeUserRepositoryFn;
        $this->mailer = $mailer;
    }
    /**
     * @return \Pike\NativeSession
     */
    public function makeSession(): SessionInterface {
        if (!$this->session) {
            $this->session = new NativeSession();
        }
        return $this->session;
    }
    /**
     * @return \Pike\Auth\Internal\AbstractMailer
     */
    public function makeMailer(): AbstractMailer {
        if (!$this->mailer) {
            $this->mailer = new PhpMailerMailer();
        }
        return $this->mailer;
    }
    /**
     * @return \Pike\Auth\Internal\AuthService
     */
    public function makeAuthService(): AuthService {
        if (!$this->authService) {
            $this->authService = new AuthService(call_user_func($this->makeUserRepositoryFn),
                                                 $this->makeCrypto());
        }
        return $this->authService;
    }
    /**
     * @return \Pike\Auth\Crypto
     */
    public function makeCrypto(): Crypto {
        return new Crypto;
    }
}
