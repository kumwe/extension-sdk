<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\Presentation;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Object-capability renderer bound by the host to one validated portal route and template.
 *
 * A host creates a distinct instance per signed route declaration. The instance closes over owner,
 * template and portal-session policy; extension code can provide only the model and host-issued request.
 *
 * @since  0.2.0
 */
interface PortalRouteRenderer
{
    /**
     * @param   array<string, mixed>    $model    Template model.
     * @param   ServerRequestInterface  $request  Active request carrying host-validated portal state.
     *
     * @return  string  Complete rendered HTML.
     *
     * @since   0.2.0
     */
    public function render(array $model, ServerRequestInterface $request): string;
}
