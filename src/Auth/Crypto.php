<?php

namespace Pike\Auth;

use Pike\PikeException;

class Crypto {
    public const SECRETBOX_KEYBYTES = SODIUM_CRYPTO_SECRETBOX_KEYBYTES;
    private const SECRETBOX_NONCEBYTES = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
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
     * @return string|bool
     */
    public function hashPass($plainPass) {
        return password_hash($plainPass, PASSWORD_DEFAULT);
    }
    /**
     * https://stackoverflow.com/a/15875555
     *
     * @return string
     * @throws \Exception
     */
    public function guidv4() {
        $data = $data ?? random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    /**
     * @return string
     * @throws \Exception
     */
    public function genRandomToken($bytes = 16) {
        return bin2hex(random_bytes($bytes));
    }
    /**
     * @param string $plainStr
     * @param string $key
     * @return string
     * @throws \Pike\PikeException|\Exception
     */
    public function encrypt($plainStr, $key) {
        $nonce = random_bytes(self::SECRETBOX_NONCEBYTES);
        try {
            $ciphertext = sodium_crypto_secretbox($plainStr, $nonce, $key);
        } catch (\SodiumException $e) {
            throw new PikeException($e->getMessage(), Authenticator::CRYPTO_FAILURE);
        }
        return base64_encode($nonce . $ciphertext);
    }
    /**
     * @param string $encodedStr
     * @param string $key
     * @return string
     * @throws \Pike\PikeException
     */
    public function decrypt($encodedStr, $key) {
        if (!($decoded = base64_decode($encodedStr)))
            throw new PikeException('Failed to decode input string',
                                    PikeException::BAD_INPUT);
        $nonce = substr($decoded, 0, self::SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, self::SECRETBOX_NONCEBYTES);
        try {
            if (($out = sodium_crypto_secretbox_open($ciphertext, $nonce, $key)))
                return $out;
        } catch (\SodiumException $e) {
            throw new PikeException($e->getMessage(),
                                    Authenticator::CRYPTO_FAILURE);
        }
        throw new PikeException('Failed to decrypt input string',
                                Authenticator::CRYPTO_FAILURE);
    }
}
