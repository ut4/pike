<?php

declare(strict_types=1);

namespace Me\MappingRoutes;

use Pike\{Request, Response};

class Controller {
    public function handleRouteA(Request $req, Response $res): void {
        $res->json((object) [
            'params' => $req->params,
            'body' => $req->body,
            'routeInfo' => $req->routeInfo,
        ]);
    }
    public function handleRouteB(Request $req, Response $res): void {
        $this->handleRouteA($req, $res);
    }
    public function handleRouteC(Request $req, Response $res): void {
        $this->handleRouteA($req, $res);
    }
    public function handleRouteD(Request $req, Response $res): void {
        $this->handleRouteA($req, $res);
    }
}
