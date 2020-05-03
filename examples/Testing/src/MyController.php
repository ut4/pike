<?php

namespace Me\Testing;

use Pike\Response;

class MyController {
    public function handleSomeRoute(Response $res): void {
        $res->json((object) ['message' => 'foo']);
    }
    public function handleAnotherRoute(Response $res): void {
        $res->json((object) ['message' => 'bar']);
    }
}
