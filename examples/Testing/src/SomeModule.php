<?php

declare(strict_types=1);

namespace Me\Testing;

use Pike\AppContext;

abstract class SomeModule {
    /**
     * @param \Pike\AppContext $ctx
     */
    public static function init(AppContext $ctx): void {
        $ctx->router->map('GET', '/some-route',
            [MyController::class, 'handleSomeRoute']
        );
        $ctx->router->map('GET', '/another-route',
            [MyController::class, 'handleAnotherRoute']
        );
    }
}
