<?php

declare(strict_types=1);

namespace Me\HelloWorld;

abstract class SomeModule {
    /**
     * @param \stdClass $ctx {\Pike\Router router}
     */
    public static function init(\stdClass $ctx) {
        $ctx->router->map('GET', '/some-route',
            [SomeController::class, 'handleSomeRoute']
        );
        $ctx->router->map('POST', '/another-route/[*:someParam]',
            [SomeController::class, 'handleAnotherRoute']
        );
    }
}
