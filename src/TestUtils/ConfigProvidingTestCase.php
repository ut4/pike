<?php

namespace Pike\TestUtils;

use PHPUnit\Framework\TestCase;

abstract class ConfigProvidingTestCase extends TestCase {
    /** @var array */
    private static $config = [];
    /**
     */
    protected function setUp(): void {
        parent::setUp();
        self::setGetConfig();
    }
    /**
     * @param array $config
     */
    protected static function setConfig(array $config): void {
        self::$config = $config;
    }
    /**
     * @return array
     * @throws \RuntimeException
     */
    protected static function setGetConfig(): array {
        if (!self::$config) {
            if (!defined('TEST_CONFIG_DIR_PATH') ||
                !is_file(TEST_CONFIG_DIR_PATH . 'config.php'))
                throw new \RuntimeException('Expected TEST_CONFIG_DIR_PATH . ' .
                    '\'config.php\' to exist.');
            if (!is_array((self::$config = require TEST_CONFIG_DIR_PATH . 'config.php')))
                throw new \RuntimeException('Expected TEST_CONFIG_DIR_PATH . \'config.php\' ' .
                    'to return an array.');
        }
        return self::$config;
    }
}
