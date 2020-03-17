<?php

namespace Pike\Auth\Internal;

use Pike\Db;
use Pike\NativeSession;
use Pike\Auth\Crypto;

/**
 * Tarjoilee Authenticator-luokalle sen tarvitsemia palveluja.
 */
class CachingServicesFactory {
    private $db;
    private $session;
    private $mailer;
    private $authService;
    /**
     * @param \Pike\Db $db
     * @param \Pike\Auth\PhpMailerMailer $mailer = null
     */
    public function __construct(Db $db, PhpMailerMailer $mailer = null) {
        $this->db = $db;
        $this->mailer = $mailer;
    }
    /**
     * @return \Pike\NativeSession
     */
    public function makeSession() {
        if (!$this->session) {
            $this->session = new NativeSession();
        }
        return $this->session;
    }
    /**
     * @return \Pike\Auth\Internal\PhpMailerMailer
     */
    public function makeMailer() {
        if (!$this->mailer) {
            $this->mailer = new PhpMailerMailer();
        }
        return $this->mailer;
    }
    /**
     * @return \Pike\Auth\Internal\AuthService
     */
    public function makeAuthService() {
        if (!$this->authService) {
            $this->authService = new AuthService(new UserRepository($this->db),
                                                 $this->makeCrypto());
        }
        return $this->authService;
    }
    /**
     * @return \Pike\Auth\Crypto
     */
    public function makeCrypto() {
        return new Crypto;
    }
}
