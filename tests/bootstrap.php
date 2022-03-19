<?php

define('PIKE_TEST_CONFIG_FILE_PATH', __DIR__ . '/' . 'config.php');

$loader = require dirname(__DIR__) . '/vendor/autoload.php';
$loader->addPsr4('Pike\\Tests\\', __DIR__);
