<?php

namespace Pike\Auth;

use Pike\Db;
use Pike\NativeSession;

/**
 * Tarjoilee Authenticator-luokalle sen tarvitsemia palveluja.
 */
class CachingServicesFactory {
    private $db;
    private $userRepository;
    private $session;
    private $mailer;
    /**
     * @param \Pike\Db $db
     */
    public function __construct(Db $db) {
        $this->db = $db;
    }
    /**
     * @return \Pike\Auth\UserRepository
     */
    public function makeUserRepository() {
        if (!$this->userRepository) {
            $this->userRepository = new UserRepository($this->db);
        }
        return $this->userRepository;
    }
    /**
     * @return \Pike\Auth\AuthUserRepository
     */
    public function makeSession() {
        if (!$this->session) {
            $this->session = new NativeSession();
        }
        return $this->session;
    }
    /**
     * @return \Pike\Auth\PhpMailerMailer
     */
    public function makeMailer() {
        if (!$this->mailer) {
            $this->mailer = new PhpMailerMailer();
        }
        return $this->mailer;
    }
}