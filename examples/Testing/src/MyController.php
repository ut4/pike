<?php

declare(strict_types=1);

namespace Me\Testing;

use Pike\Response;

class MyController {
    /**
     * @param \Pike\Response $res
     */
    public function handleSomeRoute(Response $res): void {
        $res->json((object) ['message' => 'foo']);
    }
    /**
     * @param \Pike\Response $res
     */
    public function handleAnotherRoute(Response $res): void {
        $res->json((object) ['message' => 'bar']);
    }
}
