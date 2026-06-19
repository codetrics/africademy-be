<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Authenticated encryption (libsodium XChaCha20-Poly1305) for gateway tokens.
 * The configured secret is hashed to a 32-byte key, so any secret string works.
 */
class TokenCipher
{
    private readonly string $key;

    public function __construct(
        #[Autowire('%app.sodium_aead_key%')] string $secret,
    ) {
        $this->key = sodium_crypto_generichash($secret, '', SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
    }

    public function encrypt(#[\SensitiveParameter] string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, '', $nonce, $this->key);

        return base64_encode($nonce . $ciphertext);
    }

    public function decrypt(string $encoded): ?string
    {
        $decoded = base64_decode($encoded, true);
        if ($decoded === false) {
            return null;
        }

        $nonceLength = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if (strlen($decoded) <= $nonceLength) {
            return null;
        }

        $nonce = substr($decoded, 0, $nonceLength);
        $ciphertext = substr($decoded, $nonceLength);

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ciphertext, '', $nonce, $this->key);

        return $plaintext === false ? null : $plaintext;
    }
}
