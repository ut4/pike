<?php

namespace Pike;

class NativeSession implements SessionInterface {
    private $bucketKey;
    /** 
     * @param string $bucketKey = 'rad'
     * @param string $autostart = true
     */
    public function __construct($bucketKey = 'rad', $autostart = true) {
        $this->bucketKey = $bucketKey;
        if ($autostart) $this->start();
    }
    /**
     */
    public function start() {
        if (!session_id()) session_start();
    }
    /** 
     * @param string $key
     * @param mixed $value
     */
    public function put($key, $value) {
        $_SESSION[$this->bucketKey][$key] = $value;
    }
    /** 
     * @param string $key
     * @param string $default = null
     * @return mixed
     */
    public function get($key, $default = null) {
        return $_SESSION[$this->bucketKey][$key] ?? $default;
    }
    /**
     * @param string $key
     */
    public function remove($key) {
        if (isset($_SESSION[$this->bucketKey][$key]))
            unset($_SESSION[$this->bucketKey][$key]);
    }
    /**
     */
    public function commit() {
        session_write_close();
    }
    /**
     */
    public function destroy() {
        if (isset($_SESSION[$this->bucketKey]))
            unset($_SESSION[$this->bucketKey]);
    }
}
