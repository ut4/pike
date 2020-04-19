<?php

declare(strict_types=1);

namespace Me\Testing;

abstract class SomeModule {
    /**
     * @param \stdClass $ctx {\Pike\Router router}
     */
    public static function init(\stdClass $ctx) {
        $ctx->router->map('GET', '/some-route',
            [MyController::class, 'handleSomeRoute']
        );
        $ctx->router->map('GET', '/another-route',
            [MyController::class, 'handleAnotherRoute']
        );
    }
}
