<?php

declare(strict_types=1);

namespace Me\Pike101;

use Pike\{Request, Response};

class SomeController {
    /**
     * @param \Me\Pike101\SomeClass $myClass
     * @param \Pike\Response $res
     */
    public function handleSomeRoute(SomeClass $myClass, Response $res): void {
        $data = $myClass->doSomething();
        if ($data)
            $res->json([$data]);
        else
            $res->status(500)->json(['err' => 1]);
    }
    /**
     * @param \Pike\Request $req
     * @param \Pike\Response $res
     */
    public function handleAnotherRoute(Request $req, Response $res): void {
        $res->json(['yourParamWas' => $req->params->someParam,
                    'requestBodyWas' => $req->body]);
    }
}
