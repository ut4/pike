<?php declare(strict_types=1);

namespace Pike\Validation;

class ValueValidator extends BaseValidator {
    /** @var array[] */
    private $rules = [];
    /**
     * @param string $ruleName
     * @param array ...$args
     * @return $this
     */
    public function rule(string $ruleName, ...$args): ValueValidator {
        $this->rules[] = [$this->getRuleImpl($ruleName), $args];
        return $this;
    }
    /**
     * @param mixed $value
     * @param string $valueName = 'value'
     * @return string[]
     */
    public function validate($value, string $valueName = 'value'): array {
        $errors = [];
        foreach ($this->rules as [$validator, $args]) {
            if (!call_user_func($validator[0], $value, ...$args))
                $errors[] = sprintf($validator[1], $valueName, ...$args);
        }
        return $errors;
    }
}
