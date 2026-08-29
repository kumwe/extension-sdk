<?php

declare(strict_types=1);

namespace @@PHP_NAMESPACE@@\Delivery\Administrator;

use @@PHP_NAMESPACE@@\Application\OverviewService;
use Kumwe\Extension\Spi\Binding\Http\AdministratorRouteRenderer;
use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Adapts an authorized administrator request to the component overview.
 *
 * @since  2.0.0
 */
final readonly class OverviewHandler implements RequestHandlerInterface
{
    /**
     * Bind request adaptation to the application service and shell renderer.
     *
     * @param  OverviewService        $overview  Transport-neutral component service.
     * @param  AdministratorRouteRenderer  $renderer  Owner-and-view-bound administrator renderer.
     *
     * @since  2.0.0
     */
    public function __construct(private OverviewService $overview, private AdministratorRouteRenderer $renderer)
    {
    }

    /**
     * Render the overview from an already authenticated and authorized administrator request.
     *
     * @param   ServerRequestInterface  $request  Request carrying administrator context and session attributes.
     *
     * @return  ResponseInterface  Non-cacheable HTML response rendered inside the administrator shell.
     *
     * @since   2.0.0
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $model = $this->overview->overview(
            \Kumwe\Extension\Spi\Http\ExtensionRequest::context($request),
        );

        return new HtmlResponse($this->renderer->render($model, $request), 200, ['Cache-Control' => 'no-store']);
    }
}
