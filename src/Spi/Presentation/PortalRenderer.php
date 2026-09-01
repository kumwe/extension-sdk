<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\Presentation;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Bounded portal-shell renderer exposed to extension route factories.
 *
 * @since  0.2.0
 */
interface PortalRenderer
{
    /**
     * Render the host-bound portal route declaration inside its shell.
     *
     * @param   array<string, mixed>    $model    Template model.
     * @param   ServerRequestInterface  $request  Active request carrying host-owned portal session state.
     *
     * @return  string  Complete rendered HTML.
     *
     * @since   0.2.0
     */
    public function render(array $model, ServerRequestInterface $request): string;
}
