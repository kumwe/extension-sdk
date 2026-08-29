<?php

declare(strict_types=1);

namespace Kumwe\Extension\Manifest;

use InvalidArgumentException;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\DomainListenerDefinition;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\EventConsumerDefinition;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\JobContributionDefinition;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\WebhookContributionDefinition;
use Kumwe\Extension\Spi\BusinessReporting\Domain\ProjectionDefinition;
use Kumwe\Extension\Spi\Binding\ExecutableBindingRequirements;
use Kumwe\Extension\Spi\BusinessSurface\Presentation\Field\FieldPresentationContribution;
use Kumwe\Extension\Spi\Contribution\AdministratorNavigationDefinition;
use Kumwe\Extension\Spi\Contribution\AdministratorRouteDefinition;
use Kumwe\Extension\Spi\Contribution\AdministratorViewDefinition;
use Kumwe\Extension\Spi\Contribution\AdministratorWorkspaceDefinition;
use Kumwe\Extension\Spi\Contribution\CanonicalCompositionKind;
use Kumwe\Extension\Spi\Contribution\CanonicalCompositionDocument;
use Kumwe\Extension\Spi\Contribution\CompositionBlockDeclaration;
use Kumwe\Extension\Spi\Contribution\CompositionDesignVocabularyDeclaration;
use Kumwe\Extension\Spi\Contribution\CompositionFieldControlDeclaration;
use Kumwe\Extension\Spi\Contribution\CompositionHostBinding;
use Kumwe\Extension\Spi\Contribution\CompositionInspectorDeclaration;
use Kumwe\Extension\Spi\Contribution\CompositionMigrationDeclaration;
use Kumwe\Extension\Spi\Contribution\CompositionPatternDeclaration;
use Kumwe\Extension\Spi\Contribution\ContributionDefinition;
use Kumwe\Extension\Spi\Contribution\ContributionOwner;
use Kumwe\Extension\Spi\Portal\Contribution\PortalRouteDefinition;
use Kumwe\Extension\Spi\Portal\Contribution\PortalNavigationDefinition;
use Kumwe\Extension\Spi\Portal\Contribution\PortalTemplateDefinition;
use Kumwe\Extension\Spi\Portal\Contribution\PortalWorkspaceDefinition;

/**
 * The canonical, owner-checked contribution graph declared by one signed package.
 *
 * Structural grammar, bounded values, executable definitions, duplicate detection and cross-references
 * are validated once here. A host may add admission policy, but it consumes these exact canonical values
 * and never reparses them into a second definition graph.
 *
 * Entries are indexed and sorted by identifier, so declaration order never changes what tooling reports.
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
     * Hosts may use the same closed values without importing an application-owned authorization type.
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
     * Declare the parsed set; construction goes through `fromManifest()` or `fromSchemaOne()`.
     *
     * @param  ContributionOwner                             $ownerValue       Declaring package.
     * @param  int                                           $spiVersion       Contribution SPI version.
     * @param  array<string, array<string, mixed>>           $capabilities     Capability declarations by id.
     * @param  array<string, AdministratorWorkspaceDefinition> $workspaces     Administrator workspaces by id.
     * @param  array<string, AdministratorNavigationDefinition> $navigation    Administrator navigation by id.
     * @param  array<string, AdministratorRouteDefinition>    $routes           Administrator routes by name.
     * @param  array<string, AdministratorViewDefinition>    $views            Administrator views by name.
     * @param  array<string, PortalWorkspaceDefinition>       $portalWorkspaces Portal workspaces by id.
     * @param  array<string, PortalNavigationDefinition>      $portalNavigation Portal navigation by id.
     * @param  array<string, PortalRouteDefinition>           $portalRoutes     Portal routes by name.
     * @param  array<string, PortalTemplateDefinition>       $portalTemplates  Portal templates by name.
     * @param  array<string, FieldPresentationContribution>   $fieldPresentations Field presenters by type.
     * @param  array<string, DomainListenerDefinition>       $domainListeners  Listener definitions by id.
     * @param  array<string, EventConsumerDefinition>        $eventConsumers   Consumer definitions by id.
     * @param  array<string, JobContributionDefinition>      $jobs             Job definitions by id.
     * @param  array<string, ProjectionDefinition>           $projections      Projection definitions by id.
     * @param  array<string, WebhookContributionDefinition>  $webhooks         Webhook definitions by id.
     * @param  array<string, CompositionBlockDeclaration>     $compositionBlocks Blocks by id.
     * @param  array<string, CompositionPatternDeclaration>   $compositionPatterns Patterns by id.
     * @param  array<string, CompositionFieldControlDeclaration> $compositionFieldControls Controls by id.
     * @param  array<string, CompositionInspectorDeclaration> $compositionInspectors Inspectors by id.
     * @param  array<string, CompositionDesignVocabularyDeclaration> $compositionDesignVocabularies Vocabularies by id.
     * @param  array<string, CompositionMigrationDeclaration> $compositionMigrations Migrations by id.
     * @param  array<string, CanonicalCompositionDocument>     $canonicalCompositionDocuments Documents by kind and id.
     * @param  array<string, CompositionHostBinding>           $compositionHostBindings Host bindings by id.
     * @param  array<string, int>                            $counts           Declared entries per surface.
     * @param  array<string, mixed>                          $declarations     Validated contribution object.
     *
     * @since  0.1.0
     */
    private function __construct(
        ContributionOwner $ownerValue,
        private int $spiVersion,
        private array $capabilities = [],
        private array $workspaces = [],
        private array $navigation = [],
        private array $routes = [],
        private array $views = [],
        private array $portalWorkspaces = [],
        private array $portalNavigation = [],
        private array $portalRoutes = [],
        private array $portalTemplates = [],
        private array $fieldPresentations = [],
        private array $domainListeners = [],
        private array $eventConsumers = [],
        private array $jobs = [],
        private array $projections = [],
        private array $webhooks = [],
        private array $compositionBlocks = [],
        private array $compositionPatterns = [],
        private array $compositionFieldControls = [],
        private array $compositionInspectors = [],
        private array $compositionDesignVocabularies = [],
        private array $compositionMigrations = [],
        private array $canonicalCompositionDocuments = [],
        private array $compositionHostBindings = [],
        private array $counts = [],
        private array $declarations = [],
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
        ManifestContributionGraphValidator::validate($owner, $data);
        $data = self::canonicalGraph($data);

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

        $navigation = self::indexDefinitions(array_map(static function (array $item) use (
            $owner,
        ): AdministratorNavigationDefinition {
            self::knownKeys(
                $item,
                ['id', 'workspace', 'label', 'description', 'path', 'icon', 'capability', 'priority', 'keywords',
                    'surface'],
                'navigation contribution',
            );
            $surface = self::optionalString($item, 'surface');
            $definition = new AdministratorNavigationDefinition(
                self::string($item, 'id'),
                self::string($item, 'workspace'),
                self::string($item, 'label'),
                self::string($item, 'description'),
                self::string($item, 'path'),
                self::string($item, 'icon'),
                self::string($item, 'capability'),
                self::integer($item, 'priority'),
                self::optionalString($item, 'keywords'),
                $surface === '' ? null : $surface,
            );
            $owner->assertOwns($definition->id, 'navigation');
            $owner->assertOwns($definition->workspace, 'workspace');
            $owner->assertOwns($definition->capability, 'capability');
            if ($definition->surface !== null) {
                $owner->assertOwns($definition->surface, 'interface surface');
            }

            return $definition;
        }, self::objects($administrator['navigation'] ?? [], 'contributions.administrator.navigation')), 'navigation');

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

        $portalNavigation = self::indexDefinitions(array_map(static function (array $item) use (
            $owner,
        ): PortalNavigationDefinition {
            self::knownKeys(
                $item,
                ['id', 'workspace', 'label', 'description', 'path', 'icon', 'capability', 'priority', 'keywords',
                    'surface'],
                'portal navigation contribution',
            );
            $surface = self::optionalString($item, 'surface');
            $definition = new PortalNavigationDefinition(
                self::string($item, 'id'),
                self::string($item, 'workspace'),
                self::string($item, 'label'),
                self::string($item, 'description'),
                self::string($item, 'path'),
                self::string($item, 'icon'),
                self::string($item, 'capability'),
                self::integer($item, 'priority'),
                self::optionalString($item, 'keywords'),
                $surface === '' ? null : $surface,
            );
            $owner->assertOwns($definition->id, 'portal navigation');
            $owner->assertOwns($definition->workspace, 'portal workspace');
            $owner->assertOwns($definition->capability, 'capability');
            if ($definition->surface !== null) {
                $owner->assertOwns($definition->surface, 'interface surface');
            }

            return $definition;
        }, self::objects($portal['navigation'] ?? [], 'contributions.portal.navigation')), 'portal navigation');

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

        $fieldPresentations = self::indexDefinitions(array_map(
            static fn (array $item): FieldPresentationContribution => FieldPresentationContribution::fromArray($item),
            self::objects($business['field_presentations'] ?? [], 'contributions.business.field_presentations'),
        ), 'field presentation');

        $domainListeners = self::indexDefinitions(array_map(
            static fn (array $item): DomainListenerDefinition => DomainListenerDefinition::fromArray($item),
            self::objects($integration['domain_listeners'] ?? [], 'contributions.integration.domain_listeners'),
        ), 'domain listener');
        $eventConsumers = self::indexDefinitions(array_map(
            static fn (array $item): EventConsumerDefinition => EventConsumerDefinition::fromArray($item),
            self::objects($integration['consumers'] ?? [], 'contributions.integration.consumers'),
        ), 'event consumer');
        $jobs = self::indexDefinitions(array_map(
            static fn (array $item): JobContributionDefinition => JobContributionDefinition::fromArray($item),
            self::objects($integration['jobs'] ?? [], 'contributions.integration.jobs'),
        ), 'job');
        $projections = self::indexDefinitions(array_map(
            static fn (array $item): ProjectionDefinition => ProjectionDefinition::fromArray($item),
            self::objects($integration['projections'] ?? [], 'contributions.integration.projections'),
        ), 'projection');
        $webhooks = self::indexDefinitions(array_map(
            static fn (array $item): WebhookContributionDefinition => WebhookContributionDefinition::fromArray($item),
            self::objects($integration['webhooks'] ?? [], 'contributions.integration.webhooks'),
        ), 'webhook');

        $compositionBlocks = self::indexDefinitions(array_map(
            static fn (array $item): CompositionBlockDeclaration => CompositionBlockDeclaration::fromArray($item),
            self::objects($composition['blocks'] ?? [], 'contributions.composition.blocks'),
        ), 'composition block');
        $compositionPatterns = self::indexDefinitions(array_map(
            static fn (array $item): CompositionPatternDeclaration => CompositionPatternDeclaration::fromArray($item),
            self::objects($composition['patterns'] ?? [], 'contributions.composition.patterns'),
        ), 'composition pattern');
        $compositionFieldControls = self::indexDefinitions(array_map(
            static fn (array $item): CompositionFieldControlDeclaration
                => CompositionFieldControlDeclaration::fromArray($item),
            self::objects($composition['field_controls'] ?? [], 'contributions.composition.field_controls'),
        ), 'composition field control');
        $compositionInspectors = self::indexDefinitions(array_map(
            static fn (array $item): CompositionInspectorDeclaration
                => CompositionInspectorDeclaration::fromArray($item),
            self::objects($composition['inspectors'] ?? [], 'contributions.composition.inspectors'),
        ), 'composition inspector');
        $compositionDesignVocabularies = self::indexDefinitions(array_map(
            static fn (array $item): CompositionDesignVocabularyDeclaration
                => CompositionDesignVocabularyDeclaration::fromArray($item),
            self::objects(
                $composition['design_vocabularies'] ?? [],
                'contributions.composition.design_vocabularies',
            ),
        ), 'composition design vocabulary');
        $compositionMigrations = self::indexDefinitions(array_map(
            static fn (array $item): CompositionMigrationDeclaration
                => CompositionMigrationDeclaration::fromArray($item),
            self::objects($composition['migrations'] ?? [], 'contributions.composition.migrations'),
        ), 'composition migration');
        $compositionCounts = [
            'composition.blocks' => count($compositionBlocks),
            'composition.patterns' => count($compositionPatterns),
            'composition.field_controls' => count($compositionFieldControls),
            'composition.inspectors' => count($compositionInspectors),
            'composition.design_vocabularies' => count($compositionDesignVocabularies),
            'composition.migrations' => count($compositionMigrations),
        ];

        $documents = [];
        $hostBindings = [];
        if ($manifestSchema >= 6) {
            $documents = self::indexDefinitions(array_map(static function (array $item): CanonicalCompositionDocument {
                self::knownKeys($item, ['kind', 'canonical'], 'canonical composition document');
                $kind = CanonicalCompositionKind::tryFrom(self::string($item, 'kind'))
                    ?? throw new InvalidArgumentException('A canonical composition document names an unknown kind.');
                $canonical = $item['canonical'] ?? null;
                if (!is_string($canonical)) {
                    throw new InvalidArgumentException(
                        'A canonical composition document must carry its canonical JSON string.',
                    );
                }
                return new CanonicalCompositionDocument($kind, $canonical);
            }, self::objects(
                $composition['documents'] ?? [],
                'contributions.composition.documents',
            )), 'canonical composition document');
            foreach ($documents as $document) {
                $owner->assertOwns($document->identity(), 'canonical composition document');
            }
            $hostBindings = self::indexDefinitions(array_map(static function (array $item): CompositionHostBinding {
                self::knownKeys($item, ['kind', 'id', 'renderer', 'capability'], 'composition host binding');
                $kind = CanonicalCompositionKind::tryFrom(self::string($item, 'kind'))
                    ?? throw new InvalidArgumentException('A composition host binding names an unknown kind.');

                return new CompositionHostBinding(
                    $kind,
                    self::string($item, 'id'),
                    ($item['renderer'] ?? null) !== null ? self::string($item, 'renderer') : null,
                    ($item['capability'] ?? null) !== null ? self::string($item, 'capability') : null,
                );
            }, self::objects(
                $composition['host_bindings'] ?? [],
                'contributions.composition.host_bindings',
            )), 'composition host binding');
            foreach ($hostBindings as $binding) {
                if (!isset($documents[$binding->identifier()])) {
                    throw new InvalidArgumentException(
                        'A composition host binding references an undeclared canonical document.',
                    );
                }
                if ($binding->renderer !== null) {
                    $owner->assertOwns($binding->renderer, 'studio preview renderer');
                }
                if ($binding->capability !== null && !isset($capabilities[$binding->capability])) {
                    throw new InvalidArgumentException('A composition host binding capability is undeclared.');
                }
            }
            foreach ($documents as $document) {
                if ($document->kind !== CanonicalCompositionKind::BlockDefinition) {
                    continue;
                }
                $binding = $hostBindings[$document->identifier()] ?? null;
                if (!$binding instanceof CompositionHostBinding || $binding->renderer === null) {
                    throw new InvalidArgumentException(
                        'A canonical block definition requires one non-empty renderer binding.',
                    );
                }
            }
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

        return new self(
            ownerValue: $owner,
            spiVersion: $expectedSpi,
            capabilities: $capabilities,
            workspaces: $workspaces,
            navigation: $navigation,
            routes: $routes,
            views: $views,
            portalWorkspaces: $portalWorkspaces,
            portalNavigation: $portalNavigation,
            portalRoutes: $portalRoutes,
            portalTemplates: $portalTemplates,
            fieldPresentations: $fieldPresentations,
            domainListeners: $domainListeners,
            eventConsumers: $eventConsumers,
            jobs: $jobs,
            projections: $projections,
            webhooks: $webhooks,
            compositionBlocks: $compositionBlocks,
            compositionPatterns: $compositionPatterns,
            compositionFieldControls: $compositionFieldControls,
            compositionInspectors: $compositionInspectors,
            compositionDesignVocabularies: $compositionDesignVocabularies,
            compositionMigrations: $compositionMigrations,
            canonicalCompositionDocuments: $documents,
            compositionHostBindings: $hostBindings,
            counts: $counts,
            declarations: $data,
        );
    }

    /**
     * Build the inert declaration set for the frozen schema-one grammar.
     *
     * @param   ExtensionIdentifier  $extension  Package the empty set is attributed to.
     *
     * @return  self  A set owned by that package and declaring nothing.
     *
     * @since   0.1.0
     */
    public static function fromSchemaOne(ExtensionIdentifier $extension): self
    {
        return new self(
            ContributionOwner::extension($extension->value()),
            self::SPI_VERSION,
            declarations: ['version' => self::SPI_VERSION],
        );
    }

    /**
     * Report which contribution SPI generation the declaring manifest bound itself to.
     *
     * @return  int  SPI version derived from the manifest schema; 1 for schema one.
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
     * Strict manifests reconcile this exact set against their `permissions` list.
     *
     * @return  list<string>  Sorted capability identifiers.
     *
     * @since   0.1.0
     */
    public function capabilityIdentifiers(): array
    {
        return array_keys($this->capabilities);
    }

    /** @return list<AdministratorWorkspaceDefinition> Canonical administrator workspaces. @since 0.2.0 */
    public function administratorWorkspaces(): array
    {
        return array_values($this->workspaces);
    }

    /** @param string $identifier Signed workspace identifier. @since 0.2.0 */
    public function administratorWorkspace(string $identifier): ?AdministratorWorkspaceDefinition
    {
        return $this->workspaces[$identifier] ?? null;
    }

    /** @return list<AdministratorNavigationDefinition> Canonical administrator navigation. @since 0.2.0 */
    public function administratorNavigation(): array
    {
        return array_values($this->navigation);
    }

    /** @param string $identifier Signed navigation identifier. @since 0.2.0 */
    public function administratorNavigationItem(string $identifier): ?AdministratorNavigationDefinition
    {
        return $this->navigation[$identifier] ?? null;
    }

    /** @return list<AdministratorRouteDefinition> Canonical administrator routes. @since 0.2.0 */
    public function administratorRoutes(): array
    {
        return array_values($this->routes);
    }

    /** @param string $identifier Signed route identifier. @since 0.2.0 */
    public function administratorRoute(string $identifier): ?AdministratorRouteDefinition
    {
        return $this->routes[$identifier] ?? null;
    }

    /** @return list<AdministratorViewDefinition> Canonical administrator views. @since 0.2.0 */
    public function administratorViews(): array
    {
        return array_values($this->views);
    }

    /** @param string $identifier Signed view identifier. @since 0.2.0 */
    public function administratorView(string $identifier): ?AdministratorViewDefinition
    {
        return $this->views[$identifier] ?? null;
    }

    /** @return list<PortalWorkspaceDefinition> Canonical portal workspaces. @since 0.2.0 */
    public function portalWorkspaces(): array
    {
        return array_values($this->portalWorkspaces);
    }

    /** @param string $identifier Signed portal workspace identifier. @since 0.2.0 */
    public function portalWorkspace(string $identifier): ?PortalWorkspaceDefinition
    {
        return $this->portalWorkspaces[$identifier] ?? null;
    }

    /** @return list<PortalNavigationDefinition> Canonical portal navigation. @since 0.2.0 */
    public function portalNavigation(): array
    {
        return array_values($this->portalNavigation);
    }

    /** @param string $identifier Signed portal navigation identifier. @since 0.2.0 */
    public function portalNavigationItem(string $identifier): ?PortalNavigationDefinition
    {
        return $this->portalNavigation[$identifier] ?? null;
    }

    /** @return list<PortalRouteDefinition> Canonical portal routes. @since 0.2.0 */
    public function portalRoutes(): array
    {
        return array_values($this->portalRoutes);
    }

    /** @param string $identifier Signed portal route identifier. @since 0.2.0 */
    public function portalRoute(string $identifier): ?PortalRouteDefinition
    {
        return $this->portalRoutes[$identifier] ?? null;
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

    /** @param string $identifier Signed portal template identifier. @since 0.2.0 */
    public function portalTemplate(string $identifier): ?PortalTemplateDefinition
    {
        return $this->portalTemplates[$identifier] ?? null;
    }

    /** @return list<FieldPresentationContribution> Canonical field-presenter declarations. @since 0.2.0 */
    public function fieldPresentations(): array
    {
        return array_values($this->fieldPresentations);
    }

    /** @param string $fieldType Signed field-type identifier. @since 0.2.0 */
    public function fieldPresentation(string $fieldType): ?FieldPresentationContribution
    {
        return $this->fieldPresentations[$fieldType] ?? null;
    }

    /** @return list<DomainListenerDefinition> Canonical listener definitions. @since 0.2.0 */
    public function domainListeners(): array
    {
        return array_values($this->domainListeners);
    }

    /**
     * @param   string  $identifier  Signed listener binding identifier.
     *
     * @return  ?DomainListenerDefinition  Matching definition, or null when undeclared.
     *
     * @since   0.2.0
     */
    public function domainListener(string $identifier): ?DomainListenerDefinition
    {
        return $this->domainListeners[$identifier] ?? null;
    }

    /** @return list<EventConsumerDefinition> Canonical durable-consumer definitions. @since 0.2.0 */
    public function eventConsumers(): array
    {
        return array_values($this->eventConsumers);
    }

    /**
     * @param   string  $identifier  Signed consumer binding identifier.
     *
     * @return  ?EventConsumerDefinition  Matching definition, or null when undeclared.
     *
     * @since   0.2.0
     */
    public function eventConsumer(string $identifier): ?EventConsumerDefinition
    {
        return $this->eventConsumers[$identifier] ?? null;
    }

    /** @return list<JobContributionDefinition> Canonical job definitions. @since 0.2.0 */
    public function jobs(): array
    {
        return array_values($this->jobs);
    }

    /**
     * @param   string  $identifier  Signed job binding identifier.
     *
     * @return  ?JobContributionDefinition  Matching definition, or null when undeclared.
     *
     * @since   0.2.0
     */
    public function job(string $identifier): ?JobContributionDefinition
    {
        return $this->jobs[$identifier] ?? null;
    }

    /** @return list<ProjectionDefinition> Canonical projection definitions. @since 0.2.0 */
    public function projections(): array
    {
        return array_values($this->projections);
    }

    /**
     * @param   string  $identifier  Signed projection binding identifier.
     *
     * @return  ?ProjectionDefinition  Matching definition, or null when undeclared.
     *
     * @since   0.2.0
     */
    public function projection(string $identifier): ?ProjectionDefinition
    {
        return $this->projections[$identifier] ?? null;
    }

    /** @return list<WebhookContributionDefinition> Canonical webhook definitions. @since 0.2.0 */
    public function webhooks(): array
    {
        return array_values($this->webhooks);
    }

    /**
     * @param   string  $identifier  Signed webhook binding identifier.
     *
     * @return  ?WebhookContributionDefinition  Matching definition, or null when undeclared.
     *
     * @since   0.2.0
     */
    public function webhook(string $identifier): ?WebhookContributionDefinition
    {
        return $this->webhooks[$identifier] ?? null;
    }

    /** @return list<CompositionBlockDeclaration> Canonical schema-five block declarations. @since 0.2.0 */
    public function compositionBlocks(): array
    {
        return array_values($this->compositionBlocks);
    }

    /** @param string $identifier Signed block identifier. @since 0.2.0 */
    public function compositionBlock(string $identifier): ?CompositionBlockDeclaration
    {
        return $this->compositionBlocks[$identifier] ?? null;
    }

    /** @return list<CompositionPatternDeclaration> Canonical schema-five pattern declarations. @since 0.2.0 */
    public function compositionPatterns(): array
    {
        return array_values($this->compositionPatterns);
    }

    /** @param string $identifier Signed pattern identifier. @since 0.2.0 */
    public function compositionPattern(string $identifier): ?CompositionPatternDeclaration
    {
        return $this->compositionPatterns[$identifier] ?? null;
    }

    /** @return list<CompositionFieldControlDeclaration> Canonical field-control declarations. @since 0.2.0 */
    public function compositionFieldControls(): array
    {
        return array_values($this->compositionFieldControls);
    }

    /** @param string $identifier Signed field-control identifier. @since 0.2.0 */
    public function compositionFieldControl(string $identifier): ?CompositionFieldControlDeclaration
    {
        return $this->compositionFieldControls[$identifier] ?? null;
    }

    /** @return list<CompositionInspectorDeclaration> Canonical inspector declarations. @since 0.2.0 */
    public function compositionInspectors(): array
    {
        return array_values($this->compositionInspectors);
    }

    /** @param string $identifier Signed inspector identifier. @since 0.2.0 */
    public function compositionInspector(string $identifier): ?CompositionInspectorDeclaration
    {
        return $this->compositionInspectors[$identifier] ?? null;
    }

    /** @return list<CompositionDesignVocabularyDeclaration> Canonical design vocabularies. @since 0.2.0 */
    public function compositionDesignVocabularies(): array
    {
        return array_values($this->compositionDesignVocabularies);
    }

    /** @param string $identifier Signed vocabulary identifier. @since 0.2.0 */
    public function compositionDesignVocabulary(string $identifier): ?CompositionDesignVocabularyDeclaration
    {
        return $this->compositionDesignVocabularies[$identifier] ?? null;
    }

    /** @return list<CompositionMigrationDeclaration> Canonical composition migrations. @since 0.2.0 */
    public function compositionMigrations(): array
    {
        return array_values($this->compositionMigrations);
    }

    /** @param string $identifier Signed migration identifier. @since 0.2.0 */
    public function compositionMigration(string $identifier): ?CompositionMigrationDeclaration
    {
        return $this->compositionMigrations[$identifier] ?? null;
    }

    /** @return list<CanonicalCompositionDocument> Canonical schema-six Studio documents. @since 0.2.0 */
    public function canonicalCompositionDocuments(): array
    {
        return array_values($this->canonicalCompositionDocuments);
    }

    /**
     * @param string $identifier Kind-scoped canonical document identity.
     *
     * @since 0.2.0
     */
    public function canonicalCompositionDocument(string $identifier): ?CanonicalCompositionDocument
    {
        return $this->canonicalCompositionDocuments[$identifier] ?? null;
    }

    /** @return list<CompositionHostBinding> Canonical host bindings. @since 0.2.0 */
    public function compositionHostBindings(): array
    {
        return array_values($this->compositionHostBindings);
    }

    /** @param string $identifier Kind and document identity. @since 0.2.0 */
    public function compositionHostBinding(string $identifier): ?CompositionHostBinding
    {
        return $this->compositionHostBindings[$identifier] ?? null;
    }

    /**
     * Derive the exact executable inventory a binding provider must satisfy.
     *
     * @return  ExecutableBindingRequirements  Canonical signed binding requirements.
     *
     * @since   0.2.0
     */
    public function executableBindingRequirements(): ExecutableBindingRequirements
    {
        return ExecutableBindingRequirements::fromManifestContributions($this);
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
     * Return the complete contribution object after the SDK grammar accepted it.
     *
     * A host consumes this value to apply its own admission rules without decoding or parsing
     * `kumwe.json` a second time. Object keys are sorted recursively and every member has crossed the
     * canonical graph validator, so binding lookup and host activation observe the same exact bytes.
     *
     * @return  array<string, mixed>  Complete structurally validated contribution object.
     *
     * @since   0.2.0
     */
    public function declarations(): array
    {
        return $this->declarations;
    }

    /**
     * Export the declaration set for reports and diagnostics.
     *
     * This compact diagnostic view complements `declarations()`, which exposes the complete canonical graph.
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
            $key = $item[$identifier] ?? null;
            if (!is_string($key) || $key === '') {
                throw new InvalidArgumentException(sprintf(
                    'Contribution %s has no canonical %s identifier.',
                    $kind,
                    $identifier,
                ));
            }
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
     * @template T of ContributionDefinition
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
     * Sort every decoded object while preserving list order and scalar values.
     *
     * @param   array<mixed, mixed>  $graph  Fully validated contribution graph.
     *
     * @return  array<string, mixed>  Canonical graph whose object key order is deterministic.
     *
     * @since   0.2.0
     */
    private static function canonicalGraph(array $graph): array
    {
        $canonical = [];
        foreach ($graph as $key => $value) {
            if (!is_string($key)) {
                throw new InvalidArgumentException('A canonical manifest object requires string keys.');
            }
            $canonical[$key] = self::canonicalValue($value);
        }
        ksort($canonical, SORT_STRING);

        return $canonical;
    }

    /**
     * Canonicalize nested objects inside one list without changing list order.
     *
     * @param   list<mixed>  $values  Fully validated JSON list.
     *
     * @return  list<mixed>  List with canonicalized object members.
     *
     * @since   0.2.0
     */
    private static function canonicalList(array $values): array
    {
        if (!array_is_list($values)) {
            throw new InvalidArgumentException('A canonical manifest list must use sequential indexes.');
        }
        $canonical = [];
        foreach ($values as $value) {
            $canonical[] = self::canonicalValue($value);
        }

        return $canonical;
    }

    /**
     * Canonicalize one nested JSON value without changing its scalar type.
     *
     * @param mixed $value Validated manifest value.
     *
     * @return mixed Canonical scalar, list, or object value.
     *
     * @since 0.2.0
     */
    private static function canonicalValue(mixed $value): mixed
    {
        if (!is_array($value) || $value === []) {
            return $value;
        }

        return array_is_list($value)
            ? self::canonicalList($value)
            : self::canonicalGraph($value);
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
     * @return  string  The exact canonical value.
     *
     * @throws  InvalidArgumentException  When the key is absent, not a string, or blank once trimmed.
     *
     * @since   0.1.0
     */
    private static function string(array $values, string $field): string
    {
        $value = $values[$field] ?? null;
        if (!is_string($value) || $value === '' || $value !== trim($value)) {
            throw new InvalidArgumentException(sprintf('Contribution field %s must be a non-empty string.', $field));
        }

        return $value;
    }

    /**
     * Read an optional string field, treating absence and emptiness alike.
     *
     * @param   array<string, mixed>  $values  Decoded manifest object that may hold the field.
     * @param   string                $field   Key to read, also named in the failure message.
     *
     * @return  string  The exact value, or an empty string when the key was not present.
     *
     * @throws  InvalidArgumentException  When the key is present but not a string.
     *
     * @since   0.1.0
     */
    private static function optionalString(array $values, string $field): string
    {
        $value = $values[$field] ?? '';
        if (!is_string($value) || $value !== trim($value)) {
            throw new InvalidArgumentException(sprintf('Contribution field %s must be a string.', $field));
        }

        return $value;
    }

    /**
     * Read an optional bounded list of non-empty strings from a decoded manifest object.
     *
     * @param   array<string, mixed>  $values   Object that may hold the list.
     * @param   string                $field    Key to read and name in a failure.
     * @param   list<string>          $default  Value returned when the key is absent.
     *
     * @return  list<string>  Exact unique strings in declaration order.
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
        $seen = [];
        foreach ($value as $item) {
            if (!is_string($item) || $item === '' || $item !== trim($item) || isset($seen[$item])) {
                throw new InvalidArgumentException(sprintf(
                    'Every contribution field %s entry must be a unique canonical string.',
                    $field,
                ));
            }
            $seen[$item] = true;
            $result[] = $item;
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
