<?php

// Katso myös: ut4.github.io/pike/testing.html

$loader = require dirname(__DIR__, 3) . '/vendor/autoload.php';
$loader->addPsr4('Me\\Testing\\', dirname(__DIR__) . '/src');
$loader->addPsr4('Me\\Testing\\Tests\\', __DIR__);
