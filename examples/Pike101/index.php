<?php

// Katso myös: ut4.github.io/pike/examples/basics.html

$loader = require dirname(__DIR__, 2) . '/vendor/autoload.php';
$loader->addPsr4('Me\\Pike101\\', __DIR__ . '/src');

$myModules = [new \Me\Pike101\SomeModule];
$app = new \Pike\App($myModules);

$req = \Pike\Request::createFromGlobals($_GET['q'] ?? '/');
$app->handleRequest($req);
