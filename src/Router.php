<?php

declare(strict_types=1);

namespace Pike;

use AltoRouter;

class Router extends AltoRouter {
    public $middleware = [];
    /**
     * @param string $pattern
     * @param callable $fn
     */
    public function on(string $pattern,
                       callable $fn): void {
        if ($pattern !== '*')
            throw new \Exception('Patterns other than '*' not implemented');
        $this->middleware[] = (object)['pattern' => $pattern, 'fn' => $fn];
    }
}
