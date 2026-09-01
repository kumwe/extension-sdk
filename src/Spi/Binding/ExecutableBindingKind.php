<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\Binding;

/** Closed executable surfaces whose implementations bind to signed manifest identifiers. @since 0.2.0 */
enum ExecutableBindingKind: string
{
    /** Field presentation strategy. @since 0.2.0 */
    case FieldPresenter = 'field_presenter';
    /** Money-rate provider from kumwe/conversion. @since 0.2.0 */
    case MoneyRateProvider = 'money_rate_provider';
    /** Unit-conversion provider from kumwe/conversion. @since 0.2.0 */
    case UnitConversionProvider = 'unit_conversion_provider';
    /** Custom business view handler. @since 0.2.0 */
    case CustomBusinessViewHandler = 'custom_business_view_handler';
    /** Custom business action handler. @since 0.2.0 */
    case CustomBusinessActionHandler = 'custom_business_action_handler';
    /** Administrator route handler factory. @since 0.2.0 */
    case AdministratorRoute = 'administrator_route';
    /** Portal route handler factory. @since 0.2.0 */
    case PortalRoute = 'portal_route';
    /** Synchronous domain listener. @since 0.2.0 */
    case DomainListener = 'domain_listener';
    /** Durable event consumer. @since 0.2.0 */
    case EventConsumer = 'event_consumer';
    /** Durable job handler. @since 0.2.0 */
    case JobHandler = 'job_handler';
    /** Deterministic projection builder. @since 0.2.0 */
    case Projection = 'projection';
    /** Outbound webhook transport. @since 0.2.0 */
    case Webhook = 'webhook';
    /** Studio preview block renderer. @since 0.2.0 */
    case StudioPreviewRenderer = 'studio_preview_renderer';
}
