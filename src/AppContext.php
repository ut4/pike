<?php

declare(strict_types=1);

namespace Pike;

class AppContext {
    /** @var \Pike\Router */
    public $router;
    /** @var \Pike\AppConfig */
    public $config;
    /** @var \Pike\Request */
    public $req;
    /** @var \Pike\Response */
    public $res;
    /** @var ?\Pike\Db */
    public $db;
    /** @var ?\Pike\Auth\Authenticator */
    public $auth;
}
