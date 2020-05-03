<?php

declare(strict_types=1);

namespace Me\MappingRoutes;

abstract class Module {
    /**
     * @param \stdClass $ctx {\Pike\Router router}
     */
    public static function init(\stdClass $ctx) {
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