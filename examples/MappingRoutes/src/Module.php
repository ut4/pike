<?php

declare(strict_types=1);

namespace Me\MappingRoutes;

use Pike\AppContext;

abstract class Module {
    /**
     * @param \Pike\AppContext $ctx
     */
    public static function init(AppContext $ctx): void {
        $ctx->router->map('GET', '/route-a',
            [Controller::class, 'handleRouteA']
        );
        $ctx->router->map('GET', '/route-b/[i:myNumber]/[w:myOptionalSlug]?',
            [Controller::class, 'handleRouteB']
        );
        $ctx->router->map('GET', '/route-c/[foo|bar:fooOrBar]',
            [Controller::class, 'handleRouteC'],
            'nameOfRouteC'
        );
        $ctx->router->map('POST', '/route-d/[i:id]',
            [Controller::class, 'handleRouteC', ['my' => 'context']]
        );
    }
}
