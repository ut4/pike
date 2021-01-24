<?php

declare(strict_types=1);

namespace Me\Pike101;

use Pike\AppContext;

final class SomeModule {
    /**
     * @param \Pike\AppContext $ctx
     */
    public function init(AppContext $ctx): void {
        $ctx->router->map('GET', '/some-route',
            [SomeController::class, 'handleSomeRoute']
        );
        $ctx->router->map('POST', '/another-route/[*:someParam]',
            [SomeController::class, 'handleAnotherRoute']
        );
    }
}
