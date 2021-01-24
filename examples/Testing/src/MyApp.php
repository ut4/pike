<?php

declare(strict_types=1);

namespace Me\Testing;

use Pike\{App, AppContext};

final class MyApp {
    /**
     * @return \Pike\App
     */
    public static function create(): App {
        return new App([
            new SomeModule,
            // muut moduulit tänne
        ]);
    }
}
