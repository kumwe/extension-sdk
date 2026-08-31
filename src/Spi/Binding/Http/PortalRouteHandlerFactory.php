<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\Binding\Http;

use Psr\Http\Server\RequestHandlerInterface;

/**
 * Creates a portal route handler against the bounded renderer port.
 *
 * @since  0.2.0
 */
interface PortalRouteHandlerFactory
{
    /** @since 0.2.0 */
    public function create(PortalRouteRenderer $renderer): RequestHandlerInterface;
}
