<?php

declare(strict_types=1);

namespace Pike;

class Translator {
    private $stringMap;
    private $loadStringsFn;
    /**
     * @param \Closure $fn Function<(): ['key' => 'Foo', 'another' => 'Bar %s' ...]>
     */
    public function __construct(\Closure $stringLoaderFn = null) {
        $this->stringMap = [];
        if ($stringLoaderFn) $this->setOneTimeStringLoader($stringLoaderFn);
    }
    /**
     * @param \Closure $fn Function<(): ['key' => 'Foo', 'another' => 'Bar %s' ...]>
     */
    public function setOneTimeStringLoader(\Closure $fn): void {
        $this->loadStringsFn = $fn;
    }
    /**
     * @param string $key
     * @param array $args = null
     * @return string
     */
    public function t(string $key, array $args = null): string {
        if (!$this->loadStringsFn) {
            $this->stringMap += $this->loadStringsFn->__invoke();
            $this->loadStringsFn = null;
        }
        if (($tmpl = $this->stringMap[$key] ?? null)) {
            return !$args ? $tmpl : vsprintf($tmpl, $args);
        }
        return $key;
    }
}
