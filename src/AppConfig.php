<?php

declare(strict_types=1);

namespace Pike;

use Pike\PikeException;

/**
 * Wräppää config.php:n, voidaan injektoida kontrollereihin.
 */
class AppConfig {
    /** @var object */
    private $vals;
    /**
     * @param object|array $vals
     */
    public function __construct($vals) {
        // @allow \Pike\PikeException
        $this->setVals($vals);
    }
    /**
     * @param object|array $config
     * @throws \Pike\PikeException
     */
    public function setVals($config): void {
        if (is_object($config))
            $this->vals = $config;
        elseif (is_array($config))
            $this->vals = (object) $config;
        else
            throw new PikeException('$config must be object|array',
                                    PikeException::BAD_INPUT);
    }
    /**
     * @return object
     */
    public function getVals(): object {
        return $this->vals;
    }
    /**
     * @param string $key
     * @param mixed $default = null
     * @return mixed|null
     */
    public function get(string $key, $default = null) {
        return $this->vals->$key ?? $default;
    }
}
