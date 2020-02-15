<?php

namespace Pike\TestUtils;

use PHPUnit\Framework\TestCase;

abstract class ConfigProvidingTestCase extends TestCase {
    /**
     * @return array
     */
    protected static function getAppConfig() {
        if (defined('TEST_CONFIG_DIR_PATH'))
            return require TEST_CONFIG_DIR_PATH . 'config.php';
        throw new \RuntimeException('Not implemented');
    }
}
