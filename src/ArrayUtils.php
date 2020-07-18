<?php

declare(strict_types=1);

namespace Pike;

class ArrayUtils {
    /**
     * @param array<mixed, object|array>|\ArrayObject<mixed, object|array> $array
     * @param mixed $val
     * @param string $key
     * @param mixed $default = null
     * @return mixed|null
     */
    public static function findByKey($array,
                                     $val,
                                     string $key,
                                     $default = null) {
        foreach ($array as $item) {
            if (($item->$key ?? $item[$key]) === $val) return $item;
        }
        return $default;
    }
    /**
     * @param array<mixed, object|array>|\ArrayObject<mixed, object|array> $array
     * @param mixed $val
     * @param string $key
     * @return array|\ArrayObject
     * @throws \Pike\PikeException
     */
    public static function filterByKey($array, $val, string $key) {
        if (is_array($array))
            $out = [];
        elseif ($array instanceof \ArrayObject) {
            $Cls = get_class($array);
            $out = new $Cls();
        } else
            throw new PikeException('$array must be array or (subclass of) \ArrayObject',
                                    PikeException::BAD_INPUT);
        foreach ($array as $item) {
            if (($item->$key ?? $item[$key]) === $val) $out[] = $item;
        }
        return $out;
    }
    /**
     * @param array<mixed, object|array>|\ArrayObject<mixed, object|array> $array
     * @param mixed $val
     * @param string $key
     * @return int $index tai -1
     */
    public static function findIndexByKey($array, $val, string $key): int {
        foreach ($array as $i => $item) {
            if (($item->$key ?? $item[$key]) === $val) return $i;
        }
        return -1;
    }
}
