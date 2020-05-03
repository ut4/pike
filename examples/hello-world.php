<?php

// Katso myös: /docs/fi/examples/hello-world.md

$loader = require dirname(__DIR__) . '/vendor/autoload.php';
$loader->addPsr4('Me\\HelloWorld\\', __DIR__ . '/HelloWorld/src');

$myModules = [\Me\HelloWorld\SomeModule::class];
$app = \Pike\App::create($myModules);

$req = \Pike\Request::createFromGlobals('', $_GET['q'] ?? '/');
$app->handleRequest($req);
