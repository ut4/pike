<?php

declare(strict_types=1);

namespace Me\AuthorizingRoutes;

use Auryn\Injector;
use Pike\{AppContext, Auth\ACL};

final class MyAuthModule {
    /** @var \Pike\AppContext */
    private $ctx;
    /**
     * @param \Pike\AppContext $ctx
     */
    public function init(AppContext $ctx): void {
        $ctx->acl = new ACL();
        $ctx->acl->setRules(self::makeMyAclRules());
        //
        $ctx->router->on('*', function ($req, $res, $next) use ($ctx) {
            // Tämä tulisi normaalisti sessiosta ($ctx->auth->getIdentity()->role)
            $userRole = LOGGED_IN_USER_ROLE;
            // ks. Step 1
            [$action, $resource] = explode(':', $req->routeInfo->myCtx);
            if (!$ctx->acl->can($userRole, $action, $resource))
                $res->status(403)->json(['err' => 'Not permitted']);
            else
                $next();
        });
        //
        $this->ctx = $ctx;
    }
    /**
     * @return \stdClass
     */
    private static function makeMyAclRules(): \stdClass {
        // Nämä tulisi normaalisti esim. tiedostosta tai tietokannasta.
        $resources = (object) [
            'products' => (object) [
                'create'  => 1 << 1,
                'edit'    => 1 << 2,
                'comment' => 1 << 3,
            ],
            'reviews' => (object) [
                'post'            => 1 << 1,
                'approveOrReject' => 1 << 2,
            ]
        ];
        $userPermissions = (object) [
            ACL::ROLE_EDITOR => (object) [
                'products' => ACL::makePermissions(['comment', 'edit'], $resources->products),
                'reviews'  => ACL::makePermissions('*', $resources->reviews),
            ],
            ACL::ROLE_CONTRIBUTOR => (object) [
                'products' => ACL::makePermissions(['comment'], $resources->products),
                'reviews'  => ACL::makePermissions(['post'], $resources->reviews),
            ]
        ];
        return (object) [
            'resources' => $resources,
            'userPermissions' => $userPermissions
        ];
    }
    /**
     * @param \Auryn\Injector $container
     */
    public function alterDi(Injector $container) {
        $container->share($this->ctx->acl);
    }
}
