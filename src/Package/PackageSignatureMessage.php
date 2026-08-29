<?php

declare(strict_types=1);

namespace Kumwe\Extension\Package;

/**
 * Canonical domain-separated message signed for an extension package.
 *
 * Signing a bare hexadecimal digest permits accidental cross-protocol verification when the same key is
 * used elsewhere. The fixed domain and algorithm label bind every signature to this package protocol.
 *
 * @since  0.2.0
 */
final readonly class PackageSignatureMessage
{
    /**
     * Versioned signature domain, including an unambiguous NUL separator.
     *
     * @var    string
     * @since  0.2.0
     */
    public const string DOMAIN = "kumwe-extension-package-signature-v2\0sha256:";

    /**
     * Render the exact bytes every signer and verifier must use.
     *
     * @param   PackageChecksum  $checksum  Digest of the complete package archive.
     *
     * @return  non-empty-string  Domain-separated ASCII signature message.
     *
     * @since   0.2.0
     */
    public static function forChecksum(PackageChecksum $checksum): string
    {
        return self::DOMAIN . (string) $checksum;
    }
}
