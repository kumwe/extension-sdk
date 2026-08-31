<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\Binding;

use Kumwe\Extension\Spi\Application\Automation\JobHandler;
use Kumwe\Extension\Spi\BusinessIntegration\Application\DomainEventHandler;
use Kumwe\Extension\Spi\BusinessIntegration\Application\IntegrationEventHandler;
use Kumwe\Extension\Spi\BusinessIntegration\Application\IntegrationEventTransport;
use Kumwe\Extension\Spi\BusinessReporting\Application\ProjectionBuilder;
use Kumwe\Extension\Spi\BusinessSurface\Application\Custom\CustomBusinessActionHandler;
use Kumwe\Extension\Spi\BusinessSurface\Application\Custom\CustomBusinessViewHandler;
use Kumwe\Extension\Spi\BusinessSurface\Presentation\Field\FieldPresenter;
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
    /** @since 0.2.0 */
    public function fieldPresenter(string $fieldType, FieldPresenter $presenter): void;

    /** @since 0.2.0 */
    public function moneyRateProvider(string $identifier, MoneyRateProvider $provider): void;

    /** @since 0.2.0 */
    public function unitConversionProvider(string $identifier, UnitConversionProvider $provider): void;

    /** @since 0.2.0 */
    public function customBusinessViewHandler(string $identifier, CustomBusinessViewHandler $handler): void;

    /** @since 0.2.0 */
    public function customBusinessActionHandler(string $identifier, CustomBusinessActionHandler $handler): void;

    /** @since 0.2.0 */
    public function administratorRoute(string $name, AdministratorRouteHandlerFactory $factory): void;

    /** @since 0.2.0 */
    public function portalRoute(string $name, PortalRouteHandlerFactory $factory): void;

    /** @since 0.2.0 */
    public function domainListener(string $identifier, DomainEventHandler $handler): void;

    /** @since 0.2.0 */
    public function eventConsumer(string $identifier, IntegrationEventHandler $handler): void;

    /** @since 0.2.0 */
    public function jobHandler(string $identifier, JobHandler $handler): void;

    /** @since 0.2.0 */
    public function projection(string $identifier, ProjectionBuilder $builder): void;

    /** @since 0.2.0 */
    public function webhook(string $identifier, IntegrationEventTransport $transport): void;

    /** @since 0.2.0 */
    public function studioPreviewRenderer(string $identifier, StudioPreviewBlockRenderer $renderer): void;
}
