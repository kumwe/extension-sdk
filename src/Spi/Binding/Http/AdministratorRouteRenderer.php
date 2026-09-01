<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\Binding\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Object-capability renderer bound by the host to one validated administrator route and view.
 *
 * A host creates a distinct instance per signed route declaration. The instance closes over owner, view,
 * active navigation and shell policy; extension code can provide only the model and active host-issued request.
 *
 * @since  0.2.0
 */
interface AdministratorRouteRenderer
{
    /**
     * @param   array<string, mixed>    $model    Template model.
     * @param   ServerRequestInterface  $request  Active request carrying host-validated session state.
     *
     * @return  string  Complete rendered HTML.
     *
     * @since   0.2.0
     */
    public function render(array $model, ServerRequestInterface $request): string;
}
