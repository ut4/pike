<?php

declare(strict_types=1);

namespace Pike;

use Pike\Extensions\Validation\SafeHTMLValidator;
use Pike\Validation\{ObjectValidator, ValueValidator};

/**
 * Validaatiomoduulin julkinen API.
 */
abstract class Validation {
    private const STRING_TYPE_FUNCS = [
        "alnum"  => "ctype_alnum",
        "alpha"  => "ctype_alpha",
        "cntrl"  => "ctype_cntrl",
        "digit"  => "ctype_digit",
        "graph"  => "ctype_graph",
        "lower"  => "ctype_lower",
        "print"  => "ctype_print",
        "punct"  => "ctype_punct",
        "space"  => "ctype_space",
        "upper"  => "ctype_upper",
        "xdigit" => "ctype_xdigit",
    ];
    /** @var array<string, array> */
    private static $ruleImpls = [];
    /**
     * @return \Pike\Validation\ValueValidator
     */
    public static function makeValueValidator(): ValueValidator {
        return new ValueValidator;
    }
    /**
     * @return \Pike\Validation\ObjectValidator
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
     * @return bool
     */
    public static function hasRuleImpl(string $name): bool {
        return self::getRuleImplInternal($name) !== null;
    }
    /**
     * @param string $name
     * @return array [callable, string]
     * @throws \Pike\PikeException
     */
    public static function getRuleImpl(string $name): array {
        $out = self::getRuleImplInternal($name);
        if (!$out)
            throw new PikeException("No implementation found for `{$name}`.",
                                    PikeException::BAD_INPUT);
        return $out;
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
    public static function isStringType($value, string $type): bool {
        if (($fn = self::STRING_TYPE_FUNCS[$type] ?? null) !== null)
            return is_string($value) && $fn($value);
        throw new PikeException("{$type} id not valid string type (ctype_{$type}())",
                                PikeException::BAD_INPUT);
    }
    public static function isMoreOrEqualLength(/*string|\Countable*/ $value, int $min, string $expectedType = 'string'): bool {
        return ($expectedType === 'string' && is_string($value) && mb_strlen($value) >= $min) ||
               ($expectedType === 'array' && (is_array($value) || $value instanceof \Countable) &&
                count($value) >= $min);
    }
    public static function isLessOrEqualLength(/*string|\Countable*/ $value, int $max, string $expectedType = 'string'): bool {
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
    public static function contains($value, /*string|\Traversable*/ $what, string $expectedType = 'string'): bool {
        if ($expectedType === 'string')
            return is_string($value) && strpos($value, $what) !== false;
        if ($expectedType === 'array') {
            if (!is_iterable($value)) return false;
            foreach ($value as $item) {
                if ($item === $value) return true;
            }
            return false;
        }
        return false;
    }
    public static function notContains($value, /*string|\Traversable*/ $what, string $expectedType = 'string'): bool {
        if (($expectedType === 'string' && !is_string($value)) ||
            ($expectedType === 'array' && !is_iterable($value))) return false;
        return !self::contains($value, $what, $expectedType);
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
    private static function getRuleImplInternal(string $name): ?array {
        if (!isset(self::$ruleImpls['string'])) {
            $cls = self::class . '::';
            self::$ruleImpls = array_merge(self::$ruleImpls, [
                'type'       => ["{$cls}is", '%s must be %s'],
                'stringType' => ["{$cls}isStringType", '%s must be %s string'],
                'minLength'  => ["{$cls}isMoreOrEqualLength", 'The length of %s must be at least %d'],
                'maxLength'  => ["{$cls}isLessOrEqualLength", 'The length of %s must be %d or less'],
                'min'        => ["{$cls}isEqualOrGreaterThan", 'The value of %s must be %d or greater'],
                'max'        => ["{$cls}isEqualOrLessThan", 'The value of %s must be %d or less'],
                'in'         => ["{$cls}isOneOf", 'The value of %s was not in the list'],
                'contains'   => ["{$cls}contains", 'Expected %s to contain the value'],
                'notContains'=> ["{$cls}notContains", 'Expected %s not to contain the value'],
                'identifier' => ["{$cls}isIdentifier", '%s must contain only [a-zA-Z0-9_] and start with [a-zA-Z_]'],
                'regexp'     => ["{$cls}doesMatchRegexp", 'The value of %s did not pass the regexp'],
                'safeHtml'   => [SafeHTMLValidator::class . '::isSafeHTML', 'The value of %s is not valid html'],
            ]);
        }
        return self::$ruleImpls[$name] ?? null;
    }
}
