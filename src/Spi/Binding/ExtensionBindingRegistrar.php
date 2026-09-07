<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\Binding;

use Kumwe\Extension\Spi\Application\Automation\JobHandler;
use Kumwe\Extension\Spi\BusinessIntegration\Application\DomainEventHandler;
use Kumwe\Extension\Spi\BusinessIntegration\Application\IntegrationEventHandler;
use Kumwe\Extension\Spi\BusinessIntegration\Application\IntegrationEventTransport;
use Kumwe\Reporting\Contract\ProjectionBuilder;
use Kumwe\BusinessSurface\Contract\Application\Custom\CustomBusinessActionHandler;
use Kumwe\BusinessSurface\Contract\Application\Custom\CustomBusinessViewHandler;
use Kumwe\BusinessSurface\Contract\Presentation\Field\FieldPresenter;
use Kumwe\Extension\Spi\Binding\Http\AdministratorRouteHandlerFactory;
use Kumwe\Extension\Spi\Binding\Http\PortalRouteHandlerFactory;
use Kumwe\Extension\Spi\Studio\Application\Preview\StudioPreviewBlockRenderer;
use Kumwe\Conversion\Provider\MoneyRateProvider;
use Kumwe\Conversion\Provider\UnitConversionProvider;

/**
 * Owner-bound sink for executable implementations referenced by a signed manifest.
 *
 * Every identifier must already exist in the active manifest. Implementations fail a binding when the
 * identifier is undeclared, belongs to another extension, is bound twice, or names the wrong surface.
 *
 * @since  0.2.0
 */
interface ExtensionBindingRegistrar
{
    /**
     * Binds a presenter for a custom field type declared in the manifest.
     *
     * @param   string          $fieldType  Manifest-declared field type whose values the presenter renders.
     * @param   FieldPresenter  $presenter  Presenter producing the display representation for those values.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function fieldPresenter(string $fieldType, FieldPresenter $presenter): void;

    /**
     * Binds a provider of money exchange rates declared in the manifest.
     *
     * @param   string             $identifier  Manifest-declared provider identifier being bound.
     * @param   MoneyRateProvider  $provider    Implementation supplying money exchange rates to the host.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function moneyRateProvider(string $identifier, MoneyRateProvider $provider): void;

    /**
     * Binds a provider of unit conversion factors declared in the manifest.
     *
     * @param   string                  $identifier  Manifest-declared provider identifier being bound.
     * @param   UnitConversionProvider  $provider    Implementation supplying unit conversion factors to the host.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function unitConversionProvider(string $identifier, UnitConversionProvider $provider): void;

    /**
     * Binds the handler executing a custom business view declared in the manifest.
     *
     * @param   string                     $identifier  Manifest-declared custom view identifier being bound.
     * @param   CustomBusinessViewHandler  $handler     Handler producing the custom view for the business surface.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function customBusinessViewHandler(string $identifier, CustomBusinessViewHandler $handler): void;

    /**
     * Binds the handler executing a custom business action declared in the manifest.
     *
     * @param   string                       $identifier  Manifest-declared custom action identifier being bound.
     * @param   CustomBusinessActionHandler  $handler     Handler executing the action on the business surface.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function customBusinessActionHandler(string $identifier, CustomBusinessActionHandler $handler): void;

    /**
     * Binds the handler factory for an administrator-area HTTP route declared in the manifest.
     *
     * @param   string                            $name     Manifest-declared administrator route name being bound.
     * @param   AdministratorRouteHandlerFactory  $factory  Factory producing the executable handler for the route.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function administratorRoute(string $name, AdministratorRouteHandlerFactory $factory): void;

    /**
     * Binds the handler factory for a portal-area HTTP route declared in the manifest.
     *
     * @param   string                     $name     Manifest-declared portal route name being bound.
     * @param   PortalRouteHandlerFactory  $factory  Factory producing the executable handler for the route.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function portalRoute(string $name, PortalRouteHandlerFactory $factory): void;

    /**
     * Binds a listener for a domain event subscription declared in the manifest.
     *
     * @param   string              $identifier  Manifest-declared listener identifier being bound.
     * @param   DomainEventHandler  $handler     Handler reacting to the subscribed domain events.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function domainListener(string $identifier, DomainEventHandler $handler): void;

    /**
     * Binds a consumer for an integration event subscription declared in the manifest.
     *
     * @param   string                   $identifier  Manifest-declared consumer identifier being bound.
     * @param   IntegrationEventHandler  $handler     Handler processing the consumed integration events.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function eventConsumer(string $identifier, IntegrationEventHandler $handler): void;

    /**
     * Binds the handler executing an automation job declared in the manifest.
     *
     * @param   string      $identifier  Manifest-declared job identifier being bound.
     * @param   JobHandler  $handler     Handler executing the automation job when the host dispatches it.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function jobHandler(string $identifier, JobHandler $handler): void;

    /**
     * Binds the builder maintaining a reporting projection declared in the manifest.
     *
     * @param   string             $identifier  Manifest-declared projection identifier being bound.
     * @param   ProjectionBuilder  $builder     Builder keeping the reporting projection up to date.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function projection(string $identifier, ProjectionBuilder $builder): void;

    /**
     * Binds the transport delivering integration events for a webhook declared in the manifest.
     *
     * @param   string                     $identifier  Manifest-declared webhook identifier being bound.
     * @param   IntegrationEventTransport  $transport   Transport delivering integration events to the external endpoint.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function webhook(string $identifier, IntegrationEventTransport $transport): void;

    /**
     * Binds the renderer producing studio preview output for a block declared in the manifest.
     *
     * @param   string                      $identifier  Manifest-declared preview block identifier being bound.
     * @param   StudioPreviewBlockRenderer  $renderer    Renderer producing the studio preview markup for the block.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function studioPreviewRenderer(string $identifier, StudioPreviewBlockRenderer $renderer): void;
}
