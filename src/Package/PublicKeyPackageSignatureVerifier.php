<?php

declare(strict_types=1);

namespace Kumwe\Extension\Package;

/**
 * Host-neutral cryptographic port for a public key already selected by host trust policy.
 *
 * The host decides whether the key is enabled, unexpired, unrevoked and allowed for the package owner.
 * This port performs only canonical key decoding and signature verification.
 *
 * @since  0.2.0
 */
interface PublicKeyPackageSignatureVerifier
{
    /**
     * Verify one detached signature under the supplied Ed25519 public key.
     *
     * @param   string            $base64PublicKey  Canonical base64 32-byte Ed25519 public key.
     * @param   PackageChecksum   $checksum         Digest of the exact package bytes.
     * @param   PackageSignature  $signature        Canonical detached package signature.
     *
     * @return  bool  True only when key and signature verify over the SDK's domain-separated message.
     *
     * @since   0.2.0
     */
    public function verify(
        string $base64PublicKey,
        PackageChecksum $checksum,
        PackageSignature $signature,
    ): bool;
}
