<?php

// Katso myös: ut4.github.io/pike/examples/authorizing-routes.html

$loader = require dirname(__DIR__, 2) . '/vendor/autoload.php';
$loader->addPsr4('Me\\AuthorizingRoutes\\', __DIR__ . '/src');

define('LOGGED_IN_USER_ROLE', \Pike\Auth\ACL::ROLE_EDITOR);

$myModules = [new \Me\AuthorizingRoutes\MyAuthModule,
              new \Me\AuthorizingRoutes\Product\ProductModule,
              new \Me\AuthorizingRoutes\Review\ReviewModule];
$app = new \Pike\App($myModules);

$req = \Pike\Request::createFromGlobals($_GET['q'] ?? '/');
$app->handleRequest($req);
