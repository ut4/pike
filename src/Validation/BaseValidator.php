<?php declare(strict_types=1);

namespace Pike\Validation;

use Pike\Validation;

abstract class BaseValidator {
    /** @var array<string, array> */
    protected $oneTimeRuleImpls = [];
    /**
     * @param string $name
     * @param callable $checkFn fn($value[[, $arg1], $args2]): bool
     * @param string $errorTmpl
     * @return $this
     */
    public function addRuleImpl(string $name,
                                callable $checkFn,
                                string $errorTmpl) {
        $this->oneTimeRuleImpls[$name] = [$checkFn, $errorTmpl];
        return $this;
    }
    /**
     * @param string $name
     * @return bool
     */
    public function hasRuleImpl(string $name): bool {
        return array_key_exists($name, $this->oneTimeRuleImpls) || Validation::hasRuleImpl($name);
    }
    /**
     * @param string $name
     * @return array [callable, string]
     * @throws \Pike\PikeException
     */
    protected function getRuleImpl(string $name): array {
        $out = $this->oneTimeRuleImpls[$name] ?? null;
        if ($out) return $out;
        return Validation::getRuleImpl($name);
    }
}
