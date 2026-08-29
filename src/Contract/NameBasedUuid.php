<?php

declare(strict_types=1);

namespace Kumwe\Extension\Contract;

use InvalidArgumentException;

/**
 * Derives RFC 4122 version-5 (SHA-1, name-based) UUIDs deterministically.
 *
 * Everything in this SDK that needs a stable identifier derives it from its inputs — the charter
 * forbids clocks and randomness in the library, so this one type is the SDK's whole UUID surface.
 * The derivation is the RFC's: the namespace UUID's
 * sixteen raw bytes are concatenated with the name, hashed with SHA-1, truncated to sixteen bytes, and
 * stamped with version 5 and the RFC 4122 variant. The output is byte-identical to what `ramsey/uuid`
 * produces for the same inputs, proven against the RFC reference vectors.
 *
 * @since  0.1.0
 */
final readonly class NameBasedUuid
{
    /**
     * The RFC 4122 URL namespace, under which the scaffolder derives entity identifiers.
     *
     * @var    string
     * @since  0.1.0
     */
    public const string NAMESPACE_URL = '6ba7b811-9dad-11d1-80b4-00c04fd430c8';

    /**
     * Derive the version-5 UUID of one name inside one namespace.
     *
     * @param   string  $namespace  Namespace UUID in canonical or brace-free hexadecimal form.
     * @param   string  $name       Name bytes to derive from, hashed exactly as given.
     *
     * @return  string  Canonical lowercase `xxxxxxxx-xxxx-5xxx-yxxx-xxxxxxxxxxxx` rendering.
     *
     * @throws  InvalidArgumentException  When the namespace is not a well-formed UUID.
     *
     * @since   0.1.0
     */
    public static function v5(string $namespace, string $name): string
    {
        $hexadecimal = strtolower(str_replace(['-', '{', '}', 'urn:uuid:'], '', $namespace));
        if (preg_match('/^[0-9a-f]{32}$/D', $hexadecimal) !== 1) {
            throw new InvalidArgumentException('A name-based UUID namespace must be a well-formed UUID.');
        }
        $bytes = substr(sha1((string) hex2bin($hexadecimal) . $name, true), 0, 16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x50);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
        $digits = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($digits, 0, 8),
            substr($digits, 8, 4),
            substr($digits, 12, 4),
            substr($digits, 16, 4),
            substr($digits, 20, 12),
        );
    }
}
