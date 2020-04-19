<?php

namespace Me\Testing;

use Pike\App;

abstract class MyApp {
    public static function create($config, $ctx, $makeInjector) {
        return App::create([
            SomeModule::class
            // muut moduulit tänne
        ], $config, $ctx, $makeInjector);
    }
}
