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
     * @param mixed[] $args
     * @return string
     */
    public function t(string $key, ...$args): string {
        if (($tmpl = $this->strings[$key] ?? null)) {
            return !$args ? $tmpl : vsprintf($tmpl, ...$args);
        }
        return $key;
    }
    /**
     * @param string $key
     * @return bool
     */
    public function hasKey(string $key): bool {
        return array_key_exists($key, $this->strings);
    }
}
