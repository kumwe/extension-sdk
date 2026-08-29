<?php

declare(strict_types=1);

namespace KumweContract\ManifestTwo;

use Kumwe\Extension\Spi\Application\ExtensionServiceProvider;
use Kumwe\Extension\Spi\Runtime\ExtensionContainer;

/**
 * Canonical schema-two lifecycle fixture; signed declarations come only from the manifest.
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
