<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\Binding\Http;

use Psr\Http\Server\RequestHandlerInterface;

/**
 * Creates an administrator route handler against the bounded renderer port.
 *
 * @since  0.2.0
 */
interface AdministratorRouteHandlerFactory
{
    /**
     * @param   AdministratorRouteRenderer  $renderer  Host-bound renderer closing over the validated route and view.
     *
     * @return  RequestHandlerInterface  Executable handler for the declared administrator route.
     *
     * @since   0.2.0
     */
    public function create(AdministratorRouteRenderer $renderer): RequestHandlerInterface;
}
