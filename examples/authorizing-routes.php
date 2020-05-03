<?php

// Katso myös: /docs/fi/examples/authorizing-routes.md

$loader = require dirname(__DIR__) . '/vendor/autoload.php';
$loader->addPsr4('Me\\AuthorizingRoutes\\', __DIR__ . '/AuthorizingRoutes/src');

define('LOGGED_IN_USER_ROLE', \Pike\Auth\ACL::ROLE_EDITOR);

$myModules = [\Me\AuthorizingRoutes\MyAuthModule::class,
              \Me\AuthorizingRoutes\Product\ProductModule::class,
              \Me\AuthorizingRoutes\Review\ReviewModule::class];
$app = \Pike\App::create($myModules);

$req = \Pike\Request::createFromGlobals('', $_GET['q'] ?? '/');
$app->handleRequest($req);
