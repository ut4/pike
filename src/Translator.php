<?php

namespace Pike;

class Translator {
    private $stringMap;
    private $loadStringsFn;
    /**
     * @param \Closure $fn Function<(): ['key' => 'Foo', 'another' => 'Bar %s' ...]>
     */
    public function __construct($stringLoaderFn = null) {
        $this->stringMap = [];
        if ($stringLoaderFn) $this->setOneTimeStringLoader($stringLoaderFn);
    }
    /**
     * @param \Closure $fn Function<(): ['key' => 'Foo', 'another' => 'Bar %s' ...]>
     */
    public function setOneTimeStringLoader(\Closure $fn) {
        $this->loadStringsFn = $fn;
    }
    /**
     * @param string $key
     * @param array $args = null
     * @return string
     */
    public function t($key, array $args = null) {
        if (!$this->loadStringsFn) {
            $this->stringMap += $this->loadStringsFn->invoke();
            $this->loadStringsFn = null;
        }
        if (($tmpl = $this->stringMap[$key] ?? null)) {
            return !$args ? $tmpl : vsprintf($tmpl, $args);
        }
        return $key;
    }
}
