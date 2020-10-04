<?php

declare(strict_types=1);

namespace Pike\Interfaces;

interface SessionInterface {
    /**
     */
    public function start(): void;
    /**
     * @param string $key
     * @param mixed $value
     */
    public function put(string $key, $value): void;
    /**
     * @param string $key
     * @param mixed $default = null
     * @return mixed
     */
    public function get(string $key, $default = null);
    /**
     * @param string $key
     */
    public function remove(string $key): void;
    /**
     */
    public function commit(): void;
    /**
     */
    public function destroy(): void;
}
