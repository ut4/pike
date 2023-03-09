<?php declare(strict_types=1);

namespace Pike\Validation;

use Pike\PikeException;

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
