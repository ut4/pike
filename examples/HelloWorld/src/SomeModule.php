<?php

declare(strict_types=1);

namespace Me\HelloWorld;

use Pike\AppContext;

abstract class SomeModule {
    /**
     * @param \Pike\AppContext $ctx
     */
    public static function init(AppContext $ctx) {
        $ctx->router->map('GET', '/some-route',
            [SomeController::class, 'handleSomeRoute']
        );
        $ctx->router->map('POST', '/another-route/[*:someParam]',
            [SomeController::class, 'handleAnotherRoute']
        );
    }
}
