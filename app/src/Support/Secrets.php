<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;
use SensitiveParameter;

final class Secrets
{
    private readonly string $key;

    public function __construct(?string $appKey)
    {
        if ($appKey === null || $appKey === '') {
            throw new RuntimeException('APP_KEY is not set. Generate one with: php bin/genkey.php');
        }

        $key = base64_decode($appKey, true);

        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('APP_KEY must be a base64-encoded 32-byte key.');
        }

        $this->key = $key;
    }

    public static function generateKey(): string
    {
        return base64_encode(sodium_crypto_secretbox_keygen());
    }

    public function encrypt(#[SensitiveParameter] string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce . sodium_crypto_secretbox($plaintext, $nonce, $this->key));
    }

    public function decrypt(string $ciphertext): string
    {
        $raw = base64_decode($ciphertext, true);

        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('Stored secret is malformed.');
        }

        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plaintext = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $this->key);

        if ($plaintext === false) {
            throw new RuntimeException('Unable to decrypt secret — APP_KEY may have changed.');
        }

        return $plaintext;
    }

    public static function mask(string $secret): string
    {
        $length = strlen($secret);

        return $length <= 8 ? str_repeat('•', $length) : substr($secret, 0, 4) . str_repeat('•', 8) . substr($secret, -4);
    }
}
