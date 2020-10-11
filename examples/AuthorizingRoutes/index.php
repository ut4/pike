<?php

// Katso myös: ut4.github.io/pike/examples/authorizing-routes.html

$loader = require dirname(__DIR__, 2) . '/vendor/autoload.php';
$loader->addPsr4('Me\\AuthorizingRoutes\\', __DIR__ . '/src');

define('LOGGED_IN_USER_ROLE', \Pike\Auth\ACL::ROLE_EDITOR);

$myModules = [\Me\AuthorizingRoutes\MyAuthModule::class,
              \Me\AuthorizingRoutes\Product\ProductModule::class,
              \Me\AuthorizingRoutes\Review\ReviewModule::class];
$app = \Pike\App::create($myModules);

$req = \Pike\Request::createFromGlobals($_GET['q'] ?? '/');
$app->handleRequest($req);
