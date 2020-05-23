<?php

declare(strict_types=1);

namespace Pike;

class Template {
    /** @var array<string, mixed> */
    protected $__vars;
    /** @var string */
    protected $__file;
    /** @var string */
    protected $__dir;
    /** @var array<string, mixed> */
    protected $__locals;
    /**
     * @param string $file
     * @param array<string, mixed> $vars = null
     */
    public function __construct(string $file, array $vars = null) {
        $this->__vars = $vars ?? [];
        $this->__file = $file;
        $this->__dir = dirname($file) . '/';
    }
    /**
     * @param array<string, mixed> $locals = []
     * @return string
     */
    public function render(array $locals = []): string {
        $this->__locals = $locals;
        return $this->doRender($this->__file, $locals);
    }
    /**
     * @param string $name
     * @return mixed
     */
    public function __get(string $name) {
        return $this->__vars[$name];
    }
    /**
     * @param string $__file
     * @param array<string, mixed> $__locals
     * @return string
     */
    protected function doRender(string $__file, array $__locals): string {
        ob_start();
        extract($__locals);
        include $__file;
        return ob_get_clean();
    }
}
