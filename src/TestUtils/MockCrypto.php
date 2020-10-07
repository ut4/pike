<?php

declare(strict_types=1);

namespace Pike\TestUtils;

use Pike\Auth\Crypto;

class MockCrypto extends Crypto {
    public function verifyPass(string $plainPass, string $hashedPass): bool {
        return self::mockHashPass($plainPass) === $hashedPass;
    }
    public function hashPass(string $plainPass): string {
        return self::mockHashPass($plainPass);
    }
    public function hash(string $algo, string $data, bool $useRawOutput = false): string {
        return self::mockHash($algo, $data . (!$useRawOutput ? '' : '(raw)'));
    }
    public function guidv4(): string {
        return self::mockGuidv4();
    }
    public function genRandomToken(int $_bytes = 16): string {
        return self::mockGenRandomToken();
    }
    public function encrypt(string $str, string $_key): string {
        return self::mockEncrypt($str);
    }
    public function decrypt(string $str, string $_key): string {
        return self::mockDecrypt($str);
    }
    public static function mockHashPass(string $plainPass): string {
        return "hashed: {$plainPass}";
    }
    public static function mockHash(string $algo, string $data): string {
        return "{$algo} hash of: {$data}";
    }
    public static function mockGuidv4(): string {
        return 'here\'s-guid-for-you';
    }
    public static function mockGenRandomToken(): string {
        return 'randomToken';
    }
    public static function mockEncrypt(string $str): string {
        return "encrypted: {$str}";
    }
    public static function mockDecrypt(string $str): string {
        return substr($str, strlen('encrypted: '));
    }
}
