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
use Kumwe\Extension\Spi\BusinessSurface\Application\Custom\CustomBusinessActionDeclaration;
use Kumwe\Extension\Spi\BusinessSurface\Application\Custom\CustomBusinessReference;
use Kumwe\Extension\Spi\BusinessSurface\Application\Custom\CustomBusinessViewDeclaration;
use Kumwe\Extension\Spi\BusinessSurface\Presentation\Field\FieldPresentationContribution;
use Kumwe\Extension\Spi\Contribution\ContributionOwner;

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
        self::validateGraphical($owner, $capabilities, $administrator, $portal, $interface);
        self::validateResourcePolicies($owner, $capabilities, $data);
        [$definitionHandles, $fieldTypes] = self::validateBusiness($owner, $business);
        self::validateIntegration($owner, $capabilities, $definitionHandles, $integration);
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
            $owner->assertOwns($declaration->eventType(), 'event type');
            foreach ($declaration->schemaVersions() as $version) {
                if (!isset($eventSchemas[$declaration->eventType() . '@' . $version])) {
                    throw new InvalidArgumentException('A domain listener references an undeclared event schema.');
                }
            }
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
            $owner->assertOwns($declaration->eventType(), 'event type');
            self::assertEventReferences($eventSchemas, $declaration->eventType(), $declaration->schemaVersions());
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

        foreach (self::objects($integration['schedules'] ?? [], 'integration.schedules') as $item) {
            self::keys($item, [
                'schedule_id', 'job_type', 'cron_expression', 'timezone', 'payload', 'queue', 'site_identifier',
                'enabled',
            ], ['schedule_id', 'job_type', 'cron_expression', 'timezone', 'payload', 'queue', 'enabled'], 'schedule');
            self::owned($owner, $item, 'schedule_id', 'schedule');
            $job = self::requiredString($item, 'job_type', 'schedule');
            self::assertQueueReference($queues, $item, 'schedule');
            if (!isset($jobs[$job])) {
                throw new InvalidArgumentException('A schedule references an undeclared job.');
            }
            self::requiredString($item, 'cron_expression', 'schedule');
            self::requiredString($item, 'timezone', 'schedule');
            self::object($item['payload'] ?? null, 'schedule payload');
            self::requiredBoolean($item['enabled'] ?? null, 'schedule enabled flag');
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
                self::assertEventReferences($eventSchemas, $source->eventType, $source->schemaVersions);
            }
            self::requiredString($item, 'handler_version', 'projection');
            self::requiredBoolean($item['rebuildable'] ?? null, 'projection rebuildable flag');
            self::objects($item['fields'] ?? null, 'projection fields');
            self::stringList($item['key_fields'] ?? null, 'projection key fields', 64, false);
            self::positiveInteger($item['rebuild_batch_size'] ?? null, 'projection batch size', 10_000);
        }

        foreach (self::objects($integration['reports'] ?? [], 'integration.reports') as $item) {
            $identifier = self::owned($owner, $item, 'identifier', 'report');
            self::positiveInteger($item['version'] ?? null, 'report version', 65_535);
            $source = self::requiredString($item, 'source_definition', 'report');
            $capability = self::requiredString($item, 'required_capability', 'report');
            if (!isset($definitionHandles[$source]) || !isset($capabilities[$capability])) {
                throw new InvalidArgumentException(sprintf('Report %s has an undeclared source or capability.', $identifier));
            }
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
                self::assertEventReferences($eventSchemas, $eventType, $declaration->schemaVersions());
            }
            self::assertQueueReference($queues, $item, 'webhook');
            self::positiveInteger($item['maximum_attempts'] ?? null, 'webhook attempts', 100);
        }

        self::validateConversionProviders($owner, $integration, 'rate_providers', 'currencies');
        self::validateConversionProviders($owner, $integration, 'unit_converters', 'units');
    }

    /**
     * @param ContributionOwner $owner Signed package owner.
     * @param array<string, mixed> $integration Integration declaration section.
     * @param string $surface Manifest member holding the conversion provider declarations.
     * @param string $claims Member naming each provider's claimed conversion targets.
     *
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
     * @param array<string, true> $eventSchemas Declared event schema identities.
     * @param string $eventType Referenced event type.
     * @param list<int> $versions Referenced schema versions.
     *
     * @since 0.2.0
     */
    private static function assertEventReferences(array $eventSchemas, string $eventType, array $versions): void
    {
        foreach ($versions as $version) {
            if (!isset($eventSchemas[$eventType . '@' . $version])) {
                throw new InvalidArgumentException('An executable integration declaration references an unknown event.');
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

        /** @var array<string, mixed> $value */
        return $value;
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
        foreach ($value as $item) {
            if (!is_array($item) || array_is_list($item)) {
                throw new InvalidArgumentException(sprintf('Every %s entry must be a non-empty object.', $field));
            }
        }

        /** @var list<array<string, mixed>> $value */
        return $value;
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
        foreach ($value as $item) {
            if (!is_string($item) || $item === '' || $item !== trim($item) || isset($seen[$item])) {
                throw new InvalidArgumentException(sprintf('%s contains an invalid or duplicate value.', $field));
            }
            $seen[$item] = true;
        }

        /** @var list<non-empty-string> $value */
        return $value;
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
