<?php

declare(strict_types=1);

namespace Pike;

use Pike\Extensions\Validation\SafeHTMLValidator;

/**
 * Validaatiomoduulin julkinen API.
 */
abstract class Validation {
    /** @var array<string, array> */
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
     * @param callable $fn
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
                'regexp'     => ["{$cls}doesMatchRegexp", 'The value of %s did not pass the regexp'],
                'safeHtml'   => [SafeHTMLValidator::class . '::isSafeHTML', 'The value of %s is not valid html'],
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
        if ($type === 'number') return is_numeric($value);
        if ($type === 'array') return is_array($value);
        if ($type === 'bool') return is_bool($value);
        if ($type === 'float') return is_float($value);
        if ($type === 'object') return is_object($value);
        throw new PikeException("is_{$type}() not supported",
                                PikeException::BAD_INPUT);
    }
    public static function isMoreOrEqualLength($value, int $min, string $expectedType = 'string'): bool {
        return ($expectedType === 'string' && is_string($value) && mb_strlen($value) >= $min) ||
               ($expectedType === 'array' && (is_array($value) || $value instanceof \Countable) &&
                count($value) >= $min);
    }
    public static function isLessOrEqualLength($value, int $max, string $expectedType = 'string'): bool {
        return ($expectedType === 'string' && is_string($value) && mb_strlen($value) <= $max) ||
               ($expectedType === 'array' && (is_array($value) || $value instanceof \Countable) &&
                count($value) <= $max);
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
    public static function doesMatchRegexp($str, string $pattern): bool {
        $result = is_string($str) ? preg_match($pattern, $str) : 0;
        if ($result === false) throw new PikeException("Invalid regexp {$pattern}",
                                                       PikeException::BAD_INPUT);
        return $result === 1;
    }
}

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
     * @return array [callable, string]
     * @throws \Pike\PikeException
     */
    protected function getRuleImpl(string $name): array {
        $out = $this->oneTimeRuleImpls[$name] ?? null;
        if ($out) return $out;
        return Validation::getRuleImpl($name);
    }
}

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

class ObjectValidator extends BaseValidator {
    /** @var \stdClass[] */
    private $rules = [];
    /**
     * @param string $propPath
     * @param string $ruleName
     * @param mixed ...$args
     * @return $this
     */
    public function rule(string $propPath,
                         string $ruleName,
                         ...$args): ObjectValidator {
        $rule = new \stdClass;
        $rule->validator = $this->getRuleImpl($ruleName);
        $rule->propPath = $propPath;
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
                [$propPath, $isOptional] = PropPathProcessor::parsePropPath($r->propPath);
                if (($val = $object->{$propPath} ?? null) === null && $isOptional)
                    continue;
                $isValid = call_user_func($r->validator[0], $val, ...$r->args);
                $key = $r->propPath;
            // value.by.path
            } else {
                if (strpos($r->propPath, '*?') !== false)
                    throw new PikeException("Invalid path {$r->propPath}");
                [$val, $wasOptional, $err] = PropPathProcessor::getVal($r->propPath, $object);
                if ($err) {
                    $errors[] = $err;
                    continue;
                }
                if ($val[1] === PropPathProcessor::VALUE_MULTI) {
                    foreach ($val[0] as $multiValue) {
                        if (!call_user_func($r->validator[0],
                                            $multiValue->val,
                                            ...$r->args))
                            $errors[] = sprintf($r->validator[1], $multiValue->key, ...$r->args);
                    }
                    continue;
                }
                if ($wasOptional && !$val[0]->val)
                    continue;
                $isValid = call_user_func($r->validator[0], $val[0]->val, ...$r->args);
                $key = $val[0]->key;
            }
            if (!$isValid)
                $errors[] = sprintf($r->validator[1], $key, ...$r->args);
        }
        return array_map(function ($err) {
            return str_replace(['[root].', '?'], '', $err);
        }, $errors);
    }
}

abstract class PropPathProcessor {
    public const VALUE_SINGLE = 0;
    public const VALUE_MULTI = 1;
    /**
     * Returns property|ies from $object by $path (foo.path, foo.*.path, foo.*.path.* etc.).
     *
     * @param string $path
     * @param object object
     * @return array [$valueAndValueType, $valueWasOptional, $pathError]
     */
    public static function getVal(string $path, object $object) {
        $segments = array_merge(['.'], explode('|', str_replace('.', '|.|', $path))); // 'foo.bar' -> ['.', 'foo', '.', 'bar']
        $valAndType = [(object) ['key' => '[root]', 'val' => $object], self::VALUE_SINGLE];
        $isOptional = false;
        for ($i = 0; $i < count($segments); ++$i) {
            $cur = $segments[$i];
            if ($cur !== '.')
                throw new PikeException("Invalid path {$path}");
            $next = $segments[++$i] ?? null;
            // .bar
            if ($next !== '*') {
                [$prop, $isOptional] = self::parsePropPath($next);
                // Current value is not result of .*
                if ($valAndType[1] === self::VALUE_SINGLE) {
                    $newVal = self::getObjProp($valAndType[0]->val, $prop);
                    if ($newVal === null && $isOptional)
                        return [[(object) ['val' => null], self::VALUE_SINGLE], true, null];
                    if ($newVal instanceof PikeException)
                        return [null, $isOptional, self::makeErr($valAndType[0]->key)];
                    $valAndType = [(object) [
                        'key' => "{$valAndType[0]->key}.{$prop}",
                        'val' => $newVal
                    ], self::VALUE_SINGLE];
                // Current value is result of .*
                } else {
                    $propsFromMulti = [];
                    foreach ($valAndType[0] as $i2 => $multiVal) {
                        $val = self::getObjProp($multiVal->val, $prop);
                        if ($val instanceof PikeException)
                            return [null, $isOptional, self::makeErr($multiVal->key)];
                        if (!$isOptional || $val !== null)
                            $propsFromMulti[] = (object) ['key' => "{$multiVal->key}.{$prop}", 'val' => $val];
                    }
                    $valAndType = [$propsFromMulti, self::VALUE_MULTI];
                }
            // .*
            } else {
                if ($valAndType[1] === self::VALUE_SINGLE) {
                    if (!is_array($valAndType[0]->val) && !($valAndType[0]->val instanceof \ArrayObject))
                        return [null, $isOptional, self::makeErr($valAndType[0]->key, 'an array')];
                    $itemsFromSingle = [];
                    foreach ($valAndType[0]->val as $i2 => $val)
                        $itemsFromSingle[] = (object) ['key' => "{$valAndType[0]->key}.{$i2}", 'val' => $val];
                    $valAndType = [$itemsFromSingle, self::VALUE_MULTI];
                } else {
                    $itemsFromMulti = [];
                    foreach ($valAndType[0] as $multiValue) {
                        if (!is_array($multiValue->val) && !($multiValue->val instanceof \ArrayObject))
                            return [null, $isOptional, self::makeErr($multiValue->key, 'an array')];
                        foreach ($multiValue->val as $i2 => $val)
                            $itemsFromMulti[] = (object) ['key' => "{$multiValue->key}.{$i2}", 'val' => $val];
                    }
                    $valAndType = [$itemsFromMulti, self::VALUE_MULTI];
                }
            }
        }
        return [$valAndType, $isOptional, null];
    }
    /**
     * 'foo' -> ['foo', false]
     * 'foo?' -> ['foo', true]
     *
     * @param string $propPath
     * @return array
     */
    public static function parsePropPath(string $propPath): array {
        $isOptional = $propPath[-1] === '?';
        return [!$isOptional ? $propPath : \substr($propPath, 0, \strlen($propPath) - 1),
                $isOptional];
    }
    /**
     * @param mixed $candidate
     * @param string $prop
     * @return mixed|\Pike\PikeException
     */
    private static function getObjProp($candidate, string $prop) {
        if ($candidate === null || !is_object($candidate))
            return new PikeException;
        return $candidate->{$prop} ?? null;
    }
    /**
     * @param string|string[] $path
     * @param string $what = 'an object'
     * @return string
     */
    private static function makeErr($path, string $what = 'an object'): string {
        return "Expected `{$path}` to be {$what}";
    }
}
