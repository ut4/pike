<?php

declare(strict_types=1);

namespace Pike;

class Translator {
    private $strings;
    /**
     * @param array<string, string> $strings = []
     */
    public function __construct(array $strings = []) {
        $this->strings = $strings;
    }
    /**
     * @param array<string, string> $strings
     */
    public function addStrings(array $strings): void {
        $this->strings = array_merge($this->strings, $strings);
    }
    /**
     * @param string $key
     * @param float|int|string ...$args
     * @return string
     */
    public function t(string $key, ...$args): string {
        $tmpl = $this->strings[$key] ?? $key;
        return !$args ? $tmpl : sprintf($tmpl, ...$args);
    }
    /**
     * @param string $key
     * @return bool
     */
    public function hasKey(string $key): bool {
        return array_key_exists($key, $this->strings);
    }
}
