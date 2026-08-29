<?php

declare(strict_types=1);

namespace KumweContract\ManifestSix;

use Kumwe\Extension\Spi\Binding\ExtensionBindingProvider;
use Kumwe\Extension\Spi\Binding\ExtensionBindingRegistrar;
use Kumwe\Extension\Spi\Runtime\ExtensionContainer;
use LogicException;

/** Schema-six fixture: canonical documents live in the manifest and preview code binds by ID. @since 2.0.0 */
final class Provider implements ExtensionBindingProvider
{
    /** @var string @since 2.0.0 */
    private const RENDERER = 'extension.kumwe.contract-manifest-six.grid-preview';

    /** @inheritDoc */
    public function register(ExtensionContainer $container): void
    {
        $container->share(
            self::RENDERER,
            static fn (ExtensionContainer $container): GridPreviewRenderer => new GridPreviewRenderer(),
        );
    }

    /** @inheritDoc */
    public function bind(ExtensionBindingRegistrar $bindings, ExtensionContainer $container): void
    {
        $renderer = $container->get(self::RENDERER);
        if (!$renderer instanceof GridPreviewRenderer) {
            throw new LogicException('The manifest-six preview renderer is unavailable.');
        }
        $bindings->studioPreviewRenderer('kumwe.contract-manifest-six/grid-preview', $renderer);
    }
}
