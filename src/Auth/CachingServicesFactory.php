<?php

namespace Pike\Auth;

use Pike\Db;
use Pike\NativeSession;

/**
 * Tarjoilee Authenticator-luokalle sen tarvitsemia palveluja.
 */
class CachingServicesFactory {
    private $db;
    private $userRepo;
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
    public function makeUserRepo() {
        if (!$this->userRepo) {
            $this->userRepo = new UserRepository($this->db);
        }
        return $this->userRepo;
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
     * @return null
     */
    public function makeMailer() {
        throw new \Exception('Not implemented yet');
    }
}