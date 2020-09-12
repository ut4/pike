<?php

declare(strict_types=1);

namespace Pike;

/**
 * Luokka, jolla voi kustomoida mm. applikaation "init"-vaihessa käytetyt, ja
 * Auryn\Containeriin asetettavat palvelut (db, auth jne.).
 */
class AppContext {
    /** @var array<string, string> */
    public $serviceHints;
    /** @var \Pike\Router */
    public $router;
    /** @var \Pike\AppConfig */
    public $appConfig;
    /** @var \Pike\Db */
    public $db;
    /** @var \Pike\Request */
    public $req;
    /** @var \Pike\Response */
    public $res;
    /** @var ?\Pike\Auth\Authenticator */
    public $auth;
    /** @var ?\Pike\Auth\ACL */
    public $acl;
    /**
     * @param array<string, string> $serviceHints = []
     */
    public function __construct(array $serviceHints = []) {
        $this->serviceHints = $serviceHints;
    }
}
