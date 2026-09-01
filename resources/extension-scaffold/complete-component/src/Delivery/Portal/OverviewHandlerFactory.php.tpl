<?php

declare(strict_types=1);

namespace @@PHP_NAMESPACE@@\Delivery\Portal;

use @@PHP_NAMESPACE@@\Application\OverviewService;
use Kumwe\Extension\Spi\Binding\Http\PortalRouteRenderer;
use Kumwe\Extension\Spi\Binding\Http\PortalRouteHandlerFactory;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Builds the portal handler with the renderer capability granted by the registry.
 *
 * @since  2.0.0
 */
final readonly class OverviewHandlerFactory implements PortalRouteHandlerFactory
{
    /**
     * Bind handler construction to the component application service.
     *
     * @param  OverviewService  $overview  Transport-neutral component service.
     *
     * @since  2.0.0
     */
    public function __construct(private OverviewService $overview)
    {
    }

    /**
     * Build a handler using the object-capability renderer granted by the route registry.
     *
     * @param   PortalRouteRenderer  $renderer  Owner-and-template-bound portal renderer.
     *
     * @return  RequestHandlerInterface  Ready portal overview handler.
     *
     * @since   2.0.0
     */
    public function create(PortalRouteRenderer $renderer): RequestHandlerInterface
    {
        return new OverviewHandler($this->overview, $renderer);
    }
}
