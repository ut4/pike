<?php

declare(strict_types=1);

namespace Pike;

use Pike\Interfaces\SessionInterface;

class NativeSession implements SessionInterface {
    private $bucketKey;
    /** 
     * @param string $bucketKey = 'pike'
     * @param bool $autostart = true
     */
    public function __construct(string $bucketKey = 'pike',
                                bool $autostart = true) {
        $this->bucketKey = $bucketKey;
        if ($autostart) $this->start();
    }
    /**
     */
    public function start(): void {
        if (!session_id()) session_start();
    }
    /** 
     * @param string $key
     * @param mixed $value
     */
    public function put(string $key, $value): void {
        $_SESSION[$this->bucketKey][$key] = $value;
    }
    /** 
     * @param string $key
     * @param mixed $default = null
     * @return mixed
     */
    public function get(string $key, $default = null) {
        return $_SESSION[$this->bucketKey][$key] ?? $default;
    }
    /**
     * @param string $key
     */
    public function remove(string $key): void {
        if (isset($_SESSION[$this->bucketKey][$key]))
            unset($_SESSION[$this->bucketKey][$key]);
    }
    /**
     */
    public function commit(): void {
        session_write_close();
    }
    /**
     */
    public function destroy(): void {
        if (isset($_SESSION[$this->bucketKey]))
            unset($_SESSION[$this->bucketKey]);
    }
}
