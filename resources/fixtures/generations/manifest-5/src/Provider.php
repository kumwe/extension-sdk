<?php

declare(strict_types=1);

namespace KumweContract\ManifestFive;

use Kumwe\Extension\Spi\Application\ExtensionServiceProvider;
use Kumwe\Extension\Spi\Runtime\ExtensionContainer;

/**
 * Canonical schema-five lifecycle fixture; signed declarations come only from the manifest.
 *
 * @since  1.0.0
 */
final class Provider implements ExtensionServiceProvider
{
    /** @inheritDoc */
    public function register(ExtensionContainer $container): void
    {
    }
}
