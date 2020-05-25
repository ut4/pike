<?php

declare(strict_types=1);

namespace Pike;

/**
 * Luokka, jolla voi kustomoida mm. applikaation "init"-vaihessa käytetyt, ja
 * Auryn\Containeriin asetettavat palvelut (db, auth jne.).
 */
class AppContext {
    /** @var \Pike\Router */
    public $router;
    /** @var \Pike\AppConfig */
    public $appConfig;
    /** @var \Pike\Db|null */
    public $db;
    /** @var \Pike\Auth\Authenticator|null */
    public $auth;
    /** @var \Pike\Auth\ACL|null */
    public $acl;
    /** @var array<string, string> */
    public $serviceHints;
    /**
     * @param array<string, string> $serviceHints = []
     */
    public function __construct(array $serviceHints = []) {
        $this->serviceHints = $serviceHints;
    }
}
