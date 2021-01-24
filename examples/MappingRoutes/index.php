<?php

// Katso myös: ut4.github.io/pike/examples/mapping-routes.html

$loader = require dirname(__DIR__, 2) . '/vendor/autoload.php';
$loader->addPsr4('Me\\MappingRoutes\\', __DIR__ . '/src');

$myModules = [new \Me\MappingRoutes\Module];
$app = new \Pike\App($myModules);

$req = \Pike\Request::createFromGlobals($_GET['q'] ?? '/');
$app->handleRequest($req);
