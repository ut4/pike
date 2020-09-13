<?php

declare(strict_types=1);

namespace Pike\Auth\Internal;

use Pike\{AbstractMailer, App, AppContext, NativeSession, PhpMailerMailer, SessionInterface};
use Pike\Auth\Crypto;

/**
 * Tarjoilee Authenticator-luokalle sen tarvitsemia palveluja.
 */
class CachingServicesFactory {
    /** @var ?\Pike\SessionInterface */
    private $session;
    /** @var ?\Pike\AbstractMailer */
    private $mailer;
    /** @var ?\Pike\Auth\Internal\AuthService */
    private $authService;
    /** @var ?\Pike\Auth\Internal\CookieManager */
    private $cookieManager;
    /** @var callable */
    private $makeUserRepositoryFn;
    /** @var ?callable */
    private $makeRememberMeFn;
    /** @var callable */
    private $makeMailerFn;
    /** @var \Pike\AppContext */
    private $ctx;
    /**
     * @param \Pike\AppContext $ctx
     * @param ?callable $makeUserRepositoryFn = null fn(\Pike\AppContext $ctx, \Pike\Auth\Internal\CachingServicesFactory $self): \Pike\Auth\AbstractUserRepository
     * @param callable|string|null $makeRememberMeFn = \Pike\App::MAKE_AUTOMATICALLY '@auto'|fn(\Pike\AppContext $ctx, \Pike\Auth\Internal\CachingServicesFactory $self): \Pike\Auth\Internal\DefaultRememberMe
     * @param ?callable $makeMailer = null fn(\Pike\AppContext $ctx, \Pike\Auth\Internal\CachingServicesFactory $self): \Pike\AbstractMailer
     */
    public function __construct(AppContext $ctx,
                                ?callable $makeUserRepositoryFn = null,
                                $makeRememberMeFn = App::MAKE_AUTOMATICALLY,
                                ?callable $makeMailerFn = null) {
        $this->ctx = $ctx;
        $this->makeUserRepositoryFn = $makeUserRepositoryFn
            ? $makeUserRepositoryFn
            : function (/*$ctx, $self*/): DefaultUserRepository {
                return new DefaultUserRepository($this->ctx->db);
            };
        $this->makeMailerFn = $makeMailerFn
            ? $makeMailerFn
            : function (/*$ctx, $self*/) {
                return new PhpMailerMailer;
            };
        if ($makeRememberMeFn === null)
            return;
        $this->makeRememberMeFn = is_callable($makeRememberMeFn)
            ? $makeRememberMeFn
            : function (/*$ctx, $self*/): DefaultRememberMe {
                return new DefaultRememberMe(
                    call_user_func($this->makeUserRepositoryFn, $this->ctx, $this),
                    $this->makeCookieManager(),
                    $this->makeCrypto());
            };
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
     * @return \Pike\AbstractMailer
     */
    public function makeMailer(): AbstractMailer {
        if (!$this->mailer) {
            $this->mailer = call_user_func($this->makeMailerFn, $this->ctx, $this);
        }
        return $this->mailer;
    }
    /**
     * @return \Pike\Auth\Internal\AuthService
     */
    public function makeAuthService(): AuthService {
        if (!$this->authService) {
            $this->authService = new AuthService(call_user_func($this->makeUserRepositoryFn, $this->ctx, $this),
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
    /**
     * @return ?\Pike\Auth\Internal\DefaultRememberMe
     */
    public function makeRememberMe(): ?DefaultRememberMe {
        return $this->makeRememberMeFn
            ? call_user_func($this->makeRememberMeFn, $this->ctx, $this)
            : null;
    }
    /**
     * @return \Pike\Auth\Internal\CookieManager
     */
    public function makeCookieManager(): CookieManager {
        if (!$this->cookieManager) {
            $this->cookieManager = new CookieManager($this->ctx);
        }
        return $this->cookieManager;
    }
}
