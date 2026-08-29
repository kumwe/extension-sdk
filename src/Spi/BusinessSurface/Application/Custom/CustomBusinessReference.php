<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessSurface\Application\Custom;

use InvalidArgumentException;

/** Validates owner-scoped identifiers used to bind custom handlers to signed schema contracts. @since 0.2.0 */
final class CustomBusinessReference
{
    /**
     * @param   string  $reference  Dotted identifier declared by a package.
     * @param   string  $kind       Reference kind named in the failure.
     *
     * @throws  InvalidArgumentException  When the value is not namespaced, lowercase, or within 191 bytes.
     *
     * @since   0.2.0
     */
    public static function assert(string $reference, string $kind): void
    {
        if (
            strlen($reference) > 191
            || preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)+$/D', $reference) !== 1
        ) {
            throw new InvalidArgumentException(sprintf('A custom business %s reference is invalid.', $kind));
        }
    }

    /** Block instantiation. @since 0.2.0 */
    private function __construct()
    {
    }
}
