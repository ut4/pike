<?php

namespace Pike;

class ArrayUtils {
    /**
     * @param array|\ArrayObject $array
     * @param mixed $val
     * @param string $key
     * @param mixed $default = null
     * @return mixed|null
     */
    public static function findByKey($array, $val, $key, $default = null) {
        foreach ($array as $item) {
            if ($item->$key === $val) return $item;
        }
        return $default;
    }
    /**
     * @param array|\ArrayObject $array
     * @param mixed $val
     * @param string $key
     * @return array|\ArrayObject
     * @throws \Pike\PikeException
     */
    public static function filterByKey($array, $val, $key) {
        if (is_array($array))
            $out = [];
        elseif ($array instanceof \ArrayObject) {
            $Cls = get_class($array);
            $out = new $Cls();
        } else
            throw new PikeException('$array must be array or (subclass of) \ArrayObject',
                                    PikeException::BAD_INPUT);
        foreach ($array as $item) {
            if ($item->$key === $val) $out[] = $item;
        }
        return $out;
    }
    /**
     * @param array|\ArrayObject $array
     * @param mixed $val
     * @param string $key
     * @return mixed|null
     */
    public static function findIndexByKey($array, $val, $key) {
        foreach ($array as $i => $item) {
            if ($item->$key === $val) return $i;
        }
        return -1;
    }
}
