<?php

// Katso myös: /docs/fi/examples/mapping-routes.md

$loader = require dirname(__DIR__) . '/vendor/autoload.php';
$loader->addPsr4('Me\\MappingRoutes\\', __DIR__ . '/MappingRoutes/src');

$myModules = [\Me\MappingRoutes\Module::class];
$app = \Pike\App::create($myModules);

$req = \Pike\Request::createFromGlobals('', $_GET['q'] ?? '/');
$app->handleRequest($req);
