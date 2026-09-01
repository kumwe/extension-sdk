<?php

declare(strict_types=1);

namespace Kumwe\Extension\Package;

use InvalidArgumentException;

/**
 * Verifies package signatures against a fixed, configured map of Ed25519 public keys.
 *
 * Key admission remains host policy. This adapter only selects configured material by the signature's
 * asserted identifier and delegates to the same public-key primitive dynamic trust stores consume.
 *
 * @since  0.2.0
 */
final readonly class SodiumEd25519Verifier implements PackageSignatureVerifier
{
    /**
     * Canonical base64 public keys indexed by stable key identifier.
     *
     * @var    array<string, string>
     * @since  0.2.0
     */
    private array $publicKeys;

    /**
     * Validate configured keys and bind the canonical cryptographic primitive.
     *
     * @param   array<mixed>                      $base64PublicKeys  Public keys keyed by stable identifier.
     * @param   PublicKeyPackageSignatureVerifier $verifier          Host-neutral selected-key verifier.
     *
     * @throws  InvalidArgumentException  When an identifier or key encoding is malformed.
     *
     * @since   0.2.0
     */
    public function __construct(
        array $base64PublicKeys,
        private PublicKeyPackageSignatureVerifier $verifier = new SodiumPublicKeyPackageSignatureVerifier(),
    ) {
        $keys = [];
        foreach ($base64PublicKeys as $keyId => $base64PublicKey) {
            if (
                !is_string($keyId)
                || preg_match('/^[a-z0-9][a-z0-9._:-]{2,126}$/D', $keyId) !== 1
                || !is_string($base64PublicKey)
            ) {
                throw new InvalidArgumentException('Signing keys must map stable string IDs to base64 public keys.');
            }
            $publicKey = base64_decode($base64PublicKey, true);
            if (
                !is_string($publicKey)
                || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
                || !hash_equals(base64_encode($publicKey), $base64PublicKey)
            ) {
                throw new InvalidArgumentException('Every Ed25519 public key must be canonical base64 of 32 bytes.');
            }
            $keys[$keyId] = $base64PublicKey;
        }
        $this->publicKeys = $keys;
    }

    /**
     * Verify under the configured key named by the signature.
     *
     * @param   PackageChecksum   $checksum   Digest of the exact package bytes.
     * @param   PackageSignature  $signature  Detached signature and configured key identifier.
     *
     * @return  bool  True only when that configured key verifies the domain-separated message.
     *
     * @since   0.2.0
     */
    public function verify(PackageChecksum $checksum, PackageSignature $signature): bool
    {
        $publicKey = $this->publicKeys[$signature->keyId()] ?? null;

        return $publicKey !== null && $this->verifier->verify($publicKey, $checksum, $signature);
    }
}
