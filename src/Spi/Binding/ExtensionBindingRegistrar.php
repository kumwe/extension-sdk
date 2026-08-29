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
use Kumwe\Conversion\Provider\MoneyRateProvider;
use Kumwe\Conversion\Provider\UnitConversionProvider;
use Kumwe\Producer\Render\BlockRenderer;

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
     * Bind the presenter named by one signed `business.field_presentations[].field_type` declaration.
     *
     * @param string $fieldType Owner-scoped field-type identifier from the validated manifest.
     * @param FieldPresenter $presenter Presentation behavior; duplicate or undeclared keys are refused.
     *
     * @since 0.2.0
     */
    public function fieldPresenter(string $fieldType, FieldPresenter $presenter): void;

    /**
     * Bind one signed `integration.rate_providers[].provider_id` to its conversion provider.
     *
     * @param string $identifier Owner-scoped provider identifier from the validated manifest.
     * @param MoneyRateProvider $provider Rate implementation constrained by the declaration's currencies.
     *
     * @since 0.2.0
     */
    public function moneyRateProvider(string $identifier, MoneyRateProvider $provider): void;

    /**
     * Bind one signed `integration.unit_converters[].provider_id` to its conversion provider.
     *
     * @param string $identifier Owner-scoped provider identifier from the validated manifest.
     * @param UnitConversionProvider $provider Unit implementation constrained by the declaration's units.
     *
     * @since 0.2.0
     */
    public function unitConversionProvider(string $identifier, UnitConversionProvider $provider): void;

    /**
     * Bind one signed `business.view_handlers[].handler` to executable view behavior.
     *
     * @param string $identifier Owner-scoped handler identifier from the validated manifest.
     * @param CustomBusinessViewHandler $handler Handler invoked only with its host-validated declaration/query.
     *
     * @since 0.2.0
     */
    public function customBusinessViewHandler(string $identifier, CustomBusinessViewHandler $handler): void;

    /**
     * Bind one signed `business.action_handlers[].handler` to executable action behavior.
     *
     * @param string $identifier Owner-scoped handler identifier from the validated manifest.
     * @param CustomBusinessActionHandler $handler Handler invoked only with its host-validated command.
     *
     * @since 0.2.0
     */
    public function customBusinessActionHandler(string $identifier, CustomBusinessActionHandler $handler): void;

    /**
     * Bind one signed administrator route name to a handler factory.
     *
     * @param string $name Exact `administrator.routes[].name` owned by the active package.
     * @param AdministratorRouteHandlerFactory $factory Factory receiving only the host-bound route renderer.
     *
     * @since 0.2.0
     */
    public function administratorRoute(string $name, AdministratorRouteHandlerFactory $factory): void;

    /**
     * Bind one signed portal route name to a handler factory.
     *
     * @param string $name Exact `portal.routes[].name` owned by the active package.
     * @param PortalRouteHandlerFactory $factory Factory receiving only the host-bound route renderer.
     *
     * @since 0.2.0
     */
    public function portalRoute(string $name, PortalRouteHandlerFactory $factory): void;

    /**
     * Bind one signed synchronous domain-listener identifier.
     *
     * @param string $identifier Exact `integration.domain_listeners[].listener_id` for this owner.
     * @param DomainEventHandler $handler Behavior invoked with the canonical listener definition and event.
     *
     * @since 0.2.0
     */
    public function domainListener(string $identifier, DomainEventHandler $handler): void;

    /**
     * Bind one signed durable event-consumer identifier.
     *
     * @param string $identifier Exact `integration.consumers[].consumer_id` for this owner.
     * @param IntegrationEventHandler $handler Idempotent behavior receiving the validated consumer definition.
     *
     * @since 0.2.0
     */
    public function eventConsumer(string $identifier, IntegrationEventHandler $handler): void;

    /**
     * Bind one signed automation job type.
     *
     * @param string $identifier Exact `integration.jobs[].job_type` for this owner.
     * @param JobHandler $handler Idempotent behavior receiving validated schema, payload, and context.
     *
     * @since 0.2.0
     */
    public function jobHandler(string $identifier, JobHandler $handler): void;

    /**
     * Bind one signed deterministic reporting projection.
     *
     * @param string $identifier Exact `integration.projections[].identifier` for this owner.
     * @param ProjectionBuilder $builder Builder receiving the canonical definition, event, and bounded writer.
     *
     * @since 0.2.0
     */
    public function projection(string $identifier, ProjectionBuilder $builder): void;

    /**
     * Bind one signed outbound webhook adapter.
     *
     * @param string $identifier Exact `integration.webhooks[].adapter_id` for this owner.
     * @param IntegrationEventTransport $transport Transport receiving the validated webhook definition and event.
     *
     * @since 0.2.0
     */
    public function webhook(string $identifier, IntegrationEventTransport $transport): void;

    /**
     * Bind one renderer referenced by a signed block-definition host binding.
     *
     * One renderer identifier may serve multiple block documents but is bound exactly once.
     *
     * @param string $identifier Owner-scoped non-empty `composition.host_bindings[].renderer` identifier.
     * @param BlockRenderer $renderer Producer renderer invoked only for its exact admitted block coordinate.
     *
     * @since 0.2.0
     */
    public function studioPreviewRenderer(string $identifier, BlockRenderer $renderer): void;
}
