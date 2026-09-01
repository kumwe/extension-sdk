<?php

declare(strict_types=1);

namespace KumweContract\ManifestThree;

use Kumwe\Extension\Spi\Binding\ExtensionBindingProvider;
use Kumwe\Extension\Spi\Binding\ExtensionBindingRegistrar;
use Kumwe\Extension\Spi\Runtime\ExtensionContainer;

/**
 * Schema-three fixture: declarations stay in the signed manifest and the presenter is bound by ID.
 *
 * @since  2.0.0
 */
final class Provider implements ExtensionBindingProvider
{
    /** @inheritDoc */
    public function register(ExtensionContainer $container): void
    {
    }

    /** @inheritDoc */
    public function bind(ExtensionBindingRegistrar $bindings, ExtensionContainer $container): void
    {
        $bindings->fieldPresenter('kumwe.contract-manifest-three.grade', new GradeFieldPresenter());
    }
}
