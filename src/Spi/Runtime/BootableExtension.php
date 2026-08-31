<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\Runtime;

use Kumwe\Extension\Spi\Application\ExtensionServiceProvider;

/** Optional behavior-only lifecycle phase run after every active provider has registered services. @since 0.2.0 */
interface BootableExtension extends ExtensionServiceProvider
{
    /**
     * Start behavior that requires a complete owner-scoped container.
     *
     * Declarations are forbidden in this phase; all routes, events and contributions come from the manifest
     * and executable implementations are attached through the canonical binding provider.
     *
     * @since  0.2.0
     */
    public function boot(ExtensionContainer $container): void;
}
