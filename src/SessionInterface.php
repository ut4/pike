<?php

namespace Pike;

interface SessionInterface {
    /**
     */
    public function start();
    /**
     * @param string $key
     * @param string $value
     */
    public function put($key, $value);
    /**
     * @param string $key
     * @param string $default = null
     * @return mixed
     */
    public function get($key, $default = null);
    /**
     * @param string $key
     */
    public function remove($key);
    /**
     */
    public function commit();
    /**
     */
    public function destroy();
}
