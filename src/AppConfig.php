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
     * @param object|array $vals
     * @throws \Pike\PikeException
     */
    public function setVals($vals): void {
        if (is_object($vals))
            $this->vals = $vals;
        elseif (is_array($vals))
            $this->vals = (object) $vals;
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
     * @return mixed
     */
    public function get(string $key, $default = null) {
        return $this->vals->$key ?? $default;
    }
}
