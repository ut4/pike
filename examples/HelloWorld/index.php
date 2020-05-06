<?php

// Katso myös: ut4.github.io/pike/examples/hello-world.html

$loader = require dirname(__DIR__, 2) . '/vendor/autoload.php';
$loader->addPsr4('Me\\HelloWorld\\', __DIR__ . '/src');

$myModules = [\Me\HelloWorld\SomeModule::class];
$app = \Pike\App::create($myModules);

$req = \Pike\Request::createFromGlobals('', $_GET['q'] ?? '/');
$app->handleRequest($req);
