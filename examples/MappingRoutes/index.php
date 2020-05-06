<?php

// Katso myös: ut4.github.io/pike/examples/mapping-routes.html

$loader = require dirname(__DIR__, 2) . '/vendor/autoload.php';
$loader->addPsr4('Me\\MappingRoutes\\', __DIR__ . '/src');

$myModules = [\Me\MappingRoutes\Module::class];
$app = \Pike\App::create($myModules);

$req = \Pike\Request::createFromGlobals('', $_GET['q'] ?? '/');
$app->handleRequest($req);
