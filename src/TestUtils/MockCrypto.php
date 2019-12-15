<?php

namespace Pike\TestUtils;

use Pike\Auth\Crypto;

class MockCrypto extends Crypto {
    public function verifyPass($plainPass, $hashedPass) {
        return $plainPass === $hashedPass;
    }
    public function encrypt($str, $_key) {
        return self::mockEncrypt($str);
    }
    public function decrypt($str, $_key) {
        return self::mockDecrypt($str);
    }
    public static function mockEncrypt($str) {
        return 'encrypted: ' . $str;
    }
    public static function mockDecrypt($str) {
        return substr($str, strlen('encrypted: '));
    }
}
