<?php

declare(strict_types=1);

namespace KumweContract\ManifestOne;

use Kumwe\Extension\Spi\Runtime\BootableExtension;
use Kumwe\Extension\Spi\Runtime\ExtensionContainer;

/**
 * Canonical schema-one lifecycle fixture.
 *
 * @since  1.0.0
 */
final class Provider implements BootableExtension
{
    /** @inheritDoc */
    public function register(ExtensionContainer $container): void
    {
        $container->share('extension.kumwe.contract-manifest-one.greeting', static fn (): Greeting => new Greeting());
    }

    /** @inheritDoc */
    public function boot(ExtensionContainer $container): void
    {
        $greeting = $container->get('extension.kumwe.contract-manifest-one.greeting');
        if ($greeting instanceof Greeting) {
            $greeting->boot();
        }
    }
}
