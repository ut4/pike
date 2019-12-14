<?php

namespace Pike\Auth;

class Crypto {
    /**
     * @param string $plainPass
     * @param string $hashedPass
     * @return bool
     */
    public function verifyPass($plainPass, $hashedPass) {
        return password_verify($plainPass, $hashedPass);
    }
    /**
     * @param string $plainPass
     * @return string
     */
    public function hashPass($plainPass) {
        return password_hash($plainPass, PASSWORD_DEFAULT);
    }
    /**
     * https://stackoverflow.com/a/15875555
     *
     * @return string
     */
    public function guidv4() {
        $data = $data ?? random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    /**
     * @param string $plainStr
     * @param string $key
     * @return string
     */
    public function encrypt($plainStr, $key) {
        return base64_encode($plainStr . $key); // :D
    }
    /**
     * @param string $encodedStr
     * @param string $key
     * @return string
     */
    public function decrypt($encodedStr, $key) {
        return base64_decode($encodedStr . $key);
    }
}
