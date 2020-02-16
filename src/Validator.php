<?php

namespace Pike;

/**
 * Simppeli data-validaattori. Esimerkki:
 * ```php
 * $v = new Validator((object)['foo' => 'bar']);
 * $myRule = [function($input,$key){return false;}, '%s is always nope'];
 * echo $v->check('foo', 'present') ? 'pass' : 'nope';
 * echo $v->check('foo', ['in', ['a', 'b']]) ? 'pass' : 'nope';
 * echo $v->check('foo', $myRule, 'anotherbuiltin', ['mixed', [123]]) ? 'pass' : 'nope';
 * echo json_encode($v->errors);
 * ```
 */
class Validator {
    public $errors;
    public $input;
    /**
     * @param object $input
     */
    public function __construct($input) {
        $this->errors = [];
        $this->input = $input;
    }
    /**
     * @param string $key
     * @param array ...$rules Array<string | [function, string] | [string, array]>
     * @return bool
     */
    public function is($key, ...$rules) {
        $rules[] = false;
        return $this->check($key, ...$rules);
    }
    /**
     * Ajaa kaikki £rules:t passaten niille $this->input->$key:n arvon. Pysähtyy
     * ensimmäiseen positiveen ja palauttaa false, muutoin true.
     *
     * @param string $key
     * @param array ...$rules ks. is()
     * @return bool
     */
    public function check($key, ...$rules) {
        $doLog = $rules[count($rules) - 1] !== false;
        foreach ($rules as $rule) {
            if (is_string($rule)) {
                $rule = $this->$rule();
            } elseif (is_array($rule) && is_string($rule[0])) {
                [$rname, $args] = $rule;
                $rule = $this->$rname($args);
            } elseif (is_bool($rule)) {
                break;
            }
            if (!$rule[0]($this->input, $key)) {
                if ($doLog) $this->errors[] = sprintf($rule[1], $key);
                return false;
            }
        }
        return true;
    }
    /**
     * @return array [($input: string, $key: string): bool, string]
     */
    public function present() {
        return [function ($input, $key) {
            return property_exists($input, $key);
        }, '%s is required'];
    }
    /**
     * @return array [($input: string, $key: string): bool, string]
     */
    public function string() {
        return [function ($input, $key) {
            return $this->present()[0]($input, $key) && is_string($input->$key);
        }, '%s must be a string'];
    }
    /**
     * @return array [($input: string, $key: string): bool, string]
     */
    public function integer() {
        return [function ($input, $key) {
            return $this->present()[0]($input, $key) &&
                   (is_int($input->$key) || ctype_digit((string)$input->$key));
        }, '%s must be an integer'];
    }
    /**
     * @return array [($input: string, $key: string): bool, string]
     */
    public function nonEmptyString() {
        return [function ($input, $key) {
            return $this->string()[0]($input, $key) && strlen($input->$key) > 0;
        }, '%s must be a non-empty string'];
    }
    /**
     * @param array $arr
     * @return array [($input: string, $key: string): bool, string]
     */
    public function in($arr) {
        return [function ($input, $key) use ($arr) {
            return $this->present()[0]($input, $key) && in_array($input->$key, $arr);
        }, '%s must be one of ' . json_encode($arr)];
    }
    /**
     * @return array [($input: string, $key: string): bool, string]
     */
    public function word() {
        return [function ($input, $key) {
            return $this->present()[0]($input, $key) &&
                   ctype_alnum(str_replace(['_', '-'], '', $input->$key));
        }, '%s must be a word'];
    }
}
