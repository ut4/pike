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
    private $crypto;
    private $session;
    private $mailer;
    private $userManager;
    /**
     * @param \Pike\Db $db
     * @param \Pike\Auth\Crypto $crypto
     * @param \Pike\Auth\PhpMailerMailer $mailer = null
     */
    public function __construct(Db $db, Crypto $crypto, PhpMailerMailer $mailer = null) {
        $this->db = $db;
        $this->crypto = $crypto;
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
     * @return \Pike\Auth\Internal\UserManager
     */
    public function makeUserManager() {
        if (!$this->userManager) {
            $this->userManager = new UserManager(new UserRepository($this->db),
                                                 $this->crypto,
                                                 $this);
        }
        return $this->userManager;
    }
}
