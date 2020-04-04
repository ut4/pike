<?php

declare(strict_types=1);

namespace Me\MappingRoutes;

use Pike\Response;
use Pike\Request;

class Controller {
    public function handleRouteA(Request $req, Response $res): void {
        $res->json((object) [
            'params' => $req->params,
            'body' => $req->body,
            'routeCtx' => $req->routeCtx, 
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
