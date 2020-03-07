<?php

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
    public function setVals($vals) {
        if (is_object($vals))
            $this->vals = $vals;
        elseif (is_array($vals))
            $this->vals = (object) $vals;
        else
            throw new PikeException('$vals must be object|array',
                                    PikeException::BAD_INPUT);
    }
    /**
     * @return object
     */
    public function getVals() {
        return $this->vals;
    }
    /**
     * @param string $key
     * @param mixed $default = null
     * @return mixed
     */
    public function get($key, $default = null) {
        return $this->vals->$key ?? $default;
    }
}
