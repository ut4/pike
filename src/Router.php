<?php

namespace Pike;

use AltoRouter;

class Router extends AltoRouter {
    public $middleware = [];
    /**
     * @param string $pattern
     * @param callable $fn
     */
    public function on($pattern, callable $fn) {
        if ($pattern !== '*')
            throw new \Exception('Patterns other than '*' not implemented');
        $this->middleware[] = (object)['pattern' => $pattern, 'fn' => $fn];
    }
}
