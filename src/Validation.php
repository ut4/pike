<?php

declare(strict_types=1);

namespace Pike;

/**
 * Validaatiomoduulin julkinen API.
 */
abstract class Validation {
    private static $ruleImpls = [];
    /**
     * @return \Pike\ValueValidator
     */
    public static function makeValueValidator(): ValueValidator {
        return new ValueValidator;
    }
    /**
     * @return \Pike\ObjectValidator
     */
    public static function makeObjectValidator(): ObjectValidator {
        return new ObjectValidator;
    }
    /**
     * @param string $name
     * @param callable $n
     * @param string $errorTmpl
     */
    public static function registerRuleImpl(string $name,
                                            callable $fn,
                                            string $errorTmpl): void {
        self::$ruleImpls[$name] = [$fn, $errorTmpl];
    }
    /**
     * @param string $name
     * @return array [callable, string]
     * @throws \Pike\PikeException
     */
    public static function getRuleImpl(string $name): array {
        if (!isset(self::$ruleImpls['string'])) {
            $cls = self::class . '::';
            self::$ruleImpls = array_merge(self::$ruleImpls, [
                'type'       => ["{$cls}is", '%s must be %s'],
                'minLength'  => ["{$cls}isMoreOrEqualLength", 'The length of %s must be at least %d'],
                'maxLength'  => ["{$cls}isLessOrEqualLength", 'The length of %s must be %d or less'],
                'min'        => ["{$cls}isEqualOrGreaterThan", 'The value of %s must be %d or greater'],
                'max'        => ["{$cls}isEqualOrLessThan", 'The value of %s must be %d or less'],
                'in'         => ["{$cls}isOneOf", 'The value of %s was not in the list'],
                'identifier' => ["{$cls}isIdentifier", '%s must contain only [a-zA-Z0-9_] and start with [a-zA-Z_]'],
            ]);
        }
        if (!array_key_exists($name, self::$ruleImpls))
            throw new PikeException("No implementation found for `{$name}`.",
                                    PikeException::BAD_INPUT);
        return self::$ruleImpls[$name];
    }
    // == Default asserters ====================================================
    public static function is($value, string $type): bool {
        if ($type === 'string') return is_string($value);
        if ($type === 'int') return is_int($value);
        if ($type === 'array') return is_array($value);
        if ($type === 'bool') return is_bool($value);
        if ($type === 'float') return is_float($value);
        if ($type === 'object') return is_object($value);
        throw new PikeException("is_{$type}() not supported",
                                PikeException::BAD_INPUT);
    }
    public static function isMoreOrEqualLength($strOrArray, int $min): bool {
        return (is_string($strOrArray) && mb_strlen($strOrArray) >= $min) ||
               ((is_array($strOrArray) || $strOrArray instanceof \Countable) &&
                count($strOrArray) >= $min);
    }
    public static function isLessOrEqualLength($strOrArray, int $max): bool {
        return (is_string($strOrArray) && mb_strlen($strOrArray) <= $max) ||
               ((is_array($strOrArray) || $strOrArray instanceof \Countable) &&
                count($strOrArray) <= $max);
    }
    public static function isEqualOrGreaterThan($value, int $min): bool {
        return is_numeric($value) && $value >= $min;
    }
    public static function isEqualOrLessThan($value, int $max): bool {
        return is_numeric($value) && $value <= $max;
    }
    public static function isOneOf($value, array $listOfAllowedVals): bool {
        return in_array($value, $listOfAllowedVals, true);
    }
    public static function isIdentifier($str): bool {
        return is_string($str) &&
               strlen($str) &&
               (ctype_alpha($str[0]) || $str[0] === '_') &&
               ctype_alnum(\str_replace('_', '', $str));
    }
}

class ValueValidator {
    private $rules = [];
    /**
     * @param string $ruleName
     * @param array ...$args
     * @return $this
     */
    public function rule(string $ruleName, ...$args): ValueValidator {
        $this->rules[] = [Validation::getRuleImpl($ruleName), $args];
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

class ObjectValidator {
    private $rules = [];
    /**
     * @param string $propPath
     * @param string $ruleName
     * @param array ...$args
     * @return $this
     */
    public function rule(string $propPath,
                         string $ruleName,
                         ...$args): ObjectValidator {
        $rule = new \stdClass;
        $rule->validator = Validation::getRuleImpl($ruleName);
        $rule->isOptional = $propPath[-1] === '?';
        $rule->propPath = !$rule->isOptional
            ? $propPath
            : \substr($propPath, 0, \strlen($propPath) - 1);
        $rule->args = $args;
        $this->rules[] = $rule;
        return $this;
    }
    /**
     * @param object $object
     * @return string[]
     */
    public function validate(object $object): array {
        $errors = [];
        foreach ($this->rules as $r) {
            $isValid = false;
            // fast lane
            if (strpos($r->propPath, '.') === false) {
                if ($r->isOptional && !($object->{$r->propPath} ?? null))
                    continue;
                $isValid = call_user_func($r->validator[0],
                                          $object->{$r->propPath} ?? null,
                                          ...$r->args);
            // value.by.path
            } else {
                [$val, $isIterable] = self::getValFor(explode('.', $r->propPath),
                                                      $object);
                if ($r->isOptional && !$val)
                    continue;
                if (!$isIterable) {
                    $isValid = call_user_func($r->validator[0], $val, ...$r->args);
                } else {
                    $wildcardPos = strpos($r->propPath, '*');
                    foreach ($val as $k => $v) {
                        if (!call_user_func($r->validator[0], $v, ...$r->args))
                            $errors[] = sprintf(
                                $r->validator[1],
                                $wildcardPos === false
                                    // foo.bar
                                    ? $r->propPath
                                    // foo.*.bar -> foo.0.bar or foo.prop.bar
                                    : substr($r->propPath, 0, $wildcardPos) .
                                        $k .
                                      substr($r->propPath, $wildcardPos + 1),
                                ...$r->args
                            );
                    }
                    continue;
                }
            }
            if (!$isValid)
                $errors[] = sprintf($r->validator[1], $r->propPath, ...$r->args);
        }
        return $errors;
    }
    /**
     * @param string[] $pathPieces
     * @param object $object
     * @return array [mixed, bool]
     */
    private static function getValFor(array $pathPieces,
                                      object $object): array {
        $end = count($pathPieces) - 1;
        $cur = $object;
        foreach ($pathPieces as $i => $p) {
            if ($i < $end) {
                if ($p !== '*' && property_exists($cur, $p))
                    $cur = $cur->$p;
                elseif ($p === '*') {
                    $vls = [];
                    foreach ($cur as $item) {
                        [$v, $isIterable] =  self::getValFor(array_slice($pathPieces, $i + 1), $item);
                        if (!$isIterable) $vls[] = $v;
                        else throw new \RuntimeException('Not implemented');
                    }
                    return [$vls, true];
                }
                else return [null, false];
            } else {
                if ($p !== '*') return [$cur->$p ?? null, false];
                else return [$cur, true];
            }
        }
    }
}
