<?php

declare(strict_types=1);

namespace Pike\TestUtils;

use Pike\Auth\Crypto;
use Pike\PikeException;

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
    public function genRandomToken(int $bytes = 16): string {
        return self::mockGenRandomToken($bytes);
    }
    public function encrypt(string $plainStr, string $key): string {
        return self::mockEncrypt($plainStr, $key);
    }
    public function decrypt(string $encodedStr, string $key): string {
        return self::mockDecrypt($encodedStr, $key);
    }
    public static function mockHashPass(string $plainPass): string {
        return 'hashed: ' . $plainPass;
    }
    public static function mockHash(string $algo, string $data): string {
        return "{$algo} hash of: {$data}";
    }
    public static function mockGuidv4(): string {
        return 'here\'s-guid-for-you';
    }
    public static function mockGenRandomToken(int $bytes = 16): string {
        return str_pad('randomToken', $bytes * 2, '-'); // @phan-suppress-current-line PhanParamSuspiciousOrder
    }
    public static function mockEncrypt(string $str, string $key): string {
        return "encrypted with {$key}: {$str}";
    }
    public static function mockDecrypt(string $str, string $key): string {
        if (strpos($str, "encrypted with {$key}: ") !== 0)
            throw new PikeException('Failed to decrypt input string',
                                    PikeException::ERROR_EXCEPTION);
        return substr($str, strlen("encrypted with {$key}: "));
    }
}
