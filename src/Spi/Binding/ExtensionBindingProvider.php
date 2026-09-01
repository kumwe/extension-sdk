<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\Binding;

use Kumwe\Extension\Spi\Application\ExtensionServiceProvider;
use Kumwe\Extension\Spi\Runtime\ExtensionContainer;

/**
 * Optional provider phase that binds executable code to signed manifest declaration identifiers.
 *
 * The manifest is the only declarative source. A binding provider cannot add or rewrite a declaration;
 * it can only attach executable implementations to identifiers the host already validated.
 *
 * @since  0.2.0
 */
interface ExtensionBindingProvider extends ExtensionServiceProvider
{
    /**
     * Bind executable implementations after extension-owned services have been registered.
     *
     * @param   ExtensionBindingRegistrar  $bindings   Owner-bound binding sink.
     * @param   ExtensionContainer         $container  Owner-scoped service surface.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function bind(ExtensionBindingRegistrar $bindings, ExtensionContainer $container): void;
}
