<?php

define('TEST_CONFIG_DIR_PATH', __DIR__ . '/');

$loader = require dirname(__DIR__) . '/vendor/autoload.php';
$loader->addPsr4('Pike\\Tests\\', __DIR__);
