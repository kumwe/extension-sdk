<?php

declare(strict_types=1);

namespace Kumwe\Extension\Manifest;

use InvalidArgumentException;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\JobContributionDefinition;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\ConsumerIdempotency;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\DomainListenerDefinition;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\EventConsumerDefinition;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\EventSensitivity;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\WebhookContributionDefinition;
use Kumwe\Extension\Spi\BusinessReporting\Domain\ProjectionDefinition;
use Kumwe\Extension\Spi\BusinessReporting\Domain\ReportDefinitionGuard;
use Kumwe\Extension\Spi\BusinessReporting\Domain\ReportValueType;
use Kumwe\Extension\Spi\BusinessSurface\Application\Custom\CustomBusinessActionDeclaration;
use Kumwe\Extension\Spi\BusinessSurface\Application\Custom\CustomBusinessReference;
use Kumwe\Extension\Spi\BusinessSurface\Application\Custom\CustomBusinessViewDeclaration;
use Kumwe\Extension\Spi\BusinessSurface\Presentation\Field\FieldPresentationContribution;
use Kumwe\Extension\Spi\Contribution\ContributionOwner;
use Kumwe\Extension\Support\CanonicalJson;

/**
 * Structural and referential validator for the complete canonical contribution graph.
 *
 * Host-domain admission may impose additional business semantics, but it consumes only a graph that has
 * already passed this owner, type, bound, duplicate and reference boundary.
 *
 * @internal
 * @since  0.2.0
 */
final class ManifestContributionGraphValidator
{
    /**
     * @param  ContributionOwner     $owner   Signed package owner.
     * @param  array<string, mixed>  $data    Complete contribution graph.
     * @since  0.2.0
     */
    public static function validate(ContributionOwner $owner, array $data): void
    {
        $capabilities = self::identities(
            self::objects($data['capabilities'] ?? [], 'capabilities'),
            'id',
            $owner,
            'capability',
        );
        $administrator = self::object($data['administrator'] ?? [], 'administrator');
        $portal = self::object($data['portal'] ?? [], 'portal');
        $business = self::object($data['business'] ?? [], 'business');
        $integration = self::object($data['integration'] ?? [], 'integration');
        $interface = self::object($data['interface'] ?? [], 'interface');
        $content = self::object($data['content'] ?? [], 'content');
        self::validateGraphical($owner, $capabilities, $administrator, $portal, $interface);
        self::validateResourcePolicies($owner, $capabilities, $data);
        [$definitionHandles, $fieldTypes] = self::validateBusiness($owner, $business);
        self::validateIntegration($owner, $capabilities, $definitionHandles, $integration);
        self::validateContent($owner, $content);
        foreach (self::objects($business['field_presentations'] ?? [], 'business.field_presentations') as $item) {
            $presentation = FieldPresentationContribution::fromArray($item);
            $fieldType = $presentation->fieldType;
            $owner->assertOwns($fieldType, 'field type');
            if (!isset($fieldTypes[$fieldType])) {
                throw new InvalidArgumentException('A field presentation must reference a declared field type.');
            }
        }
    }

    /**
     * @param  ContributionOwner     $owner         Signed package owner.
     * @param  array<string, true>   $capabilities  Declared capability identities.
     * @param  array<string, mixed>  $data          Complete contribution graph.
     *
     * @since  0.2.0
     */
    private static function validateResourcePolicies(
        ContributionOwner $owner,
        array $capabilities,
        array $data,
    ): void {
        $policies = [];
        foreach (self::objects($data['resource_policies'] ?? [], 'resource_policies') as $item) {
            $identifier = self::owned($owner, $item, 'id', 'resource policy');
            $capability = self::requiredString($item, 'capability', 'resource policy');
            if (!isset($capabilities[$capability])) {
                throw new InvalidArgumentException('A resource policy references an undeclared capability.');
            }
            self::unique($policies, $identifier, 'resource policy');
        }
    }

    /**
     * @param  ContributionOwner     $owner          Signed package owner.
     * @param  array<string, true>   $capabilities   Declared capability identities.
     * @param  array<string, mixed>  $administrator  Administrator declarations.
     * @param  array<string, mixed>  $portal         Portal declarations.
     * @param  array<string, mixed>  $interface      KIS declarations.
     *
     * @since  0.2.0
     */
    private static function validateGraphical(
        ContributionOwner $owner,
        array $capabilities,
        array $administrator,
        array $portal,
        array $interface,
    ): void {
        $workspaces = self::identities(
            self::objects($administrator['workspaces'] ?? [], 'administrator.workspaces'),
            'id',
            $owner,
            'workspace',
        );
        $views = self::identities(
            self::objects($administrator['views'] ?? [], 'administrator.views'),
            'name',
            $owner,
            'view',
        );
        $routes = self::identities(
            self::objects($administrator['routes'] ?? [], 'administrator.routes'),
            'name',
            $owner,
            'route',
        );
        foreach (self::objects($administrator['navigation'] ?? [], 'administrator.navigation') as $item) {
            $id = self::owned($owner, $item, 'id', 'navigation');
            $workspace = self::requiredString($item, 'workspace', 'administrator navigation');
            $capability = self::requiredString($item, 'capability', 'administrator navigation');
            if (!isset($workspaces[$workspace]) || !isset($capabilities[$capability])) {
                throw new InvalidArgumentException(sprintf('Administrator navigation %s has an undeclared reference.', $id));
            }
        }
        foreach (self::objects($administrator['routes'] ?? [], 'administrator.routes') as $item) {
            $name = self::requiredString($item, 'name', 'administrator route');
            $capability = self::requiredString($item, 'capability', 'administrator route');
            $view = self::requiredString($item, 'view', 'administrator route');
            self::stringList($item['methods'] ?? null, 'administrator route methods', 16, false);
            if (!isset($capabilities[$capability]) || !isset($views[$view]) || !isset($routes[$name])) {
                throw new InvalidArgumentException('An administrator route has an undeclared reference.');
            }
        }

        $portalWorkspaces = self::identities(
            self::objects($portal['workspaces'] ?? [], 'portal.workspaces'),
            'id',
            $owner,
            'portal workspace',
        );
        $templates = self::identities(
            self::objects($portal['templates'] ?? [], 'portal.templates'),
            'name',
            $owner,
            'portal template',
        );
        foreach (self::objects($portal['navigation'] ?? [], 'portal.navigation') as $item) {
            $id = self::owned($owner, $item, 'id', 'portal navigation');
            $workspace = self::requiredString($item, 'workspace', 'portal navigation');
            $capability = self::requiredString($item, 'capability', 'portal navigation');
            if (!isset($portalWorkspaces[$workspace]) || !isset($capabilities[$capability])) {
                throw new InvalidArgumentException(sprintf('Portal navigation %s has an undeclared reference.', $id));
            }
        }
        self::identities(
            self::objects($portal['routes'] ?? [], 'portal.routes'),
            'name',
            $owner,
            'portal route',
        );
        foreach (self::objects($portal['routes'] ?? [], 'portal.routes') as $item) {
            $capability = self::requiredString($item, 'capability', 'portal route');
            $template = self::requiredString($item, 'template', 'portal route');
            self::stringList($item['methods'] ?? null, 'portal route methods', 16, false);
            if (!isset($capabilities[$capability]) || !isset($templates[$template])) {
                throw new InvalidArgumentException('A portal route has an undeclared reference.');
            }
        }

        $surfaces = [];
        foreach (self::objects($interface['surfaces'] ?? [], 'interface.surfaces') as $item) {
            $surface = self::owned($owner, $item, 'surface', 'interface surface');
            if (isset($surfaces[$surface])) {
                throw new InvalidArgumentException('An interface surface is declared more than once.');
            }
            foreach (self::stringList($item['capabilities'] ?? null, 'interface capabilities', 32, false) as $capability) {
                if (!isset($capabilities[$capability])) {
                    throw new InvalidArgumentException('An interface surface references an undeclared capability.');
                }
            }
            $surfaces[$surface] = true;
        }
    }

    /**
     * @param   ContributionOwner     $owner     Signed package owner.
     * @param   array<string, mixed>  $business  Business declaration section.
     *
     * @return  array{array<string, true>, array<string, true>}  Definition and field-type identities.
     *
     * @since   0.2.0
     */
    private static function validateBusiness(ContributionOwner $owner, array $business): array
    {
        $fieldTypes = [];
        foreach (self::objects($business['field_types'] ?? [], 'business.field_types') as $item) {
            self::keys(
                $item,
                ['id', 'label', 'description', 'value_type', 'storage_type', 'configuration_keys'],
                ['id', 'label', 'description', 'value_type', 'storage_type'],
                'field type',
            );
            $id = self::owned($owner, $item, 'id', 'field type');
            self::requiredString($item, 'label', 'field type');
            self::requiredString($item, 'description', 'field type');
            self::requiredString($item, 'value_type', 'field type');
            self::requiredString($item, 'storage_type', 'field type');
            self::stringList($item['configuration_keys'] ?? [], 'field type configuration keys', 32, true);
            self::unique($fieldTypes, $id, 'field type');
        }

        $viewContracts = [];
        $actionContracts = [];
        $customReferences = [];
        foreach (self::objects($business['view_handlers'] ?? [], 'business.view_handlers') as $item) {
            $declaration = CustomBusinessViewDeclaration::fromManifest($item);
            $owner->assertOwns($declaration->handler, 'custom business view handler');
            $owner->assertOwns($declaration->schema, 'custom business view schema');
            if (isset($viewContracts[$declaration->handler])) {
                throw new InvalidArgumentException('A custom business view handler is declared more than once.');
            }
            $viewContracts[$declaration->handler] = $declaration->schema;
            self::unique($customReferences, $declaration->handler, 'custom business reference');
            self::unique($customReferences, $declaration->schema, 'custom business reference');
        }
        foreach (self::objects($business['action_handlers'] ?? [], 'business.action_handlers') as $item) {
            $declaration = CustomBusinessActionDeclaration::fromManifest($item);
            $owner->assertOwns($declaration->handler, 'custom business action handler');
            $owner->assertOwns($declaration->schema, 'custom business action schema');
            if (isset($actionContracts[$declaration->handler])) {
                throw new InvalidArgumentException('A custom business action handler is declared more than once.');
            }
            $actionContracts[$declaration->handler] = $declaration->schema;
            self::unique($customReferences, $declaration->handler, 'custom business reference');
            self::unique($customReferences, $declaration->schema, 'custom business reference');
        }

        $definitions = [];
        foreach (self::objects($business['definitions'] ?? [], 'business.definitions') as $item) {
            self::keys($item, [
                'id', 'owner', 'site', 'handle', 'singular_label', 'plural_label', 'status', 'definition_version',
                'storage_mode', 'identity_strategy', 'scope', 'audit_enabled', 'revisions_enabled', 'fields',
                'relationships', 'views', 'actions', 'workflow', 'compatibility_metadata',
                'administrator_exposure', 'portal_exposure', 'public_exposure', 'soft_delete_enabled',
                'record_invariants', 'portal_operations', 'label_translations',
            ], [
                'id', 'owner', 'site', 'handle', 'singular_label', 'plural_label', 'status', 'definition_version',
                'storage_mode', 'identity_strategy', 'scope', 'fields', 'relationships', 'views', 'actions',
            ], 'business definition');
            $declaredOwner = self::object($item['owner'] ?? null, 'business definition owner');
            self::keys($declaredOwner, ['type', 'identifier'], ['type', 'identifier'], 'business definition owner');
            if (
                self::requiredString($declaredOwner, 'type', 'business definition owner') !== 'extension'
                || self::requiredString($declaredOwner, 'identifier', 'business definition owner') !== $owner->identifier()
            ) {
                throw new InvalidArgumentException('A business definition must belong to its signed package.');
            }
            self::requiredString($item, 'id', 'business definition');
            $handle = self::owned($owner, $item, 'handle', 'business definition');
            self::positiveInteger($item['definition_version'] ?? null, 'business definition version', 65_535);
            $fields = self::objects($item['fields'] ?? null, 'business definition fields');
            if ($fields === []) {
                throw new InvalidArgumentException('A business definition requires at least one field.');
            }
            self::objects($item['relationships'] ?? null, 'business definition relationships');
            foreach (self::objects($item['views'] ?? null, 'business definition views') as $view) {
                $handler = $view['handler'] ?? null;
                $schema = $view['schema'] ?? null;
                if (($handler === null) !== ($schema === null)) {
                    throw new InvalidArgumentException('A custom business view requires both handler and schema.');
                }
                if ($handler !== null) {
                    if (!is_string($handler) || !is_string($schema)) {
                        throw new InvalidArgumentException('A custom business view reference must be a string.');
                    }
                    CustomBusinessReference::assert($handler, 'view handler');
                    CustomBusinessReference::assert($schema, 'view schema');
                    if (($viewContracts[$handler] ?? null) !== $schema) {
                        throw new InvalidArgumentException('A business view references an undeclared handler/schema pair.');
                    }
                }
            }
            foreach (self::objects($item['actions'] ?? null, 'business definition actions') as $action) {
                $handler = $action['handler'] ?? null;
                $schema = $action['schema'] ?? null;
                if (($handler === null) !== ($schema === null)) {
                    throw new InvalidArgumentException('A custom business action requires both handler and schema.');
                }
                if ($handler !== null) {
                    if (!is_string($handler) || !is_string($schema)) {
                        throw new InvalidArgumentException('A custom business action reference must be a string.');
                    }
                    CustomBusinessReference::assert($handler, 'action handler');
                    CustomBusinessReference::assert($schema, 'action schema');
                    if (($actionContracts[$handler] ?? null) !== $schema) {
                        throw new InvalidArgumentException('A business action references an undeclared handler/schema pair.');
                    }
                }
            }
            self::unique($definitions, $handle, 'business definition');
        }

        return [$definitions, $fieldTypes];
    }

    /**
     * Validate the complete multilingual-content declaration set.
     *
     * @param ContributionOwner $owner Signed package owner.
     * @param array<string, mixed> $content Content declaration section.
     *
     * @since 0.2.0
     */
    private static function validateContent(ContributionOwner $owner, array $content): void
    {
        $groups = [];
        foreach (self::objects($content['translation_groups'] ?? [], 'content.translation_groups') as $item) {
            self::keys(
                $item,
                ['group_id', 'locales', 'fallback_locale'],
                ['group_id', 'locales', 'fallback_locale'],
                'content translation group',
            );
            $identifier = self::owned($owner, $item, 'group_id', 'content translation group');
            $locales = self::stringList(
                $item['locales'] ?? null,
                'content translation group locales',
                64,
                false,
            );
            $normalized = [];
            foreach ($locales as $locale) {
                $normalized[self::canonicalLocale($locale, 'content translation group locale')] = true;
            }
            $fallback = self::canonicalLocale(
                self::requiredString($item, 'fallback_locale', 'content translation group'),
                'content translation group fallback',
            );
            if (!isset($normalized[$fallback])) {
                throw new InvalidArgumentException(
                    'A content translation group fallback must be one of its declared locales.',
                );
            }
            self::unique($groups, $identifier, 'content translation group');
        }
    }

    /**
     * @param  ContributionOwner     $owner              Signed package owner.
     * @param  array<string, true>   $capabilities       Capability identities.
     * @param  array<string, true>   $definitionHandles  Business definition handles.
     * @param  array<string, mixed>  $integration        Integration declaration section.
     *
     * @since  0.2.0
     */
    private static function validateIntegration(
        ContributionOwner $owner,
        array $capabilities,
        array $definitionHandles,
        array $integration,
    ): void {
        $eventSchemas = [];
        foreach (self::objects($integration['event_schemas'] ?? [], 'integration.event_schemas') as $item) {
            self::keys($item, ['event_type', 'schema_version', 'sensitivity', 'payload_schema', 'maximum_bytes'], [
                'event_type', 'schema_version', 'sensitivity', 'payload_schema', 'maximum_bytes',
            ], 'event schema');
            $eventType = self::owned($owner, $item, 'event_type', 'event schema');
            $version = self::positiveInteger($item['schema_version'] ?? null, 'event schema version', 65_535);
            $sensitivity = self::requiredString($item, 'sensitivity', 'event schema');
            if (EventSensitivity::tryFrom($sensitivity) === null) {
                throw new InvalidArgumentException('An event schema sensitivity is invalid.');
            }
            self::nonEmptyObject($item['payload_schema'] ?? null, 'event payload schema');
            $maximumBytes = self::positiveInteger($item['maximum_bytes'] ?? null, 'event maximum bytes', 1_048_576);
            if ($maximumBytes < 2) {
                throw new InvalidArgumentException('An event schema payload ceiling is invalid.');
            }
            self::unique($eventSchemas, $eventType . '@' . $version, 'event schema');
        }

        $queues = [];
        foreach (self::objects($integration['queues'] ?? [], 'integration.queues') as $item) {
            self::keys($item, [
                'queue_id', 'lease_seconds', 'maximum_attempts', 'maximum_in_flight', 'retention_days',
            ], [
                'queue_id', 'lease_seconds', 'maximum_attempts', 'maximum_in_flight', 'retention_days',
            ], 'queue');
            $id = self::owned($owner, $item, 'queue_id', 'queue');
            self::positiveInteger($item['lease_seconds'] ?? null, 'queue lease', 3_600);
            self::positiveInteger($item['maximum_attempts'] ?? null, 'queue attempts', 100);
            self::positiveInteger($item['maximum_in_flight'] ?? null, 'queue concurrency', 1_024);
            self::positiveInteger($item['retention_days'] ?? null, 'queue retention', 3_650);
            self::unique($queues, $id, 'queue');
        }

        foreach (self::objects($integration['domain_listeners'] ?? [], 'integration.domain_listeners') as $item) {
            self::keys($item, [
                'listener_id', 'event_type', 'schema_versions', 'handler_version', 'priority', 'sensitivity_ceiling',
            ], [
                'listener_id', 'event_type', 'schema_versions', 'handler_version', 'priority', 'sensitivity_ceiling',
            ], 'domain listener');
            $declaration = DomainListenerDefinition::fromArray($item);
            $owner->assertOwns($declaration->identifier(), 'domain listener');
            self::assertEventReferences(
                $owner,
                $eventSchemas,
                $declaration->eventType(),
                $declaration->schemaVersions(),
            );
            self::requiredString($item, 'handler_version', 'domain listener');
            if (!is_int($item['priority'] ?? null) || $item['priority'] < -1_000 || $item['priority'] > 1_000) {
                throw new InvalidArgumentException('A domain listener priority is invalid.');
            }
        }

        foreach (self::objects($integration['consumers'] ?? [], 'integration.consumers') as $item) {
            self::keys($item, [
                'consumer_id', 'event_type', 'schema_versions', 'handler_version', 'queue', 'aggregate_ordered',
                'idempotency', 'maximum_attempts', 'sensitivity_ceiling',
            ], [
                'consumer_id', 'event_type', 'schema_versions', 'handler_version', 'queue', 'aggregate_ordered',
                'idempotency', 'maximum_attempts', 'sensitivity_ceiling',
            ], 'event consumer');
            $declaration = EventConsumerDefinition::fromArray($item);
            $owner->assertOwns($declaration->identifier(), 'event consumer');
            self::assertEventReferences(
                $owner,
                $eventSchemas,
                $declaration->eventType(),
                $declaration->schemaVersions(),
            );
            self::assertQueueReference($queues, $item, 'event consumer');
            self::requiredBoolean($item['aggregate_ordered'] ?? null, 'event consumer aggregate ordering');
            $idempotency = self::requiredString($item, 'idempotency', 'event consumer');
            if (ConsumerIdempotency::tryFrom($idempotency) === null) {
                throw new InvalidArgumentException('An event consumer idempotency contract is invalid.');
            }
            self::positiveInteger($item['maximum_attempts'] ?? null, 'event consumer attempts', 100);
        }

        $jobs = [];
        foreach (self::objects($integration['jobs'] ?? [], 'integration.jobs') as $item) {
            self::keys($item, [
                'job_type', 'schema_version', 'handler_version', 'payload_schema', 'queue', 'maximum_attempts',
                'installation_wide',
            ], [
                'job_type', 'schema_version', 'handler_version', 'payload_schema', 'queue', 'maximum_attempts',
                'installation_wide',
            ], 'job');
            $declaration = JobContributionDefinition::fromArray($item);
            $owner->assertOwns($declaration->identifier(), 'job');
            self::assertQueueReference($queues, $item, 'job');
            self::requiredString($item, 'handler_version', 'job');
            self::nonEmptyObject($item['payload_schema'] ?? null, 'job payload schema');
            self::positiveInteger($item['maximum_attempts'] ?? null, 'job attempts', 100);
            self::requiredBoolean($item['installation_wide'] ?? null, 'job installation scope');
            self::unique($jobs, $declaration->identifier(), 'job');
        }

        $schedules = [];
        foreach (self::objects($integration['schedules'] ?? [], 'integration.schedules') as $item) {
            self::keys($item, [
                'schedule_id', 'job_type', 'cron_expression', 'timezone', 'payload', 'queue', 'site_identifier',
                'enabled',
            ], ['schedule_id', 'job_type', 'cron_expression', 'timezone', 'payload', 'queue', 'enabled'], 'schedule');
            $identifier = self::owned($owner, $item, 'schedule_id', 'schedule');
            $job = self::requiredString($item, 'job_type', 'schedule');
            self::assertQueueReference($queues, $item, 'schedule');
            if (!isset($jobs[$job])) {
                throw new InvalidArgumentException('A schedule references an undeclared job.');
            }
            self::requiredString($item, 'cron_expression', 'schedule');
            self::requiredString($item, 'timezone', 'schedule');
            self::object($item['payload'] ?? null, 'schedule payload');
            self::requiredBoolean($item['enabled'] ?? null, 'schedule enabled flag');
            self::unique($schedules, $identifier, 'schedule');
        }

        foreach (self::objects($integration['projections'] ?? [], 'integration.projections') as $item) {
            self::keys($item, [
                'identifier', 'version', 'handler_version', 'rebuildable', 'sensitivity_ceiling', 'sources',
                'fields', 'key_fields', 'rebuild_batch_size',
            ], [
                'identifier', 'version', 'handler_version', 'rebuildable', 'sensitivity_ceiling', 'sources',
                'fields', 'key_fields', 'rebuild_batch_size',
            ], 'projection');
            $declaration = ProjectionDefinition::fromArray($item);
            $owner->assertOwns($declaration->identifier(), 'projection');
            foreach ($declaration->sources as $source) {
                self::assertEventReferences($owner, $eventSchemas, $source->eventType, $source->schemaVersions);
            }
            self::requiredString($item, 'handler_version', 'projection');
            self::requiredBoolean($item['rebuildable'] ?? null, 'projection rebuildable flag');
            self::objects($item['fields'] ?? null, 'projection fields');
            self::stringList($item['key_fields'] ?? null, 'projection key fields', 64, false);
            self::positiveInteger($item['rebuild_batch_size'] ?? null, 'projection batch size', 10_000);
        }

        $reports = [];
        foreach (self::objects($integration['reports'] ?? [], 'integration.reports') as $item) {
            [$identifier, $source, $capability] = self::validateReport($owner, $item);
            if (!isset($definitionHandles[$source]) || !isset($capabilities[$capability])) {
                throw new InvalidArgumentException(sprintf('Report %s has an undeclared source or capability.', $identifier));
            }
            self::unique($reports, $identifier, 'report');
        }

        foreach (self::objects($integration['webhooks'] ?? [], 'integration.webhooks') as $item) {
            self::keys($item, [
                'adapter_id', 'event_types', 'schema_versions', 'handler_version', 'queue', 'idempotency',
                'maximum_attempts', 'sensitivity_ceiling',
            ], [
                'adapter_id', 'event_types', 'schema_versions', 'handler_version', 'queue', 'idempotency',
                'maximum_attempts', 'sensitivity_ceiling',
            ], 'webhook');
            $declaration = WebhookContributionDefinition::fromArray($item);
            $owner->assertOwns($declaration->identifier(), 'webhook');
            foreach ($declaration->eventTypes() as $eventType) {
                self::assertEventReferences($owner, $eventSchemas, $eventType, $declaration->schemaVersions());
            }
            self::assertQueueReference($queues, $item, 'webhook');
            self::positiveInteger($item['maximum_attempts'] ?? null, 'webhook attempts', 100);
        }

        self::validateConversionProviders($owner, $integration, 'rate_providers', 'currencies');
        self::validateConversionProviders($owner, $integration, 'unit_converters', 'units');
    }

    /**
     * Validate one closed report document before the host interprets it.
     *
     * @param ContributionOwner $owner Signed package owner.
     * @param array<string, mixed> $item Report declaration.
     *
     * @return array{string, string, string} Identifier, source definition and capability.
     *
     * @since 0.2.0
     */
    private static function validateReport(ContributionOwner $owner, array $item): array
    {
        self::keys($item, [
            'identifier', 'version', 'title', 'source_definition', 'required_capability',
            'administrator_visible', 'portal_visible', 'parameters', 'filters', 'columns', 'groups',
            'aggregates', 'formulas', 'sorts', 'drill_downs', 'synchronous_row_cap',
        ], [
            'identifier', 'version', 'title', 'source_definition', 'required_capability',
            'administrator_visible', 'portal_visible', 'parameters', 'filters', 'columns', 'groups',
            'aggregates', 'formulas', 'sorts', 'drill_downs', 'synchronous_row_cap',
        ], 'report');
        $identifier = self::owned($owner, $item, 'identifier', 'report');
        ReportDefinitionGuard::identifier($identifier, 'identifier');
        self::positiveInteger($item['version'] ?? null, 'report version', 65_535);
        self::reportLabel(self::requiredString($item, 'title', 'report'), 'title');
        $source = self::requiredString($item, 'source_definition', 'report');
        ReportDefinitionGuard::identifier($source, 'source definition');
        $capability = self::requiredString($item, 'required_capability', 'report');
        self::requiredBoolean($item['administrator_visible'] ?? null, 'report administrator visibility');
        self::requiredBoolean($item['portal_visible'] ?? null, 'report portal visibility');

        $parameterNames = self::validateReportParameters($item['parameters'] ?? null);
        [$columnAliases, $columnTypes, $relation] = self::validateReportColumns($item['columns'] ?? null);
        self::validateReportFilters($item['filters'] ?? null, $parameterNames, $relation);
        [$outputAliases, $outputTypes] = self::validateReportOutputs(
            $item,
            $columnAliases,
            $columnTypes,
        );
        self::validateReportSorts($item['sorts'] ?? null, $outputAliases);
        self::validateReportDrillDowns($item['drill_downs'] ?? null, $outputAliases, $outputTypes);
        self::positiveInteger($item['synchronous_row_cap'] ?? null, 'report synchronous row cap', 1_000);

        return [$identifier, $source, $capability];
    }

    /**
     * @param mixed $value Parameter declaration list.
     *
     * @return array<string, true> Declared parameter names.
     *
     * @since 0.2.0
     */
    private static function validateReportParameters(mixed $value): array
    {
        $seen = [];
        foreach (self::boundedObjects($value, 'report parameters', 32, true) as $item) {
            self::keys(
                $item,
                ['name', 'type', 'required', 'multiple', 'default'],
                ['name', 'type', 'required', 'multiple', 'default'],
                'report parameter',
            );
            $name = self::reportHandle($item, 'name', 'report parameter');
            $type = self::reportValueType($item, 'type', 'report parameter');
            $required = self::requiredBoolean($item['required'] ?? null, 'report parameter required flag');
            $multiple = self::requiredBoolean($item['multiple'] ?? null, 'report parameter multiple flag');
            $default = $item['default'];
            if ($required && $default !== null) {
                throw new InvalidArgumentException('A required report parameter cannot declare a default.');
            }
            if ($default !== null) {
                self::validateReportDefault($default, $type, $multiple);
            }
            self::unique($seen, $name, 'report parameter');
        }

        return $seen;
    }

    /**
     * @param mixed $value Candidate parameter default.
     * @param ReportValueType $type Declared scalar type.
     * @param bool $multiple Whether the default is a list.
     *
     * @since 0.2.0
     */
    private static function validateReportDefault(mixed $value, ReportValueType $type, bool $multiple): void
    {
        $values = $multiple ? $value : [$value];
        if (!is_array($values) || !array_is_list($values) || $values === [] || count($values) > 100) {
            throw new InvalidArgumentException('A report parameter default has an invalid value list.');
        }
        foreach ($values as $member) {
            if (!$type->accepts($member)) {
                throw new InvalidArgumentException('A report parameter default has the wrong type.');
            }
        }
    }

    /**
     * @param mixed $value Column declaration list.
     *
     * @return array{array<string, true>, array<string, ReportValueType>, ?string} Aliases, types and relation.
     *
     * @since 0.2.0
     */
    private static function validateReportColumns(mixed $value): array
    {
        $aliases = [];
        $types = [];
        $relation = null;
        foreach (self::boundedObjects($value, 'report columns', 64, false) as $item) {
            self::keys(
                $item,
                ['alias', 'label', 'source', 'type'],
                ['alias', 'label', 'source', 'type'],
                'report column',
            );
            $alias = self::reportHandle($item, 'alias', 'report column');
            self::reportLabel(self::requiredString($item, 'label', 'report column'), 'column label');
            $source = self::reportPath($item, 'source', 'report column');
            self::recordReportRelation($source, $relation);
            self::unique($aliases, $alias, 'report column');
            $types[$alias] = self::reportValueType($item, 'type', 'report column');
        }

        return [$aliases, $types, $relation];
    }

    /**
     * @param mixed $value Filter declaration list.
     * @param array<string, true> $parameters Declared parameters.
     * @param ?string $relation Single relation already traversed by columns.
     *
     * @since 0.2.0
     */
    private static function validateReportFilters(mixed $value, array $parameters, ?string &$relation): void
    {
        foreach (self::boundedObjects($value, 'report filters', 32, true) as $item) {
            self::keys(
                $item,
                ['field', 'operator', 'parameter', 'quantifier'],
                ['field', 'operator', 'parameter', 'quantifier'],
                'report filter',
            );
            $field = self::reportPath($item, 'field', 'report filter');
            self::recordReportRelation($field, $relation);
            $operator = self::requiredString($item, 'operator', 'report filter');
            if (
                !in_array($operator, [
                'eq', 'ne', 'lt', 'lte', 'gt', 'gte', 'contains', 'starts_with', 'ends_with', 'in',
                'not_in', 'is_null', 'is_not_null',
                ], true)
            ) {
                throw new InvalidArgumentException('A report filter operator is unsupported.');
            }
            $parameter = self::nullableString($item, 'parameter', 'report filter');
            $nullTest = $operator === 'is_null' || $operator === 'is_not_null';
            if ($nullTest !== ($parameter === null)) {
                throw new InvalidArgumentException('Only report null filters omit a parameter.');
            }
            if ($parameter !== null) {
                ReportDefinitionGuard::handle($parameter, 'filter parameter');
                if (!isset($parameters[$parameter])) {
                    throw new InvalidArgumentException('A report filter references an undeclared parameter.');
                }
            }
            $quantifier = self::requiredString($item, 'quantifier', 'report filter');
            if (!in_array($quantifier, ['any', 'none', 'all'], true)) {
                throw new InvalidArgumentException('A report relation quantifier is unsupported.');
            }
        }
    }

    /**
     * @param array<string, mixed> $report Report declaration.
     * @param array<string, true> $columnAliases Declared column aliases.
     * @param array<string, ReportValueType> $columnTypes Column types by alias.
     *
     * @return array{array<string, true>, array<string, ReportValueType>} Output aliases and types.
     *
     * @since 0.2.0
     */
    private static function validateReportOutputs(array $report, array $columnAliases, array $columnTypes): array
    {
        $groupAliases = [];
        foreach (self::boundedObjects($report['groups'] ?? null, 'report groups', 4, true) as $item) {
            self::keys($item, ['column'], ['column'], 'report group');
            $column = self::reportHandle($item, 'column', 'report group');
            self::assertReportReference($columnAliases, $column, 'group');
            self::unique($groupAliases, $column, 'report group');
        }

        $aggregates = self::boundedObjects($report['aggregates'] ?? null, 'report aggregates', 16, true);
        $outputAliases = ($groupAliases !== [] || $aggregates !== []) ? $groupAliases : $columnAliases;
        $outputTypes = ($groupAliases !== [] || $aggregates !== [])
            ? array_intersect_key($columnTypes, $groupAliases)
            : $columnTypes;
        foreach ($aggregates as $item) {
            self::keys(
                $item,
                ['alias', 'function', 'column'],
                ['alias', 'function', 'column'],
                'report aggregate',
            );
            $alias = self::reportHandle($item, 'alias', 'report aggregate');
            $function = self::requiredString($item, 'function', 'report aggregate');
            if (!in_array($function, ['count', 'sum', 'min', 'max', 'avg'], true)) {
                throw new InvalidArgumentException('A report aggregate function is unsupported.');
            }
            $column = self::nullableString($item, 'column', 'report aggregate');
            if (($function === 'count') !== ($column === null)) {
                throw new InvalidArgumentException('Only a count report aggregate omits its source column.');
            }
            $type = ReportValueType::Integer;
            if ($column !== null) {
                ReportDefinitionGuard::handle($column, 'aggregate column');
                self::assertReportReference($columnAliases, $column, 'aggregate');
                $sourceType = $columnTypes[$column];
                if (in_array($function, ['sum', 'avg'], true)) {
                    if (!in_array($sourceType, [ReportValueType::Integer, ReportValueType::Decimal], true)) {
                        throw new InvalidArgumentException('A numeric report aggregate requires a numeric column.');
                    }
                    $type = ReportValueType::Decimal;
                } else {
                    if (in_array($function, ['min', 'max'], true) && $sourceType === ReportValueType::Boolean) {
                        throw new InvalidArgumentException(
                            'A boolean report column cannot be ordered for an aggregate.',
                        );
                    }
                    $type = $sourceType;
                }
            }
            self::unique($outputAliases, $alias, 'report output');
            $outputTypes[$alias] = $type;
        }

        foreach (self::boundedObjects($report['formulas'] ?? null, 'report formulas', 16, true) as $item) {
            self::keys(
                $item,
                ['alias', 'label', 'type', 'expression'],
                ['alias', 'label', 'type', 'expression'],
                'report formula',
            );
            $alias = self::reportHandle($item, 'alias', 'report formula');
            self::reportLabel(self::requiredString($item, 'label', 'report formula'), 'formula label');
            $type = self::reportValueType($item, 'type', 'report formula');
            $expression = self::nonEmptyObject($item['expression'] ?? null, 'report formula expression');
            if (strlen(CanonicalJson::encode($expression)) > 32_768) {
                throw new InvalidArgumentException('A report formula expression exceeds its byte bound.');
            }
            $operations = 0;
            [, $dependencies] = self::validateReportExpression($expression, 1, $operations);
            foreach ($dependencies as $dependency => $_present) {
                self::assertReportReference($outputAliases, $dependency, 'formula dependency');
            }
            self::unique($outputAliases, $alias, 'report output');
            $outputTypes[$alias] = $type;
        }

        return [$outputAliases, $outputTypes];
    }

    /**
     * @param mixed $value Sort declaration list.
     * @param array<string, true> $outputs Declared outputs.
     *
     * @since 0.2.0
     */
    private static function validateReportSorts(mixed $value, array $outputs): void
    {
        foreach (self::boundedObjects($value, 'report sorts', 5, true) as $item) {
            self::keys(
                $item,
                ['output', 'direction', 'nulls_last'],
                ['output', 'direction', 'nulls_last'],
                'report sort',
            );
            $output = self::reportHandle($item, 'output', 'report sort');
            self::assertReportReference($outputs, $output, 'sort');
            $direction = self::requiredString($item, 'direction', 'report sort');
            if (!in_array($direction, ['asc', 'desc'], true)) {
                throw new InvalidArgumentException('A report sort direction is unsupported.');
            }
            self::requiredBoolean($item['nulls_last'] ?? null, 'report sort null ordering');
        }
    }

    /**
     * @param mixed $value Drill-down declaration list.
     * @param array<string, true> $outputs Declared outputs.
     * @param array<string, ReportValueType> $types Output types by alias.
     *
     * @since 0.2.0
     */
    private static function validateReportDrillDowns(mixed $value, array $outputs, array $types): void
    {
        foreach (self::boundedObjects($value, 'report drill downs', 8, true) as $item) {
            self::keys(
                $item,
                ['record', 'definition', 'view'],
                ['record', 'definition', 'view'],
                'report drill down',
            );
            $record = self::reportHandle($item, 'record', 'report drill down');
            self::assertReportReference($outputs, $record, 'drill-down');
            if (($types[$record] ?? null) !== ReportValueType::Identifier) {
                throw new InvalidArgumentException('A report drill-down requires an identifier output.');
            }
            $definition = self::requiredString($item, 'definition', 'report drill down');
            ReportDefinitionGuard::identifier($definition, 'drill-down definition');
            $view = self::requiredString($item, 'view', 'report drill down');
            ReportDefinitionGuard::identifier($view, 'drill-down view');
        }
    }

    /**
     * Validate one bounded expression node and return its declared type and field dependencies.
     *
     * @param array<string, mixed> $expression Expression node.
     * @param int $depth Current one-based nesting depth.
     * @param int $operations Running node count.
     *
     * @return array{string, array<string, true>} Type and field dependencies.
     *
     * @since 0.2.0
     */
    private static function validateReportExpression(array $expression, int $depth, int &$operations): array
    {
        if ($depth > 12 || ++$operations > 128) {
            throw new InvalidArgumentException('A report formula expression exceeds its structural bound.');
        }
        $operator = self::requiredString($expression, 'op', 'report expression');
        $type = self::requiredString($expression, 'type', 'report expression');
        $arity = [
            'eq' => [2, 2], 'ne' => [2, 2], 'lt' => [2, 2], 'lte' => [2, 2], 'gt' => [2, 2],
            'gte' => [2, 2], 'and' => [2, 16], 'or' => [2, 16], 'not' => [1, 1], 'add' => [2, 16],
            'subtract' => [2, 2], 'multiply' => [2, 16], 'divide' => [2, 2], 'concat' => [2, 16],
            'coalesce' => [2, 16], 'if' => [3, 3], 'is_null' => [1, 1], 'in' => [2, 32],
            'contains' => [2, 2],
        ];
        if (!in_array($type, ['any', 'null', 'boolean', 'integer', 'decimal', 'string', 'date', 'time', 'datetime'], true)) {
            throw new InvalidArgumentException('A report formula expression type is unsupported.');
        }

        if ($operator === 'literal') {
            self::keys($expression, ['op', 'type', 'value'], ['op', 'type', 'value'], 'report literal expression');
            self::validateReportLiteral($type, $expression['value']);

            return [$type, []];
        }
        if ($operator === 'field') {
            self::keys($expression, ['op', 'type', 'field'], ['op', 'type', 'field'], 'report field expression');
            $field = self::reportHandle($expression, 'field', 'report field expression');

            return [$type, [$field => true]];
        }
        if ($operator === 'line_aggregate') {
            self::keys(
                $expression,
                ['op', 'type', 'lines', 'aggregate', 'field'],
                ['op', 'type', 'lines', 'aggregate'],
                'report line aggregate expression',
            );
            self::reportHandle($expression, 'lines', 'report line aggregate expression');
            $aggregate = self::requiredString($expression, 'aggregate', 'report line aggregate expression');
            if (
                !in_array($aggregate, ['count', 'sum'], true)
                || ($aggregate === 'count' && $type !== 'integer')
                || ($aggregate === 'sum' && !in_array($type, ['integer', 'decimal'], true))
            ) {
                throw new InvalidArgumentException('A report line aggregate expression is unsupported.');
            }
            $field = self::nullableString($expression, 'field', 'report line aggregate expression');
            if (($aggregate === 'count') !== ($field === null)) {
                throw new InvalidArgumentException('A report line aggregate expression has an invalid field.');
            }
            if ($field !== null) {
                ReportDefinitionGuard::handle($field, 'line aggregate field');
            }

            return [$type, []];
        }
        if (!isset($arity[$operator])) {
            throw new InvalidArgumentException('A report formula expression operator is unsupported.');
        }
        self::keys(
            $expression,
            ['op', 'type', 'args', 'scale'],
            ['op', 'type', 'args'],
            'report operator expression',
        );
        $arguments = self::objects($expression['args'] ?? null, 'report expression arguments');
        [$minimum, $maximum] = $arity[$operator];
        if (count($arguments) < $minimum || count($arguments) > $maximum) {
            throw new InvalidArgumentException('A report formula expression operator has invalid arity.');
        }
        $argumentTypes = [];
        $dependencies = [];
        foreach ($arguments as $argument) {
            [$argumentType, $argumentDependencies] = self::validateReportExpression(
                $argument,
                $depth + 1,
                $operations,
            );
            $argumentTypes[] = $argumentType;
            $dependencies += $argumentDependencies;
        }
        $scale = $expression['scale'] ?? null;
        if (
            $scale !== null
            && (!is_int($scale) || $scale < 0 || $scale > 30 || $operator !== 'divide' || $type !== 'decimal')
        ) {
            throw new InvalidArgumentException('A report formula expression scale is invalid.');
        }
        if ($operator === 'divide' && $type === 'decimal' && !is_int($scale)) {
            throw new InvalidArgumentException('A decimal report division requires an explicit scale.');
        }
        self::validateReportExpressionTypes($operator, $type, $argumentTypes);

        return [$type, $dependencies];
    }

    /**
     * @param string $type Declared literal type.
     * @param mixed $value Literal value.
     *
     * @since 0.2.0
     */
    private static function validateReportLiteral(string $type, mixed $value): void
    {
        $valid = match ($type) {
            'null' => $value === null,
            'boolean' => is_bool($value),
            'integer' => is_int($value),
            'decimal' => is_string($value)
                && preg_match('/^-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$/D', $value) === 1,
            'string', 'date', 'time', 'datetime' => is_string($value) && strlen($value) <= 4_096,
            'any' => is_null($value) || is_bool($value) || is_int($value) || is_string($value),
            default => false,
        };
        if (!$valid) {
            throw new InvalidArgumentException('A report formula literal has the wrong type.');
        }
    }

    /**
     * @param string $operator Operator name.
     * @param string $type Declared result type.
     * @param list<string> $arguments Argument result types.
     *
     * @since 0.2.0
     */
    private static function validateReportExpressionTypes(string $operator, string $type, array $arguments): void
    {
        if (
            in_array($operator, [
                'eq', 'ne', 'lt', 'lte', 'gt', 'gte', 'and', 'or', 'not', 'is_null', 'in', 'contains',
            ], true)
            && $type !== 'boolean'
        ) {
            throw new InvalidArgumentException('A report formula predicate must produce a boolean.');
        }
        if (
            in_array($operator, ['and', 'or', 'not'], true) && array_filter(
                $arguments,
                static fn (string $argument): bool => $argument !== 'boolean',
            ) !== []
        ) {
            throw new InvalidArgumentException('A report formula logical operator requires boolean arguments.');
        }
        if (in_array($operator, ['add', 'subtract', 'multiply', 'divide'], true)) {
            if (
                !in_array($type, ['integer', 'decimal'], true)
                || array_filter($arguments, static fn (string $argument): bool => $argument !== $type) !== []
            ) {
                throw new InvalidArgumentException('A report formula arithmetic operator has incompatible types.');
            }
        }
        if (in_array($operator, ['eq', 'ne', 'in', 'lt', 'lte', 'gt', 'gte'], true)) {
            self::assertSameReportExpressionTypes($arguments);
        }
        if (
            in_array($operator, ['lt', 'lte', 'gt', 'gte'], true)
            && !in_array($arguments[0], ['integer', 'decimal', 'string', 'date', 'time', 'datetime'], true)
        ) {
            throw new InvalidArgumentException('A report formula ordered comparison has an unsupported type.');
        }
        if ($operator === 'contains' && ($arguments !== ['string', 'string'] || $type !== 'boolean')) {
            throw new InvalidArgumentException('A report formula contains operator has incompatible types.');
        }
        if (
            $operator === 'concat' && (
            $type !== 'string'
            || array_filter($arguments, static fn (string $argument): bool => $argument !== 'string') !== []
            )
        ) {
            throw new InvalidArgumentException('A report formula concat operator has incompatible types.');
        }
        if (
            $operator === 'if' && (
            $arguments[0] !== 'boolean'
            || !self::reportExpressionTypeCompatible($type, $arguments[1])
            || !self::reportExpressionTypeCompatible($type, $arguments[2])
            )
        ) {
            throw new InvalidArgumentException('A report formula conditional has incompatible types.');
        }
        if ($operator === 'coalesce') {
            foreach ($arguments as $argument) {
                if (!self::reportExpressionTypeCompatible($type, $argument)) {
                    throw new InvalidArgumentException('A report formula coalesce has incompatible types.');
                }
            }
        }
    }

    /**
     * @param list<string> $arguments Argument result types.
     *
     * @since 0.2.0
     */
    private static function assertSameReportExpressionTypes(array $arguments): void
    {
        $expected = $arguments[0];
        foreach ($arguments as $argument) {
            if ($argument !== $expected) {
                throw new InvalidArgumentException('A report formula operator has incompatible argument types.');
            }
        }
    }

    /**
     * @param string $result Declared expression result type.
     * @param string $argument Argument expression type.
     *
     * @since 0.2.0
     */
    private static function reportExpressionTypeCompatible(string $result, string $argument): bool
    {
        return $result === 'any' || $argument === $result || $argument === 'null';
    }

    /**
     * @param mixed $value Candidate object list.
     * @param string $field Diagnostic field name.
     * @param int $maximum Maximum members.
     * @param bool $emptyAllowed Whether an empty collection is valid.
     *
     * @return list<array<string, mixed>> Validated object list.
     *
     * @since 0.2.0
     */
    private static function boundedObjects(mixed $value, string $field, int $maximum, bool $emptyAllowed): array
    {
        $objects = self::objects($value, $field);
        if (count($objects) > $maximum || (!$emptyAllowed && $objects === [])) {
            throw new InvalidArgumentException(sprintf('%s exceeds its declared collection bound.', $field));
        }

        return $objects;
    }

    /**
     * @param array<string, mixed> $item Declaration object.
     * @param string $member Member name.
     * @param string $kind Declaration kind.
     *
     * @since 0.2.0
     */
    private static function reportHandle(array $item, string $member, string $kind): string
    {
        $value = self::requiredString($item, $member, $kind);
        ReportDefinitionGuard::handle($value, $kind . ' ' . $member);

        return $value;
    }

    /**
     * @param array<string, mixed> $item Declaration object.
     * @param string $member Member name.
     * @param string $kind Declaration kind.
     *
     * @since 0.2.0
     */
    private static function reportPath(array $item, string $member, string $kind): string
    {
        $value = self::requiredString($item, $member, $kind);
        ReportDefinitionGuard::path($value, $kind . ' ' . $member);

        return $value;
    }

    /**
     * @param array<string, mixed> $item Declaration object.
     * @param string $member Member name.
     * @param string $kind Declaration kind.
     *
     * @since 0.2.0
     */
    private static function reportValueType(array $item, string $member, string $kind): ReportValueType
    {
        $type = ReportValueType::tryFrom(self::requiredString($item, $member, $kind));
        if (!$type instanceof ReportValueType) {
            throw new InvalidArgumentException('A report value type is unsupported.');
        }

        return $type;
    }

    /**
     * @param string $value Candidate display label.
     * @param string $field Diagnostic field name.
     *
     * @since 0.2.0
     */
    private static function reportLabel(string $value, string $field): void
    {
        if (mb_strlen($value) > 191) {
            throw new InvalidArgumentException(sprintf('A report %s exceeds its length bound.', $field));
        }
    }

    /**
     * @param string $path Validated report path.
     * @param ?string $relation Relation already traversed.
     *
     * @since 0.2.0
     */
    private static function recordReportRelation(string $path, ?string &$relation): void
    {
        if (!str_contains($path, '.')) {
            return;
        }
        $candidate = explode('.', $path, 2)[0];
        if ($relation !== null && $relation !== $candidate) {
            throw new InvalidArgumentException('A report may traverse only one declared relationship.');
        }
        $relation = $candidate;
    }

    /**
     * @param array<string, true> $available Declared aliases.
     * @param string $value Referenced alias.
     * @param string $kind Reference kind.
     *
     * @since 0.2.0
     */
    private static function assertReportReference(array $available, string $value, string $kind): void
    {
        if (!isset($available[$value])) {
            throw new InvalidArgumentException(sprintf('A report %s references an undeclared output.', $kind));
        }
    }

    /**
     * @param array<string, mixed> $item Declaration object.
     * @param string $member Nullable member name.
     * @param string $kind Declaration kind.
     *
     * @since 0.2.0
     */
    private static function nullableString(array $item, string $member, string $kind): ?string
    {
        $value = $item[$member] ?? null;
        if ($value !== null && (!is_string($value) || $value === '' || $value !== trim($value))) {
            throw new InvalidArgumentException(sprintf('A %s has invalid nullable string %s.', $kind, $member));
        }

        return $value;
    }

    /**
     * @param string $locale Candidate canonical locale tag.
     * @param string $field Diagnostic field name.
     *
     * @return string Validated tag.
     *
     * @since 0.2.0
     */
    private static function canonicalLocale(string $locale, string $field): string
    {
        $candidate = str_replace('_', '-', trim($locale));
        if (
            preg_match(
                '/^([A-Za-z]{2,3})(?:-([A-Za-z]{4}))?(?:-([A-Za-z]{2}|[0-9]{3}))?$/D',
                $candidate,
                $parts,
            ) !== 1
        ) {
            throw new InvalidArgumentException(sprintf('A %s must be a language tag.', $field));
        }

        return implode('-', array_filter([
            strtolower($parts[1]),
            ($parts[2] ?? '') === '' ? null : ucfirst(strtolower($parts[2])),
            ($parts[3] ?? '') === '' ? null : strtoupper($parts[3]),
        ], is_string(...)));
    }

    /**
     * @param ContributionOwner    $owner        Signed package owner.
     * @param array<string, mixed> $integration Validated integration contribution object.
     * @param string               $surface     Provider list member.
     * @param string               $claims      Provider claim-list member.
     * @since 0.2.0
     */
    private static function validateConversionProviders(
        ContributionOwner $owner,
        array $integration,
        string $surface,
        string $claims,
    ): void {
        $seen = [];
        foreach (self::objects($integration[$surface] ?? [], 'integration.' . $surface) as $item) {
            self::keys($item, ['provider_id', $claims, 'priority'], ['provider_id', $claims, 'priority'], $surface);
            $identifier = self::owned($owner, $item, 'provider_id', $surface);
            self::stringList($item[$claims] ?? null, $surface . ' ' . $claims, 64, false);
            $priority = $item['priority'] ?? null;
            if (!is_int($priority) || $priority < -128 || $priority > 127) {
                throw new InvalidArgumentException('A conversion provider priority is invalid.');
            }
            self::unique($seen, $identifier, $surface);
        }
    }

    /**
     * Package-owned event references must resolve inside the same signed manifest. External event
     * references remain typed unresolved dependencies for the host event registry to admit or refuse.
     *
     * @param ContributionOwner $owner Signed package owner.
     * @param array<string, true> $eventSchemas Package-declared event schema identities.
     * @param string $eventType Referenced event type.
     * @param list<int> $versions Referenced schema versions.
     *
     * @since 0.2.0
     */
    private static function assertEventReferences(
        ContributionOwner $owner,
        array $eventSchemas,
        string $eventType,
        array $versions,
    ): void {
        if (!str_starts_with($eventType, $owner->namespace() . '.')) {
            return;
        }
        foreach ($versions as $version) {
            if (!isset($eventSchemas[$eventType . '@' . $version])) {
                throw new InvalidArgumentException(
                    'An executable integration declaration references an undeclared package-owned event.',
                );
            }
        }
    }

    /**
     * @param array<string, true> $queues Declared queue identities.
     * @param array<string, mixed> $item Executable declaration.
     * @param string $kind Declaration kind used in failures.
     *
     * @since 0.2.0
     */
    private static function assertQueueReference(array $queues, array $item, string $kind): void
    {
        $queue = self::requiredString($item, 'queue', $kind);
        if (!isset($queues[$queue])) {
            throw new InvalidArgumentException(sprintf('A %s references an undeclared queue.', $kind));
        }
    }

    /**
     * @param   list<array<string, mixed>>  $items   Declarations to index.
     * @param   string                     $member  Identity member name.
     * @param   ContributionOwner          $owner   Signed package owner.
     * @param   string                     $kind    Declaration kind.
     *
     * @return  array<string, true>
     *
     * @since   0.2.0
     */
    private static function identities(
        array $items,
        string $member,
        ContributionOwner $owner,
        string $kind,
    ): array {
        $seen = [];
        foreach ($items as $item) {
            self::unique($seen, self::owned($owner, $item, $member, $kind), $kind);
        }

        return $seen;
    }

    /**
     * @param ContributionOwner $owner Signed package owner.
     * @param array<string, mixed> $item Declaration object.
     * @param string $member Identity member name.
     * @param string $kind Declaration kind.
     *
     * @since 0.2.0
     */
    private static function owned(ContributionOwner $owner, array $item, string $member, string $kind): string
    {
        $identifier = self::requiredString($item, $member, $kind);
        $owner->assertOwns($identifier, $kind);

        return $identifier;
    }

    /**
     * @param array<string, true> $seen Seen identities, updated in place.
     * @param string $identifier Candidate identity.
     * @param string $kind Declaration kind.
     *
     * @since 0.2.0
     */
    private static function unique(array &$seen, string $identifier, string $kind): void
    {
        if (isset($seen[$identifier])) {
            throw new InvalidArgumentException(sprintf('%s %s is declared more than once.', $kind, $identifier));
        }
        $seen[$identifier] = true;
    }

    /**
     * @param mixed $value Candidate object.
     * @param string $field Diagnostic field name.
     *
     * @return array<string, mixed> Validated object.
     *
     * @since 0.2.0
     */
    private static function object(mixed $value, string $field): array
    {
        if (!is_array($value) || (array_is_list($value) && $value !== [])) {
            throw new InvalidArgumentException(sprintf('%s must be an object.', $field));
        }

        $object = [];
        foreach ($value as $key => $member) {
            if (!is_string($key)) {
                throw new InvalidArgumentException(sprintf('%s must use string object keys.', $field));
            }
            $object[$key] = $member;
        }

        return $object;
    }

    /**
     * @param mixed $value Candidate object.
     * @param string $field Diagnostic field name.
     *
     * @return non-empty-array<string, mixed> Validated non-empty object.
     *
     * @since 0.2.0
     */
    private static function nonEmptyObject(mixed $value, string $field): array
    {
        $object = self::object($value, $field);
        if ($object === []) {
            throw new InvalidArgumentException(sprintf('%s must not be empty.', $field));
        }

        return $object;
    }

    /**
     * @param mixed $value Candidate object list.
     * @param string $field Diagnostic field name.
     *
     * @return list<array<string, mixed>> Validated object list.
     *
     * @since 0.2.0
     */
    private static function objects(mixed $value, string $field): array
    {
        if (!is_array($value) || !array_is_list($value) || count($value) > 128) {
            throw new InvalidArgumentException(sprintf('%s must be a bounded object list.', $field));
        }
        $objects = [];
        foreach ($value as $item) {
            $object = self::object($item, $field . ' entry');
            if ($object === []) {
                throw new InvalidArgumentException(sprintf('Every %s entry must be a non-empty object.', $field));
            }
            $objects[] = $object;
        }

        return $objects;
    }

    /**
     * @param array<string, mixed> $item Declaration object.
     * @param list<string> $allowed Closed member set.
     * @param list<string> $required Required members.
     * @param string $kind Declaration kind.
     *
     * @since 0.2.0
     */
    private static function keys(array $item, array $allowed, array $required, string $kind): void
    {
        $unknown = array_diff(array_keys($item), $allowed);
        $missing = array_diff($required, array_keys($item));
        if ($unknown !== [] || $missing !== []) {
            throw new InvalidArgumentException(sprintf('A %s has missing or unknown members.', $kind));
        }
    }

    /**
     * @param array<string, mixed> $item Declaration object.
     * @param string $member Required member name.
     * @param string $kind Declaration kind.
     *
     * @since 0.2.0
     */
    private static function requiredString(array $item, string $member, string $kind): string
    {
        $value = $item[$member] ?? null;
        if (
            !is_string($value)
            || $value === ''
            || $value !== trim($value)
            || strlen($value) > 262_144
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/D', $value) === 1
        ) {
            throw new InvalidArgumentException(sprintf('A %s requires canonical string %s.', $kind, $member));
        }

        return $value;
    }

    /**
     * @param mixed $value Candidate list.
     * @param string $field Diagnostic field name.
     * @param int $maximum Maximum list members.
     * @param bool $emptyAllowed Whether an empty list is valid.
     *
     * @return list<string> Validated distinct strings.
     *
     * @since 0.2.0
     */
    private static function stringList(mixed $value, string $field, int $maximum, bool $emptyAllowed): array
    {
        if (!is_array($value) || !array_is_list($value) || count($value) > $maximum || (!$emptyAllowed && $value === [])) {
            throw new InvalidArgumentException(sprintf('%s must be a bounded string list.', $field));
        }
        $seen = [];
        $strings = [];
        foreach ($value as $item) {
            if (!is_string($item) || $item === '' || $item !== trim($item) || isset($seen[$item])) {
                throw new InvalidArgumentException(sprintf('%s contains an invalid or duplicate value.', $field));
            }
            $seen[$item] = true;
            $strings[] = $item;
        }

        return $strings;
    }

    /**
     * @param mixed $value Candidate integer.
     * @param string $field Diagnostic field name.
     * @param int $maximum Inclusive upper bound.
     *
     * @since 0.2.0
     */
    private static function positiveInteger(mixed $value, string $field, int $maximum): int
    {
        if (!is_int($value) || $value < 1 || $value > $maximum) {
            throw new InvalidArgumentException(sprintf('%s must be a bounded positive integer.', $field));
        }

        return $value;
    }

    /**
     * @param mixed $value Candidate boolean.
     * @param string $field Diagnostic field name.
     *
     * @since 0.2.0
     */
    private static function requiredBoolean(mixed $value, string $field): bool
    {
        if (!is_bool($value)) {
            throw new InvalidArgumentException(sprintf('%s must be a boolean.', $field));
        }

        return $value;
    }

    /** Prevent construction of the stateless validator. @since 0.2.0 */
    private function __construct()
    {
    }
}
