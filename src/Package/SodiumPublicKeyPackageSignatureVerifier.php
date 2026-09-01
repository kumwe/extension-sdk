<?php

declare(strict_types=1);

namespace Kumwe\Extension\Package;

/**
 * Libsodium verification for a host-selected Ed25519 public key.
 *
 * @since  0.2.0
 */
final readonly class SodiumPublicKeyPackageSignatureVerifier implements PublicKeyPackageSignatureVerifier
{
    /**
     * Verify a package signature without making a key-trust decision.
     *
     * @param   string            $base64PublicKey  Canonical base64 Ed25519 public key.
     * @param   PackageChecksum   $checksum         Digest of the exact package bytes.
     * @param   PackageSignature  $signature        Detached signature and asserted key identifier.
     *
     * @return  bool  True only for canonical key material and a valid package-protocol signature.
     *
     * @since   0.2.0
     */
    public function verify(
        string $base64PublicKey,
        PackageChecksum $checksum,
        PackageSignature $signature,
    ): bool {
        $publicKey = base64_decode($base64PublicKey, true);
        if (
            !is_string($publicKey)
            || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            || !hash_equals(base64_encode($publicKey), $base64PublicKey)
        ) {
            return false;
        }

        return sodium_crypto_sign_verify_detached(
            $signature->bytes(),
            PackageSignatureMessage::forChecksum($checksum),
            $publicKey,
        );
    }
}
