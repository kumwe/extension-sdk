<?php

declare(strict_types=1);

namespace Kumwe\Extension\Manifest;

use InvalidArgumentException;
use Kumwe\Extension\Spi\BusinessSurface\Presentation\Field\FieldPresentationContribution;
use Kumwe\Extension\Spi\Contribution\AdministratorRouteDefinition;
use Kumwe\Extension\Spi\Contribution\AdministratorViewDefinition;
use Kumwe\Extension\Spi\Contribution\AdministratorWorkspaceDefinition;
use Kumwe\Extension\Spi\Contribution\CanonicalCompositionKind;
use Kumwe\Extension\Spi\Contribution\CompositionBlockDeclaration;
use Kumwe\Extension\Spi\Contribution\CompositionDesignVocabularyDeclaration;
use Kumwe\Extension\Spi\Contribution\CompositionFieldControlDeclaration;
use Kumwe\Extension\Spi\Contribution\CompositionHostBinding;
use Kumwe\Extension\Spi\Contribution\CompositionInspectorDeclaration;
use Kumwe\Extension\Spi\Contribution\CompositionMigrationDeclaration;
use Kumwe\Extension\Spi\Contribution\CompositionPatternDeclaration;
use Kumwe\Extension\Spi\Contribution\ContributionOwner;
use Kumwe\Extension\Spi\Portal\Contribution\PortalRouteDefinition;
use Kumwe\Extension\Spi\Portal\Contribution\PortalTemplateDefinition;
use Kumwe\Extension\Spi\Portal\Contribution\PortalWorkspaceDefinition;

/**
 * The contributions one package declares, parsed structurally and bounded, for tooling to report on.
 *
 * This is the SDK's half of the contribution contract. It reproduces the structural layer of the
 * App's manifest contribution parse — the closed key sets per schema generation, the SPI version
 * binding, the per-declaration field grammar, the ownership namespace assertions, and the duplicate
 * refusals — and it constructs the ported declaration types wherever the classification let them
 * move. What it deliberately does not reproduce is the App's deep semantic validation: business
 * definitions, interface-surface conformance, integration payload schemas, and canonical Studio
 * documents are captured as bounded declarations and counted, while their full validation remains
 * the App's at admission and activation, where the authoritative domain types live. Findings the
 * shared inspection produces never depend on that deep layer: reference checks read only the views
 * and portal templates parsed here, and both are the ported byte-equivalent definitions.
 *
 * Entries are indexed and sorted by identifier exactly as the App's set is, so declaration order
 * never changes what tooling reports.
 *
 * @since  0.1.0
 */
final readonly class ManifestContributions
{
    /**
     * Version of the contribution service-provider interface read from schema 2 and 3 manifests.
     *
     * @var    int
     * @since  0.1.0
     */
    public const int SPI_VERSION = 1;

    /**
     * Contribution SPI used by manifest schema 4 business-integration packages.
     *
     * @var    int
     * @since  0.1.0
     */
    public const int CURRENT_SPI_VERSION = 2;

    /**
     * Contribution SPI used by manifest schema 5 packages, which opened the composition surfaces.
     *
     * @var    int
     * @since  0.1.0
     */
    public const int COMPOSITION_SPI_VERSION = 3;

    /**
     * Contribution SPI used by manifest schema 6 packages, which carry canonical Studio documents.
     *
     * @var    int
     * @since  0.1.0
     */
    public const int CANONICAL_COMPOSITION_SPI_VERSION = 4;

    /**
     * Recognised capability and resource-policy lifecycle values, `active` being the default.
     *
     * The vocabulary mirrors the App's authorization lifecycle enum by value, so a manifest the App
     * accepts is accepted here and one it refuses is refused with the same message.
     *
     * @var    list<string>
     * @since  0.1.0
     */
    private const array LIFECYCLES = ['active', 'deprecated', 'disabled', 'retired'];

    /**
     * Package that owns every identifier declared in this set.
     *
     * @var    ContributionOwner
     * @since  0.1.0
     */
    public ContributionOwner $owner;

    /**
     * Declare the parsed set; construction goes through `fromManifest()` or `legacy()`.
     *
     * @param   ContributionOwner                            $ownerValue       Declaring package.
     * @param   int                                          $spiVersion       Contribution SPI version.
     * @param   array<string, array<string, mixed>>          $capabilities     Capability declarations by id.
     * @param   array<string, AdministratorViewDefinition>   $views            Administrator views by name.
     * @param   array<string, PortalTemplateDefinition>      $portalTemplates  Portal templates by name.
     * @param   array<string, int>                           $counts           Declared entries per surface.
     *
     * @since   0.1.0
     */
    private function __construct(
        ContributionOwner $ownerValue,
        private int $spiVersion,
        private array $capabilities = [],
        private array $views = [],
        private array $portalTemplates = [],
        private array $counts = [],
    ) {
        $this->owner = $ownerValue;
    }

    /**
     * Parse one strict manifest's `contributions` object under its schema generation's grammar.
     *
     * @param   ExtensionIdentifier   $extension       Declaring package identity.
     * @param   array<string, mixed>  $data            Decoded `contributions` object.
     * @param   int                   $manifestSchema  Manifest schema generation, 2 through 6.
     *
     * @return  self  Parsed, indexed and counted declaration set.
     *
     * @throws  InvalidArgumentException  When the schema, SPI version, a key set, a field, an
     *          identifier's ownership, or a duplicate declaration violates the frozen grammar.
     *
     * @since   0.1.0
     */
    public static function fromManifest(ExtensionIdentifier $extension, array $data, int $manifestSchema = 3): self
    {
        if (!in_array($manifestSchema, [2, 3, 4, 5, 6], true)) {
            throw new InvalidArgumentException(
                'Typed extension contributions require manifest schema 2, 3, 4, 5, or 6.',
            );
        }
        $data = self::object($data, 'contributions');
        self::knownKeys($data, ExtensionManifestGrammar::contributionKeys($manifestSchema), 'contributions');
        $expectedSpi = match (true) {
            $manifestSchema >= 6 => self::CANONICAL_COMPOSITION_SPI_VERSION,
            $manifestSchema >= 5 => self::COMPOSITION_SPI_VERSION,
            $manifestSchema >= 4 => self::CURRENT_SPI_VERSION,
            default => self::SPI_VERSION,
        };
        if (($data['version'] ?? null) !== $expectedSpi) {
            throw new InvalidArgumentException(sprintf(
                'Manifest schema %d requires extension contribution SPI version %d.',
                $manifestSchema,
                $expectedSpi,
            ));
        }
        $owner = ContributionOwner::extension($extension->value());
        $administrator = self::object($data['administrator'] ?? [], 'contributions.administrator');
        self::knownKeys($administrator, ['workspaces', 'navigation', 'routes', 'views'], 'administrator contributions');
        $business = self::object($data['business'] ?? [], 'contributions.business');
        self::knownKeys($business, ExtensionManifestGrammar::businessKeys($manifestSchema), 'business contributions');
        $portal = self::object($data['portal'] ?? [], 'contributions.portal');
        self::knownKeys($portal, ['workspaces', 'navigation', 'routes', 'templates'], 'portal contributions');
        $interface = self::object($data['interface'] ?? [], 'contributions.interface');
        self::knownKeys($interface, ['surfaces'], 'interface contributions');
        $content = self::object($data['content'] ?? [], 'contributions.content');
        self::knownKeys($content, ExtensionManifestGrammar::contentKeys($manifestSchema), 'content contributions');
        $composition = self::object($data['composition'] ?? [], 'contributions.composition');
        self::knownKeys(
            $composition,
            ExtensionManifestGrammar::compositionKeys($manifestSchema),
            'composition contributions',
        );
        $integration = self::object($data['integration'] ?? [], 'contributions.integration');
        self::knownKeys(
            $integration,
            ExtensionManifestGrammar::integrationKeys($manifestSchema),
            'integration contributions',
        );

        $capabilities = self::index(array_map(static function (array $item) use ($owner): array {
            self::knownKeys(
                $item,
                ['id', 'label', 'description', 'allowed_scopes', 'delegatable', 'high_impact', 'lifecycle', 'version'],
                'capability contribution',
            );
            $declaration = [
                'id' => self::string($item, 'id'),
                'label' => self::string($item, 'label'),
                'description' => self::string($item, 'description'),
                'allowed_scopes' => self::strings($item, 'allowed_scopes', ['global', 'site']),
                'delegatable' => self::boolean($item, 'delegatable', true),
                'high_impact' => self::boolean($item, 'high_impact', false),
                'lifecycle' => self::lifecycle($item),
                'version' => self::positiveInteger($item, 'version', 1),
            ];
            $owner->assertOwns($declaration['id'], 'capability');

            return $declaration;
        }, self::objects($data['capabilities'] ?? [], 'contributions.capabilities')), 'capability', 'id');

        $resourcePolicies = self::index(array_map(static function (array $item) use ($owner): array {
            self::knownKeys(
                $item,
                ['id', 'capability', 'resources', 'installation_global', 'system_identities', 'lifecycle', 'version'],
                'resource-policy contribution',
            );
            if (self::strings($item, 'system_identities', []) !== []) {
                throw new InvalidArgumentException(
                    'Extension resource policies cannot grant authority to system identities.',
                );
            }
            foreach (self::objects($item['resources'] ?? null, 'resource policy resources') as $resource) {
                self::knownKeys($resource, ['type', 'identifiers'], 'resource-policy target');
                self::string($resource, 'type');
                self::strings($resource, 'identifiers', []);
            }
            $declaration = [
                'id' => self::string($item, 'id'),
                'capability' => self::string($item, 'capability'),
                'installation_global' => self::boolean($item, 'installation_global', false),
                'lifecycle' => self::lifecycle($item),
                'version' => self::positiveInteger($item, 'version', 1),
            ];
            $owner->assertOwns($declaration['id'], 'resource policy');
            $owner->assertOwns($declaration['capability'], 'capability');

            return $declaration;
        }, self::objects($data['resource_policies'] ?? [], 'contributions.resource_policies')), 'resource policy', 'id');

        $workspaces = self::indexDefinitions(array_map(static function (array $item) use (
            $owner,
        ): AdministratorWorkspaceDefinition {
            self::knownKeys($item, ['id', 'label', 'description', 'priority'], 'workspace contribution');
            $definition = new AdministratorWorkspaceDefinition(
                self::string($item, 'id'),
                self::string($item, 'label'),
                self::string($item, 'description'),
                self::integer($item, 'priority'),
            );
            $owner->assertOwns($definition->id, 'workspace');

            return $definition;
        }, self::objects($administrator['workspaces'] ?? [], 'contributions.administrator.workspaces')), 'workspace');

        $navigation = self::index(array_map(static function (array $item) use ($owner): array {
            self::knownKeys(
                $item,
                ['id', 'workspace', 'label', 'description', 'path', 'icon', 'capability', 'priority', 'keywords',
                    'surface'],
                'navigation contribution',
            );
            $declaration = [
                'id' => self::string($item, 'id'),
                'workspace' => self::string($item, 'workspace'),
                'label' => self::string($item, 'label'),
                'description' => self::string($item, 'description'),
                'path' => self::string($item, 'path'),
                'icon' => self::string($item, 'icon'),
                'capability' => self::string($item, 'capability'),
                'priority' => self::integer($item, 'priority'),
                'keywords' => self::optionalString($item, 'keywords'),
                'surface' => self::optionalString($item, 'surface'),
            ];
            $owner->assertOwns($declaration['id'], 'navigation');
            $owner->assertOwns($declaration['workspace'], 'workspace');
            $owner->assertOwns($declaration['capability'], 'capability');

            return $declaration;
        }, self::objects($administrator['navigation'] ?? [], 'contributions.administrator.navigation')), 'navigation', 'id');

        $routes = self::indexDefinitions(array_map(static function (array $item) use (
            $owner,
        ): AdministratorRouteDefinition {
            self::knownKeys($item, ['name', 'path', 'methods', 'capability', 'view'], 'route contribution');
            $methods = $item['methods'] ?? null;
            $definition = new AdministratorRouteDefinition(
                self::string($item, 'name'),
                self::string($item, 'path'),
                is_array($methods) ? $methods : throw new InvalidArgumentException('Route methods must be a list.'),
                self::string($item, 'capability'),
                self::string($item, 'view'),
            );
            $owner->assertOwns($definition->name, 'route');
            $owner->assertOwns($definition->capability, 'capability');
            $owner->assertOwns($definition->view, 'view');

            return $definition;
        }, self::objects($administrator['routes'] ?? [], 'contributions.administrator.routes')), 'route');

        $views = self::indexDefinitions(array_map(static function (array $item) use (
            $owner,
        ): AdministratorViewDefinition {
            self::knownKeys($item, ['name', 'template'], 'view contribution');
            $definition = new AdministratorViewDefinition(
                self::string($item, 'name'),
                self::string($item, 'template'),
            );
            $owner->assertOwns($definition->name, 'view');

            return $definition;
        }, self::objects($administrator['views'] ?? [], 'contributions.administrator.views')), 'view');

        $portalWorkspaces = self::indexDefinitions(array_map(static function (array $item) use (
            $owner,
        ): PortalWorkspaceDefinition {
            self::knownKeys($item, ['id', 'label', 'description', 'priority'], 'portal workspace contribution');
            $definition = new PortalWorkspaceDefinition(
                self::string($item, 'id'),
                self::string($item, 'label'),
                self::string($item, 'description'),
                self::integer($item, 'priority'),
            );
            $owner->assertOwns($definition->id, 'portal workspace');

            return $definition;
        }, self::objects($portal['workspaces'] ?? [], 'contributions.portal.workspaces')), 'portal workspace');

        $portalNavigation = self::index(array_map(static function (array $item) use ($owner): array {
            self::knownKeys(
                $item,
                ['id', 'workspace', 'label', 'description', 'path', 'icon', 'capability', 'priority', 'keywords',
                    'surface'],
                'portal navigation contribution',
            );
            $declaration = [
                'id' => self::string($item, 'id'),
                'workspace' => self::string($item, 'workspace'),
                'label' => self::string($item, 'label'),
                'description' => self::string($item, 'description'),
                'path' => self::string($item, 'path'),
                'icon' => self::string($item, 'icon'),
                'capability' => self::string($item, 'capability'),
                'priority' => self::integer($item, 'priority'),
                'keywords' => self::optionalString($item, 'keywords'),
                'surface' => self::optionalString($item, 'surface'),
            ];
            $owner->assertOwns($declaration['id'], 'portal navigation');
            $owner->assertOwns($declaration['workspace'], 'portal workspace');
            $owner->assertOwns($declaration['capability'], 'capability');

            return $declaration;
        }, self::objects($portal['navigation'] ?? [], 'contributions.portal.navigation')), 'portal navigation', 'id');

        $portalRoutes = self::indexDefinitions(array_map(static function (array $item) use (
            $owner,
        ): PortalRouteDefinition {
            self::knownKeys($item, ['name', 'path', 'methods', 'capability', 'template'], 'portal route contribution');
            $methods = $item['methods'] ?? null;
            $definition = new PortalRouteDefinition(
                self::string($item, 'name'),
                self::string($item, 'path'),
                is_array($methods) ? $methods : throw new InvalidArgumentException('Route methods must be a list.'),
                self::string($item, 'capability'),
                self::string($item, 'template'),
            );
            $owner->assertOwns($definition->name, 'portal route');
            $owner->assertOwns($definition->capability, 'capability');
            $owner->assertOwns($definition->template, 'portal template');

            return $definition;
        }, self::objects($portal['routes'] ?? [], 'contributions.portal.routes')), 'portal route');

        $portalTemplates = self::indexDefinitions(array_map(static function (array $item) use (
            $owner,
        ): PortalTemplateDefinition {
            self::knownKeys($item, ['name', 'template'], 'portal template contribution');
            $definition = new PortalTemplateDefinition(
                self::string($item, 'name'),
                self::string($item, 'template'),
            );
            $owner->assertOwns($definition->name, 'portal template');

            return $definition;
        }, self::objects($portal['templates'] ?? [], 'contributions.portal.templates')), 'portal template');

        $interfaceSurfaces = self::index(array_map(static function (array $item) use ($owner): array {
            $surface = self::string($item, 'surface');
            $owner->assertOwns($surface, 'interface surface');

            return ['surface' => $surface] + $item;
        }, self::objects($interface['surfaces'] ?? [], 'contributions.interface.surfaces')), 'interface surface', 'surface');
        if (array_key_exists('interface', $data) && $interfaceSurfaces === []) {
            throw new InvalidArgumentException('A declared KIS interface section requires at least one surface.');
        }

        $fieldPresentations = array_map(
            static fn (array $item): FieldPresentationContribution => FieldPresentationContribution::fromArray($item),
            self::objects($business['field_presentations'] ?? [], 'contributions.business.field_presentations'),
        );

        $compositionDeclarations = [
            'composition.blocks' => [$composition['blocks'] ?? [], 'contributions.composition.blocks',
                static fn (array $item): object => CompositionBlockDeclaration::fromArray($item)],
            'composition.patterns' => [$composition['patterns'] ?? [], 'contributions.composition.patterns',
                static fn (array $item): object => CompositionPatternDeclaration::fromArray($item)],
            'composition.field_controls' => [$composition['field_controls'] ?? [],
                'contributions.composition.field_controls',
                static fn (array $item): object => CompositionFieldControlDeclaration::fromArray($item)],
            'composition.inspectors' => [$composition['inspectors'] ?? [], 'contributions.composition.inspectors',
                static fn (array $item): object => CompositionInspectorDeclaration::fromArray($item)],
            'composition.design_vocabularies' => [$composition['design_vocabularies'] ?? [],
                'contributions.composition.design_vocabularies',
                static fn (array $item): object => CompositionDesignVocabularyDeclaration::fromArray($item)],
            'composition.migrations' => [$composition['migrations'] ?? [], 'contributions.composition.migrations',
                static fn (array $item): object => CompositionMigrationDeclaration::fromArray($item)],
        ];
        $compositionCounts = [];
        foreach ($compositionDeclarations as $surface => [$declared, $field, $factory]) {
            $identifiers = [];
            foreach (self::objects($declared, $field) as $item) {
                /** @var object{identifier: callable(): string} $declaration */
                $declaration = $factory($item);
                /** @var string $identifier */
                $identifier = $declaration->identifier();
                if (isset($identifiers[$identifier])) {
                    throw new InvalidArgumentException(sprintf(
                        'Contribution %s %s is declared more than once.',
                        substr($surface, strlen('composition.')),
                        $identifier,
                    ));
                }
                $identifiers[$identifier] = true;
            }
            $compositionCounts[$surface] = count($identifiers);
        }

        $documents = [];
        $hostBindings = [];
        if ($manifestSchema >= 6) {
            foreach (self::objects($composition['documents'] ?? [], 'contributions.composition.documents') as $item) {
                self::knownKeys($item, ['kind', 'canonical'], 'canonical composition document');
                $kind = CanonicalCompositionKind::tryFrom(self::string($item, 'kind'))
                    ?? throw new InvalidArgumentException('A canonical composition document names an unknown kind.');
                $canonical = $item['canonical'] ?? null;
                if (!is_string($canonical)) {
                    throw new InvalidArgumentException(
                        'A canonical composition document must carry its canonical JSON string.',
                    );
                }
                $documents[] = ['kind' => $kind->value, 'canonical' => $canonical];
            }
            $hostBindings = array_map(static function (array $item): CompositionHostBinding {
                self::knownKeys($item, ['kind', 'id', 'renderer', 'capability'], 'composition host binding');
                $kind = CanonicalCompositionKind::tryFrom(self::string($item, 'kind'))
                    ?? throw new InvalidArgumentException('A composition host binding names an unknown kind.');

                return new CompositionHostBinding(
                    $kind,
                    self::string($item, 'id'),
                    ($item['renderer'] ?? null) !== null ? self::string($item, 'renderer') : null,
                    ($item['capability'] ?? null) !== null ? self::string($item, 'capability') : null,
                );
            }, self::objects($composition['host_bindings'] ?? [], 'contributions.composition.host_bindings'));
        }

        $counts = array_filter([
            'capabilities' => count($capabilities),
            'resource_policies' => count($resourcePolicies),
            'administrator.workspaces' => count($workspaces),
            'administrator.navigation' => count($navigation),
            'administrator.routes' => count($routes),
            'administrator.views' => count($views),
            'portal.workspaces' => count($portalWorkspaces),
            'portal.navigation' => count($portalNavigation),
            'portal.templates' => count($portalTemplates),
            'portal.routes' => count($portalRoutes),
            'interface.surfaces' => count($interfaceSurfaces),
            'business.field_types' => count(self::objects(
                $business['field_types'] ?? [],
                'contributions.business.field_types',
            )),
            'business.field_presentations' => count($fieldPresentations),
            'business.definitions' => count(self::objects(
                $business['definitions'] ?? [],
                'contributions.business.definitions',
            )),
            'business.view_handlers' => count(self::objects(
                $business['view_handlers'] ?? [],
                'contributions.business.view_handlers',
            )),
            'business.action_handlers' => count(self::objects(
                $business['action_handlers'] ?? [],
                'contributions.business.action_handlers',
            )),
            'integration.event_schemas' => count(self::objects(
                $integration['event_schemas'] ?? [],
                'contributions.integration.event_schemas',
            )),
            'integration.domain_listeners' => count(self::objects(
                $integration['domain_listeners'] ?? [],
                'contributions.integration.domain_listeners',
            )),
            'integration.consumers' => count(self::objects(
                $integration['consumers'] ?? [],
                'contributions.integration.consumers',
            )),
            'integration.jobs' => count(self::objects($integration['jobs'] ?? [], 'contributions.integration.jobs')),
            'integration.queues' => count(self::objects(
                $integration['queues'] ?? [],
                'contributions.integration.queues',
            )),
            'integration.schedules' => count(self::objects(
                $integration['schedules'] ?? [],
                'contributions.integration.schedules',
            )),
            'integration.projections' => count(self::objects(
                $integration['projections'] ?? [],
                'contributions.integration.projections',
            )),
            'integration.reports' => count(self::objects(
                $integration['reports'] ?? [],
                'contributions.integration.reports',
            )),
            'integration.webhooks' => count(self::objects(
                $integration['webhooks'] ?? [],
                'contributions.integration.webhooks',
            )),
            'integration.money_rate_providers' => count(self::objects(
                $integration['rate_providers'] ?? [],
                'contributions.integration.rate_providers',
            )),
            'integration.unit_conversion_providers' => count(self::objects(
                $integration['unit_converters'] ?? [],
                'contributions.integration.unit_converters',
            )),
            'content.translation_groups' => count(self::objects(
                $content['translation_groups'] ?? [],
                'contributions.content.translation_groups',
            )),
            ...$compositionCounts,
            'composition.documents' => count($documents),
            'composition.host_bindings' => count($hostBindings),
        ], static fn (int $count): bool => $count > 0);

        return new self($owner, $expectedSpi, $capabilities, $views, $portalTemplates, $counts);
    }

    /**
     * The empty declaration set a schema-1 package stands in with.
     *
     * @param   ExtensionIdentifier  $extension    Package the empty set is attributed to.
     * @param   list<string>         $permissions  The manifest's schema-1 permission codes; not read.
     *
     * @return  self  A set owned by that package and declaring nothing.
     *
     * @since   0.1.0
     */
    public static function legacy(ExtensionIdentifier $extension, array $permissions): self
    {
        return new self(ContributionOwner::extension($extension->value()), self::SPI_VERSION);
    }

    /**
     * Report which contribution SPI generation the declaring manifest bound itself to.
     *
     * @return  int  SPI version derived from the manifest schema; 1 for a legacy set.
     *
     * @since   0.1.0
     */
    public function spiVersion(): int
    {
        return $this->spiVersion;
    }

    /**
     * List the capability identifiers this package declares, sorted by identifier.
     *
     * The order matches the App's indexed capability set, which is what strict manifests reconcile
     * their `permissions` list against.
     *
     * @return  list<string>  Sorted capability identifiers.
     *
     * @since   0.1.0
     */
    public function capabilityIdentifiers(): array
    {
        return array_keys($this->capabilities);
    }

    /**
     * List the declared administrator views, sorted by view name.
     *
     * @return  list<AdministratorViewDefinition>  Every declared view; reference checks read each
     *          view's template.
     *
     * @since   0.1.0
     */
    public function views(): array
    {
        return array_values($this->views);
    }

    /**
     * List the declared portal templates, sorted by template name.
     *
     * @return  list<PortalTemplateDefinition>  Every declared portal template.
     *
     * @since   0.1.0
     */
    public function portalTemplates(): array
    {
        return array_values($this->portalTemplates);
    }

    /**
     * Report how many entries each contribution surface declares, empty surfaces omitted.
     *
     * The keys are the dotted surface names the frozen contract's generation records use for their
     * fixture inventories, so a parsed compatibility package can be compared directly against what
     * its generation promises.
     *
     * @return  array<string, int>  Declared entry count per dotted surface key.
     *
     * @since   0.1.0
     */
    public function surfaceCounts(): array
    {
        return $this->counts;
    }

    /**
     * Export the declaration set for reports and diagnostics.
     *
     * This is the SDK's bounded view — the SPI version and the per-surface declaration counts —
     * not the App's deep normalized contribution export, which needs domain types that stay in the
     * App by classification.
     *
     * @return  array{version: int, declared: array<string, int>}  Canonical bounded export.
     *
     * @since   0.1.0
     */
    public function toArray(): array
    {
        return ['version' => $this->spiVersion, 'declared' => $this->counts];
    }

    /**
     * Key structural declarations by an identifier field, refusing repeats, then sort by key.
     *
     * @param   list<array<string, mixed>>  $items       Declarations of one kind, in manifest order.
     * @param   string                      $kind        Kind name used in the duplicate message.
     * @param   string                      $identifier  Field carrying each declaration's identifier.
     *
     * @return  array<string, array<string, mixed>>  Declarations keyed and sorted by identifier.
     *
     * @throws  InvalidArgumentException  When an identifier repeats.
     *
     * @since   0.1.0
     */
    private static function index(array $items, string $kind, string $identifier = 'id'): array
    {
        $result = [];
        foreach ($items as $item) {
            $key = (string) $item[$identifier];
            if (isset($result[$key])) {
                throw new InvalidArgumentException(sprintf(
                    'Contribution %s %s is declared more than once.',
                    $kind,
                    $key,
                ));
            }
            $result[$key] = $item;
        }
        ksort($result, SORT_STRING);

        return $result;
    }

    /**
     * Key ported definition objects by their own identifier, refusing repeats, then sort by key.
     *
     * @template T of object
     *
     * @param   list<T>  $items  Definitions of one kind, in manifest order.
     * @param   string   $kind   Kind name used in the duplicate message.
     *
     * @return  array<string, T>  Definitions keyed and sorted by identifier.
     *
     * @throws  InvalidArgumentException  When an identifier repeats.
     *
     * @since   0.1.0
     */
    private static function indexDefinitions(array $items, string $kind): array
    {
        $result = [];
        foreach ($items as $item) {
            /** @var string $key */
            $key = $item->identifier();
            if (isset($result[$key])) {
                throw new InvalidArgumentException(sprintf(
                    'Contribution %s %s is declared more than once.',
                    $kind,
                    $key,
                ));
            }
            $result[$key] = $item;
        }
        ksort($result, SORT_STRING);

        return $result;
    }

    /**
     * Insist a decoded manifest value is a JSON object.
     *
     * @param   mixed   $value  Decoded manifest value to check.
     * @param   string  $field  Dotted manifest path named in the failure message.
     *
     * @return  array<string, mixed>  The object, empty when nothing was declared.
     *
     * @throws  InvalidArgumentException  When the value is a non-empty list or not an array.
     *
     * @since   0.1.0
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
     * Insist a decoded manifest value is a bounded list of JSON objects.
     *
     * The 128-entry cap is a denial-of-service guard: manifest parsing happens before the package
     * is trusted, so a declaration list is never allowed to be unbounded work.
     *
     * @param   mixed   $value  Decoded manifest value to check.
     * @param   string  $field  Dotted manifest path named in the failure message.
     *
     * @return  list<array<string, mixed>>  The entries in manifest order, each known to be keyed.
     *
     * @throws  InvalidArgumentException  When the value is not a list, holds more than 128 entries,
     *          or contains anything that is not an object.
     *
     * @since   0.1.0
     */
    private static function objects(mixed $value, string $field): array
    {
        if (!is_array($value) || !array_is_list($value) || count($value) > 128) {
            throw new InvalidArgumentException(sprintf('%s must be a list of at most 128 objects.', $field));
        }
        $result = [];
        foreach ($value as $item) {
            if (!is_array($item) || array_is_list($item)) {
                throw new InvalidArgumentException(sprintf('Every %s entry must be an object.', $field));
            }
            /** @var array<string, mixed> $item */
            $result[] = $item;
        }

        return $result;
    }

    /**
     * Reject a manifest object carrying any key this generation does not understand.
     *
     * @param   array<string, mixed>  $values   Decoded manifest object to inspect.
     * @param   list<string>          $allowed  Every key the generation accepts at that position.
     * @param   string                $field    Manifest section named in the failure message.
     *
     * @return  void
     *
     * @throws  InvalidArgumentException  When any key falls outside the allowed set.
     *
     * @since   0.1.0
     */
    private static function knownKeys(array $values, array $allowed, string $field): void
    {
        $unknown = array_diff(array_keys($values), $allowed);
        if ($unknown !== []) {
            sort($unknown, SORT_STRING);
            throw new InvalidArgumentException(sprintf('%s contains unknown key %s.', $field, $unknown[0]));
        }
    }

    /**
     * Read a required non-empty string field out of a decoded manifest object.
     *
     * @param   array<string, mixed>  $values  Decoded manifest object holding the field.
     * @param   string                $field   Key to read, also named in the failure message.
     *
     * @return  string  The value with surrounding whitespace removed.
     *
     * @throws  InvalidArgumentException  When the key is absent, not a string, or blank once trimmed.
     *
     * @since   0.1.0
     */
    private static function string(array $values, string $field): string
    {
        $value = $values[$field] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(sprintf('Contribution field %s must be a non-empty string.', $field));
        }

        return trim($value);
    }

    /**
     * Read an optional string field, treating absence and emptiness alike.
     *
     * @param   array<string, mixed>  $values  Decoded manifest object that may hold the field.
     * @param   string                $field   Key to read, also named in the failure message.
     *
     * @return  string  The trimmed value, or an empty string when the key was not present.
     *
     * @throws  InvalidArgumentException  When the key is present but not a string.
     *
     * @since   0.1.0
     */
    private static function optionalString(array $values, string $field): string
    {
        $value = $values[$field] ?? '';
        if (!is_string($value)) {
            throw new InvalidArgumentException(sprintf('Contribution field %s must be a string.', $field));
        }

        return trim($value);
    }

    /**
     * Read an optional bounded list of non-empty strings from a decoded manifest object.
     *
     * @param   array<string, mixed>  $values   Object that may hold the list.
     * @param   string                $field    Key to read and name in a failure.
     * @param   list<string>          $default  Value returned when the key is absent.
     *
     * @return  list<string>  Trimmed strings in declaration order.
     *
     * @throws  InvalidArgumentException  When the value is not a list of at most 128 non-empty strings.
     *
     * @since   0.1.0
     */
    private static function strings(array $values, string $field, array $default): array
    {
        $value = $values[$field] ?? $default;
        if (!is_array($value) || !array_is_list($value) || count($value) > 128) {
            throw new InvalidArgumentException(sprintf('Contribution field %s must be a bounded string list.', $field));
        }
        $result = [];
        foreach ($value as $item) {
            if (!is_string($item) || trim($item) === '') {
                throw new InvalidArgumentException(sprintf(
                    'Every contribution field %s entry must be a non-empty string.',
                    $field,
                ));
            }
            $result[] = trim($item);
        }

        return $result;
    }

    /**
     * Read an optional strict boolean from a decoded manifest object.
     *
     * @param   array<string, mixed>  $values   Object that may hold the value.
     * @param   string                $field    Key to read and name in a failure.
     * @param   bool                  $default  Value returned when the key is absent.
     *
     * @return  bool  Decoded boolean without scalar coercion.
     *
     * @throws  InvalidArgumentException  When a present value is not a boolean.
     *
     * @since   0.1.0
     */
    private static function boolean(array $values, string $field, bool $default): bool
    {
        $value = $values[$field] ?? $default;
        if (!is_bool($value)) {
            throw new InvalidArgumentException(sprintf('Contribution field %s must be a boolean.', $field));
        }

        return $value;
    }

    /**
     * Read a capability or policy lifecycle, defaulting an omitted value to active.
     *
     * @param   array<string, mixed>  $values  Declaration object that may carry `lifecycle`.
     *
     * @return  string  Validated lifecycle value.
     *
     * @throws  InvalidArgumentException  When the value is not a recognized lifecycle string.
     *
     * @since   0.1.0
     */
    private static function lifecycle(array $values): string
    {
        $value = $values['lifecycle'] ?? 'active';
        if (!is_string($value)) {
            throw new InvalidArgumentException('Contribution field lifecycle must be a string.');
        }
        if (!in_array($value, self::LIFECYCLES, true)) {
            throw new InvalidArgumentException('Contribution field lifecycle is not recognized.');
        }

        return $value;
    }

    /**
     * Read an optional positive integer from a decoded manifest object.
     *
     * @param   array<string, mixed>  $values   Object that may hold the value.
     * @param   string                $field    Key to read and name in a failure.
     * @param   int                   $default  Positive value returned when the key is absent.
     *
     * @return  int  Strict positive integer without numeric-string coercion.
     *
     * @throws  InvalidArgumentException  When a present value is not a positive integer.
     *
     * @since   0.1.0
     */
    private static function positiveInteger(array $values, string $field, int $default): int
    {
        $value = $values[$field] ?? $default;
        if (!is_int($value) || $value < 1) {
            throw new InvalidArgumentException(sprintf('Contribution field %s must be a positive integer.', $field));
        }

        return $value;
    }

    /**
     * Read a required integer field out of a decoded manifest object.
     *
     * A numeric string is not coerced, so a priority written as `"10"` in a manifest is a
     * declaration error rather than a silently accepted value.
     *
     * @param   array<string, mixed>  $values  Decoded manifest object holding the field.
     * @param   string                $field   Key to read, also named in the failure message.
     *
     * @return  int  The value exactly as decoded.
     *
     * @throws  InvalidArgumentException  When the key is absent or is not an integer.
     *
     * @since   0.1.0
     */
    private static function integer(array $values, string $field): int
    {
        $value = $values[$field] ?? null;
        if (!is_int($value)) {
            throw new InvalidArgumentException(sprintf('Contribution field %s must be an integer.', $field));
        }

        return $value;
    }
}
