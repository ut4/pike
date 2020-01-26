<?php

require dirname(__DIR__) . '/vendor/autoload.php';

class SomeModule {
    public static function init(\stdClass $ctx) {
        $ctx->router->map('GET', '/protected',
            [SomeController::class, 'handleProtectdRequest', true]
        );
        $ctx->router->map('GET', '/[**:stuff]',
            [SomeController::class, 'handleSomeRequest', false]
        );
    }
}

class SomeController {
    public function handleProtectdRequest(\Pike\Response $res) {
        $res->json(['message' => 'Hello <authenticateduser>']);
    }
    public function handleSomeRequest(\Pike\Request $req, \Pike\Response $res) {
        $res->json(['hello' => $req->params->stuff]);
    }
}

$app = \Pike\App::create([SomeModule::class], []);
$app->handleRequest(\Pike\Request::createFromGlobals('', $_GET['q'] ?? '/'));
