<?php

declare(strict_types=1);

namespace Me\Testing;

use Pike\App;
use Pike\AppContext;

abstract class MyApp {
    /**
     * @param array<string, mixed> $config
     * @param \Pike\AppContext $ctx
     * @param callable $makeInjector = null
     * @return \Pike\App
     */
    public static function create(array $config,
                                  AppContext $ctx,
                                  callable $makeInjector = null): App {
        return App::create([
            SomeModule::class
            // muut moduulit tänne
        ], $config, $ctx, $makeInjector);
    }
}
