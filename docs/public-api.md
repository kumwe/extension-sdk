# SDK public API

Every public SDK type and declared member is generated from the canonical classification, source PHPDoc and reflection. The parent and interface names in the machine manifest identify inherited contracts; the SDK does not claim ownership of their declarations. Internal Support helpers are excluded.

See [architecture](architecture.md) and [host integration](host-integration.md) for authority boundaries, construction, I/O and collaborator lifetimes. Every example and signature below is a contract reference; publishing a package never grants host admission. The two PHPUnit bridge types require the optional PHPUnit development dependency and are absent from the production qualification count.

## Kumwe\Extension\Contract\NameBasedUuid

/**
 * Derives RFC 4122 version-5 (SHA-1, name-based) UUIDs deterministically.
 *
 * Everything in this SDK that needs a stable identifier derives it from its inputs — the charter
 * forbids clocks and randomness in the library, so this one type is the SDK's whole UUID surface.
 * The derivation is the RFC's: the namespace UUID's
 * sixteen raw bytes are concatenated with the name, hashed with SHA-1, truncated to sixteen bytes, and
 * stamped with version 5 and the RFC 4122 variant. The output is byte-identical to what `ramsey/uuid`
 * produces for the same inputs, proven against the RFC reference vectors.
 *
 * @since  0.1.0
 */

### v5

/**
     * Derive the version-5 UUID of one name inside one namespace.
     *
     * @param   string  $namespace  Namespace UUID in canonical or brace-free hexadecimal form.
     * @param   string  $name       Name bytes to derive from, hashed exactly as given.
     *
     * @return  string  Canonical lowercase `xxxxxxxx-xxxx-5xxx-yxxx-xxxxxxxxxxxx` rendering.
     *
     * @throws  InvalidArgumentException  When the namespace is not a well-formed UUID.
     *
     * @since   0.1.0
     */

```php
public static function v5(string $namespace, string $name): string;
```

### Public constants

/**
     * The RFC 4122 URL namespace, under which the scaffolder derives entity identifiers.
     *
     * @var    string
     * @since  0.1.0
     */

- `NAMESPACE_URL = '6ba7b811-9dad-11d1-80b4-00c04fd430c8'`

## Kumwe\Extension\Manifest\ExtensionDependency

/**
 * One other package an extension requires, and the versions of it that will do.
 *
 * `ExtensionManifest` keeps these in declaration order after rejecting self-references and repeats, so
 * a dependency here names a package other than the one declaring it, at most once. Installation reads
 * the list twice: once to write the release's `extension_dependencies` rows, and once to refuse a
 * package whose requirements are absent or out of range. `optional` only excuses absence — a package
 * that is installed still has to satisfy the constraint.
 *
 * @since  0.1.0
 */

### __construct

/**
     * Capture one requirement exactly as the manifest declared it.
     *
     * @param  ExtensionIdentifier  $extension   Package that must be installed for the declaring one to run.
     * @param  VersionConstraint    $constraint  Range of that package's versions this declaration accepts.
     * @param  bool                 $optional    True when the requirement may be skipped if nothing provides it.
     *
     * @since  0.1.0
     */

```php
public function __construct(Kumwe\Extension\Manifest\ExtensionIdentifier $extension, Kumwe\Extension\Manifest\VersionConstraint $constraint, bool $optional = false);
```

### extension

/**
     * The package this requirement points at.
     *
     * @return  ExtensionIdentifier  Identifier of the required package, never the declaring package itself.
     *
     * @since   0.1.0
     */

```php
public function extension(): Kumwe\Extension\Manifest\ExtensionIdentifier;
```

### constraint

/**
     * The version range the required package has to fall in.
     *
     * @return  VersionConstraint  Constraint as declared; `*` when the manifest named no range.
     *
     * @since   0.1.0
     */

```php
public function constraint(): Kumwe\Extension\Manifest\VersionConstraint;
```

### isOptional

/**
     * Whether an installation with nothing providing this package is still allowed to proceed.
     *
     * @return  bool  True when the requirement is skipped while absent, false when absence blocks install.
     *
     * @since   0.1.0
     */

```php
public function isOptional(): bool;
```

### isSatisfiedBy

/**
     * Decide whether an installed version of the required package meets this requirement.
     *
     * Only the version is judged. Whether the package is present at all, and whether `optional` excuses
     * its absence, is the caller's question — this method is never reached for a missing dependency.
     *
     * @param   SemanticVersion  $version  Version of the required package that is actually installed.
     *
     * @return  bool  True when that version falls inside the declared constraint.
     *
     * @since   0.1.0
     */

```php
public function isSatisfiedBy(Kumwe\Extension\Manifest\SemanticVersion $version): bool;
```

## Kumwe\Extension\Manifest\ExtensionIdentifier

/**
 * Canonical `vendor/name` identity every part of the extension subsystem keys an extension by.
 *
 * Parsing through `fromString` is the only way to obtain one, so an instance is proof that the value
 * has been trimmed, lowercased, and matched against the two-segment grammar. Collaborators lean on
 * that: `ExtensionTableNames` folds the value straight into a physical table prefix without
 * re-checking it, and code holding only a raw string routes it through here before using it as a
 * registry key or a contribution owner, so one grammar governs every spelling of an extension.
 *
 * @since  0.1.0
 */

### fromString

/**
     * Parse and normalise an identifier written as `vendor/name`.
     *
     * Surrounding whitespace and uppercase input are normalised away, so those are not errors.
     * Everything else is refused rather than repaired: a missing or extra slash, an empty segment, a
     * segment that does not open with a letter or digit, a character outside `[a-z0-9._-]`, or a
     * segment longer than 63 characters.
     *
     * @param   string  $value  Raw identifier from a manifest, a request path, or configuration.
     *
     * @return  self  The normalised identifier.
     *
     * @throws  InvalidArgumentException  When the value does not match the `vendor/name` grammar.
     *
     * @since   0.1.0
     */

```php
public static function fromString(string $value): Kumwe\Extension\Manifest\ExtensionIdentifier;
```

### value

/**
     * Expose the normalised value for storage, keying, and identifier composition.
     *
     * @return  string  Lowercase `vendor/name`, unchanged by upgrades of the extension it names.
     *
     * @since   0.1.0
     */

```php
public function value(): string;
```

### equals

/**
     * Compare two identifiers by their normalised value.
     *
     * @param   self  $other  Identifier to compare this one against.
     *
     * @return  bool  True when both name the same extension.
     *
     * @since   0.1.0
     */

```php
public function equals(Kumwe\Extension\Manifest\ExtensionIdentifier $other): bool;
```

### __toString

/**
     * Render the identifier where it is interpolated into a message, path, or query parameter.
     *
     * @return  string  The same value `value()` returns.
     *
     * @since   0.1.0
     */

```php
public function __toString(): string;
```

## Kumwe\Extension\Manifest\ExtensionManifest

/**
 * Validated in-memory form of an extension's `kumwe.json`, and the only shape the installer trusts.
 *
 * Construction is the validation boundary for everything a package declares about itself. Every
 * collection is checked for shape and bounded in size, and every autoload prefix, migration class,
 * capability identifier, and asset path is matched against a grammar before it is stored, so the
 * installer, the runtime loader, and the contribution registrar all read manifest data without
 * re-checking it. Five schema revisions are accepted: schema 1 predates typed shell contributions and
 * is given an empty contribution set, schema 2 adds closed typed shell/business declarations, and schema
 * 3 adds signed field-presentation and custom business view/action contracts while leaving schema 2's
 * accepted contribution grammar unchanged. Schema 4 adds contribution SPI 2 durable events, automation,
 * projections, reports, and outbound adapters; schema 5 adds the SPI 3 declarative composition
 * contributions. Strict manifests keep `permissions` identical to their contributed capabilities.
 *
 * @since  0.1.0
 */

### __construct

/**
     * Validate and store everything a package declares about itself.
     *
     * Callers that already hold parsed values use this directly; anything starting from a document
     * should go through `fromJson`, which performs the schema-level checks this constructor does not.
     *
     * @param   ExtensionIdentifier        $identifier             Identity the extension registers under.
     * @param   ExtensionType              $type                   Kind of extension the package installs as.
     * @param   SemanticVersion            $version                Version this manifest describes.
     * @param string $serviceProvider Fully qualified provider class the runtime instantiates.
     * @param   VersionConstraint          $kumweCompatibility     Kumwe versions the extension declares support for.
     * @param   VersionConstraint          $phpCompatibility       PHP versions the extension declares support for.
     * @param   array<mixed>               $dependencies           `ExtensionDependency` list, at most 256, no repeats.
     * @param   array<mixed>               $autoload               PSR-4 prefix to package-relative directory map.
     * @param   array<mixed>               $migrations             Migration class names to run, in order.
     * @param   array<mixed>               $configuration          Configuration object, stored as given.
     * @param   array<mixed>               $permissions            Capability identifiers the extension declares.
     * @param   array<mixed>               $routes                 Route declaration objects, at most 256.
     * @param   array<mixed>               $events                 Event declaration objects, at most 256.
     * @param   array<mixed>               $assets                 Package-relative asset paths, at most 512.
     * @param   ?ManifestContributions     $contributions          Strict contributions; null selects schema-one.
     * @param   int                        $schemaVersion          Manifest schema revision; 1 through 6 are supported.
     * @param   ?TemplateKisCompatibility  $templateCompatibility  Closed KIS compatibility contract for templates.
     *
     * @throws  InvalidArgumentException  When the schema is unsupported or any declared value fails its check.
     *
     * @since   0.1.0
     */

```php
public function __construct(Kumwe\Extension\Manifest\ExtensionIdentifier $identifier, Kumwe\Extension\Manifest\ExtensionType $type, Kumwe\Extension\Manifest\SemanticVersion $version, string $serviceProvider, Kumwe\Extension\Manifest\VersionConstraint $kumweCompatibility, Kumwe\Extension\Manifest\VersionConstraint $phpCompatibility, array $dependencies = array (
), array $autoload = array (
), array $migrations = array (
), array $configuration = array (
), array $permissions = array (
), array $routes = array (
), array $events = array (
), array $assets = array (
), ?Kumwe\Extension\Manifest\ManifestContributions $contributions = NULL, int $schemaVersion = 1, ?Kumwe\Extension\Manifest\TemplateKisCompatibility $templateCompatibility = NULL);
```

### fromJson

/**
     * Parse a `kumwe.json` document into a validated manifest.
     *
     * Decoding is bounded before anything else happens — one mebibyte of input, 32 levels of nesting
     * — so a hostile document cannot exhaust memory on its way to being rejected. A strict
     * document is additionally closed to unknown keys at every level whose key set is known, which
     * turns a misspelled field into an install failure instead of a silently ignored declaration.
     * Schemas 2 through 5 also reconcile `permissions` with contributed capabilities: an absent list is
     * filled in from them, and a present one must match them exactly, order included. Historical
     * schema-one templates without a declaration receive the exact KIS 1.0 compatibility point;
     * template manifests using a strict schema must declare their closed compatibility envelope.
     *
     * @param   string  $json  Raw manifest document read from the package root.
     *
     * @return  self  The validated manifest, with its contribution set resolved for the schema in use.
     *
     * @throws  InvalidArgumentException  When the document is oversized, malformed, or fails any check.
     *
     * @since   0.1.0
     * @param CanonicalEncoder $canonicalEncoder Canonical encoding port supplied by the composition root.
     */

```php
public static function fromJson(Kumwe\CanonicalJson\CanonicalEncoder $canonicalEncoder, string $json): Kumwe\Extension\Manifest\ExtensionManifest;
```

### schemaVersion

/**
     * Report which manifest revision the package was written against.
     *
     * @return  int  1 for the frozen first schema through 6 for canonical composition contributions.
     *
     * @since   0.1.0
     */

```php
public function schemaVersion(): int;
```

### identifier

/**
     * Name the extension this manifest describes.
     *
     * @return  ExtensionIdentifier  Identity the registry keys the extension by.
     *
     * @since   0.1.0
     */

```php
public function identifier(): Kumwe\Extension\Manifest\ExtensionIdentifier;
```

### type

/**
     * Report the kind of extension the package installs as.
     *
     * @return  ExtensionType  Kind fixed at first install, which a later upgrade may not change.
     *
     * @since   0.1.0
     */

```php
public function type(): Kumwe\Extension\Manifest\ExtensionType;
```

### version

/**
     * Report the version the package declares for itself.
     *
     * @return  SemanticVersion  Version an upgrade is compared against.
     *
     * @since   0.1.0
     */

```php
public function version(): Kumwe\Extension\Manifest\SemanticVersion;
```

### serviceProvider

/**
     * Name the class the runtime instantiates to let the extension register its services.
     *
     * @return  string  Fully qualified class name; validated as a shape, never checked for existence.
     *
     * @since   0.1.0
     */

```php
public function serviceProvider(): string;
```

### supports

/**
     * Decide whether this extension declares support for a given Kumwe and PHP pair.
     *
     * @param   SemanticVersion  $kumweVersion  Kumwe version the extension would run on.
     * @param   SemanticVersion  $phpVersion    PHP version of that runtime.
     *
     * @return  bool  True only when both declared constraints accept their version.
     *
     * @since   0.1.0
     */

```php
public function supports(Kumwe\Extension\Manifest\SemanticVersion $kumweVersion, Kumwe\Extension\Manifest\SemanticVersion $phpVersion): bool;
```

### dependencies

/**
     * List the extensions that must be present before this one can be enabled.
     *
     * @return  list<ExtensionDependency>  Dependencies in manifest order; empty when the package stands alone.
     *
     * @since   0.1.0
     */

```php
public function dependencies(): array;
```

### autoload

/**
     * Report the PSR-4 mapping the runtime registers so the extension's classes can be found.
     *
     * @return  array<string, string>  Namespace prefix to package-relative directory, sorted by prefix.
     *
     * @since   0.1.0
     */

```php
public function autoload(): array;
```

### migrations

/**
     * List the migration classes the installer runs for this extension.
     *
     * @return  list<class-string>  Migrations in declaration order, which is the order they must run in.
     *
     * @since   0.1.0
     */

```php
public function migrations(): array;
```

### configuration

/**
     * Report the configuration block the package ships.
     *
     * @return  array<string, mixed>  The object exactly as declared; empty when the package declares none.
     *
     * @since   0.1.0
     */

```php
public function configuration(): array;
```

### permissions

/**
     * List the capability identifiers the extension declares.
     *
     * @return  list<string>  De-duplicated identifiers; for strict schemas these mirror contributed capabilities.
     *
     * @since   0.1.0
     */

```php
public function permissions(): array;
```

### schemaOneRoutes

/**
     * List inert schema-one route metadata retained only for frozen-document inspection.
     *
     * @return  list<array<string, mixed>>  Objects as declared; this type checks their shape, not their content.
     *
     * @since   0.1.0
     */

```php
public function schemaOneRoutes(): array;
```

### schemaOneEvents

/**
     * List inert schema-one event metadata retained only for frozen-document inspection.
     *
     * @return  list<array<string, mixed>>  Objects as declared; this type checks their shape, not their content.
     *
     * @since   0.1.0
     */

```php
public function schemaOneEvents(): array;
```

### assets

/**
     * List the package-relative asset paths the extension publishes.
     *
     * @return  list<string>  De-duplicated relative paths, each already proven free of traversal.
     *
     * @since   0.1.0
     */

```php
public function assets(): array;
```

### contributions

/**
     * Report the typed contributions the extension makes to the application shell.
     *
     * @return  ManifestContributions  Parsed strict contributions; an empty owned set for schema 1.
     *
     * @since   0.1.0
     */

```php
public function contributions(): Kumwe\Extension\Manifest\ManifestContributions;
```

### templateCompatibility

/**
     * Report the KIS component and token contract required by a template package.
     *
     * @return  ?TemplateKisCompatibility  Compatibility declaration for templates, null for other types.
     *
     * @since   0.1.0
     */

```php
public function templateCompatibility(): ?Kumwe\Extension\Manifest\TemplateKisCompatibility;
```

## Kumwe\Extension\Manifest\ExtensionType

/**
 * Kind of extension a package declares, taken from the `type` field of its manifest.
 *
 * The value is fixed when the package is first installed and an upgrade that disagrees with it is
 * refused, so this classifies what an installed package *is* rather than what it currently does.
 * Most of the lifecycle treats every kind alike; `Template` is the exception, because activating one
 * binds it to a site or administrator theme surface and an upgrade clears those bindings again.
 *
 * @since  0.1.0
 */

### cases

Generated enum/runtime member.

```php
public static function cases(): array;
```

### from

Generated enum/runtime member.

```php
public static function from(string|int $value): static;
```

### tryFrom

Generated enum/runtime member.

```php
public static function tryFrom(string|int $value): ?static;
```

### Public properties



- `readonly string $name`


- `readonly string $value`

### Public constants

/**
     * Extension that reacts to events raised elsewhere instead of owning a delivery surface.
     *
     * @since  0.1.0
     */

- `Plugin = \Kumwe\Extension\Manifest\ExtensionType::Plugin`
/**
     * Extension that renders a self-contained fragment a template places within a page.
     *
     * @since  0.1.0
     */

- `Module = \Kumwe\Extension\Manifest\ExtensionType::Module`
/**
     * Extension that supplies a theme; the only kind whose activation names a site or administrator surface.
     *
     * @since  0.1.0
     */

- `Template = \Kumwe\Extension\Manifest\ExtensionType::Template`
/**
     * Extension that owns a feature area end to end, including its routes, views, and workspace.
     *
     * @since  0.1.0
     */

- `Component = \Kumwe\Extension\Manifest\ExtensionType::Component`
/**
     * Extension that exists to deliver a related set of other extensions as one installable unit.
     *
     * @since  0.1.0
     */

- `Package = \Kumwe\Extension\Manifest\ExtensionType::Package`
/**
     * Extension that ships translations for surfaces other extensions provide.
     *
     * @since  0.1.0
     */

- `Language = \Kumwe\Extension\Manifest\ExtensionType::Language`

## Kumwe\Extension\Manifest\ManifestContributions

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

### fromManifest

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
     * @param CanonicalEncoder $canonicalEncoder Canonical encoding port supplied by the composition root.
     */

```php
public static function fromManifest(Kumwe\CanonicalJson\CanonicalEncoder $canonicalEncoder, Kumwe\Extension\Manifest\ExtensionIdentifier $extension, array $data, int $manifestSchema = 3): Kumwe\Extension\Manifest\ManifestContributions;
```

### fromSchemaOne

/**
     * Build the inert declaration set for the frozen schema-one grammar.
     *
     * @param   ExtensionIdentifier  $extension  Package the empty set is attributed to.
     *
     * @return  self  A set owned by that package and declaring nothing.
     *
     * @since   0.1.0
     */

```php
public static function fromSchemaOne(Kumwe\Extension\Manifest\ExtensionIdentifier $extension): Kumwe\Extension\Manifest\ManifestContributions;
```

### spiVersion

/**
     * Report which contribution SPI generation the declaring manifest bound itself to.
     *
     * @return  int  SPI version derived from the manifest schema; 1 for schema one.
     *
     * @since   0.1.0
     */

```php
public function spiVersion(): int;
```

### capabilityIdentifiers

/**
     * List the capability identifiers this package declares, sorted by identifier.
     *
     * Strict manifests reconcile this exact set against their `permissions` list.
     *
     * @return  list<string>  Sorted capability identifiers.
     *
     * @since   0.1.0
     */

```php
public function capabilityIdentifiers(): array;
```

### administratorWorkspaces

/** @return list<AdministratorWorkspaceDefinition> Canonical administrator workspaces. @since 0.2.0 */

```php
public function administratorWorkspaces(): array;
```

### administratorWorkspace

/** @param string $identifier Signed workspace identifier. @since 0.2.0 */

```php
public function administratorWorkspace(string $identifier): ?Kumwe\Administrator\Contract\AdministratorWorkspaceDefinition;
```

### administratorNavigation

/** @return list<AdministratorNavigationDefinition> Canonical administrator navigation. @since 0.2.0 */

```php
public function administratorNavigation(): array;
```

### administratorNavigationItem

/** @param string $identifier Signed navigation identifier. @since 0.2.0 */

```php
public function administratorNavigationItem(string $identifier): ?Kumwe\Administrator\Contract\AdministratorNavigationDefinition;
```

### administratorRoutes

/** @return list<AdministratorRouteDefinition> Canonical administrator routes. @since 0.2.0 */

```php
public function administratorRoutes(): array;
```

### administratorRoute

/** @param string $identifier Signed route identifier. @since 0.2.0 */

```php
public function administratorRoute(string $identifier): ?Kumwe\Administrator\Contract\AdministratorRouteDefinition;
```

### administratorViews

/** @return list<AdministratorViewDefinition> Canonical administrator views. @since 0.2.0 */

```php
public function administratorViews(): array;
```

### administratorView

/** @param string $identifier Signed view identifier. @since 0.2.0 */

```php
public function administratorView(string $identifier): ?Kumwe\Administrator\Contract\AdministratorViewDefinition;
```

### portalWorkspaces

/** @return list<PortalWorkspaceDefinition> Canonical portal workspaces. @since 0.2.0 */

```php
public function portalWorkspaces(): array;
```

### portalWorkspace

/** @param string $identifier Signed portal workspace identifier. @since 0.2.0 */

```php
public function portalWorkspace(string $identifier): ?Kumwe\Portal\Contract\PortalWorkspaceDefinition;
```

### portalNavigation

/** @return list<PortalNavigationDefinition> Canonical portal navigation. @since 0.2.0 */

```php
public function portalNavigation(): array;
```

### portalNavigationItem

/** @param string $identifier Signed portal navigation identifier. @since 0.2.0 */

```php
public function portalNavigationItem(string $identifier): ?Kumwe\Portal\Contract\PortalNavigationDefinition;
```

### portalRoutes

/** @return list<PortalRouteDefinition> Canonical portal routes. @since 0.2.0 */

```php
public function portalRoutes(): array;
```

### portalRoute

/** @param string $identifier Signed portal route identifier. @since 0.2.0 */

```php
public function portalRoute(string $identifier): ?Kumwe\Portal\Contract\PortalRouteDefinition;
```

### portalTemplates

/**
     * List the declared portal templates, sorted by template name.
     *
     * @return  list<PortalTemplateDefinition>  Every declared portal template.
     *
     * @since   0.1.0
     */

```php
public function portalTemplates(): array;
```

### portalTemplate

/** @param string $identifier Signed portal template identifier. @since 0.2.0 */

```php
public function portalTemplate(string $identifier): ?Kumwe\Portal\Contract\PortalTemplateDefinition;
```

### fieldPresentations

/** @return list<FieldPresentationContribution> Canonical field-presenter declarations. @since 0.2.0 */

```php
public function fieldPresentations(): array;
```

### fieldPresentation

/** @param string $fieldType Signed field-type identifier. @since 0.2.0 */

```php
public function fieldPresentation(string $fieldType): ?Kumwe\BusinessSurface\Contract\Presentation\Field\FieldPresentationContribution;
```

### domainListeners

/** @return list<DomainListenerDefinition> Canonical listener definitions. @since 0.2.0 */

```php
public function domainListeners(): array;
```

### domainListener

/**
     * @param   string  $identifier  Signed listener binding identifier.
     *
     * @return  ?DomainListenerDefinition  Matching definition, or null when undeclared.
     *
     * @since   0.2.0
     */

```php
public function domainListener(string $identifier): ?Kumwe\Integration\DomainListenerDefinition;
```

### eventConsumers

/** @return list<EventConsumerDefinition> Canonical durable-consumer definitions. @since 0.2.0 */

```php
public function eventConsumers(): array;
```

### eventConsumer

/**
     * @param   string  $identifier  Signed consumer binding identifier.
     *
     * @return  ?EventConsumerDefinition  Matching definition, or null when undeclared.
     *
     * @since   0.2.0
     */

```php
public function eventConsumer(string $identifier): ?Kumwe\Integration\EventConsumerDefinition;
```

### jobs

/** @return list<JobContributionDefinition> Canonical job definitions. @since 0.2.0 */

```php
public function jobs(): array;
```

### job

/**
     * @param   string  $identifier  Signed job binding identifier.
     *
     * @return  ?JobContributionDefinition  Matching definition, or null when undeclared.
     *
     * @since   0.2.0
     */

```php
public function job(string $identifier): ?Kumwe\Automation\JobContributionDefinition;
```

### projections

/** @return list<ProjectionDefinition> Canonical projection definitions. @since 0.2.0 */

```php
public function projections(): array;
```

### projection

/**
     * @param   string  $identifier  Signed projection binding identifier.
     *
     * @return  ?ProjectionDefinition  Matching definition, or null when undeclared.
     *
     * @since   0.2.0
     */

```php
public function projection(string $identifier): ?Kumwe\Reporting\Domain\ProjectionDefinition;
```

### webhooks

/** @return list<WebhookContributionDefinition> Canonical webhook definitions. @since 0.2.0 */

```php
public function webhooks(): array;
```

### webhook

/**
     * @param   string  $identifier  Signed webhook binding identifier.
     *
     * @return  ?WebhookContributionDefinition  Matching definition, or null when undeclared.
     *
     * @since   0.2.0
     */

```php
public function webhook(string $identifier): ?Kumwe\Integration\WebhookContributionDefinition;
```

### compositionBlocks

/** @return list<CompositionBlockDeclaration> Canonical schema-five block declarations. @since 0.2.0 */

```php
public function compositionBlocks(): array;
```

### compositionBlock

/** @param string $identifier Signed block identifier. @since 0.2.0 */

```php
public function compositionBlock(string $identifier): ?Kumwe\Extension\Spi\Contribution\CompositionBlockDeclaration;
```

### compositionPatterns

/** @return list<CompositionPatternDeclaration> Canonical schema-five pattern declarations. @since 0.2.0 */

```php
public function compositionPatterns(): array;
```

### compositionPattern

/** @param string $identifier Signed pattern identifier. @since 0.2.0 */

```php
public function compositionPattern(string $identifier): ?Kumwe\Extension\Spi\Contribution\CompositionPatternDeclaration;
```

### compositionFieldControls

/** @return list<CompositionFieldControlDeclaration> Canonical field-control declarations. @since 0.2.0 */

```php
public function compositionFieldControls(): array;
```

### compositionFieldControl

/** @param string $identifier Signed field-control identifier. @since 0.2.0 */

```php
public function compositionFieldControl(string $identifier): ?Kumwe\Extension\Spi\Contribution\CompositionFieldControlDeclaration;
```

### compositionInspectors

/** @return list<CompositionInspectorDeclaration> Canonical inspector declarations. @since 0.2.0 */

```php
public function compositionInspectors(): array;
```

### compositionInspector

/** @param string $identifier Signed inspector identifier. @since 0.2.0 */

```php
public function compositionInspector(string $identifier): ?Kumwe\Extension\Spi\Contribution\CompositionInspectorDeclaration;
```

### compositionDesignVocabularies

/** @return list<CompositionDesignVocabularyDeclaration> Canonical design vocabularies. @since 0.2.0 */

```php
public function compositionDesignVocabularies(): array;
```

### compositionDesignVocabulary

/** @param string $identifier Signed vocabulary identifier. @since 0.2.0 */

```php
public function compositionDesignVocabulary(string $identifier): ?Kumwe\Extension\Spi\Contribution\CompositionDesignVocabularyDeclaration;
```

### compositionMigrations

/** @return list<CompositionMigrationDeclaration> Canonical composition migrations. @since 0.2.0 */

```php
public function compositionMigrations(): array;
```

### compositionMigration

/** @param string $identifier Signed migration identifier. @since 0.2.0 */

```php
public function compositionMigration(string $identifier): ?Kumwe\Extension\Spi\Contribution\CompositionMigrationDeclaration;
```

### canonicalCompositionDocuments

/** @return list<CanonicalCompositionDocument> Canonical schema-six Studio documents. @since 0.2.0 */

```php
public function canonicalCompositionDocuments(): array;
```

### canonicalCompositionDocument

/**
     * @param string $identifier Kind-scoped canonical document identity.
     *
     * @since 0.2.0
     */

```php
public function canonicalCompositionDocument(string $identifier): ?Kumwe\Extension\Spi\Contribution\CanonicalCompositionDocument;
```

### compositionHostBindings

/** @return list<CompositionHostBinding> Canonical host bindings. @since 0.2.0 */

```php
public function compositionHostBindings(): array;
```

### compositionHostBinding

/** @param string $identifier Kind and document identity. @since 0.2.0 */

```php
public function compositionHostBinding(string $identifier): ?Kumwe\Extension\Spi\Contribution\CompositionHostBinding;
```

### executableBindingRequirements

/**
     * Derive the exact executable inventory a binding provider must satisfy.
     *
     * @return  ExecutableBindingRequirements  Canonical signed binding requirements.
     *
     * @since   0.2.0
     */

```php
public function executableBindingRequirements(): Kumwe\Extension\Spi\Binding\ExecutableBindingRequirements;
```

### surfaceCounts

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

```php
public function surfaceCounts(): array;
```

### declarations

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

```php
public function declarations(): array;
```

### toArray

/**
     * Export the declaration set for reports and diagnostics.
     *
     * This compact diagnostic view complements `declarations()`, which exposes the complete canonical graph.
     *
     * @return  array{version: int, declared: array<string, int>}  Canonical bounded export.
     *
     * @since   0.1.0
     */

```php
public function toArray(): array;
```

### Public properties

/**
     * Package that owns every identifier declared in this set.
     *
     * @var    ContributionOwner
     * @since  0.1.0
     */

- `readonly Kumwe\Contribution\ContributionOwner $owner`

### Public constants

/**
     * Version of the contribution service-provider interface read from schema 2 and 3 manifests.
     *
     * @var    int
     * @since  0.1.0
     */

- `SPI_VERSION = 1`
/**
     * Contribution SPI used by manifest schema 4 business-integration packages.
     *
     * @var    int
     * @since  0.1.0
     */

- `CURRENT_SPI_VERSION = 2`
/**
     * Contribution SPI used by manifest schema 5 packages, which opened the composition surfaces.
     *
     * @var    int
     * @since  0.1.0
     */

- `COMPOSITION_SPI_VERSION = 3`
/**
     * Contribution SPI used by manifest schema 6 packages, which carry canonical Studio documents.
     *
     * @var    int
     * @since  0.1.0
     */

- `CANONICAL_COMPOSITION_SPI_VERSION = 4`

## Kumwe\Extension\Manifest\ManifestIdentifierPolicies

/**
 * Manifest surface choices projected onto the canonical contribution policy contract.
 *
 * The SDK selects its manifest grammar; Contribution owns identifier validation and ownership.
 *
 * @since 0.3.0
 */

### forKind

/**
     * Select the documented grammar for one SDK manifest contribution kind.
     *
     * @param string $kind Manifest surface label.
     * @return SurfaceIdentifierPolicy Explicit canonical policy, without a registry or global default.
     * @since 0.3.0
     */

```php
public static function forKind(string $kind): Kumwe\Contribution\SurfaceIdentifierPolicy;
```

## Kumwe\Extension\Manifest\SemanticVersion

/**
 * Version of an extension release, parsed under Semantic Versioning 2.0.0 and orderable.
 *
 * The constructor is private, so every instance came through `fromString()` and is known to be well
 * formed: three non-negative components inside the platform integer range, plus the optional
 * pre-release identifiers and build metadata the grammar allows. That is what lets the rest of the
 * extension code compare versions without re-validating them — `ExtensionRecord` and
 * `DoctrineExtensionManager` use `compare()` to decide whether a packaged manifest is really an
 * upgrade, and `VersionConstraint` evaluates every dependency range through it.
 *
 * @since  0.1.0
 */

### fromString

/**
     * Parse a version string into a comparable value.
     *
     * Surrounding whitespace is trimmed, and what remains must match the Semantic Versioning grammar
     * in full: a two-part version such as `2.0` is rejected rather than padded, and a component with a
     * leading zero is rejected rather than normalised.
     *
     * @param   string  $value  Version as written in a manifest or a registry row, for example
     *          `2.1.0-beta.1+build.5`.
     *
     * @return  self  Parsed version, keeping both its pre-release identifiers and its build metadata.
     *
     * @throws  InvalidArgumentException  When the value exceeds 128 characters, does not match the
     *          Semantic Versioning grammar, or carries a core component past the platform integer range.
     *
     * @since   0.1.0
     */

```php
public static function fromString(string $value): Kumwe\Extension\Manifest\SemanticVersion;
```

### major

/**
     * Report the major component of the version core.
     *
     * @return  int  Non-negative; `VersionConstraint` reads it to place the upper bound of a `^` shorthand.
     *
     * @since   0.1.0
     */

```php
public function major(): int;
```

### minor

/**
     * Report the minor component of the version core.
     *
     * @return  int  Non-negative; the component a `~` shorthand increments to find its upper bound.
     *
     * @since   0.1.0
     */

```php
public function minor(): int;
```

### patch

/**
     * Report the patch component of the version core.
     *
     * @return  int  Non-negative; the component a `^0.0.x` shorthand increments to find its upper bound.
     *
     * @since   0.1.0
     */

```php
public function patch(): int;
```

### isPreRelease

/**
     * Report whether this version is a pre-release rather than a finished one.
     *
     * @return  bool  True when pre-release identifiers are present, which is exactly the condition that
     *          orders this version below the same core version without them.
     *
     * @since   0.1.0
     */

```php
public function isPreRelease(): bool;
```

### compare

/**
     * Order this version against another under the Semantic Versioning precedence rules.
     *
     * Major, minor and patch decide the outcome first. Where they tie, a version carrying pre-release
     * identifiers ranks below one that carries none, and otherwise the identifiers are walked pairwise
     * until one differs, with the shorter list ranking below a longer list that shares its prefix.
     * Build metadata is ignored entirely, so `2.0.0+one` and `2.0.0+two` have equal precedence.
     *
     * @param   self  $other  Version to order this one against.
     *
     * @return  int  Negative when this version precedes `$other`, zero when neither takes precedence,
     *          positive when this version follows it.
     *
     * @since   0.1.0
     */

```php
public function compare(Kumwe\Extension\Manifest\SemanticVersion $other): int;
```

### __toString

/**
     * Render the version back to the string form it was parsed from.
     *
     * @return  string  Dotted version core, pre-release identifiers after `-`, build metadata after `+`.
     *
     * @since   0.1.0
     */

```php
public function __toString(): string;
```

## Kumwe\Extension\Manifest\TemplateKisCompatibility

/**
 * Versioned KIS compatibility envelope declared by an installable template package.
 *
 * A template names the KIS standard it requires and inclusive version bounds for the public component
 * and token contracts it consumes. Parsing closes the declaration to known keys and proves both ranges
 * are ordered, while activation compares those ranges with the versions supplied by the running host.
 *
 * @since  0.1.0
 */

### schemaOneKis

/**
     * Supply the exact KIS 1.0 contract historical schema-one templates implicitly targeted.
     *
     * This narrow default preserves schema-one manifests accepted before the envelope existed. It
     * does not create an open range: those packages are admitted only against the original KIS 1.0
     * standard and the exact 1.0.0 component and token contracts.
     *
     * @return  self  Exact KIS 1.0 compatibility used only for undeclared schema-one templates.
     *
     * @since   0.1.0
     */

```php
public static function schemaOneKis(): Kumwe\Extension\Manifest\TemplateKisCompatibility;
```

### fromArray

/**
     * Parse and validate a template's closed KIS compatibility object.
     *
     * @param   array<string, mixed>  $declaration  Value of the manifest's top-level `template` field.
     *
     * @return  self  Validated contract and inclusive component/token bounds.
     *
     * @throws  InvalidArgumentException  When a key, contract version, standard identifier, range, or
     *          version value is malformed.
     *
     * @since   0.1.0
     */

```php
public static function fromArray(array $declaration): Kumwe\Extension\Manifest\TemplateKisCompatibility;
```

### contract

/**
     * Report the compatibility declaration format the package uses.
     *
     * @return  int  Version of the closed `template` manifest object.
     *
     * @since   0.1.0
     */

```php
public function contract(): int;
```

### standard

/**
     * Name the KIS major/minor standard the package requires.
     *
     * @return  string  Identifier such as `kis-1.0`.
     *
     * @since   0.1.0
     */

```php
public function standard(): string;
```

### supportsComponents

/**
     * Decide whether a host component contract lies inside the declared inclusive range.
     *
     * @param   SemanticVersion  $version  Public KIS component contract supplied by the host.
     *
     * @return  bool  True when the version is no older than the minimum and no newer than the maximum.
     *
     * @since   0.1.0
     */

```php
public function supportsComponents(Kumwe\Extension\Manifest\SemanticVersion $version): bool;
```

### supportsTokens

/**
     * Decide whether a host token contract lies inside the declared inclusive range.
     *
     * @param   SemanticVersion  $version  Public KIS token contract supplied by the host.
     *
     * @return  bool  True when the version is no older than the minimum and no newer than the maximum.
     *
     * @since   0.1.0
     */

```php
public function supportsTokens(Kumwe\Extension\Manifest\SemanticVersion $version): bool;
```

## Kumwe\Extension\Manifest\VersionConstraint

/**
 * Range of versions a manifest declaration is willing to accept.
 *
 * Manifests state dependency ranges and platform compatibility as text; this parses that text once,
 * into the set of comparisons `accepts()` then evaluates against any `SemanticVersion`. Three forms
 * are understood: `*` or the empty string for "any version", a whitespace-separated list of `=`, `<`,
 * `<=`, `>` and `>=` comparisons that must all hold at once, and the `~` and `^` shorthands, which are
 * expanded at parse time into the equivalent lower and upper bound. The declared text is kept
 * alongside the comparisons, so a constraint renders back as the manifest wrote it, not as the
 * bounds it expanded to.
 *
 * @since  0.1.0
 */

### fromString

/**
     * Parse a declared constraint expression.
     *
     * Whitespace around and between tokens carries no meaning, and an empty expression is read as `*`.
     * A `~` or `^` shorthand is recognised whole and expanded before tokenising ever happens; every
     * other expression is split on whitespace, and a token with no leading operator is an exact-version
     * comparison, so `2.0.0` means `=2.0.0`.
     *
     * @param   string  $expression  Constraint text from a manifest, such as `*`, `^2.1.0` or
     *          `>=2.0.0 <3.0.0`.
     *
     * @return  self  Constraint that holds only where every token of the expression holds.
     *
     * @throws  InvalidArgumentException  When the expression exceeds 255 characters, cannot be split
     *          into tokens, names a version the Semantic Versioning grammar rejects, or is a shorthand
     *          whose upper bound would run past the platform integer range.
     *
     * @since   0.1.0
     */

```php
public static function fromString(string $expression): Kumwe\Extension\Manifest\VersionConstraint;
```

### accepts

/**
     * Decide whether a version falls inside this constraint.
     *
     * Every parsed comparison has to hold, so a constraint that parsed to none of them — `*` — accepts
     * everything. Ordering is `SemanticVersion::compare()`, which means build metadata is ignored and a
     * pre-release ranks below the finished version of the same core, so `2.0.0-beta` fails `>=2.0.0`.
     *
     * @param   SemanticVersion  $candidate  Version being offered, typically the one an installed or
     *          packaged extension reports.
     *
     * @return  bool  True only when the candidate clears every comparison in the constraint.
     *
     * @since   0.1.0
     */

```php
public function accepts(Kumwe\Extension\Manifest\SemanticVersion $candidate): bool;
```

### __toString

/**
     * Render the constraint as the manifest declared it.
     *
     * @return  string  The trimmed expression text, or `*` where the manifest left it empty; a
     *          shorthand renders as the shorthand, not as the pair of bounds it expanded to.
     *
     * @since   0.1.0
     */

```php
public function __toString(): string;
```

## Kumwe\Extension\Package\ArchiveContentReader

/**
 * Port that streams an extension archive's file contents without writing any of them to disk.
 *
 * `ArchiveReader` answers what an archive claims to contain; this answers what the immutable inspected
 * snapshot actually contains. Nothing an implementation yields is written to disk. Reading is separate
 * from listing so neutral safety findings and all expansion budgets are established before any entry is
 * expanded, and the snapshot checksum prevents a second set of bytes being substituted later.
 *
 * @since  0.1.0
 */

### contents

/**
     * Yield every regular file entry as a path and its complete expanded bytes.
     *
     * Directory entries are skipped, since they carry no content to inspect. Implementations expand one
     * entry at a time so peak memory tracks the largest entry rather than the whole archive.
     *
     * @param   InspectedPackage  $package  Immutable package identity, entry table and shared limits.
     *
     * @return  iterable<string, string>  Complete entry bytes keyed by package path, in listing order.
     *
     * @since   0.1.0
     */

```php
public function contents(Kumwe\Extension\Package\InspectedPackage $package): iterable;
```

## Kumwe\Extension\Package\ArchiveEntry

/**
 * One row of an extension archive's directory: what an entry is, where it sits, and how large it claims
 * to be.
 *
 * An `ArchiveReader` builds these from the archive's header alone, without expanding anything, so
 * `PackageSafetyInspector` can report a decompression bomb, a symbolic link or a case-colliding path while
 * none of the package's bytes have touched the filesystem. The sizes are therefore the archive's own
 * claims and are not evidence about the eventual output — treating them as budgets to enforce is the
 * point, and trusting them as facts is not.
 *
 * The constructor refuses a negative size and a directory that claims payload bytes, so no limit check
 * downstream has to weigh a negative budget or a directory that reads like a file.
 *
 * @since  0.1.0
 */

### __construct

/**
     * Record one entry as the archive directory describes it.
     *
     * @param   PackagePath       $path               Relative, traversal-free location the entry occupies
     *          inside the archive.
     * @param   ArchiveEntryType  $type               Whether the entry is a file, a directory, or a link.
     * @param   int               $compressedBytes    Bytes the entry occupies inside the archive; zero for a
     *          directory, and never negative.
     * @param   int               $uncompressedBytes  Bytes the header claims the entry expands to; zero for a
     *          directory, and never negative.
     * @param   bool              $encrypted          Whether the entry requires decryption before expansion.
     *
     * @throws  InvalidArgumentException  When either size is negative, or a directory entry reports payload
     *          bytes.
     *
     * @since   0.1.0
     */

```php
public function __construct(Kumwe\Extension\Package\PackagePath $path, Kumwe\Extension\Package\ArchiveEntryType $type, int $compressedBytes, int $uncompressedBytes, bool $encrypted = false);
```

### path

/**
     * Return where the entry sits inside the archive.
     *
     * @return  PackagePath  Relative path, already proven free of absolute roots, `..` segments, backslashes
     *          and control characters; neutral safety inspection compares these case-insensitively to catch
     *          collisions that only a case-insensitive filesystem would notice.
     *
     * @since   0.1.0
     */

```php
public function path(): Kumwe\Extension\Package\PackagePath;
```

### type

/**
     * Return what kind of entry this is.
     *
     * @return  ArchiveEntryType  The classification the reader derived from the archive directory; a
     *          symbolic link here is reported before content expansion.
     *
     * @since   0.1.0
     */

```php
public function type(): Kumwe\Extension\Package\ArchiveEntryType;
```

### compressedBytes

/**
     * Return how much room the entry takes up inside the archive.
     *
     * @return  int  Stored size in bytes, as the archive header reports it; zero for a directory. A file
     *          that claims expanded bytes yet zero stored bytes is treated as an impossible claim and
     *          produces a finding, since it would otherwise present an unbounded compression ratio.
     *
     * @since   0.1.0
     */

```php
public function compressedBytes(): int;
```

### uncompressedBytes

/**
     * Return how large the entry claims it will be once expanded.
     *
     * @return  int  Expanded size in bytes as declared by the archive header, never verified against real
     *          output; zero for a directory. It is what the per-entry, whole-package and compression-ratio
     *          limits are measured against, which is why an inflated or absent claim is caught as a
     *          violation rather than believed.
     *
     * @since   0.1.0
     */

```php
public function uncompressedBytes(): int;
```

### encrypted

/**
     * Report whether the ZIP entry is encrypted.
     *
     * @return  bool  True when the central directory names a non-plaintext encryption method.
     *
     * @since   0.2.0
     */

```php
public function encrypted(): bool;
```

## Kumwe\Extension\Package\ArchiveEntryType

/**
 * What a single entry in an extension archive is, as classified from the archive directory.
 *
 * `ZipArchiveReader` decides the case from the entry name and the Unix mode in the ZIP external
 * attributes, before anything is expanded. `PackageSafetyInspector` then reports two facts it
 * could not make from a path and a size alone: a link is reported before expansion, and only a regular
 * file at the archive root can satisfy the required `kumwe.json` manifest.
 *
 * @since  0.1.0
 */

### cases

Generated enum/runtime member.

```php
public static function cases(): array;
```

### from

Generated enum/runtime member.

```php
public static function from(string|int $value): static;
```

### tryFrom

Generated enum/runtime member.

```php
public static function tryFrom(string|int $value): ?static;
```

### Public properties



- `readonly string $name`


- `readonly string $value`

### Public constants

/**
     * A regular file carrying payload bytes, and the only kind that can serve as the package manifest.
     *
     * @since  0.1.0
     */

- `File = \Kumwe\Extension\Package\ArchiveEntryType::File`
/**
     * A path entry with no payload of its own, so both of its recorded sizes are zero.
     *
     * @since  0.1.0
     */

- `Directory = \Kumwe\Extension\Package\ArchiveEntryType::Directory`
/**
     * An entry whose mode marks it a symbolic link and must be surfaced as unsafe metadata.
     *
     * A link inside a package is a way to redirect a later write outside the deployment, or to smuggle a
     * reference to a host file into the extension tree, so no packaging need justifies allowing one.
     *
     * @since  0.1.0
     */

- `SymbolicLink = \Kumwe\Extension\Package\ArchiveEntryType::SymbolicLink`
/**
     * A filesystem object that is neither a regular file, directory nor symbolic link.
     *
     * FIFOs, sockets and device nodes have no legitimate portable package meaning and are reported before
     * extraction rather than being treated as ordinary files.
     *
     * @since  0.2.0
     */

- `Special = \Kumwe\Extension\Package\ArchiveEntryType::Special`

## Kumwe\Extension\Package\ArchivePackage

/**
 * Immutable description of what an extension archive contains, built without extracting it.
 *
 * `ArchiveReader` assembles one from an archive's directory listing and `PackageSafetyInspector` reports
 * objective discrepancies while none of its bytes have reached the filesystem. The constructor
 * shape-validates the entry table, so every reader of `entries()` may assume a non-empty list of
 * `ArchiveEntry`.
 *
 * @since  0.1.0
 */

### __construct

/**
     * Validate an entry table and freeze it as an archive description.
     *
     * The parameter type is deliberately wide because the caller passes on whatever the archive
     * directory yielded; this constructor is the boundary that proves the shape before any downstream
     * code relies on it.
     *
     * @param   array<mixed>  $entries  Entry descriptions read from the archive directory, in listing order.
     *
     * @throws  InvalidArgumentException  When the value is not a non-empty list of ArchiveEntry objects.
     *
     * @since   0.1.0
     */

```php
public function __construct(array $entries);
```

### entries

/**
     * Return the archive's entry table.
     *
     * @return  list<ArchiveEntry>  Every entry the reader found, in listing order; never empty.
     *
     * @since   0.1.0
     */

```php
public function entries(): array;
```

## Kumwe\Extension\Package\ArchiveReader

/**
 * Port that lists an extension archive's contents without unpacking any of them.
 *
 * Hosts and author tooling begin package inspection here. Keeping central-directory access behind this
 * port lets neutral safety inspection remain archive-format independent; `ZipArchiveReader` is the
 * shipped binding.
 *
 * @since  0.1.0
 */

### inspect

/**
     * Read an archive's directory listing into a description neutral safety inspection can judge.
     *
     * Implementations inspect a non-public snapshot and never extract while producing the descriptor.
     *
     * @param   string         $archiveFile  Path of the staged archive file to inspect.
     * @param   PackageLimits  $limits       Exact resource budget for this inspection.
     *
     * @return  ArchivePackage  Every entry with its type and its compressed and expanded sizes.
     *
     * @since   0.1.0
     */

```php
public function inspect(string $archiveFile, Kumwe\Extension\Package\PackageLimits $limits): Kumwe\Extension\Package\ArchivePackage;
```

## Kumwe\Extension\Package\InspectedPackage

/**
 * Immutable identity and metadata snapshot produced only by inspecting one staged ZIP.
 *
 * The constructor is private: callers cannot combine a checksum, entry table, manifest or finding list
 * from different sources. `inspect()` computes every field from the same stable archive bytes. Content
 * readers verify the checksum again before and after expansion. The caller must keep the canonical
 * archive path in a privately owned, non-shared staging directory for the lifetime of the snapshot;
 * current-file checks fail closed, but no pathname API can make a shared writable directory race-free.
 *
 * @since  0.2.0
 */

### inspect

/**
     * Capture one same-bytes snapshot through the canonical ZIP inspection implementation.
     *
     * @param   string         $archiveFile  Canonical absolute path in caller-controlled private staging.
     * @param   PackageLimits  $limits       Exact limits carried into the resulting snapshot.
     *
     * @return  self  Closed snapshot whose public fields all derive from the same stable bytes.
     *
     * @throws  InvalidArgumentException  When the caller supplies a non-canonical or unavailable path.
     * @throws  InvalidPackage  When package bytes are too malformed to produce a complete snapshot.
     * @throws  RuntimeException  When stable filesystem reads or hashing fail.
     *
     * @since   0.2.0
     * @param CanonicalEncoder $canonicalEncoder Canonical encoding port supplied by the composition root.
     */

```php
public static function inspect(Kumwe\CanonicalJson\CanonicalEncoder $canonicalEncoder, string $archiveFile, Kumwe\Extension\Package\PackageLimits $limits = \Kumwe\Extension\Package\PackageLimits::__set_state(array(
   'maximumEntries' => 4096,
   'maximumEntryBytes' => 67108864,
   'maximumExpandedBytes' => 268435456,
   'maximumCompressedBytes' => 268435456,
   'maximumArchiveBytes' => 285212672,
   'maximumCompressionRatio' => 100,
   'maximumManifestBytes' => 1048576,
   'maximumBillOfMaterialsBytes' => 4194304,
   'maximumProvenanceBytes' => 16384,
   'readChunkBytes' => 262144,
))): Kumwe\Extension\Package\InspectedPackage;
```

### paths

/**
     * Return package paths in central-directory order.
     *
     * @return  list<string>  Validated portable package paths.
     *
     * @since   0.2.0
     */

```php
public function paths(): array;
```

### expandedBytes

/**
     * Sum declared expanded bytes without exceeding the configured bound.
     *
     * @return  int  Declared expanded bytes, capped one byte above the configured maximum.
     *
     * @since   0.2.0
     */

```php
public function expandedBytes(): int;
```

### hasNoSafetyFindings

/**
     * Report whether central-directory inspection produced no safety discrepancy.
     *
     * This is a fact about the report, not permission to install or extract the package.
     *
     * @return  bool  True when the neutral safety finding list is empty.
     *
     * @since   0.2.0
     */

```php
public function hasNoSafetyFindings(): bool;
```

### assertCurrentArchiveIdentity

/**
     * Require the live staged path to remain the same bounded regular-file bytes as this snapshot.
     *
     * Hosts may call this immediately before a pathname-based operation. They must still retain the
     * package in private staging so another process cannot swap the path between this check and that
     * operation.
     *
     * @return  void
     *
     * @throws  RuntimeException  When the path is unavailable, non-regular, oversized or changed.
     *
     * @since   0.2.0
     */

```php
public function assertCurrentArchiveIdentity(): void;
```

### __serialize

/**
     * Prevent persistence from turning a stale path and caller-authored fields into a new snapshot.
     *
     * @return  array<never, never>  Never returned; snapshots must be inspected again.
     *
     * @throws  LogicException  Always, because serialized snapshots cannot retain the same-bytes proof.
     *
     * @since   0.2.0
     */

```php
public function __serialize(): array;
```

### __unserialize

/**
     * Prevent native deserialization from bypassing the closed constructor.
     *
     * @param   array<mixed>  $data  Untrusted serialized fields, never adopted.
     *
     * @return  void
     *
     * @throws  LogicException  Always, because snapshots can only come from `inspect()`.
     *
     * @since   0.2.0
     */

```php
public function __unserialize(array $data): void;
```

### Public properties



- `readonly string $archive`


- `readonly Kumwe\Extension\Package\PackageChecksum $checksum`


- `readonly Kumwe\Extension\Package\ArchivePackage $entries`


- `readonly Kumwe\Extension\Manifest\ExtensionManifest $manifest`


- `readonly string $manifestJson`


- `readonly Kumwe\Extension\Package\PackageLimits $limits`


- `readonly array $safetyFindings`

## Kumwe\Extension\Package\InvalidPackage

/**
 * Signals package data too malformed to describe as a complete inspected snapshot.
 *
 * This is not an admission decision. Author tooling converts it to a neutral finding, while a host can
 * apply its own policy. Transport and filesystem failures remain separate runtime exceptions.
 *
 * @since  0.2.0
 */

### __construct

/**
     * Retain the stable finding that explains why no snapshot could be produced.
     *
     * @param  PackageFinding  $finding  Policy-neutral malformed-package fact.
     *
     * @since  0.2.0
     */

```php
public function __construct(Kumwe\Extension\Package\PackageFinding $finding);
```

### Public properties



- `readonly Kumwe\Extension\Package\PackageFinding $finding`

## Kumwe\Extension\Package\PackageAttestationState

/**
 * What inspection established about a package's bill of materials or provenance.
 *
 * The SDK reports all four observable states and leaves their policy meaning to the consuming host.
 * A document is verified, absent, present but invalid, or not inspected because package safety blocked
 * content expansion. Nothing in this enum admits or refuses a package; it is evidence for caller policy.
 *
 * @since  0.1.0
 */

### cases

Generated enum/runtime member.

```php
public static function cases(): array;
```

### from

Generated enum/runtime member.

```php
public static function from(string|int $value): static;
```

### tryFrom

Generated enum/runtime member.

```php
public static function tryFrom(string|int $value): ?static;
```

### Public properties



- `readonly string $name`


- `readonly string $value`

### Public constants

/**
     * Package safety findings prevented attestation bytes from being expanded.
     *
     * @since  0.2.0
     */

- `NotInspected = \Kumwe\Extension\Package\PackageAttestationState::NotInspected`
/**
     * The document was present and agreed with the package bytes it describes.
     *
     * @since  0.1.0
     */

- `Verified = \Kumwe\Extension\Package\PackageAttestationState::Verified`
/**
     * The package carried no such document, so nothing was claimed and nothing was checked.
     *
     * @since  0.1.0
     */

- `Absent = \Kumwe\Extension\Package\PackageAttestationState::Absent`
/**
     * A document was present but could not be parsed or reconciled with the package.
     *
     * @since  0.2.0
     */

- `Invalid = \Kumwe\Extension\Package\PackageAttestationState::Invalid`

## Kumwe\Extension\Package\PackageBillOfMaterials

/**
 * The CycloneDX bill of materials an extension package carries about its own contents.
 *
 * CycloneDX 1.6 was chosen over SPDX for three reasons that are specific to this repository rather
 * than to the formats in the abstract. The release pipeline already emits CycloneDX for the core
 * images and the source tree, so an operator correlating a package against the core it runs on reads
 * one format instead of two. Its JSON serialization is small enough to embed in every package and to
 * store on a release row, where an SPDX document of the same fidelity is several times the size. And
 * its component model has a first-class `file` type with a hash list, which is exactly the unit of
 * inventory an extension has: the deterministic builder refuses a packaged `vendor/` or
 * `node_modules/` tree, so bundled third-party code arrives as vendored source files, and a per-file
 * digest is the only honest way to describe it.
 *
 * The document lives inside the package as `kumwe.sbom.json`, not beside it. That is what makes it
 * evidence rather than an assertion: the package digest covers it, and the detached Ed25519 signature
 * covers the package digest, so the publisher's signature already vouches for the inventory without a
 * second signature format. The document necessarily excludes itself and its provenance sibling from
 * its own component list — a document cannot contain its own digest — and reconciliation verifies that
 * exclusion rather than assuming it, by requiring every other entry to be listed with a matching
 * digest and every listed component to exist.
 *
 * No timestamp is emitted. CycloneDX makes `metadata.timestamp` optional, and a build clock would
 * defeat the byte-reproducibility the package builder exists to provide.
 *
 * @since  0.1.0
 */

### forPackage

/**
     * Build the document for one package from its manifest and its per-entry digests.
     *
     * @param   ExtensionManifest      $manifest      Strict parsed manifest naming the component this
     *          bill of materials describes.
     * @param   array<string, string>  $entryDigests  Lowercase SHA-256 by package path, covering every
     *          packaged file except the two attestation documents.
     *
     * @return  self  Document ready to encode into the package.
     *
     * @throws  InvalidArgumentException  When a digest is not a lowercase SHA-256 value.
     *
     * @since   0.1.0
     */

```php
public static function forPackage(Kumwe\Extension\Manifest\ExtensionManifest $manifest, array $entryDigests): Kumwe\Extension\Package\PackageBillOfMaterials;
```

### fromJson

/**
     * Decode the exact canonical CycloneDX profile emitted by this SDK.
     *
     * This package-level attestation is intentionally narrower than arbitrary CycloneDX. Unknown,
     * missing or reordered semantic fields would create a second representation of the same inventory,
     * so they are rejected and profile evolution requires an explicit format revision.
     *
     * @param   string  $json  Raw document bytes read from the package.
     *
     * @return  self  Validated document.
     *
     * @throws  InvalidArgumentException  When the document is oversized, is not a CycloneDX object of a
     *          supported specification version, or holds an unreadable component.
     * @throws  JsonException  When the JSON is malformed or too deeply nested.
     *
     * @since   0.1.0
     */

```php
public static function fromJson(string $json): Kumwe\Extension\Package\PackageBillOfMaterials;
```

### fileDigests

/**
     * Report the file components the document claims, as a digest keyed by package path.
     *
     * Only `file` components participate; anything else CycloneDX permits is another kind of claim and
     * is not something a package's own bytes can confirm.
     *
     * @return  array<string, string>  Lowercase SHA-256 by package path, sorted by path.
     *
     * @throws  InvalidArgumentException  When a file component names no path or no SHA-256 digest, or
     *          names the same path twice.
     *
     * @since   0.1.0
     */

```php
public function fileDigests(): array;
```

### reconcile

/**
     * Compare the document's inventory against the digests actually computed from the package.
     *
     * @param   ExtensionManifest      $manifest      Manifest carried by the same inspected package.
     * @param   array<string, string>  $entryDigests  Lowercase SHA-256 by package path for every packaged
     *          file except the two attestation documents.
     *
     * @return  list<string>  Sorted mismatch descriptions; empty when the inventory is exact.
     *
     * @throws  InvalidArgumentException  When a file component names no path or no SHA-256 digest.
     *
     * @since   0.1.0
     */

```php
public function reconcile(Kumwe\Extension\Manifest\ExtensionManifest $manifest, array $entryDigests): array;
```

### componentCount

/**
     * Report how many file components the document inventories.
     *
     * @return  int  Component count, used by the operator surface as the inventory's size.
     *
     * @throws  InvalidArgumentException  When a file component names no path or no SHA-256 digest.
     *
     * @since   0.1.0
     */

```php
public function componentCount(): int;
```

### toJson

/**
     * Encode the document deterministically for embedding in a package.
     *
     * @return  string  Pretty-printed JSON ending with one newline.
     *
     * @throws  JsonException  When an internal field cannot be encoded.
     *
     * @since   0.1.0
     */

```php
public function toJson(): string;
```

### Public properties



- `readonly array $document`

### Public constants

/**
     * Package path the bill of materials is carried at.
     *
     * @var    string
     * @since  0.1.0
     */

- `PATH = 'kumwe.sbom.json'`
/**
     * CycloneDX specification version emitted and accepted.
     *
     * @var    string
     * @since  0.1.0
     */

- `SPEC_VERSION = '1.6'`
/**
     * Largest bill of materials expanded during bounded evidence inspection.
     *
     * @var    int
     * @since  0.1.0
     */

- `MAXIMUM_BYTES = 4194304`

## Kumwe\Extension\Package\PackageChecksum

/**
 * SHA-256 digest that stands for the exact bytes of an extension package.
 *
 * The digest is a portable package identity for snapshot binding, storage and signature verification.
 * Signatures use its hexadecimal rendering rather than loading the archive into memory. Comparison
 * goes through `matches`, which is constant time.
 *
 * @since  0.1.0
 */

### sha256

/**
     * Adopt a digest supplied as text, such as one read back from the registry or a release feed.
     *
     * Surrounding whitespace and uppercase hexadecimal are normalised; anything that is not exactly
     * 64 hexadecimal characters is refused rather than padded or truncated.
     *
     * @param   string  $hexadecimalDigest  Digest as written, in either case.
     *
     * @return  self  The normalised digest.
     *
     * @throws  InvalidArgumentException  When the value is not a 64-character hexadecimal digest.
     *
     * @since   0.1.0
     */

```php
public static function sha256(string $hexadecimalDigest): Kumwe\Extension\Package\PackageChecksum;
```

### calculate

/**
     * Compute the digest of package bytes already held in memory.
     *
     * @param   string  $packageBytes  Complete archive contents to hash.
     *
     * @return  self  Digest of exactly those bytes.
     *
     * @since   0.1.0
     */

```php
public static function calculate(string $packageBytes): Kumwe\Extension\Package\PackageChecksum;
```

### matches

/**
     * Check package bytes against this digest in constant time.
     *
     * @param   string  $packageBytes  Complete archive contents to verify.
     *
     * @return  bool  True when those bytes hash to this digest.
     *
     * @since   0.1.0
     */

```php
public function matches(string $packageBytes): bool;
```

### __toString

/**
     * Render the digest for storage, logging, and signature verification.
     *
     * @return  non-empty-string  The 64-character lowercase hexadecimal digest.
     *
     * @since   0.1.0
     */

```php
public function __toString(): string;
```

## Kumwe\Extension\Package\PackageCodeConformance

/**
 * The bounded, code-free static checks a package's contents are judged by, wherever they are judged.
 *
 * Author tooling and host inspection share this implementation. Keeping checks here makes their facts
 * identical without prescribing whether a consuming host blocks, warns or ignores any finding.
 *
 * Nothing here loads, includes or executes packaged code. PHP files are tokenized with `TOKEN_PARSE`,
 * which parses without binding a single symbol, and every other check is a string or path comparison.
 *
 * @since  0.1.0
 */

### phpFindings

/**
     * Parse one PHP source file and optionally inspect its authoring convention.
     *
     * @param   string  $path      Package path quoted in each violation.
     * @param   string  $contents  PHP source bytes.
     * @param   PackageEvidenceScope  $scope  Package-only or complete authoring evidence.
     *
     * @return  list<PackageFinding>  Syntax and, when requested, strict-types findings.
     *
     * @since   0.1.0
     */

```php
public function phpFindings(string $path, string $contents, Kumwe\Extension\Package\PackageEvidenceScope $scope): array;
```

### markerViolations

/**
     * Detect unresolved scaffold and unfinished-work markers in packaged text.
     *
     * @param   string  $path      Package path quoted in the violation.
     * @param   string  $contents  Text contents.
     *
     * @return  list<PackageFinding>  Text-encoding or unresolved-marker findings.
     *
     * @since   0.1.0
     */

```php
public function markerViolations(string $path, string $contents): array;
```

### referenceViolations

/**
     * Check the manifest references that can be resolved without autoloading extension classes.
     *
     * The provider class, every declared migration, every declared asset and every contributed template
     * must resolve to a path the package actually carries. A manifest that names a class the package
     * does not ship is not a style problem: it is a package that will fail when its provider is resolved.
     *
     * @param   ExtensionManifest  $manifest  Strict parsed package manifest.
     * @param   list<string>       $paths     Every path the package carries.
     *
     * @return  list<PackageFinding>  Sorted findings; empty when every declared reference resolves.
     *
     * @since   0.1.0
     */

```php
public function referenceViolations(Kumwe\Extension\Manifest\ExtensionManifest $manifest, array $paths): array;
```

### isTextPath

/**
     * Decide whether an entry is a text format subject to marker scanning.
     *
     * @param   string  $path  Package path.
     *
     * @return  bool  True for supported text extensions and conventional text file names.
     *
     * @since   0.1.0
     */

```php
public function isTextPath(string $path): bool;
```

### isPhpPath

/**
     * Report whether a path names packaged PHP source.
     *
     * @param   string  $path  Package path.
     *
     * @return  bool  True when the entry is a `.php` file in any letter case.
     *
     * @since   0.1.0
     */

```php
public function isPhpPath(string $path): bool;
```

## Kumwe\Extension\Package\PackageEvidenceInspector

/**
 * Inspects code and attestations from one immutable package snapshot without making an admission decision.
 *
 * Safety findings are returned without expanding entries. For a structurally clean snapshot, all static,
 * inventory and provenance facts are returned as deterministic coded findings. Transport and I/O failures
 * still throw because no honest evidence report can be produced from unreadable or changing bytes.
 *
 * @since  0.2.0
 */

### __construct

/**
     * Bind evidence inspection to bounded content expansion and shared code checks.
     *
     * @param  ArchiveContentReader    $contents     Snapshot-bound content reader.
     * @param  PackageCodeConformance  $conformance  Code-free static checks.
     *
     * @since  0.2.0
     */

```php
public function __construct(Kumwe\Extension\Package\ArchiveContentReader $contents, Kumwe\Extension\Package\PackageCodeConformance $conformance);
```

### inspect

/**
     * Inspect one exact staged package snapshot.
     *
     * @param   InspectedPackage      $package  Archive identity, entries, manifest and limits from one read.
     * @param   PackageEvidenceScope  $scope    Package-only or complete authoring evidence.
     *
     * @return  PackageEvidenceReport  Neutral package evidence and coded findings.
     *
     * @throws  InvalidArgumentException  When the staged archive is no longer available.
     * @throws  RuntimeException  When bytes change or cannot be read completely.
     *
     * @since   0.2.0
     */

```php
public function inspect(Kumwe\Extension\Package\InspectedPackage $package, Kumwe\Extension\Package\PackageEvidenceScope $scope = \Kumwe\Extension\Package\PackageEvidenceScope::Authoring): Kumwe\Extension\Package\PackageEvidenceReport;
```

## Kumwe\Extension\Package\PackageEvidenceReport

/**
 * Neutral, deterministic facts produced by inspecting one immutable package snapshot.
 *
 * Findings carry stable codes and messages but no severity or admission outcome. Hosts map those codes
 * to their own block, warn or off posture; author tooling may independently derive conformance.
 *
 * @since  0.2.0
 */

### __construct

/**
     * Retain all objective evidence and findings from one scan.
     *
     * @param   PackageEvidenceScope     $scope             Evidence depth actually executed.
     * @param   PackageAttestationState  $sbomState         Inventory inspection state.
     * @param   ?string                  $sbomSha256        SHA-256 of inventory bytes when present.
     * @param   int                      $sbomComponents    Verified file components.
     * @param   ?array<string, mixed>    $sbom              Readable CycloneDX document, or null.
     * @param   PackageAttestationState  $provenanceState   Provenance inspection state.
     * @param   ?string                  $provenanceSha256  SHA-256 of provenance bytes when present.
     * @param   ?string                  $builderReference  Publisher-asserted builder identity.
     * @param   ?array<string, mixed>    $provenance        Readable provenance document, or null.
     * @param   array<string, bool>      $checks            Named objective verification outcomes.
     * @param   list<PackageFinding>     $findings          Stable policy-neutral findings.
     *
     * @throws  InvalidArgumentException  When counts, checks or findings are malformed.
     *
     * @since   0.2.0
     */

```php
public function __construct(Kumwe\Extension\Package\PackageEvidenceScope $scope, Kumwe\Extension\Package\PackageAttestationState $sbomState, ?string $sbomSha256, int $sbomComponents, ?array $sbom, Kumwe\Extension\Package\PackageAttestationState $provenanceState, ?string $provenanceSha256, ?string $builderReference, ?array $provenance, array $checks, array $findings);
```

### toArray

/**
     * Export the policy-neutral evidence document.
     *
     * @return  array<string, mixed>  Stable JSON-compatible evidence fields.
     *
     * @since   0.2.0
     */

```php
public function toArray(): array;
```

### auditMetadata

/**
     * Reduce evidence to concise policy-neutral audit metadata.
     *
     * @return  array{scope: string, sbom: string, provenance: string, checks_passed: bool, findings: int}
     *          Audit facts.
     *
     * @since   0.2.0
     */

```php
public function auditMetadata(): array;
```

### Public properties



- `readonly Kumwe\Extension\Package\PackageEvidenceScope $scope`


- `readonly Kumwe\Extension\Package\PackageAttestationState $sbomState`


- `readonly ?string $sbomSha256`


- `readonly int $sbomComponents`


- `readonly ?array $sbom`


- `readonly Kumwe\Extension\Package\PackageAttestationState $provenanceState`


- `readonly ?string $provenanceSha256`


- `readonly ?string $builderReference`


- `readonly ?array $provenance`


- `readonly array $checks`


- `readonly array $findings`

## Kumwe\Extension\Package\PackageEvidenceScope

/**
 * Host-neutral depth of code-free evidence collection over one package snapshot.
 *
 * Package scope establishes archive, executable, reference and attestation facts. Authoring scope adds
 * source conventions useful to an author's CI. Neither scope assigns severity or an admission outcome.
 *
 * @since  0.2.0
 */

### includesAuthoring

/**
     * Report whether author-only source convention checks belong in this scan.
     *
     * @return  bool  True only for the complete authoring scope.
     *
     * @since   0.2.0
     */

```php
public function includesAuthoring(): bool;
```

### cases

Generated enum/runtime member.

```php
public static function cases(): array;
```

### from

Generated enum/runtime member.

```php
public static function from(string|int $value): static;
```

### tryFrom

Generated enum/runtime member.

```php
public static function tryFrom(string|int $value): ?static;
```

### Public properties



- `readonly string $name`


- `readonly string $value`

### Public constants

/**
     * Inspect package safety, PHP syntax, manifest references, inventory and provenance.
     *
     * @since  0.2.0
     */

- `Package = \Kumwe\Extension\Package\PackageEvidenceScope::Package`
/**
     * Add strict types, unfinished markers, text encoding and README checks to package evidence.
     *
     * @since  0.2.0
     */

- `Authoring = \Kumwe\Extension\Package\PackageEvidenceScope::Authoring`

## Kumwe\Extension\Package\PackageFinding

/**
 * One policy-neutral, machine-addressable fact found while inspecting a package.
 *
 * Codes identify facts rather than severities. A consuming host decides which codes it blocks, warns
 * about or ignores; the SDK supplies the same code and message to every consumer.
 *
 * @since  0.2.0
 */

### __construct

/**
     * Validate and retain one finding.
     *
     * @param   string   $code     Stable dotted lowercase finding code.
     * @param   string   $message  Bounded human-readable explanation.
     * @param   ?string  $path     Package path concerned, when one entry caused the finding.
     *
     * @throws  InvalidArgumentException  When the code, message or optional path is malformed.
     *
     * @since   0.2.0
     */

```php
public function __construct(string $code, string $message, ?string $path = NULL);
```

### toArray

/**
     * Export the stable machine document.
     *
     * @return  array{code: string, message: string, path?: string}  Finding fields without policy metadata.
     *
     * @since   0.2.0
     */

```php
public function toArray(): array;
```

### __toString

/**
     * Render the stable human explanation for consoles and exception bridges.
     *
     * @return  string  The exact finding message.
     *
     * @since   0.2.0
     */

```php
public function __toString(): string;
```

### Public properties



- `readonly string $code`


- `readonly string $message`


- `readonly ?string $path`

## Kumwe\Extension\Package\PackageLimits

/**
 * One immutable resource budget shared by archive listing, expansion, evidence inspection and building.
 *
 * A package must not be accepted under one set of limits and then read under another. Carrying the
 * complete budget in the inspected snapshot makes every later operation enforce the same ceilings.
 *
 * @since  0.2.0
 */

### __construct

/**
     * Configure every bounded package operation.
     *
     * @param   int  $maximumEntries           Most central-directory entries in one package.
     * @param   int  $maximumEntryBytes        Largest expanded regular-file entry.
     * @param   int  $maximumExpandedBytes     Largest total expanded package size.
     * @param   int  $maximumCompressedBytes   Largest total compressed entry size.
     * @param   int  $maximumArchiveBytes      Largest staged ZIP file, including directory metadata.
     * @param   int  $maximumCompressionRatio  Highest expanded-to-compressed ratio for one entry.
     * @param   int  $maximumManifestBytes     Largest root manifest document.
     * @param   int  $maximumBillOfMaterialsBytes  Largest embedded inventory document.
     * @param   int  $maximumProvenanceBytes   Largest embedded provenance document.
     * @param   int  $readChunkBytes           Bytes read from one entry stream at a time.
     *
     * @throws  InvalidArgumentException  When a limit is not positive, an individual entry can exceed
     *          the package total, a protocol-document cap escapes the shared expansion budget, or the
     *          read chunk exceeds the per-entry ceiling.
     *
     * @since   0.2.0
     */

```php
public function __construct(int $maximumEntries = 4096, int $maximumEntryBytes = 67108864, int $maximumExpandedBytes = 268435456, int $maximumCompressedBytes = 268435456, int $maximumArchiveBytes = 285212672, int $maximumCompressionRatio = 100, int $maximumManifestBytes = 1048576, int $maximumBillOfMaterialsBytes = 4194304, int $maximumProvenanceBytes = 16384, int $readChunkBytes = 262144);
```

### Public properties



- `readonly int $maximumEntries`


- `readonly int $maximumEntryBytes`


- `readonly int $maximumExpandedBytes`


- `readonly int $maximumCompressedBytes`


- `readonly int $maximumArchiveBytes`


- `readonly int $maximumCompressionRatio`


- `readonly int $maximumManifestBytes`


- `readonly int $maximumBillOfMaterialsBytes`


- `readonly int $maximumProvenanceBytes`


- `readonly int $readChunkBytes`

## Kumwe\Extension\Package\PackagePath

/**
 * Relative, traversal-free path naming one entry inside an extension package.
 *
 * `ZipArchiveReader` puts every archive entry name through `fromString` before it becomes an
 * `ArchiveEntry`, which is what lets the inspection and extraction code downstream treat a path as
 * plain data. A hostile archive therefore fails while its index is being read — before any byte is
 * written to disk — rather than at extraction time.
 *
 * @since  0.1.0
 */

### fromString

/**
     * Validate an archive entry name and normalise it into a safe relative path.
     *
     * Normalisation is deliberately minimal: only trailing slashes are dropped, so a directory entry
     * and a reference to that same directory settle on one value and nothing else is rewritten.
     * Everything unsafe is refused instead of sanitised. Package paths use a portable ASCII profile so
     * case folding and filesystem normalization have the same result on every supported host. Empty,
     * absolute, drive-qualified, control-bearing and over-long paths are refused, as are dot segments,
     * trailing dots, Windows device names and characters whose filesystem meaning varies by platform.
     *
     * @param   string  $path  Entry name exactly as the archive records it.
     *
     * @return  self  The normalised relative path.
     *
     * @throws  InvalidArgumentException  When the path is empty, absolute, over-long, or unsafe.
     *
     * @since   0.1.0
     */

```php
public static function fromString(string $path): Kumwe\Extension\Package\PackagePath;
```

### value

/**
     * Expose the path for joining against an extraction root or comparing against another entry.
     *
     * @return  string  Relative path, `/`-separated, with no trailing slash and no `.` or `..` segment.
     *
     * @since   0.1.0
     */

```php
public function value(): string;
```

### __toString

/**
     * Render the path where it is interpolated into a message or a filesystem join.
     *
     * @return  string  The same value `value()` returns.
     *
     * @since   0.1.0
     */

```php
public function __toString(): string;
```

## Kumwe\Extension\Package\PackageProvenance

/**
 * The publisher-asserted statement of how a package was produced, carried inside the package.
 *
 * The bill of materials says what is inside; this says where it came from. The fields mirror the SLSA
 * provenance predicate — a build type, a builder identity, the subject being described and the
 * materials it was assembled from — without adopting the in-toto envelope, because the envelope exists
 * to carry a signature from a build service that is a different trust domain than the publisher, and
 * here they are the same party running the same SDK. Embedding the statement in the package instead
 * puts it under the publisher's existing detached Ed25519 signature, which is the honest strength of
 * the claim: verification can prove the publisher asserted it, not that an independent builder
 * observed it.
 *
 * Two fields are load-bearing during reconciliation. `materials.sbom_sha256` binds this statement to the exact
 * bill of materials in the same package, so the two documents cannot be mixed between builds, and
 * `subject` must name the manifest the package actually carries, so a statement cannot be lifted from
 * one release onto another. Everything else is recorded and shown, never believed.
 *
 * No build timestamp is emitted, for the same reason the bill of materials carries none: the package
 * builder's contract is byte reproducibility, and a clock reading would break it.
 *
 * @since  0.1.0
 */

### forPackage

/**
     * Build the statement for one package from its manifest and the bill of materials beside it.
     *
     * @param   ExtensionManifest  $manifest       Strict parsed manifest identifying the subject.
     * @param   string             $sbomSha256     Lowercase SHA-256 of the encoded bill of materials.
     * @param   int                $entryCount     Packaged files inventoried, excluding both attestations.
     * @param   int                $expandedBytes  Sum of those files' byte lengths.
     *
     * @return  self  Statement ready to encode into the package.
     *
     * @throws  InvalidArgumentException  When the bill-of-materials digest is not a SHA-256 value, or a
     *          count is negative.
     *
     * @since   0.1.0
     */

```php
public static function forPackage(Kumwe\Extension\Manifest\ExtensionManifest $manifest, string $sbomSha256, int $entryCount, int $expandedBytes): Kumwe\Extension\Package\PackageProvenance;
```

### fromJson

/**
     * Decode a provenance statement read from a package, rejecting every unknown or missing key.
     *
     * Unlike the bill of materials this format is Kumwe's own, so it is parsed strictly in both
     * directions: an unrecognised top-level key is invalid rather than something to carry forward,
     * which is what keeps the statement's meaning from drifting between builders.
     *
     * @param   string  $json  Raw statement bytes read from the package.
     *
     * @return  self  Validated statement.
     *
     * @throws  InvalidArgumentException  When the statement is oversized, is not an object of exactly the
     *          declared keys, declares an unsupported format or build type, or holds a malformed section.
     * @throws  JsonException  When the JSON is malformed or too deeply nested.
     *
     * @since   0.1.0
     */

```php
public static function fromJson(string $json): Kumwe\Extension\Package\PackageProvenance;
```

### reconcile

/**
     * Check the statement against the package it travels in.
     *
     * @param   ExtensionManifest  $manifest       Manifest the package actually carries.
     * @param   string             $sbomSha256     SHA-256 of the verified bill of materials beside it.
     * @param   int                $entryCount     Actual inventoried regular-file count.
     * @param   int                $expandedBytes  Actual inventoried expanded-byte count.
     *
     * @return  list<string>  Sorted mismatch descriptions; empty when the statement describes this package.
     *
     * @since   0.1.0
     */

```php
public function reconcile(Kumwe\Extension\Manifest\ExtensionManifest $manifest, string $sbomSha256, int $entryCount, int $expandedBytes): array;
```

### builderReference

/**
     * Report the builder identity the statement asserts, for display beside the verification result.
     *
     * @return  string  `name@version`, or `unknown` when the statement names neither.
     *
     * @since   0.1.0
     */

```php
public function builderReference(): string;
```

### toJson

/**
     * Encode the statement deterministically for embedding in a package.
     *
     * @return  string  Pretty-printed JSON ending with one newline.
     *
     * @throws  JsonException  When an internal field cannot be encoded.
     *
     * @since   0.1.0
     */

```php
public function toJson(): string;
```

### Public properties



- `readonly array $statement`

### Public constants

/**
     * Package path the provenance statement is carried at.
     *
     * @var    string
     * @since  0.1.0
     */

- `PATH = 'kumwe.provenance.json'`
/**
     * Stable statement format identifier.
     *
     * @var    string
     * @since  0.1.0
     */

- `FORMAT = 'kumwe-extension-provenance-v1'`
/**
     * Build type naming the process the subject was produced by.
     *
     * @var    string
     * @since  0.1.0
     */

- `BUILD_TYPE = 'https://kumwe.dev/extension/deterministic-package/v1'`
/**
     * Name of the builder that produces conforming packages.
     *
     * @var    string
     * @since  0.1.0
     */

- `BUILDER_NAME = 'kumwe-deterministic-package-builder'`
/**
     * Builder revision, deliberately a format revision rather than the CMS release.
     *
     * Stamping the running release here would make the same source tree build to different bytes on
     * two Kumwe versions, which is precisely the property the deterministic builder exists to hold.
     * This value changes only when the packaging rules themselves change.
     *
     * @var    string
     * @since  0.1.0
     */

- `BUILDER_VERSION = '1'`
/**
     * Largest provenance statement expanded during bounded evidence inspection.
     *
     * @var    int
     * @since  0.1.0
     */

- `MAXIMUM_BYTES = 16384`

## Kumwe\Extension\Package\PackageSafetyInspector

/**
 * Produces deterministic archive-safety findings without deciding whether a host admits the package.
 *
 * Every rule is evaluated from the central-directory snapshot before entry contents are expanded. The
 * consuming host owns the decision; SDK author tooling can independently treat the same findings as
 * conformance failures.
 *
 * @since  0.2.0
 */

### findings

/**
     * Inspect a central-directory description and return every objective discrepancy.
     *
     * @param   ArchivePackage  $package  Validated archive entry table.
     * @param   PackageLimits   $limits   Exact resource budget for this inspection.
     *
     * @return  list<PackageFinding>  Findings sorted by code, path and message.
     *
     * @since   0.2.0
     */

```php
public function findings(Kumwe\Extension\Package\ArchivePackage $package, Kumwe\Extension\Package\PackageLimits $limits): array;
```

## Kumwe\Extension\Package\PackageSignature

/**
 * Detached Ed25519 signature offered with an extension package, paired with the key that made it.
 *
 * Both halves are needed to answer the trust question, and each half is answered by a different
 * collaborator: a consuming host decides whether the named key is accepted, while a
 * `PackageSignatureVerifier` checks the bytes against the package
 * digest. Decoding and length validation happen once, here, so no malformed signature ever reaches
 * the cryptographic call.
 *
 * @since  0.1.0
 */

### ed25519

/**
     * Build a signature from the base64 form a package or release feed carries.
     *
     * Decoding is strict, so padding or alphabet errors fail here rather than producing bytes that
     * silently fail verification later.
     *
     * @param   string  $keyId            Signing key ID; lowercase, 3 to 127 characters.
     * @param   string  $base64Signature  Detached signature, base64 encoded.
     *
     * @return  self  The decoded signature bound to its key ID.
     *
     * @throws  InvalidArgumentException  When the key ID is not a stable identifier or the signature is not 64 bytes.
     *
     * @since   0.1.0
     */

```php
public static function ed25519(string $keyId, string $base64Signature): Kumwe\Extension\Package\PackageSignature;
```

### keyId

/**
     * Name the key a verifier must resolve a public key for before checking these bytes.
     *
     * @return  string  Lowercase key ID as the signer wrote it, matched against the trusted key set.
     *
     * @since   0.1.0
     */

```php
public function keyId(): string;
```

### algorithm

/**
     * Name the signature scheme, for storage columns and operator-facing output.
     *
     * @return  string  Always `ed25519`; this type models no other scheme.
     *
     * @since   0.1.0
     */

```php
public function algorithm(): string;
```

### bytes

/**
     * Hand the raw signature to the verification primitive.
     *
     * @return  non-falsy-string  Exactly 64 decoded bytes, in the form Ed25519 verification expects.
     *
     * @since   0.1.0
     */

```php
public function bytes(): string;
```

### asBase64

/**
     * Re-encode the signature for transport or storage in a text field.
     *
     * @return  string  Base64 of the same bytes `ed25519()` decoded.
     *
     * @since   0.1.0
     */

```php
public function asBase64(): string;
```

## Kumwe\Extension\Package\PackageSignatureMessage

/**
 * Canonical domain-separated message signed for an extension package.
 *
 * Signing a bare hexadecimal digest permits accidental cross-protocol verification when the same key is
 * used elsewhere. The fixed domain and algorithm label bind every signature to this package protocol.
 *
 * @since  0.2.0
 */

### forChecksum

/**
     * Render the exact bytes every signer and verifier must use.
     *
     * @param   PackageChecksum  $checksum  Digest of the complete package archive.
     *
     * @return  non-empty-string  Domain-separated ASCII signature message.
     *
     * @since   0.2.0
     */

```php
public static function forChecksum(Kumwe\Extension\Package\PackageChecksum $checksum): string;
```

### Public constants

/**
     * Versioned signature domain, including an unambiguous NUL separator.
     *
     * @var    string
     * @since  0.2.0
     */

- `DOMAIN = 'kumwe-extension-package-signature-v2' . "\0" . 'sha256:'`

## Kumwe\Extension\Package\PackageSignatureVerifier

/**
 * Port answering the purely cryptographic half of the package trust question.
 *
 * It reports whether a detached signature verifies over a package digest under the key the signature
 * names, and nothing else. Whether that key is one a host accepts remains host policy, which is why this
 * port returns a boolean instead of throwing. It suits configured key maps; dynamic trust stores can
 * consume `PublicKeyPackageSignatureVerifier` after selecting a key under their own rules.
 *
 * @since  0.1.0
 */

### verify

/**
     * Check a detached signature over a package digest.
     *
     * @param   PackageChecksum   $checksum   Digest of the package the signature is meant to cover.
     * @param   PackageSignature  $signature  Signature bytes together with the ID of the key that made them.
     *
     * @return  bool  True when the signature verifies; false when it does not or the key is unknown here.
     *
     * @since   0.1.0
     */

```php
public function verify(Kumwe\Extension\Package\PackageChecksum $checksum, Kumwe\Extension\Package\PackageSignature $signature): bool;
```

## Kumwe\Extension\Package\PublicKeyPackageSignatureVerifier

/**
 * Host-neutral cryptographic port for a public key already selected by host trust policy.
 *
 * The host decides whether the key is enabled, unexpired, unrevoked and allowed for the package owner.
 * This port performs only canonical key decoding and signature verification.
 *
 * @since  0.2.0
 */

### verify

/**
     * Verify one detached signature under the supplied Ed25519 public key.
     *
     * @param   string            $base64PublicKey  Canonical base64 32-byte Ed25519 public key.
     * @param   PackageChecksum   $checksum         Digest of the exact package bytes.
     * @param   PackageSignature  $signature        Canonical detached package signature.
     *
     * @return  bool  True only when key and signature verify over the SDK's domain-separated message.
     *
     * @since   0.2.0
     */

```php
public function verify(string $base64PublicKey, Kumwe\Extension\Package\PackageChecksum $checksum, Kumwe\Extension\Package\PackageSignature $signature): bool;
```

## Kumwe\Extension\Package\SodiumEd25519Verifier

/**
 * Verifies package signatures against a fixed, configured map of Ed25519 public keys.
 *
 * Key admission remains host policy. This adapter only selects configured material by the signature's
 * asserted identifier and delegates to the same public-key primitive dynamic trust stores consume.
 *
 * @since  0.2.0
 */

### __construct

/**
     * Validate configured keys and bind the canonical cryptographic primitive.
     *
     * @param   array<mixed>                      $base64PublicKeys  Public keys keyed by stable identifier.
     * @param   PublicKeyPackageSignatureVerifier $verifier          Host-neutral selected-key verifier.
     *
     * @throws  InvalidArgumentException  When an identifier or key encoding is malformed.
     *
     * @since   0.2.0
     */

```php
public function __construct(array $base64PublicKeys, Kumwe\Extension\Package\PublicKeyPackageSignatureVerifier $verifier = \Kumwe\Extension\Package\SodiumPublicKeyPackageSignatureVerifier::__set_state(array(
)));
```

### verify

/**
     * Verify under the configured key named by the signature.
     *
     * @param   PackageChecksum   $checksum   Digest of the exact package bytes.
     * @param   PackageSignature  $signature  Detached signature and configured key identifier.
     *
     * @return  bool  True only when that configured key verifies the domain-separated message.
     *
     * @since   0.2.0
     */

```php
public function verify(Kumwe\Extension\Package\PackageChecksum $checksum, Kumwe\Extension\Package\PackageSignature $signature): bool;
```

## Kumwe\Extension\Package\SodiumPublicKeyPackageSignatureVerifier

/**
 * Libsodium verification for a host-selected Ed25519 public key.
 *
 * @since  0.2.0
 */

### verify

/**
     * Verify a package signature without making a key-trust decision.
     *
     * @param   string            $base64PublicKey  Canonical base64 Ed25519 public key.
     * @param   PackageChecksum   $checksum         Digest of the exact package bytes.
     * @param   PackageSignature  $signature        Detached signature and asserted key identifier.
     *
     * @return  bool  True only for canonical key material and a valid package-protocol signature.
     *
     * @since   0.2.0
     */

```php
public function verify(string $base64PublicKey, Kumwe\Extension\Package\PackageChecksum $checksum, Kumwe\Extension\Package\PackageSignature $signature): bool;
```

## Kumwe\Extension\Package\ZipArchiveContentReader

/**
 * Expands regular ZIP entries from one immutable inspected package snapshot.
 *
 * The archive checksum and central-directory rows are checked again, each stream is bounded by the same
 * per-entry and total limits used during inspection, and no entry is written to disk. A changed archive,
 * under-reported expansion or incomplete read fails before inconsistent bytes are yielded further.
 *
 * @since  0.2.0
 */

### contents

/**
     * Expand regular entries in central-directory order under the snapshot's exact limits.
     *
     * @param   InspectedPackage  $package  Stable package snapshot to read.
     *
     * @return  Generator<string, string>  Complete regular-file bytes keyed by portable package path.
     *
     * @throws  InvalidArgumentException  When safety findings remain or the staged path is unavailable.
     * @throws  RuntimeException  When the archive changes, an entry disagrees with its snapshot, or expansion
     *          exceeds a declared or configured bound.
     *
     * @since   0.2.0
     */

```php
public function contents(Kumwe\Extension\Package\InspectedPackage $package): Generator;
```

## Kumwe\Extension\Package\ZipArchiveReader

/**
 * Reads a bounded ZIP central directory without expanding entry contents.
 *
 * Every path, size, encryption flag and Unix entry type is preserved in the resulting entry table.
 * Malformed directory records are package-data findings, while inaccessible files remain caller or I/O
 * failures. No archive content is written to disk.
 *
 * @since  0.2.0
 */

### inspect

/**
     * Read one ZIP central directory into a validated package description.
     *
     * @param   string         $archiveFile  Canonical staged archive path.
     * @param   PackageLimits  $limits       Exact staged-archive and entry-count limits.
     *
     * @return  ArchivePackage  Complete entry table in central-directory order.
     *
     * @throws  InvalidArgumentException  When the path is not a readable regular file.
     * @throws  InvalidPackage  When ZIP data is malformed, unsupported or exceeds metadata limits.
     * @since   0.2.0
     */

```php
public function inspect(string $archiveFile, Kumwe\Extension\Package\PackageLimits $limits): Kumwe\Extension\Package\ArchivePackage;
```

## Kumwe\Extension\Spi\Application\Automation\JobHandler

/**
 * Idempotent executable bound to one manifest-declared job type.
 *
 * The binding registrar owns the identifier. Implementations therefore expose behavior only and never
 * repeat the signed declaration in executable code.
 *
 * @since  0.2.0
 */

### handle

/**
     * @param  JobContributionDefinition  $definition  Signed job contribution declaration this handler is bound to.
     * @param  array<string, mixed>       $payload     Payload validated against the signed job schema.
     * @param  ExecutionContext           $context     Host-provided execution services scoped to this job run.
     *
     * @since  0.2.0
     */

```php
public function handle(Kumwe\Automation\JobContributionDefinition $definition, array $payload, Kumwe\Extension\Spi\Application\ExecutionContext $context): void;
```

## Kumwe\Extension\Spi\Application\ExecutionContext

/**
 * Host-issued identity and trace context handed to extension code.
 *
 * This interface carries no grant, capability or authorization decision. A host constructs the concrete
 * context only after authenticating the invocation and must not accept an extension-created implementation
 * back across an authorization or record-disclosure boundary.
 *
 * @since  0.2.0
 */

### siteIdentifier

/**
     * Return the site under which the extension callback executes.
     *
     * @return  string  Stable site identifier.
     *
     * @since   0.2.0
     */

```php
public function siteIdentifier(): string;
```

### actorId

/** @return string Stable human or system actor identifier. @since 0.2.0 */

```php
public function actorId(): string;
```

### organizationIdentifier

/** @return ?string Active organization identifier, or null outside an organization scope. @since 0.2.0 */

```php
public function organizationIdentifier(): ?string;
```

### workspaceIdentifier

/** @return ?string Active workspace identifier, or null outside a workspace scope. @since 0.2.0 */

```php
public function workspaceIdentifier(): ?string;
```

### requestId

/** @return string Identifier of this unit of work. @since 0.2.0 */

```php
public function requestId(): string;
```

### correlationId

/** @return string Identifier shared across the active trace. @since 0.2.0 */

```php
public function correlationId(): string;
```

### deliverySurface

/**
     * @return  string  Delivery surface such as administrator, portal, api, mcp, cli or background.
     *
     * @since   0.2.0
     */

```php
public function deliverySurface(): string;
```

## Kumwe\Extension\Spi\Application\ExtensionServiceProvider

/**
 * The single entry point an extension package exposes to Kumwe.
 *
 * A manifest names one class under `provider`, and the host's runtime loader instantiates it
 * with no arguments and rejects the extension outright when it does not implement this interface — so
 * this contract, not file scanning, is how an extension gets to run at all. Registration is the only
 * phase every extension takes part in. Executable implementations are attached to already-validated
 * manifest identifiers through `ExtensionBindingProvider`; code cannot add declarations.
 *
 * @since  0.1.0
 */

### register

/**
     * Compose this extension's services into the container it was given.
     *
     * Runs during the pass over every active provider, while the container is still being filled, so a
     * service another extension registers may not be resolvable yet — resolve collaborators lazily
     * inside the factories registered here, or move behavior-only startup to `BootableExtension::boot()`. The
     * container is restricted to this extension: it exposes only the host services allowlisted to the
     * package plus whatever the package shares itself, and it cannot be retained as a global registry.
     *
     * @param   ExtensionContainer  $container  The extension's own restricted container to share factories on.
     *
     * @return  void
     *
     * @since   0.1.0
     */

```php
public function register(Kumwe\Extension\Spi\Runtime\ExtensionContainer $container): void;
```

## Kumwe\Extension\Spi\Binding\ExecutableBindingKind

/** Closed executable surfaces whose implementations bind to signed manifest identifiers. @since 0.2.0 */

### cases

Generated enum/runtime member.

```php
public static function cases(): array;
```

### from

Generated enum/runtime member.

```php
public static function from(string|int $value): static;
```

### tryFrom

Generated enum/runtime member.

```php
public static function tryFrom(string|int $value): ?static;
```

### Public properties



- `readonly string $name`


- `readonly string $value`

### Public constants

/** Field presentation strategy. @since 0.2.0 */

- `FieldPresenter = \Kumwe\Extension\Spi\Binding\ExecutableBindingKind::FieldPresenter`
/** Money-rate provider from kumwe/conversion. @since 0.2.0 */

- `MoneyRateProvider = \Kumwe\Extension\Spi\Binding\ExecutableBindingKind::MoneyRateProvider`
/** Unit-conversion provider from kumwe/conversion. @since 0.2.0 */

- `UnitConversionProvider = \Kumwe\Extension\Spi\Binding\ExecutableBindingKind::UnitConversionProvider`
/** Custom business view handler. @since 0.2.0 */

- `CustomBusinessViewHandler = \Kumwe\Extension\Spi\Binding\ExecutableBindingKind::CustomBusinessViewHandler`
/** Custom business action handler. @since 0.2.0 */

- `CustomBusinessActionHandler = \Kumwe\Extension\Spi\Binding\ExecutableBindingKind::CustomBusinessActionHandler`
/** Administrator route handler factory. @since 0.2.0 */

- `AdministratorRoute = \Kumwe\Extension\Spi\Binding\ExecutableBindingKind::AdministratorRoute`
/** Portal route handler factory. @since 0.2.0 */

- `PortalRoute = \Kumwe\Extension\Spi\Binding\ExecutableBindingKind::PortalRoute`
/** Synchronous domain listener. @since 0.2.0 */

- `DomainListener = \Kumwe\Extension\Spi\Binding\ExecutableBindingKind::DomainListener`
/** Durable event consumer. @since 0.2.0 */

- `EventConsumer = \Kumwe\Extension\Spi\Binding\ExecutableBindingKind::EventConsumer`
/** Durable job handler. @since 0.2.0 */

- `JobHandler = \Kumwe\Extension\Spi\Binding\ExecutableBindingKind::JobHandler`
/** Deterministic projection builder. @since 0.2.0 */

- `Projection = \Kumwe\Extension\Spi\Binding\ExecutableBindingKind::Projection`
/** Outbound webhook transport. @since 0.2.0 */

- `Webhook = \Kumwe\Extension\Spi\Binding\ExecutableBindingKind::Webhook`
/** Studio preview block renderer. @since 0.2.0 */

- `StudioPreviewRenderer = \Kumwe\Extension\Spi\Binding\ExecutableBindingKind::StudioPreviewRenderer`

## Kumwe\Extension\Spi\Binding\ExecutableBindingRequirements

/** Exact executable binding inventory derived from one validated signed manifest. @since 0.2.0 */

### fromManifestContributions

/**
     * Build requirements from one canonical manifest contribution set.
     *
     * The contribution set has a private constructor and can only come from the SDK manifest parser, so
     * callers cannot manufacture executable authority by supplying a raw array to this factory.
     *
     * @param   ManifestContributions  $contributions  Canonical contribution set.
     *
     * @return  self  Exact executable inventory.
     *
     * @since   0.2.0
     */

```php
public static function fromManifestContributions(Kumwe\Extension\Manifest\ManifestContributions $contributions): Kumwe\Extension\Spi\Binding\ExecutableBindingRequirements;
```

### identifiers

/**
     * Return required identifiers for one executable kind.
     *
     * @param   ExecutableBindingKind  $kind  Executable surface.
     *
     * @return  list<string>  Sorted required identifiers.
     *
     * @since   0.2.0
     */

```php
public function identifiers(Kumwe\Extension\Spi\Binding\ExecutableBindingKind $kind): array;
```

### assertDeclared

/**
     * Refuse an undeclared or wrong-kind binding before implementation storage.
     *
     * @param  ExecutableBindingKind  $kind        Attempted executable surface.
     * @param  string                 $identifier  Attempted signed identifier.
     *
     * @since  0.2.0
     */

```php
public function assertDeclared(Kumwe\Extension\Spi\Binding\ExecutableBindingKind $kind, string $identifier): void;
```

### assertSatisfied

/**
     * Require exactly one binding for every signed executable identifier and no others.
     *
     * @param  array<string, list<string>>  $bound  Recorded identifiers keyed by kind value.
     *
     * @since  0.2.0
     */

```php
public function assertSatisfied(array $bound): void;
```

### toArray

/** @return array<string, list<string>> Canonical binding inventory. @since 0.2.0 */

```php
public function toArray(): array;
```

## Kumwe\Extension\Spi\Binding\ExtensionBindingProvider

/**
 * Optional provider phase that binds executable code to signed manifest declaration identifiers.
 *
 * The manifest is the only declarative source. A binding provider cannot add or rewrite a declaration;
 * it can only attach executable implementations to identifiers the host already validated.
 *
 * @since  0.2.0
 */

### bind

/**
     * Bind executable implementations after extension-owned services have been registered.
     *
     * @param   ExtensionBindingRegistrar  $bindings   Owner-bound binding sink.
     * @param   ExtensionContainer         $container  Owner-scoped service surface.
     *
     * @return  void
     *
     * @since   0.2.0
     */

```php
public function bind(Kumwe\Extension\Spi\Binding\ExtensionBindingRegistrar $bindings, Kumwe\Extension\Spi\Runtime\ExtensionContainer $container): void;
```

## Kumwe\Extension\Spi\Binding\ExtensionBindingRegistrar

/**
 * Owner-bound sink for executable implementations referenced by a signed manifest.
 *
 * Every identifier must already exist in the active manifest. Implementations fail a binding when the
 * identifier is undeclared, belongs to another extension, is bound twice, or names the wrong surface.
 *
 * @since  0.2.0
 */

### fieldPresenter

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

```php
public function fieldPresenter(string $fieldType, Kumwe\BusinessSurface\Contract\Presentation\Field\FieldPresenter $presenter): void;
```

### moneyRateProvider

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

```php
public function moneyRateProvider(string $identifier, Kumwe\Conversion\Provider\MoneyRateProvider $provider): void;
```

### unitConversionProvider

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

```php
public function unitConversionProvider(string $identifier, Kumwe\Conversion\Provider\UnitConversionProvider $provider): void;
```

### customBusinessViewHandler

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

```php
public function customBusinessViewHandler(string $identifier, Kumwe\BusinessSurface\Contract\Application\Custom\CustomBusinessViewHandler $handler): void;
```

### customBusinessActionHandler

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

```php
public function customBusinessActionHandler(string $identifier, Kumwe\BusinessSurface\Contract\Application\Custom\CustomBusinessActionHandler $handler): void;
```

### administratorRoute

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

```php
public function administratorRoute(string $name, Kumwe\Extension\Spi\Binding\Http\AdministratorRouteHandlerFactory $factory): void;
```

### portalRoute

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

```php
public function portalRoute(string $name, Kumwe\Extension\Spi\Binding\Http\PortalRouteHandlerFactory $factory): void;
```

### domainListener

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

```php
public function domainListener(string $identifier, Kumwe\Extension\Spi\BusinessIntegration\Application\DomainEventHandler $handler): void;
```

### eventConsumer

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

```php
public function eventConsumer(string $identifier, Kumwe\Extension\Spi\BusinessIntegration\Application\IntegrationEventHandler $handler): void;
```

### jobHandler

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

```php
public function jobHandler(string $identifier, Kumwe\Extension\Spi\Application\Automation\JobHandler $handler): void;
```

### projection

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

```php
public function projection(string $identifier, Kumwe\Reporting\Contract\ProjectionBuilder $builder): void;
```

### webhook

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

```php
public function webhook(string $identifier, Kumwe\Extension\Spi\BusinessIntegration\Application\IntegrationEventTransport $transport): void;
```

### studioPreviewRenderer

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

```php
public function studioPreviewRenderer(string $identifier, Kumwe\Extension\Spi\Studio\Application\Preview\StudioPreviewBlockRenderer $renderer): void;
```

## Kumwe\Extension\Spi\Binding\Http\AdministratorRouteHandlerFactory

/**
 * Creates an administrator route handler against the bounded renderer port.
 *
 * @since  0.2.0
 */

### create

/**
     * @param   AdministratorRouteRenderer  $renderer  Host-bound renderer closing over the validated route and view.
     *
     * @return  RequestHandlerInterface  Executable handler for the declared administrator route.
     *
     * @since   0.2.0
     */

```php
public function create(Kumwe\Extension\Spi\Binding\Http\AdministratorRouteRenderer $renderer): Psr\Http\Server\RequestHandlerInterface;
```

## Kumwe\Extension\Spi\Binding\Http\AdministratorRouteRenderer

/**
 * Object-capability renderer bound by the host to one validated administrator route and view.
 *
 * A host creates a distinct instance per signed route declaration. The instance closes over owner, view,
 * active navigation and shell policy; extension code can provide only the model and active host-issued request.
 *
 * @since  0.2.0
 */

### render

/**
     * @param   array<string, mixed>    $model    Template model.
     * @param   ServerRequestInterface  $request  Active request carrying host-validated session state.
     *
     * @return  string  Complete rendered HTML.
     *
     * @since   0.2.0
     */

```php
public function render(array $model, Psr\Http\Message\ServerRequestInterface $request): string;
```

## Kumwe\Extension\Spi\Binding\Http\PortalRouteHandlerFactory

/**
 * Creates a portal route handler against the bounded renderer port.
 *
 * @since  0.2.0
 */

### create

/** @param PortalRouteRenderer $renderer Bounded renderer port the produced handler delegates portal page rendering to. @since 0.2.0 */

```php
public function create(Kumwe\Extension\Spi\Binding\Http\PortalRouteRenderer $renderer): Psr\Http\Server\RequestHandlerInterface;
```

## Kumwe\Extension\Spi\Binding\Http\PortalRouteRenderer

/**
 * Object-capability renderer bound by the host to one validated portal route and template.
 *
 * A host creates a distinct instance per signed route declaration. The instance closes over owner,
 * template and portal-session policy; extension code can provide only the model and host-issued request.
 *
 * @since  0.2.0
 */

### render

/**
     * @param   array<string, mixed>    $model    Template model.
     * @param   ServerRequestInterface  $request  Active request carrying host-validated portal state.
     *
     * @return  string  Complete rendered HTML.
     *
     * @since   0.2.0
     */

```php
public function render(array $model, Psr\Http\Message\ServerRequestInterface $request): string;
```

## Kumwe\Extension\Spi\BusinessIntegration\Application\DomainEventHandler

/** Executable bound to one manifest-declared synchronous listener. @since 0.2.0 */

### handle

/**
     * Observe one synchronous event inside the authoritative transaction; throwing aborts the mutation.
     *
     * @param   DomainListenerDefinition  $definition  Manifest-declared listener contract this handler is bound to.
     * @param   DomainEvent               $event       Transaction-local event being delivered synchronously.
     *
     * @return  void
     *
     * @since   0.2.0
     */

```php
public function handle(Kumwe\Integration\DomainListenerDefinition $definition, Kumwe\Integration\DomainEvent $event): void;
```

## Kumwe\Extension\Spi\BusinessIntegration\Application\IntegrationEventHandler

/** Idempotent executable bound to one manifest-declared durable consumer. @since 0.2.0 */

### handle

/**
     * Consume one durable event delivery, tolerating at-least-once redelivery.
     *
     * @param   EventConsumerDefinition  $definition  Manifest-declared consumer contract this handler is bound to.
     * @param   IntegrationEvent         $event       Immutable versioned event being delivered.
     * @param   ExecutionContext         $context     Host-issued execution capabilities for this delivery.
     *
     * @return  void
     *
     * @since   0.2.0
     */

```php
public function handle(Kumwe\Integration\EventConsumerDefinition $definition, Kumwe\Integration\IntegrationEvent $event, Kumwe\Extension\Spi\Application\ExecutionContext $context): void;
```

## Kumwe\Extension\Spi\BusinessIntegration\Application\IntegrationEventTransport

/** Outbound executable bound to one manifest-declared webhook adapter. @since 0.2.0 */

### publish

/**
     * Publish one durable event delivery, tolerating at-least-once redelivery.
     *
     * @param   WebhookContributionDefinition  $definition  Outbound-adapter contract this transport is bound to.
     * @param   IntegrationEvent               $event       Durable event being routed through the adapter.
     *
     * @return  void
     *
     * @since   0.2.0
     */

```php
public function publish(Kumwe\Integration\WebhookContributionDefinition $definition, Kumwe\Integration\IntegrationEvent $event): void;
```

## Kumwe\Extension\Spi\BusinessRecord\Application\BusinessRecordPage

/** Policy-admitted bounded page returned by the host business-record reader. @since 0.2.0 */

### records

/** @return list<BusinessRecordView> @since 0.2.0 */

```php
public function records(): array;
```

### nextCursor

/** @since 0.2.0 */

```php
public function nextCursor(): ?Kumwe\Record\Query\RecordCursor;
```

### aggregates

/** @return array<string, int|string|null> @since 0.2.0 */

```php
public function aggregates(): array;
```

## Kumwe\Extension\Spi\BusinessRecord\Application\BusinessRecordQueryPurpose

/**
 * Security purpose under which a record collection is evaluated and disclosed.
 *
 * @since  0.2.0
 */

### cases

Generated enum/runtime member.

```php
public static function cases(): array;
```

### from

Generated enum/runtime member.

```php
public static function from(string|int $value): static;
```

### tryFrom

Generated enum/runtime member.

```php
public static function tryFrom(string|int $value): ?static;
```

### Public properties



- `readonly string $name`


- `readonly string $value`

### Public constants

/** Ordinary interactive collection browsing. @since 0.2.0 */

- `Browse = \Kumwe\Extension\Spi\BusinessRecord\Application\BusinessRecordQueryPurpose::Browse`
/** Reporting, including grouped or aggregate output. @since 0.2.0 */

- `Report = \Kumwe\Extension\Spi\BusinessRecord\Application\BusinessRecordQueryPurpose::Report`
/** Export disclosure intended to leave the interactive surface. @since 0.2.0 */

- `Export = \Kumwe\Extension\Spi\BusinessRecord\Application\BusinessRecordQueryPurpose::Export`

## Kumwe\Extension\Spi\BusinessRecord\Application\BusinessRecordReadRequest

/** Immutable request for one host-authorized page of policy-admitted business records. @since 0.2.0 */

### __construct

/**
     * @param  ExecutionContext            $context                 Authenticated site context supplied by the host.
     * @param  string                      $definitionIdentifier    Published definition UUID or multi-segment handle.
     * @param  RecordQuerySpecification    $specification           Bounded query grammar shared by every delivery path.
     * @param ?string $organizationIdentifier Organization scope, when the definition requires one.
     * @param  BusinessRecordQueryPurpose  $purpose                 Security purpose of this disclosure.
     *
     * @since  0.2.0
     */

```php
public function __construct(Kumwe\Extension\Spi\Application\ExecutionContext $context, string $definitionIdentifier, Kumwe\Record\Query\RecordQuerySpecification $specification, ?string $organizationIdentifier = NULL, Kumwe\Extension\Spi\BusinessRecord\Application\BusinessRecordQueryPurpose $purpose = \Kumwe\Extension\Spi\BusinessRecord\Application\BusinessRecordQueryPurpose::Browse);
```

### Public properties



- `readonly Kumwe\Extension\Spi\Application\ExecutionContext $context`


- `readonly string $definitionIdentifier`


- `readonly Kumwe\Record\Query\RecordQuerySpecification $specification`


- `readonly ?string $organizationIdentifier`


- `readonly Kumwe\Extension\Spi\BusinessRecord\Application\BusinessRecordQueryPurpose $purpose`

## Kumwe\Extension\Spi\BusinessRecord\Application\BusinessRecordReader

/**
 * Host policy boundary through which extension code may browse business records.
 *
 * The host resolves the signed definition, applies capability, scope, row and field policy, and returns
 * only disclosure-safe views. Implementations must never expose persistence records through this port and
 * must verify that the request carries the concrete host-issued execution context for the active invocation;
 * implementing the public interface is not proof of authority.
 *
 * @since  0.2.0
 */

### readPage

/** @param BusinessRecordReadRequest $query Host-authorized request naming the definition, scope and page to disclose. @since 0.2.0 */

```php
public function readPage(Kumwe\Extension\Spi\BusinessRecord\Application\BusinessRecordReadRequest $query): Kumwe\Extension\Spi\BusinessRecord\Application\BusinessRecordPage;
```

## Kumwe\Extension\Spi\BusinessRecord\Application\BusinessRecordView

/** Read-only disclosure-safe projection of one business record. @since 0.2.0 */

### definitionIdentifier

/** @since 0.2.0 */

```php
public function definitionIdentifier(): string;
```

### definitionVersion

/** @since 0.2.0 */

```php
public function definitionVersion(): int;
```

### recordIdentifier

/** @since 0.2.0 */

```php
public function recordIdentifier(): string;
```

### version

/** @since 0.2.0 */

```php
public function version(): int;
```

### siteIdentifier

/** @since 0.2.0 */

```php
public function siteIdentifier(): ?string;
```

### organizationIdentifier

/** @since 0.2.0 */

```php
public function organizationIdentifier(): ?string;
```

### workflowState

/** @since 0.2.0 */

```php
public function workflowState(): ?string;
```

### values

/** @return array<string, mixed> Policy-admitted field values only. @since 0.2.0 */

```php
public function values(): array;
```

### updatedAt

/** @since 0.2.0 */

```php
public function updatedAt(): DateTimeImmutable;
```

## Kumwe\Extension\Spi\Contribution\CanonicalCompositionDocument

/**
 * One exact canonical Studio contribution document from a schema-six manifest.
 *
 * Producer owns canonical JSON and the pinned Studio schema corpus. This SDK value owns the package
 * declaration: it preserves the signed bytes, decoded document and kind-scoped identity consumed by a
 * host, after requiring the exact Producer schema to accept them.
 *
 * @since 0.2.0
 */

### __construct

/**
     * @param CanonicalCompositionKind $kind Declared Studio contribution kind.
     * @param string $canonical Exact canonical JSON bytes carried by the signed manifest.
     *
     * @throws InvalidArgumentException When bytes are invalid, non-canonical, over budget, have the
     *         wrong root or kind, lack an identity, or fail Producer's exact Studio document schema.
     *
     * @since 0.2.0
     */

```php
public function __construct(Kumwe\Extension\Spi\Contribution\CanonicalCompositionKind $kind, string $canonical);
```

### identifier

/** Kind-scoped identity used by manifest lookups and host bindings. @since 0.2.0 */

```php
public function identifier(): string;
```

### identity

/** Identity value stored inside the canonical document. @since 0.2.0 */

```php
public function identity(): string;
```

### document

/**
     * Decode an isolated view of the signed canonical bytes.
     *
     * A fresh object graph prevents one consumer from mutating the value observed by another or
     * making decoded state disagree with `canonical` and `identity()`.
     *
     * @return stdClass Decoded canonical document with JSON objects preserved as objects.
     *
     * @throws LogicException When previously validated canonical bytes cannot be decoded.
     *
     * @since 0.2.0
     */

```php
public function document(): stdClass;
```

### toArray

/**
     * @return array{kind: string, canonical: string} Exact manifest declaration.
     *
     * @since 0.2.0
     */

```php
public function toArray(): array;
```

### Public properties



- `readonly Kumwe\Extension\Spi\Contribution\CanonicalCompositionKind $kind`


- `readonly string $canonical`

### Public constants

/** Largest canonical document admitted by the published contribution profile. @since 0.2.0 */

- `MAXIMUM_CANONICAL_BYTES = 262144`

## Kumwe\Extension\Spi\Contribution\CanonicalCompositionKind

/**
 * The canonical Studio contribution kinds a manifest schema 6 package may declare.
 *
 * Each case names one published `@kumwe/studio-protocol` document schema, vendored at the exact
 * pinned release owned by `kumwe/producer`. Schema 6 carries these canonical documents while the
 * frozen schema-5 vocabulary stays exactly as released beside them.
 *
 * @since  0.1.0
 */

### identityMember

/**
     * The member that carries a document's identity within this kind.
     *
     * @return  string  `type` for a block definition, `id` for every other kind, as the pinned
     *          schemas declare.
     *
     * @since   0.1.0
     */

```php
public function identityMember(): string;
```

### cases

Generated enum/runtime member.

```php
public static function cases(): array;
```

### from

Generated enum/runtime member.

```php
public static function from(string|int $value): static;
```

### tryFrom

Generated enum/runtime member.

```php
public static function tryFrom(string|int $value): ?static;
```

### Public properties



- `readonly string $name`


- `readonly string $value`

### Public constants

/**
     * A placeable block with its complete canonical `propertySchema` document.
     *
     * @since  0.1.0
     */

- `BlockDefinition = \Kumwe\Extension\Spi\Contribution\CanonicalCompositionKind::BlockDefinition`
/**
     * A reusable structure arranged from declared blocks.
     *
     * @since  0.1.0
     */

- `Pattern = \Kumwe\Extension\Spi\Contribution\CanonicalCompositionKind::Pattern`
/**
     * An editing control adapting one field kind to the authoring surface.
     *
     * @since  0.1.0
     */

- `FieldAdapter = \Kumwe\Extension\Spi\Contribution\CanonicalCompositionKind::FieldAdapter`
/**
     * An inspector panel opened for declared block types.
     *
     * @since  0.1.0
     */

- `Inspector = \Kumwe\Extension\Spi\Contribution\CanonicalCompositionKind::Inspector`
/**
     * A design vocabulary of tokens, recipes and size roles a theme remaps.
     *
     * @since  0.1.0
     */

- `DesignVocabulary = \Kumwe\Extension\Spi\Contribution\CanonicalCompositionKind::DesignVocabulary`
/**
     * A document migration stepping declared artifacts between revisions.
     *
     * @since  0.1.0
     */

- `Migration = \Kumwe\Extension\Spi\Contribution\CanonicalCompositionKind::Migration`

## Kumwe\Extension\Spi\Contribution\CompositionBlockDeclaration

/**
 * What a package declares before any of its blocks may appear in a composed document.
 *
 * A block is the unit an author places on a canvas: a bounded property schema for what its instances
 * carry, the named slots other blocks may be nested into, and the renderer binding the Gate B surface
 * resolves when the document is rendered. All three are declared here, in the signed manifest, because
 * a composition document outlives the code that produced it — the contract has to be inspectable before
 * install and stable afterwards, exactly as decision D16 requires. Nothing here renders or stores: the
 * declaration is inert until the composition surface ships, and an extension declaring one today
 * installs unchanged when it does.
 *
 * The renderer binding is an owner-namespaced reference rather than a class name or a template path,
 * so the declaration promises nothing about implementation shape; a binding that names nothing is
 * refused at admission because an unresolvable block would otherwise surface as a runtime hole.
 *
 * @since  0.1.0
 */

### __construct

/**
     * Declare one block, its bounded properties, its slots, and its renderer binding.
     *
     * @param   string                     $blockId     Namespaced identifier inside the declaring
     *          package's namespace.
     * @param   CompositionPropertySchema  $properties  Bounded property schema its instances carry.
     * @param   list<string>               $slots       Named positions other blocks may be nested into.
     * @param   string                     $renderer    Owner-namespaced renderer binding resolved at Gate B.
     * @param   int                        $version     Declared block revision, one or higher.
     *
     * @throws  InvalidArgumentException  When the identifier or renderer is not namespaced, a slot name is
     *          malformed or repeated, the slot list is over its bound, or the version is not positive.
     *
     * @since   0.1.0
     */

```php
public function __construct(string $blockId, Kumwe\Extension\Spi\Contribution\CompositionPropertySchema $properties, array $slots, string $renderer, int $version = 1);
```

### identifier

/**
     * The identifier this block is registered, referenced, and migrated under.
     *
     * @return  string  Namespaced block identity, inside the declaring package's namespace.
     *
     * @since   0.1.0
     */

```php
public function identifier(): string;
```

### renderer

/**
     * The owner-namespaced renderer binding this block resolves through when the surface ships.
     *
     * @return  string  Namespaced renderer reference; never a class name or a template path.
     *
     * @since   0.1.0
     */

```php
public function renderer(): string;
```

### version

/**
     * The declared revision of this block's schema, which its migrations step between.
     *
     * @return  int  One or higher; a migration may never target a version past this.
     *
     * @since   0.1.0
     */

```php
public function version(): int;
```

### toArray

/**
     * Serialize the declaration for the signed manifest, the runtime publication, and inventory.
     *
     * @return  array{
     *              block_id: string,
     *              properties: array<string, array<string, mixed>>,
     *              slots: list<string>,
     *              renderer: string,
     *              version: int
     *          }  Canonical declaration.
     *
     * @since   0.1.0
     */

```php
public function toArray(): array;
```

### fromArray

/**
     * Reconstitute the declaration from validated manifest data.
     *
     * @param   array<string, mixed>  $data  Declaration as `toArray()` produced it.
     *
     * @return  self  Validated composition block declaration.
     *
     * @throws  InvalidArgumentException  When a member is missing, extra, or mistyped, or the embedded
     *          property schema fails the published profile.
     *
     * @since   0.1.0
     */

```php
public static function fromArray(array $data): Kumwe\Extension\Spi\Contribution\CompositionBlockDeclaration;
```

### Public properties

/**
     * Declared slot names, sorted so two orderings declare the same block.
     *
     * @var    list<string>
     * @since  0.1.0
     */

- `readonly array $slots`


- `readonly Kumwe\Extension\Spi\Contribution\CompositionPropertySchema $properties`

### Public constants

/**
     * Slots one block may declare, which bounds nesting an author can reach from one placement.
     *
     * @var    int
     * @since  0.1.0
     */

- `MAXIMUM_SLOTS = 16`

## Kumwe\Extension\Spi\Contribution\CompositionDesignVocabularyDeclaration

/**
 * The design vocabulary a package declares: its tokens, its recipes, and the size roles a theme remaps.
 *
 * A block never carries a colour, a font or a pixel width; it names an entry from a declared vocabulary,
 * and the active theme decides what that name resolves to. Tokens are the atomic names, recipes are
 * named combinations of them, and size roles are the dimensional slots — the roles a theme remaps when
 * it scales a layout — so declaring them is what lets a contributed block be restyled by a theme the
 * package has never met. The names are scoped by the vocabulary's own namespaced identifier, and each
 * list is bounded, deduplicated by refusal and sorted, so the manifest is a closed readable claim.
 *
 * An empty vocabulary is refused: a declaration that names nothing would occupy an identifier while
 * promising nothing a theme could remap.
 *
 * @since  0.1.0
 */

### __construct

/**
     * Declare one vocabulary: its tokens, its recipes, and its size roles.
     *
     * @param   string        $vocabularyId  Namespaced identifier inside the declaring package's namespace.
     * @param   list<string>  $tokens        Atomic design token names scoped to this vocabulary.
     * @param   list<string>  $recipes       Named combinations of tokens scoped to this vocabulary.
     * @param   list<string>  $sizeRoles     Dimensional role names a theme remaps.
     * @param   int           $version       Declared vocabulary revision, one or higher.
     *
     * @throws  InvalidArgumentException  When the identifier is not namespaced, every list is empty, a list
     *          is over its bound, a name is malformed or repeated, or the version is not positive.
     *
     * @since   0.1.0
     */

```php
public function __construct(string $vocabularyId, array $tokens, array $recipes, array $sizeRoles, int $version = 1);
```

### identifier

/**
     * The identifier this vocabulary is registered and its names are scoped under.
     *
     * @return  string  Namespaced vocabulary identity, inside the declaring package's namespace.
     *
     * @since   0.1.0
     */

```php
public function identifier(): string;
```

### version

/**
     * The declared revision of this vocabulary.
     *
     * @return  int  One or higher.
     *
     * @since   0.1.0
     */

```php
public function version(): int;
```

### toArray

/**
     * Serialize the declaration for the signed manifest, the runtime publication, and inventory.
     *
     * @return  array{
     *              vocabulary_id: string,
     *              tokens: list<string>,
     *              recipes: list<string>,
     *              size_roles: list<string>,
     *              version: int
     *          }  Canonical declaration.
     *
     * @since   0.1.0
     */

```php
public function toArray(): array;
```

### fromArray

/**
     * Reconstitute the declaration from validated manifest data.
     *
     * @param   array<string, mixed>  $data  Declaration as `toArray()` produced it.
     *
     * @return  self  Validated design vocabulary declaration.
     *
     * @throws  InvalidArgumentException  When a member is missing, extra, or mistyped.
     *
     * @since   0.1.0
     */

```php
public static function fromArray(array $data): Kumwe\Extension\Spi\Contribution\CompositionDesignVocabularyDeclaration;
```

### Public properties

/**
     * Declared atomic design token names, sorted.
     *
     * @var    list<string>
     * @since  0.1.0
     */

- `readonly array $tokens`
/**
     * Declared recipe names, sorted.
     *
     * @var    list<string>
     * @since  0.1.0
     */

- `readonly array $recipes`
/**
     * Declared size role names a theme remaps, sorted.
     *
     * @var    list<string>
     * @since  0.1.0
     */

- `readonly array $sizeRoles`

### Public constants

/**
     * Tokens one vocabulary may declare.
     *
     * @var    int
     * @since  0.1.0
     */

- `MAXIMUM_TOKENS = 64`
/**
     * Recipes one vocabulary may declare.
     *
     * @var    int
     * @since  0.1.0
     */

- `MAXIMUM_RECIPES = 32`
/**
     * Size roles one vocabulary may declare.
     *
     * @var    int
     * @since  0.1.0
     */

- `MAXIMUM_SIZE_ROLES = 16`

## Kumwe\Extension\Spi\Contribution\CompositionFieldControlDeclaration

/**
 * An editing control a package declares for one property type of the published composition profile.
 *
 * When the Gate B authoring surface opens a block for editing, every property is edited through a
 * control; the profile ships defaults, and a package may declare a richer one — a palette for a choice,
 * a slider for a number — under its own identity. The declaration binds the control to exactly one
 * property type from the closed vocabulary, so what it claims to edit is a checkable fact rather than a
 * runtime discovery, and a control for a type the profile does not publish is refused at admission.
 *
 * Nothing executable is declared: the control's implementation is an authoring-surface concern that
 * arrives with Gate B, and this declaration is what lets that arrival change nothing for a package
 * published today.
 *
 * @since  0.1.0
 */

### __construct

/**
     * Declare one field control and the property type it edits.
     *
     * @param   string                   $controlId  Namespaced identifier inside the declaring
     *          package's namespace.
     * @param   CompositionPropertyType  $edits      Published property type this control edits.
     * @param   int                      $version    Declared control revision, one or higher.
     *
     * @throws  InvalidArgumentException  When the identifier is not namespaced or the version is not positive.
     *
     * @since   0.1.0
     */

```php
public function __construct(string $controlId, Kumwe\Extension\Spi\Contribution\CompositionPropertyType $edits, int $version = 1);
```

### identifier

/**
     * The identifier this control is registered and resolved under.
     *
     * @return  string  Namespaced control identity, inside the declaring package's namespace.
     *
     * @since   0.1.0
     */

```php
public function identifier(): string;
```

### version

/**
     * The declared revision of this control.
     *
     * @return  int  One or higher.
     *
     * @since   0.1.0
     */

```php
public function version(): int;
```

### toArray

/**
     * Serialize the declaration for the signed manifest, the runtime publication, and inventory.
     *
     * @return  array{control_id: string, edits: string, version: int}  Canonical declaration.
     *
     * @since   0.1.0
     */

```php
public function toArray(): array;
```

### fromArray

/**
     * Reconstitute the declaration from validated manifest data.
     *
     * @param   array<string, mixed>  $data  Declaration as `toArray()` produced it.
     *
     * @return  self  Validated field control declaration.
     *
     * @throws  InvalidArgumentException  When a member is missing, extra, or mistyped, or the edited type is
     *          outside the published vocabulary.
     *
     * @since   0.1.0
     */

```php
public static function fromArray(array $data): Kumwe\Extension\Spi\Contribution\CompositionFieldControlDeclaration;
```

### Public properties



- `readonly Kumwe\Extension\Spi\Contribution\CompositionPropertyType $edits`

## Kumwe\Extension\Spi\Contribution\CompositionHostBinding

/**
 * The bounded host metadata binding one canonical composition document into this application.
 *
 * A canonical document is portable Studio JSON and never carries host-specific data: renderer
 * bindings, authority and host references live here instead, never as a proprietary JSON Schema
 * keyword inside the document. A binding names the document it
 * belongs to by kind and identity, the owner-namespaced renderer the Gate B surface resolves for a
 * block, and optionally the declared capability an authoring surface must hold before offering it.
 *
 * @since  0.1.0
 */

### __construct

/**
     * Bind one declared canonical document to its host-side metadata.
     *
     * @param   CanonicalCompositionKind  $kind        Kind of the document this binding belongs to.
     * @param   string                    $documentId  Identity of the document within that kind.
     * @param   string|null               $renderer    Owner-namespaced renderer binding; required for
     *          a block definition, absent for kinds the host does not render directly.
     * @param   string|null               $capability  Declared capability an authoring surface must
     *          hold before offering this contribution, or null for no additional gate.
     *
     * @throws  InvalidArgumentException  When the document identity is empty or a named member is an
     *          empty string.
     *
     * @since   0.1.0
     */

```php
public function __construct(Kumwe\Extension\Spi\Contribution\CanonicalCompositionKind $kind, string $documentId, ?string $renderer = NULL, ?string $capability = NULL);
```

### identifier

/**
     * The binding's identity: one binding per document, addressed by kind and document identity.
     *
     * @return  string  The kind value and document identity joined by one space.
     *
     * @since   0.1.0
     */

```php
public function identifier(): string;
```

### toArray

/**
     * Export the comparable structure reconciliation and the inventory use.
     *
     * @return  array<string, mixed>  Every declared member of this binding.
     *
     * @since   0.1.0
     */

```php
public function toArray(): array;
```

### Public properties



- `readonly Kumwe\Extension\Spi\Contribution\CanonicalCompositionKind $kind`


- `readonly string $documentId`


- `readonly ?string $renderer`


- `readonly ?string $capability`

## Kumwe\Extension\Spi\Contribution\CompositionInspectorDeclaration

/**
 * An inspector a package declares for one of its own composition blocks.
 *
 * The inspector is the panel the Gate B authoring surface opens beside a selected block: the place its
 * properties are edited as one arrangement rather than field by field. The profile derives a default
 * inspector from the block's bounded property schema, and a package may declare a purpose-built one for
 * a block it owns. Binding is by declared block identifier, checked against the same manifest, so an
 * inspector for a block the package does not declare is refused at admission rather than discovered as
 * an orphan when the surface ships.
 *
 * Nothing executable is declared; the panel's implementation is a Gate B authoring-surface concern.
 *
 * @since  0.1.0
 */

### __construct

/**
     * Declare one inspector and the owned block it inspects.
     *
     * @param   string  $inspectorId  Namespaced identifier inside the declaring package's namespace.
     * @param   string  $block        Namespaced identifier of the declared block this inspector opens for.
     * @param   int     $version      Declared inspector revision, one or higher.
     *
     * @throws  InvalidArgumentException  When either identifier is not namespaced or the version is
     *          not positive.
     *
     * @since   0.1.0
     */

```php
public function __construct(string $inspectorId, string $block, int $version = 1);
```

### identifier

/**
     * The identifier this inspector is registered and resolved under.
     *
     * @return  string  Namespaced inspector identity, inside the declaring package's namespace.
     *
     * @since   0.1.0
     */

```php
public function identifier(): string;
```

### block

/**
     * The declared block this inspector opens for.
     *
     * @return  string  Namespaced block identifier, declared in the same manifest.
     *
     * @since   0.1.0
     */

```php
public function block(): string;
```

### version

/**
     * The declared revision of this inspector.
     *
     * @return  int  One or higher.
     *
     * @since   0.1.0
     */

```php
public function version(): int;
```

### toArray

/**
     * Serialize the declaration for the signed manifest, the runtime publication, and inventory.
     *
     * @return  array{inspector_id: string, block: string, version: int}  Canonical declaration.
     *
     * @since   0.1.0
     */

```php
public function toArray(): array;
```

### fromArray

/**
     * Reconstitute the declaration from validated manifest data.
     *
     * @param   array<string, mixed>  $data  Declaration as `toArray()` produced it.
     *
     * @return  self  Validated inspector declaration.
     *
     * @throws  InvalidArgumentException  When a member is missing, extra, or mistyped.
     *
     * @since   0.1.0
     */

```php
public static function fromArray(array $data): Kumwe\Extension\Spi\Contribution\CompositionInspectorDeclaration;
```

## Kumwe\Extension\Spi\Contribution\CompositionMigrationDeclaration

/**
 * A migration a package declares for documents one of its composition blocks appears in.
 *
 * A composition document outlives the block revision it was authored against, so a block that changes
 * shape owes the documents that use it a declared path forward. The migration names the owned block, the
 * revision it steps from and the higher revision it steps to, and a bounded list of operations from a
 * closed vocabulary — rename a property, remove one, or clear one back to absent. The vocabulary is
 * closed for the same reason a property schema is bounded: a migration is data a signed manifest
 * carries, never code, so what it can do to a stored document is fixed at admission rather than
 * discovered at upgrade.
 *
 * Nothing is executed at Gate A. The Gate B runtime replays these declarations over stored documents;
 * declaring them now is what lets a block evolve without stranding the documents it already appears in.
 *
 * @since  0.1.0
 */

### __construct

/**
     * Declare one migration: the owned block, the revisions it steps between, and its operations.
     *
     * @param   string                       $migrationId  Namespaced identifier inside the declaring
     *          package's namespace.
     * @param   string                       $block        Namespaced identifier of the declared block
     *          whose documents it migrates.
     * @param   int                          $fromVersion  Block revision the migration steps from.
     * @param   int                          $toVersion    Higher block revision it steps to.
     * @param   list<array<string, string>>  $operations   Ordered operations from the closed vocabulary.
     *
     * @throws  InvalidArgumentException  When an identifier is not namespaced, the revisions are not an
     *          ascending positive pair, the operation list is empty or over its bound, or an operation
     *          falls outside the closed vocabulary or carries the wrong members.
     *
     * @since   0.1.0
     */

```php
public function __construct(string $migrationId, string $block, int $fromVersion, int $toVersion, array $operations);
```

### identifier

/**
     * The identifier this migration is registered and replayed under.
     *
     * @return  string  Namespaced migration identity, inside the declaring package's namespace.
     *
     * @since   0.1.0
     */

```php
public function identifier(): string;
```

### block

/**
     * The declared block whose documents this migration steps forward.
     *
     * @return  string  Namespaced block identifier, declared in the same manifest.
     *
     * @since   0.1.0
     */

```php
public function block(): string;
```

### fromVersion

/**
     * The block revision this migration steps from.
     *
     * @return  int  Positive revision lower than the target.
     *
     * @since   0.1.0
     */

```php
public function fromVersion(): int;
```

### toVersion

/**
     * The block revision this migration steps to.
     *
     * @return  int  Revision higher than the source and never past the block's declared version.
     *
     * @since   0.1.0
     */

```php
public function toVersion(): int;
```

### toArray

/**
     * Serialize the declaration for the signed manifest, the runtime publication, and inventory.
     *
     * @return  array{
     *              migration_id: string,
     *              block: string,
     *              from_version: int,
     *              to_version: int,
     *              operations: non-empty-list<array<string, string>>
     *          }  Canonical declaration.
     *
     * @since   0.1.0
     */

```php
public function toArray(): array;
```

### fromArray

/**
     * Reconstitute the declaration from validated manifest data.
     *
     * @param   array<string, mixed>  $data  Declaration as `toArray()` produced it.
     *
     * @return  self  Validated composition migration declaration.
     *
     * @throws  InvalidArgumentException  When a member is missing, extra, or mistyped, or an operation is
     *          not an object of strings.
     *
     * @since   0.1.0
     */

```php
public static function fromArray(array $data): Kumwe\Extension\Spi\Contribution\CompositionMigrationDeclaration;
```

### Public properties

/**
     * Canonical declared operations, in declaration order.
     *
     * @var    non-empty-list<array<string, string>>
     * @since  0.1.0
     */

- `readonly array $operations`

### Public constants

/**
     * Operations one migration may declare.
     *
     * @var    int
     * @since  0.1.0
     */

- `MAXIMUM_OPERATIONS = 32`
/**
     * The closed vocabulary of operations a declared migration may apply to a stored document.
     *
     * @var    list<string>
     * @since  0.1.0
     */

- `ACTIONS = array (   0 => 'clear',   1 => 'remove',   2 => 'rename', )`

## Kumwe\Extension\Spi\Contribution\CompositionPatternDeclaration

/**
 * A reusable composition structure a package declares, built only from blocks it also declares.
 *
 * A pattern is what an author reaches for instead of assembling the same blocks by hand: a declared
 * arrangement the Gate B surface offers as one placement. At Gate A the declaration carries the
 * arrangement as an ordered, bounded list of block references, because that is the part an extension
 * author must be able to depend on — which blocks the pattern is made of, in which order. The full
 * document body a placement expands into is a Gate B artifact produced by the runtime from these same
 * blocks, so it is deliberately not declared here.
 *
 * The reference list is ordered and may repeat a block, because a structure legitimately uses the same
 * block twice; it is bounded, and every reference must name a block declared in the same manifest, so a
 * pattern can never smuggle in a dependency on structure its own package does not promise.
 *
 * @since  0.1.0
 */

### __construct

/**
     * Declare one pattern and the ordered blocks it arranges.
     *
     * @param   string        $patternId  Namespaced identifier inside the declaring package's namespace.
     * @param   list<string>  $blocks     Namespaced block identifiers in arrangement order.
     * @param   int           $version    Declared pattern revision, one or higher.
     *
     * @throws  InvalidArgumentException  When the identifier is not namespaced, the block list is empty or
     *          over its bound, a reference is not a namespaced identifier, or the version is not positive.
     *
     * @since   0.1.0
     */

```php
public function __construct(string $patternId, array $blocks, int $version = 1);
```

### identifier

/**
     * The identifier this pattern is registered and offered under.
     *
     * @return  string  Namespaced pattern identity, inside the declaring package's namespace.
     *
     * @since   0.1.0
     */

```php
public function identifier(): string;
```

### version

/**
     * The declared revision of this pattern's arrangement.
     *
     * @return  int  One or higher.
     *
     * @since   0.1.0
     */

```php
public function version(): int;
```

### toArray

/**
     * Serialize the declaration for the signed manifest, the runtime publication, and inventory.
     *
     * @return  array{pattern_id: string, blocks: non-empty-list<string>, version: int}  Canonical declaration.
     *
     * @since   0.1.0
     */

```php
public function toArray(): array;
```

### fromArray

/**
     * Reconstitute the declaration from validated manifest data.
     *
     * @param   array<string, mixed>  $data  Declaration as `toArray()` produced it.
     *
     * @return  self  Validated composition pattern declaration.
     *
     * @throws  InvalidArgumentException  When a member is missing, extra, or mistyped.
     *
     * @since   0.1.0
     */

```php
public static function fromArray(array $data): Kumwe\Extension\Spi\Contribution\CompositionPatternDeclaration;
```

### Public properties

/**
     * Ordered block references this pattern is assembled from, repeats permitted.
     *
     * @var    non-empty-list<string>
     * @since  0.1.0
     */

- `readonly array $blocks`

### Public constants

/**
     * Block references one pattern may hold, which bounds what a single placement can expand into.
     *
     * @var    int
     * @since  0.1.0
     */

- `MAXIMUM_BLOCKS = 32`

## Kumwe\Extension\Spi\Contribution\CompositionPropertySchema

/**
 * The bounded property schema one composition block declares, held to the published schema profile.
 *
 * A block's properties are the only structure its instances may carry in a stored composition document,
 * so this is where the platform's rule that stored composition holds bounded typed structure — never
 * markup, styles or code — is enforced for extension blocks. Every property names a type from the closed
 * `CompositionPropertyType` vocabulary and carries exactly the bound that type requires: a length for
 * text, a range for numbers, a closed value list for a choice, a platform artifact kind for a reference.
 * A property with no bound is not a looser declaration; it is a refused one, at admission and again at
 * install, before any runtime exists to consume it.
 *
 * Properties are keyed and sorted by name so two manifests declaring the same schema in a different
 * order export the same bytes and reconcile identically.
 *
 * @since  0.1.0
 */

### __construct

/**
     * Validate one block's declared properties against the published schema profile.
     *
     * @param   array<string, array<string, mixed>>  $properties  Property specifications keyed by name, each
     *          naming its type and carrying exactly the bound that type requires.
     *
     * @throws  InvalidArgumentException  When there are too many properties, a name is malformed, a type is
     *          outside the closed vocabulary, or a specification is missing, exceeding, or violating the
     *          bound its type requires.
     *
     * @since   0.1.0
     */

```php
public function __construct(array $properties);
```

### toArray

/**
     * Serialize the schema for the signed manifest, the runtime publication, and inventory.
     *
     * @return  array<string, array<string, mixed>>  Canonical property specifications keyed by name.
     *
     * @since   0.1.0
     */

```php
public function toArray(): array;
```

### fromArray

/**
     * Reconstitute the schema from validated manifest data.
     *
     * @param   array<string, mixed>  $data  Property map exactly as `toArray()` produced it.
     *
     * @return  self  Validated bounded property schema.
     *
     * @throws  InvalidArgumentException  When a property specification is not an object, or any
     *          specification fails the profile's checks.
     *
     * @since   0.1.0
     */

```php
public static function fromArray(array $data): Kumwe\Extension\Spi\Contribution\CompositionPropertySchema;
```

### Public properties

/**
     * Canonical property specifications, keyed and sorted by property name.
     *
     * @var    array<string, array<string, mixed>>
     * @since  0.1.0
     */

- `readonly array $properties`

### Public constants

/**
     * Properties one block may declare, which bounds the manifest and every stored instance.
     *
     * @var    int
     * @since  0.1.0
     */

- `MAXIMUM_PROPERTIES = 32`
/**
     * Longest declarable single-line string, in characters.
     *
     * @var    int
     * @since  0.1.0
     */

- `MAXIMUM_STRING_LENGTH = 500`
/**
     * Longest declarable multi-line text run, in characters.
     *
     * @var    int
     * @since  0.1.0
     */

- `MAXIMUM_TEXT_LENGTH = 10000`
/**
     * Values one choice property may enumerate, which keeps the closed list a readable claim.
     *
     * @var    int
     * @since  0.1.0
     */

- `MAXIMUM_CHOICE_VALUES = 32`
/**
     * Platform artifact kinds a reference property may point at.
     *
     * The list is closed because a reference is a claim on an authoritative platform surface: content,
     * media and business records all live behind existing contracts, and a reference to anything else
     * would be a reference the platform cannot police.
     *
     * @var    list<string>
     * @since  0.1.0
     */

- `REFERENCE_KINDS = array (   0 => 'business_record',   1 => 'content',   2 => 'media', )`

## Kumwe\Extension\Spi\Contribution\CompositionPropertyType

/**
 * The closed vocabulary of property types a composition block may declare.
 *
 * This is the published composition schema profile's type list, fixed at Gate A so the contract an
 * extension author reads and the contract the Gate B runtime enforces are the same list. Every type
 * carries its own boundedness obligation — a string carries a maximum length, a number carries its
 * range, a choice carries its closed value list — which `CompositionPropertySchema` enforces at
 * declaration time. There is deliberately no open or structured type: a block property can never
 * smuggle unbounded structure, markup or code into a stored composition document.
 *
 * @since  0.1.0
 */

### cases

Generated enum/runtime member.

```php
public static function cases(): array;
```

### from

Generated enum/runtime member.

```php
public static function from(string|int $value): static;
```

### tryFrom

Generated enum/runtime member.

```php
public static function tryFrom(string|int $value): ?static;
```

### Public properties



- `readonly string $name`


- `readonly string $value`

### Public constants

/**
     * A single line of plain text, bounded by a declared maximum length.
     *
     * @since  0.1.0
     */

- `String = \Kumwe\Extension\Spi\Contribution\CompositionPropertyType::String`
/**
     * A multi-line run of plain text, bounded by a declared maximum length.
     *
     * @since  0.1.0
     */

- `Text = \Kumwe\Extension\Spi\Contribution\CompositionPropertyType::Text`
/**
     * A whole number bounded by a declared inclusive minimum and maximum.
     *
     * @since  0.1.0
     */

- `Integer = \Kumwe\Extension\Spi\Contribution\CompositionPropertyType::Integer`
/**
     * A decimal number bounded by a declared inclusive minimum and maximum.
     *
     * @since  0.1.0
     */

- `Number = \Kumwe\Extension\Spi\Contribution\CompositionPropertyType::Number`
/**
     * A true-or-false flag, bounded by its own two values.
     *
     * @since  0.1.0
     */

- `Boolean = \Kumwe\Extension\Spi\Contribution\CompositionPropertyType::Boolean`
/**
     * One value out of a closed declared list, the profile's enumeration type.
     *
     * @since  0.1.0
     */

- `Choice = \Kumwe\Extension\Spi\Contribution\CompositionPropertyType::Choice`
/**
     * A reference to a platform-owned artifact of a declared kind, never an inline copy of one.
     *
     * @since  0.1.0
     */

- `Reference = \Kumwe\Extension\Spi\Contribution\CompositionPropertyType::Reference`

## Kumwe\Extension\Spi\Contribution\TranslationSetItemAssociation

/**
 * The versioned claim that one stored content item belongs to a package's declared translation set.
 *
 * `TranslationGroupDeclaration` is admission metadata: it says which sets a package will publish and in
 * which languages, and it is signed before any of the package's code runs. What it deliberately does
 * not carry is a runtime item identifier, because content entries only come into existence after
 * install. This type is the other half — the additive generation-one contract a package hands to
 * `ContentService::translateContributed()` to place one of its stored entries into a declared set. It
 * is a new type beside the frozen declaration rather than a reinterpretation of it, so every package
 * admitted against the declaration-only contract keeps exactly the bytes and behaviour it was admitted
 * with.
 *
 * The association is deliberately closed at both ends. It names its owner and the declared set, and the
 * set identifier must sit inside that owner's namespace, so a package cannot claim another package's
 * set by spelling its identifier. Core then resolves the pair against the active contribution registry
 * before anything is stored, which is where an undeclared set, a withdrawn package or an undeclared
 * locale is refused.
 *
 * The runtime translation group is derived rather than allocated: one name-based UUID per generation,
 * site, owner and set. That derivation is part of the frozen generation-one promise — the same
 * association resolves to the same group across requests, restarts and reinstalls, which is what makes
 * the stored `translation_group_id` on each entry a durable link back to the declaring package without
 * a second storage surface. A future generation may derive differently; generation one may not change.
 *
 * @since  0.1.0
 */

### __construct

/**
     * Associate the declaring package with one of its declared translation sets.
     *
     * @param   string  $owner           Package identifier in `vendor/name` form; `core` is refused
     *          because core content declares no set and uses the plain translation path.
     * @param   string  $translationSet  Declared set identifier, inside that package's namespace and in
     *          the declaration's own identifier grammar.
     * @param   int     $generation      Association-contract generation; only `GENERATION` is implemented.
     *
     * @throws  InvalidArgumentException  When the generation is unsupported, the owner is not a package
     *          identifier, or the set identifier is malformed or outside the owner's namespace.
     *
     * @since   0.1.0
     */

```php
public function __construct(string $owner, string $translationSet, int $generation = 1);
```

### groupIdForSite

/**
     * Derive the runtime translation group this association resolves to within one site.
     *
     * @param   string  $siteIdentifier  Site whose content the associated entries belong to.
     *
     * @return  string  Deterministic UUID of the logical item's group in that site.
     *
     * @throws  InvalidArgumentException  When the site identifier is empty.
     *
     * @since   0.1.0
     */

```php
public function groupIdForSite(string $siteIdentifier): string;
```

### toArray

/**
     * Export the association for the audit trail and diagnostics.
     *
     * @return  array{generation: int, owner: string, translation_set: string}  Canonical association.
     *
     * @since   0.1.0
     */

```php
public function toArray(): array;
```

### Public properties

/**
     * Package the associated item belongs to, whose namespace bounds the set it may name.
     *
     * @var    ContributionOwner
     * @since  0.1.0
     */

- `readonly Kumwe\Contribution\ContributionOwner $owner`
/**
     * Declared translation-set identifier the item is associated with, inside the owner's namespace.
     *
     * @var    string
     * @since  0.1.0
     */

- `readonly string $translationSet`
/**
     * Contract generation this association was written against.
     *
     * @var    int
     * @since  0.1.0
     */

- `readonly int $generation`

### Public constants

/**
     * The one association-contract generation this build of core implements.
     *
     * Carried explicitly on every association so a package compiled against a later generation is
     * refused outright instead of being resolved under rules it did not write against.
     *
     * @var    int
     * @since  0.1.0
     */

- `GENERATION = 1`
/**
     * Name-based UUID namespace the generation-one group derivation hashes under.
     *
     * Fixed forever for generation one: changing it would silently detach every stored entry from the
     * set its package associated it with.
     *
     * @var    string
     * @since  0.1.0
     */

- `GROUP_NAMESPACE = '018f22e2-7c8b-7ab0-8f3a-88e8026bc101'`

## Kumwe\Extension\Spi\Http\ExtensionRequest

/**
 * Reads canonical extension context attributes from a PSR-7 request.
 *
 * @since  0.2.0
 */

### context

/**
     * Read the host-supplied execution context.
     *
     * @param   ServerRequestInterface  $request  Incoming PSR-7 request whose attributes the host populated.
     *
     * @return  ExecutionContext  Canonical extension context.
     *
     * @since   0.2.0
     */

```php
public static function context(Psr\Http\Message\ServerRequestInterface $request): Kumwe\Extension\Spi\Application\ExecutionContext;
```

### csrfToken

/**
     * Read the host-supplied CSRF token, when the surface uses one.
     *
     * @param   ServerRequestInterface  $request  Incoming PSR-7 request that may carry the host-issued token attribute.
     *
     * @return  ?string  CSRF token or null.
     *
     * @since   0.2.0
     */

```php
public static function csrfToken(Psr\Http\Message\ServerRequestInterface $request): ?string;
```

### Public constants

/** @var string @since 0.2.0 */

- `CONTEXT = 'kumwe.extension.execution_context'`
/** @var string @since 0.2.0 */

- `CSRF_TOKEN = 'kumwe.extension.csrf_token'`

## Kumwe\Extension\Spi\Migration\ExtensionMigration

/**
 * Reversible extension-owned schema migration.
 *
 * @since  0.2.0
 */

### id

/** @since 0.2.0 */

```php
public function id(): string;
```

### up

/**
     * @param  Connection           $database  Host database connection the schema change executes on.
     * @param  ExtensionTableNames  $tables    Allocator resolving logical names to this extension's physical tables.
     *
     * @since  0.2.0
     */

```php
public function up(Doctrine\DBAL\Connection $database, Kumwe\Extension\Spi\Migration\ExtensionTableNames $tables): void;
```

### down

/**
     * @param  Connection           $database  Host database connection the schema rollback executes on.
     * @param  ExtensionTableNames  $tables    Allocator resolving logical names to this extension's physical tables.
     *
     * @since  0.2.0
     */

```php
public function down(Doctrine\DBAL\Connection $database, Kumwe\Extension\Spi\Migration\ExtensionTableNames $tables): void;
```

## Kumwe\Extension\Spi\Migration\ExtensionTableNames

/**
 * Owner-bound physical table-name allocator handed to extension migrations.
 *
 * @since  0.2.0
 */

### raw

/** @param string $name Logical table name from the manifest, resolved to its owner-prefixed physical name. @since 0.2.0 */

```php
public function raw(string $name): string;
```

### quoted

/** @param string $name Logical table name from the manifest, resolved and quoted for safe use in SQL statements. @since 0.2.0 */

```php
public function quoted(string $name): string;
```

## Kumwe\Extension\Spi\Runtime\BootableExtension

/** Optional behavior-only lifecycle phase run after every active provider has registered services. @since 0.2.0 */

### boot

/**
     * Start behavior that requires a complete owner-scoped container.
     *
     * Declarations are forbidden in this phase; all routes, events and contributions come from the manifest
     * and executable implementations are attached through the canonical binding provider.
     *
     * @param   ExtensionContainer  $container  Owner-scoped container, complete after every provider has registered.
     *
     * @since   0.2.0
     */

```php
public function boot(Kumwe\Extension\Spi\Runtime\ExtensionContainer $container): void;
```

## Kumwe\Extension\Spi\Runtime\ExtensionContainer

/**
 * Service surface an extension is given in place of the application container.
 *
 * Every extension callback — `register()`, `boot()` and each contribution — receives one of these rather
 * than the host container, so an extension reaches exactly the services the runtime chose to pass it,
 * plus the ones it registered itself. An implementation owes two guarantees: an identifier that was
 * never granted fails instead of resolving, and a factory the extension registers cannot take over the
 * name of a service it was handed. `RestrictedExtensionContainer` is the implementation the runtime
 * builds, one per active extension. This is an API compatibility boundary, not a security sandbox — it
 * bounds what trusted in-process extension code can reach, not what hostile code could do. See
 * `docs/architecture/extensions.md` for the ambient authority admitted code inherits regardless.
 *
 * @since  0.1.0
 */

### get

/**
     * Resolve a service this extension is allowed to see.
     *
     * @param   string  $id  Identifier of a service the runtime granted, or of one this extension shared.
     *
     * @return  object  The resolved service; an identifier that was not granted is an error, never a null
     *          or placeholder return.
     *
     * @since   0.1.0
     */

```php
public function get(string $id): object;
```

### share

/**
     * Register a factory for a service of this extension's own, built on first resolution.
     *
     * The identifier has to stay inside the extension's own namespace, which is what keeps two
     * extensions from colliding and keeps either of them from shadowing a granted host service.
     *
     * @param   string                                $id       Namespaced identifier to register under.
     * @param   callable(ExtensionContainer): object  $factory  Receives this container and returns the
     *          service; called at most once per identifier.
     *
     * @return  void
     *
     * @since   0.1.0
     */

```php
public function share(string $id, callable $factory): void;
```

## Kumwe\Extension\Spi\Runtime\ExtensionEvent

/**
 * Event surface an extension listener receives when a Kumwe domain event is dispatched.
 *
 * Event names and their argument maps are versioned extension API, and this contract is the whole of
 * what a listener may rely on: the event's name, its named arguments, and the propagation flag. The
 * contract deliberately names no vendor type, so the dispatch engine behind it can change without the
 * extension surface moving again. Arguments are read-only through this surface — a listener reacts to a
 * domain event, it does not rewrite it for the listeners that follow.
 *
 * @since  0.1.0
 */

### getName

/**
     * Get the domain event name, such as `onKumweExtensionAfterActivate`.
     *
     * @return  string  The event name.
     *
     * @since   0.1.0
     */

```php
public function getName(): string;
```

### getArgument

/**
     * Get a named argument from the event's payload.
     *
     * @param   string  $name     Name of the argument to read.
     * @param   mixed   $default  Value returned when the payload carries no such argument.
     *
     * @return  mixed  The argument value or the default.
     *
     * @since   0.1.0
     */

```php
public function getArgument(string $name, mixed $default = NULL): mixed;
```

### isStopped

/**
     * Tell whether a listener has stopped the event's propagation.
     *
     * @return  bool  True when propagation has been stopped.
     *
     * @since   0.1.0
     */

```php
public function isStopped(): bool;
```

### stopPropagation

/**
     * Stop the event's propagation to the listeners that would follow.
     *
     * @return  void
     *
     * @since   0.1.0
     */

```php
public function stopPropagation(): void;
```

## Kumwe\Extension\Spi\Studio\Application\Preview\StudioPreviewBindingResult

/**
 * Bounded outcome of resolving one Blueprint value binding.
 *
 * @since  0.1.0
 */

### __construct

/**
     * Capture one available, hidden, or unresolved value without interpreting it as markup.
     *
     * @param   bool   $available  Whether a trusted source supplied a value or declared fallback.
     * @param   bool   $hidden     Whether binding policy suppresses the block's visible value.
     * @param   mixed  $value      JSON value when available, null otherwise.
     *
     * @throws  InvalidArgumentException  When contradictory state is supplied.
     *
     * @since   0.1.0
     */

```php
public function __construct(bool $available, bool $hidden, mixed $value);
```

### unavailable

/**
     * Create the absence result used when a node has no value binding or resolution fails closed.
     *
     * @return  self  Unavailable non-hidden value.
     *
     * @since   0.1.0
     */

```php
public static function unavailable(): Kumwe\Extension\Spi\Studio\Application\Preview\StudioPreviewBindingResult;
```

### hidden

/**
     * Create the result required by `onNull` or `onError` hide policy.
     *
     * @return  self  Hidden value carrying no source data.
     *
     * @since   0.1.0
     */

```php
public static function hidden(): Kumwe\Extension\Spi\Studio\Application\Preview\StudioPreviewBindingResult;
```

### Public properties



- `readonly bool $available`


- `readonly bool $hidden`


- `readonly mixed $value`

## Kumwe\Extension\Spi\Studio\Application\Preview\StudioPreviewBlock

/** Read-only view of a validated contributed block. @since 0.2.0 */

### id

/** @since 0.2.0 */

```php
public function id(): string;
```

### type

/** @since 0.2.0 */

```php
public function type(): string;
```

### version

/** @since 0.2.0 */

```php
public function version(): string;
```

### property

/** @param string $name Manifest-declared property name whose configured value is read. @since 0.2.0 */

```php
public function property(string $name): mixed;
```

## Kumwe\Extension\Spi\Studio\Application\Preview\StudioPreviewBlockFragment

/**
 * Fixed safe presentation decision returned by a preview block renderer.
 *
 * @since  0.1.0
 */

### __construct

/**
     * Retain only allowlisted element/class names and plain text.
     *
     * @param   string                 $element           Fixed semantic HTML element name.
     * @param   string                 $className         Fixed stylesheet class name.
     * @param   string                 $text              Plain UTF-8 text, never markup.
     * @param   bool                   $hidden            Whether binding policy hides content but retains its marker.
     * @param   array<string, string>  $layoutAttributes  Closed one-width preview or all-width public layout
     *          projection.
     *
     * @throws  InvalidArgumentException  When a renderer tries to emit an unsafe name, value, or attribute.
     *
     * @since   0.1.0
     */

```php
public function __construct(string $element, string $className, string $text, bool $hidden = false, array $layoutAttributes = array (
));
```

### Public properties

/**
     * Deterministically ordered safe attributes for one core layout fragment.
     *
     * @var    array<string, string>
     * @since  0.1.0
     */

- `readonly array $layoutAttributes`


- `readonly string $element`


- `readonly string $className`


- `readonly string $text`


- `readonly bool $hidden`

## Kumwe\Extension\Spi\Studio\Application\Preview\StudioPreviewBlockRenderer

/** Safe executable bound to one manifest-declared Studio preview capability. @since 0.2.0 */

### render

/**
     * Produce the fixed safe presentation fragment for one block at one preview width.
     *
     * @param   StudioPreviewBlock          $block     Read-only view of the validated contributed block.
     * @param   StudioPreviewBindingResult  $binding   Resolved Blueprint value binding for the block's content.
     * @param   string                      $viewport  Semantic preview width, such as `compact` or `expanded`.
     *
     * @return  StudioPreviewBlockFragment  Safe allowlisted fragment the preview surface may emit.
     *
     * @since   0.2.0
     */

```php
public function render(Kumwe\Extension\Spi\Studio\Application\Preview\StudioPreviewBlock $block, Kumwe\Extension\Spi\Studio\Application\Preview\StudioPreviewBindingResult $binding, string $viewport): Kumwe\Extension\Spi\Studio\Application\Preview\StudioPreviewBlockFragment;
```

## Kumwe\Extension\Toolchain\ComponentScaffolder

/**
 * Publishes a complete extension source tree from the shipped, bounded template.
 *
 * Files are written to a private sibling directory, validated as a whole, and renamed into place only
 * after every token has been resolved and the generated manifest passes the production parser.
 *
 * @since  0.1.0
 */

### __construct

/**
     * Select the shipped template or an explicit fixture root used by tests.
     *
     * @param  ?string  $templateRoot  Absolute template root; null selects the shipped component template.
     *
     * @since  0.1.0
     * @param CanonicalEncoder $canonicalEncoder Canonical encoding port supplied by the composition root.
     */

```php
public function __construct(Kumwe\CanonicalJson\CanonicalEncoder $canonicalEncoder, ?string $templateRoot = NULL);
```

### scaffold

/**
     * Generate and atomically publish one extension source tree.
     *
     * Existing targets are never merged into or replaced. A failed generation removes only the private
     * sibling directory allocated by this invocation, leaving the requested target absent.
     *
     * @param   ScaffoldRequest  $request  Validated package identity and target information.
     *
     * @return  ScaffoldResult  Published path and generated file count.
     *
     * @throws  InvalidArgumentException  When template or target paths are unsafe.
     * @throws  RuntimeException  When a template cannot be read, written, validated, or published atomically.
     *
     * @since   0.1.0
     */

```php
public function scaffold(Kumwe\Extension\Toolchain\ScaffoldRequest $request): Kumwe\Extension\Toolchain\ScaffoldResult;
```

## Kumwe\Extension\Toolchain\ConformanceReport

/**
 * Stable author-tool result derived from neutral package findings.
 *
 * A host does not consume `conforms()` as admission policy. It consumes the underlying coded findings
 * and applies its own posture; this author-facing report treats every failed check as nonconforming.
 *
 * @since  0.2.0
 */

### __construct

/**
     * Retain the optional snapshot, objective checks and neutral findings.
     *
     * @param   ?PackageInspection     $inspection  Snapshot, or null when package data was too malformed.
     * @param   array<string, bool>    $checks      Named objective outcomes.
     * @param   list<PackageFinding>   $findings    Stable neutral findings.
     *
     * @throws  InvalidArgumentException  When findings are not a typed list.
     *
     * @since   0.2.0
     */

```php
public function __construct(?Kumwe\Extension\Toolchain\PackageInspection $inspection, array $checks, array $findings);
```

### conforms

/**
     * Derive the author-tool conformance outcome.
     *
     * @return  bool  True only when a complete snapshot has no finding or failed check.
     *
     * @since   0.2.0
     */

```php
public function conforms(): bool;
```

### toArray

/**
     * Export the stable author-facing report.
     *
     * @return  array<string, mixed>  Package identity, checks and coded findings.
     *
     * @since   0.2.0
     */

```php
public function toArray(): array;
```

### Public properties



- `readonly ?Kumwe\Extension\Toolchain\PackageInspection $inspection`


- `readonly array $checks`


- `readonly array $findings`

## Kumwe\Extension\Toolchain\DeterministicPackageBuilder

/**
 * Builds byte-reproducible, bounded ZIP packages from complete extension source trees.
 *
 * Entries are sorted, stored without compressor variance, stamped with the ZIP epoch, and assigned a
 * fixed regular-file mode. The finished archive is re-read through `PackageInspector` before publication.
 *
 * Two documents are generated rather than copied from the source tree: a CycloneDX bill of materials at
 * `kumwe.sbom.json` inventorying every packaged file by SHA-256, and a provenance statement at
 * `kumwe.provenance.json` naming the builder and binding itself to that inventory. Both are ordinary
 * archive entries, so the package digest covers them and the detached signature therefore vouches for
 * them without a second signature format; both are excluded from the inventory they participate in,
 * because a document cannot carry its own digest, and evidence reconciliation verifies that exclusion rather than
 * trusting it. Neither carries a timestamp, so the reproducibility contract is unchanged: the same
 * source tree still builds to the same bytes.
 *
 * @since  0.1.0
 */

### __construct

/**
     * Bind post-build verification to the production package inspector.
     *
     * @param  PackageInspector  $inspector  Safety and manifest boundary for the completed ZIP.
     *
     * @since  0.1.0
     * @param CanonicalEncoder $canonicalEncoder Canonical encoding port supplied by the composition root.
     */

```php
public function __construct(Kumwe\CanonicalJson\CanonicalEncoder $canonicalEncoder, Kumwe\Extension\Toolchain\PackageInspector $inspector);
```

### build

/**
     * Build one deterministic archive and publish it without replacing an existing path.
     *
     * @param   string  $sourceDirectory  Canonical absolute extension source root.
     * @param   string  $outputFile       Canonical absolute `.zip` path outside the source root.
     *
     * @return  PackageBuildResult  Published archive and its verified package inspection.
     *
     * @throws  InvalidArgumentException  When source or output paths are unsafe or conflict.
     * @throws  RuntimeException  When a source changes during reading or the ZIP cannot be built and verified.
     *
     * @since   0.1.0
     */

```php
public function build(string $sourceDirectory, string $outputFile): Kumwe\Extension\Toolchain\PackageBuildResult;
```

## Kumwe\Extension\Toolchain\ExtensionConformanceTestCase

/**
 * PHPUnit base class providing one assertion for installable extension fixtures.
 *
 * This bridge activates only where an author's own suite already installs `phpunit/phpunit`
 * (suggested, deliberately never required — the SDK's own check lane stays dependency-free); the
 * class is simply never autoloaded otherwise. The assertion runs the SDK's self-contained author
 * conformance over neutral package findings.
 *
 * @since  0.1.0
 */

## Kumwe\Extension\Toolchain\ExtensionLifecycleTestCase

/**
 * Reusable PHPUnit contract that executes the complete Kumwe extension acceptance lifecycle.
 *
 * The SDK never drives a live platform itself: a consuming platform's suite extends this class and
 * supplies the adapter backed by its own real deployment, which is where install, activate,
 * upgrade, disable, reactivate and uninstall actually run. Like its static sibling, the bridge
 * activates only where `phpunit/phpunit` is already installed by the consuming suite.
 *
 * @since  0.1.0
 */

### testExtensionLifecycleConformance

/**
     * Run every static and stateful conformance gate in the defined order.
     *
     * @return  void
     *
     * @since   0.1.0
     */

```php
public function testExtensionLifecycleConformance(): void;
```

## Kumwe\Extension\Toolchain\ExtensionPackageConformance

/**
 * Public SDK facade for repeatable code-free extension package conformance.
 *
 * This is the entry point an author's CI installs and calls, and it is fully self-contained: the
 * production defaults wire the SDK archive reader, safety limits and shared static checks from this
 * package alone — no host application appears anywhere in the dependency tree. Static package
 * conformance runs entirely here; lifecycle conformance is a port,
 * driven through whatever `LifecycleConformanceAdapter` the platform under test supplies.
 *
 * @since  0.1.0
 */

### __construct

/**
     * Bind the facade to a configured core conformance runner.
     *
     * @param  StaticConformanceRunner  $runner  Configured core conformance service.
     *
     * @since  0.1.0
     */

```php
public function __construct(Kumwe\Extension\Toolchain\StaticConformanceRunner $runner);
```

### withProductionDefaults

/**
     * Create a facade using the SDK's documented default archive limits.
     *
     * @return  self  Ready-to-run conformance facade.
     *
     * @since   0.1.0
     * @param CanonicalEncoder $canonicalEncoder Canonical encoding port supplied by the composition root.
     */

```php
public static function withProductionDefaults(Kumwe\CanonicalJson\CanonicalEncoder $canonicalEncoder): Kumwe\Extension\Toolchain\ExtensionPackageConformance;
```

### run

/**
     * Inspect one canonical absolute package path without executing its code.
     *
     * @param   string  $archiveFile  Canonical absolute extension ZIP path.
     *
     * @return  ConformanceReport  Stable package inventory, checks and coded findings.
     *
     * @since   0.1.0
     */

```php
public function run(string $archiveFile): Kumwe\Extension\Toolchain\ConformanceReport;
```

### runLifecycle

/**
     * Execute static checks and every platform-backed lifecycle acceptance gate.
     *
     * @param   LifecycleConformanceAdapter  $adapter         Real platform test-environment adapter.
     * @param   string                       $basePackage     Canonical absolute initial package path.
     * @param   string                       $upgradePackage  Canonical absolute upgrade package path.
     *
     * @return  LifecycleConformanceReport  Ordered gate and recovery verdicts.
     *
     * @since   0.1.0
     */

```php
public function runLifecycle(Kumwe\Extension\Toolchain\LifecycleConformanceAdapter $adapter, string $basePackage, string $upgradePackage): Kumwe\Extension\Toolchain\LifecycleConformanceReport;
```

## Kumwe\Extension\Toolchain\LifecycleConformanceAdapter

/**
 * Adapter a platform test suite implements to exercise a real extension lifecycle environment.
 *
 * Every assertion method must throw when its gate fails. Implementations may use browser, API, console,
 * MCP, worker, backup, and database harnesses, but must never silently skip a declared platform surface.
 *
 * @since  0.1.0
 */

### assertPackageSafetyAndSigning

/**
     * Prove both packages pass the production safety boundary and carry signatures accepted by trust policy.
     *
     * This gate must verify the exact package bytes supplied to the lifecycle run. Merely checking that a
     * detached-signature document is well formed is insufficient: the adapter must exercise the deployment's
     * real key lookup, namespace admission, revocation, expiry, checksum, and signature-verification path.
     *
     * @param   string  $basePackage     Canonical absolute initial package path.
     * @param   string  $upgradePackage  Canonical absolute upgrade package path.
     *
     * @return  void
     *
     * @since   0.1.0
     */

```php
public function assertPackageSafetyAndSigning(string $basePackage, string $upgradePackage): void;
```

### assertSchemaPlan

/**
     * Prove install and upgrade schema plans are additive, bounded, and reversible where required.
     *
     * @param   string  $basePackage     Canonical absolute initial package path.
     * @param   string  $upgradePackage  Canonical absolute upgrade package path.
     *
     * @return  void
     *
     * @since   0.1.0
     */

```php
public function assertSchemaPlan(string $basePackage, string $upgradePackage): void;
```

### install

/**
     * Install and activate the initial package in a clean environment.
     *
     * @param   string  $basePackage  Canonical absolute initial package path.
     *
     * @return  void
     *
     * @since   0.1.0
     */

```php
public function install(string $basePackage): void;
```

### assertDefinitions

/**
     * Prove installed definitions and executable providers exactly reconcile with the signed declarations.
     *
     * The active trusted generation must contain the expected entity definitions, field types, migrations,
     * routes, jobs, events, projections, reports, and policy declarations, and schema planning must have
     * materialized the same versioned definition graph rather than a partial or reinterpreted substitute.
     *
     * @return  void
     *
     * @since   0.1.0
     */

```php
public function assertDefinitions(): void;
```

### assertAuthorizationAndFieldPolicies

/**
     * Prove deny-by-default authorization and field disclosure policies on every delivery surface.
     *
     * @return  void
     *
     * @since   0.1.0
     */

```php
public function assertAuthorizationAndFieldPolicies(): void;
```

### assertRoutes

/**
     * Prove every declared route is mounted, guarded, and withdrawn with runtime trust.
     *
     * @return  void
     *
     * @since   0.1.0
     */

```php
public function assertRoutes(): void;
```

### assertRestAndOpenApi

/**
     * Prove REST behavior and generated OpenAPI contracts agree.
     *
     * @return  void
     *
     * @since   0.1.0
     */

```php
public function assertRestAndOpenApi(): void;
```

### assertCliAndMcp

/**
     * Prove CLI and MCP adapters preserve the same capability and schema contracts.
     *
     * @return  void
     *
     * @since   0.1.0
     */

```php
public function assertCliAndMcp(): void;
```

### assertJobsEventsAndReports

/**
     * Prove event delivery, durable jobs, projections, and reports execute and retry safely.
     *
     * Worker and scheduler processes must use the exact trusted contribution generation, detect a stale
     * generation after activation changes, and resume the same durable work after a controlled restart.
     *
     * @return  void
     *
     * @since   0.1.0
     */

```php
public function assertJobsEventsAndReports(): void;
```

### assertPortalAndAdministrator

/**
     * Prove administrator and portal contributions render only under their declared authority.
     *
     * The gate must exercise real browser navigation, accessibility assertions, and contribution withdrawal;
     * a raw successful HTTP response alone does not prove this surface.
     *
     * @return  void
     *
     * @since   0.1.0
     */

```php
public function assertPortalAndAdministrator(): void;
```

### assertBackupAndRestore

/**
     * Prove extension data and runtime state survive a documented backup and restore cycle.
     *
     * Restored package checksums, authoritative and derived data, durable work, and audit evidence must be
     * compared with their pre-backup values in a clean installation.
     *
     * @return  void
     *
     * @since   0.1.0
     */

```php
public function assertBackupAndRestore(): void;
```

### upgrade

/**
     * Upgrade the active installation to the supplied package and verify compensation on failure.
     *
     * @param   string  $upgradePackage  Canonical absolute upgrade package path.
     *
     * @return  void
     *
     * @since   0.1.0
     */

```php
public function upgrade(string $upgradePackage): void;
```

### disable

/**
     * Disable the installed extension and prove every executable contribution is withdrawn.
     *
     * @return  void
     *
     * @since   0.1.0
     */

```php
public function disable(): void;
```

### reactivate

/**
     * Reactivate the disabled extension and prove its generation becomes usable again.
     *
     * @return  void
     *
     * @since   0.1.0
     */

```php
public function reactivate(): void;
```

### assertDatabaseMatrix

/**
     * Run the same lifecycle assertions against every database configured by the consuming CI matrix.
     *
     * @param   string  $basePackage     Canonical absolute initial package path.
     * @param   string  $upgradePackage  Canonical absolute upgrade package path.
     *
     * @return  void
     *
     * @since   0.1.0
     */

```php
public function assertDatabaseMatrix(string $basePackage, string $upgradePackage): void;
```

### uninstall

/**
     * Uninstall the extension and prove policy-controlled data removal and contribution withdrawal.
     *
     * @return  void
     *
     * @since   0.1.0
     */

```php
public function uninstall(): void;
```

### recover

/**
     * Restore a clean test environment after success or failure; repeated calls must be safe.
     *
     * @return  void
     *
     * @since   0.1.0
     */

```php
public function recover(): void;
```

## Kumwe\Extension\Toolchain\LifecycleConformanceReport

/**
 * Stable result of a full platform-backed extension lifecycle conformance run.
 *
 * @since  0.1.0
 */

### __construct

/**
     * Record ordered gate verdicts and their platform failure evidence.
     *
     * @param  array<string, bool>  $checks      Ordered lifecycle gate verdicts.
     * @param  list<string>         $violations  Failure messages in observation order.
     *
     * @since  0.1.0
     */

```php
public function __construct(array $checks, array $violations);
```

### conforms

/**
     * Decide whether every declared platform gate and recovery passed.
     *
     * @return  bool  True only when all checks are true and no violation was recorded.
     *
     * @since   0.1.0
     */

```php
public function conforms(): bool;
```

### toArray

/**
     * Export a machine-readable lifecycle report for CI artifacts.
     *
     * @return  array{format: string, conforms: bool, checks: array<string, bool>, violations: list<string>}
     *          Stable full-lifecycle verdict.
     *
     * @since   0.1.0
     */

```php
public function toArray(): array;
```

### Public properties



- `readonly array $checks`


- `readonly array $violations`

## Kumwe\Extension\Toolchain\LifecycleConformanceRunner

/**
 * Executes static package checks and the complete platform-backed lifecycle gate sequence.
 *
 * @since  0.1.0
 */

### __construct

/**
     * Bind full lifecycle execution to the static package gate.
     *
     * @param  StaticConformanceRunner  $static  Code-free package conformance leg.
     *
     * @since  0.1.0
     */

```php
public function __construct(Kumwe\Extension\Toolchain\StaticConformanceRunner $static);
```

### run

/**
     * Run every acceptance surface in dependency order and always invoke idempotent recovery.
     *
     * The first failed gate stops dependent mutations, preserving evidence and avoiding misleading
     * secondary failures. Recovery is still attempted and has its own explicit verdict.
     *
     * @param   LifecycleConformanceAdapter  $adapter         Real platform test-environment adapter.
     * @param   string                       $basePackage     Canonical absolute initial package path.
     * @param   string                       $upgradePackage  Canonical absolute upgrade package path.
     *
     * @return  LifecycleConformanceReport  Ordered gate and cleanup verdicts.
     *
     * @since   0.1.0
     */

```php
public function run(Kumwe\Extension\Toolchain\LifecycleConformanceAdapter $adapter, string $basePackage, string $upgradePackage): Kumwe\Extension\Toolchain\LifecycleConformanceReport;
```

## Kumwe\Extension\Toolchain\PackageBuildResult

/**
 * Result of an atomically published deterministic extension archive.
 *
 * @since  0.1.0
 */

### __construct

/**
     * Retain the output path and its post-publication production inspection.
     *
     * @param  string             $archive     Canonical absolute output path.
     * @param  PackageInspection  $inspection  Immutable inspection of the published package.
     *
     * @since  0.1.0
     */

```php
public function __construct(string $archive, Kumwe\Extension\Toolchain\PackageInspection $inspection);
```

### toArray

/**
     * Export the build identity without duplicating the full manifest inventory.
     *
     * @return  array{archive: string, package_sha256: string, entry_count: int}  Stable build summary.
     *
     * @since   0.1.0
     */

```php
public function toArray(): array;
```

### Public properties



- `readonly string $archive`


- `readonly Kumwe\Extension\Toolchain\PackageInspection $inspection`

## Kumwe\Extension\Toolchain\PackageInspection

/**
 * Toolchain view over one immutable, host-neutral inspected package snapshot.
 *
 * @since  0.2.0
 */

### __construct

/**
     * Retain the canonical package snapshot without copying its fields into a second authority.
     *
     * @param  InspectedPackage  $package  Stable archive, manifest, limits and neutral safety findings.
     *
     * @since  0.2.0
     */

```php
public function __construct(Kumwe\Extension\Package\InspectedPackage $package);
```

### toArray

/**
     * Export the stable package description used by author tooling.
     *
     * Root routes and events are intentionally absent: they are not an executable or advisory package
     * channel. Executable contribution authority remains solely in the typed manifest contribution graph.
     *
     * @return  array<string, mixed>  JSON-compatible package and manifest inventory.
     *
     * @since   0.2.0
     */

```php
public function toArray(): array;
```

### Public properties



- `readonly Kumwe\Extension\Package\InspectedPackage $package`

## Kumwe\Extension\Toolchain\PackageInspector

/**
 * Toolchain facade over the package domain's closed same-bytes snapshot constructor.
 *
 * Callers must retain input archives in a privately owned, non-shared staging directory while the
 * resulting snapshot is in use. Current-file identity checks supplement, but cannot replace, that
 * ownership boundary for pathname operations.
 *
 * @since  0.2.0
 */

### __construct

/**
     * Bind every inspection to one immutable resource budget.
     *
     * @param  PackageLimits  $limits  Exact limits carried into each resulting snapshot.
     *
     * @since  0.2.0
     * @param CanonicalEncoder $canonicalEncoder Canonical encoding port supplied by the composition root.
     */

```php
public function __construct(Kumwe\CanonicalJson\CanonicalEncoder $canonicalEncoder, Kumwe\Extension\Package\PackageLimits $limits = \Kumwe\Extension\Package\PackageLimits::__set_state(array(
   'maximumEntries' => 4096,
   'maximumEntryBytes' => 67108864,
   'maximumExpandedBytes' => 268435456,
   'maximumCompressedBytes' => 268435456,
   'maximumArchiveBytes' => 285212672,
   'maximumCompressionRatio' => 100,
   'maximumManifestBytes' => 1048576,
   'maximumBillOfMaterialsBytes' => 4194304,
   'maximumProvenanceBytes' => 16384,
   'readChunkBytes' => 262144,
)));
```

### inspect

/**
     * Inspect one canonical ZIP through the non-forgeable package snapshot factory.
     *
     * @param   string  $archiveFile  Canonical absolute path in caller-controlled private staging.
     *
     * @return  PackageInspection  Toolchain view over the immutable inspected snapshot.
     *
     * @since   0.2.0
     */

```php
public function inspect(string $archiveFile): Kumwe\Extension\Toolchain\PackageInspection;
```

### limits

/**
     * Return the exact resource budget used and embedded in every snapshot.
     *
     * @return  PackageLimits  Immutable shared package limits.
     *
     * @since   0.2.0
     */

```php
public function limits(): Kumwe\Extension\Package\PackageLimits;
```

## Kumwe\Extension\Toolchain\PackageSigner

/**
 * Signs an inspected package digest with a protected Ed25519 secret key and writes a public sidecar.
 *
 * @since  0.1.0
 */

### __construct

/**
     * Bind signing to protected key loading and production package inspection.
     *
     * @param  ProtectedSigningKeyReader  $keys       Reader enforcing owner-only signing-key files.
     * @param  PackageInspector           $inspector  Package safety and checksum boundary.
     *
     * @since  0.1.0
     */

```php
public function __construct(Kumwe\Extension\Toolchain\ProtectedSigningKeyReader $keys, Kumwe\Extension\Toolchain\PackageInspector $inspector);
```

### sign

/**
     * Sign the SDK's versioned, domain-separated message for the inspected package digest.
     *
     * @param   string  $archiveFile  Canonical absolute package ZIP path.
     * @param   string  $keyId        Trust-store identifier of the matching public key.
     * @param   string  $keyFile      Canonical absolute protected secret-key path.
     *
     * @return  SignatureDocument  Public detached signature sidecar data.
     *
     * @throws  InvalidArgumentException  When package, key identifier, or key file is invalid.
     *
     * @since   0.1.0
     */

```php
public function sign(string $archiveFile, string $keyId, string $keyFile): Kumwe\Extension\Toolchain\SignatureDocument;
```

### write

/**
     * Publish a signature sidecar without replacing an existing path.
     *
     * @param   SignatureDocument  $document    Validated detached signature document.
     * @param   string             $outputFile  Canonical absolute sidecar path.
     *
     * @return  void
     *
     * @throws  InvalidArgumentException  When the output path is relative, non-canonical, or already exists.
     * @throws  RuntimeException  When private staging, protection, or atomic publication fails.
     *
     * @since   0.1.0
     */

```php
public function write(Kumwe\Extension\Toolchain\SignatureDocument $document, string $outputFile): void;
```

## Kumwe\Extension\Toolchain\ProtectedSigningKeyReader

/**
 * Reads an Ed25519 secret key from an owner-only regular file without following links.
 *
 * Files may contain a 32-byte seed or 64-byte secret key in raw, hexadecimal, or strict base64 form.
 * A seed is expanded with libsodium so callers always receive the canonical 64-byte signing key.
 *
 * @since  0.1.0
 */

### read

/**
     * Read and normalize one protected Ed25519 signing key.
     *
     * @param   string  $path  Canonical absolute owner-only key-file path.
     *
     * @return  non-empty-string  Exactly 64 bytes accepted by `sodium_crypto_sign_detached`.
     *
     * @throws  InvalidArgumentException  When path, owner, mode, encoding, or key length is unsafe.
     * @throws  RuntimeException  When the stable file cannot be locked or read completely.
     *
     * @since   0.1.0
     */

```php
public function read(string $path): string;
```

## Kumwe\Extension\Toolchain\ScaffoldRequest

/**
 * Validated input for creating one complete extension source tree.
 *
 * The target stays as an absolute path so scaffolding never depends on the process working directory.
 * Package identity, PHP namespace, label, and version are proven here before a template is read.
 *
 * @since  0.1.0
 */

### __construct

/**
     * Validate values before the scaffolder is allowed to touch the filesystem.
     *
     * @param   string  $identifier       Canonical `vendor/name` package identifier.
     * @param   string  $phpNamespace     Root PHP namespace without a trailing separator.
     * @param   string  $targetDirectory  Absolute path that must not already exist.
     * @param   string  $label            Human-readable component label.
     * @param   string  $version          Semantic Versioning release written to the manifest.
     *
     * @throws  InvalidArgumentException  When a namespace, path, label, or version is malformed.
     *
     * @since   0.1.0
     */

```php
public function __construct(string $identifier, string $phpNamespace, string $targetDirectory, string $label, string $version = '1.0.0');
```

### contributionNamespace

/**
     * Return the dotted namespace every owned contribution identifier begins with.
     *
     * @return  string  Package identifier with its vendor separator changed to a dot.
     *
     * @since   0.1.0
     */

```php
public function contributionNamespace(): string;
```

### Public properties

/**
     * Validated package identity used in the generated manifest.
     *
     * @var    ExtensionIdentifier
     * @since  0.1.0
     */

- `readonly Kumwe\Extension\Manifest\ExtensionIdentifier $identifier`
/**
     * Validated release version used in the generated manifest.
     *
     * @var    SemanticVersion
     * @since  0.1.0
     */

- `readonly Kumwe\Extension\Manifest\SemanticVersion $version`
/**
     * Human-readable component label after surrounding whitespace is removed.
     *
     * @var    string
     * @since  0.1.0
     */

- `readonly string $label`


- `readonly string $phpNamespace`


- `readonly string $targetDirectory`

## Kumwe\Extension\Toolchain\ScaffoldResult

/**
 * Immutable summary of a source tree published by the scaffolder.
 *
 * @since  0.1.0
 */

### __construct

/**
     * Record the published tree and the number of generated regular files.
     *
     * @param  string  $directory  Absolute path of the completed component tree.
     * @param  int     $fileCount  Number of files generated from the selected template.
     *
     * @since  0.1.0
     */

```php
public function __construct(string $directory, int $fileCount);
```

### toArray

/**
     * Export the result for console and SDK consumers.
     *
     * @return  array{directory: string, file_count: int}  Stable scaffold result.
     *
     * @since   0.1.0
     */

```php
public function toArray(): array;
```

### Public properties



- `readonly string $directory`


- `readonly int $fileCount`

## Kumwe\Extension\Toolchain\SignatureDocument

/**
 * Portable detached-signature sidecar for a deterministic extension package.
 *
 * @since  0.1.0
 */

### __construct

/**
     * Validate the digest, key identifier, and detached Ed25519 signature.
     *
     * @param   string  $keyId            Trust-store key identifier.
     * @param   string  $packageSha256    Lowercase hexadecimal package checksum.
     * @param   string  $base64Signature  Detached Ed25519 signature in strict base64 form.
     *
     * @throws  InvalidArgumentException  When any signature field is malformed.
     *
     * @since   0.1.0
     */

```php
public function __construct(string $keyId, string $packageSha256, string $base64Signature);
```

### fromJson

/**
     * Decode a strict signature sidecar and reject every unknown or missing semantic field.
     *
     * @param   string  $json  Raw sidecar document.
     *
     * @return  self  Validated signature document.
     *
     * @throws  InvalidArgumentException  When the shape, format, or a field is invalid.
     * @throws  JsonException  When the JSON is malformed or too deeply nested.
     *
     * @since   0.1.0
     */

```php
public static function fromJson(string $json): Kumwe\Extension\Toolchain\SignatureDocument;
```

### toArray

/**
     * Export the stable sidecar object.
     *
     * @return  array{format: string, algorithm: string, key_id: string, package_sha256: string, signature: string}
     *          Detached signature fields in canonical order.
     *
     * @since   0.1.0
     */

```php
public function toArray(): array;
```

### toJson

/**
     * Encode the sidecar deterministically for storage and transport.
     *
     * @return  string  Pretty-printed JSON ending with one newline.
     *
     * @throws  JsonException  When an internal field cannot be encoded.
     *
     * @since   0.1.0
     */

```php
public function toJson(): string;
```

### Public properties

/**
     * Trust-store identifier of the public key matching this signature.
     *
     * @var    string
     * @since  0.1.0
     */

- `readonly string $keyId`
/**
     * Canonical lowercase SHA-256 digest of the exact package bytes.
     *
     * @var    string
     * @since  0.1.0
     */

- `readonly string $packageSha256`
/**
     * Canonical base64 encoding of the detached Ed25519 signature.
     *
     * @var    string
     * @since  0.1.0
     */

- `readonly string $base64Signature`

### Public constants

/**
     * Stable sidecar format identifier.
     *
     * @var    string
     * @since  0.1.0
     */

- `FORMAT = 'kumwe-extension-signature-v2'`

## Kumwe\Extension\Toolchain\StaticConformanceRunner

/**
 * Performs bounded, code-free author conformance over the same neutral evidence a host can inspect.
 *
 * Invalid package data is represented as coded findings. Filesystem and transport failures still throw,
 * because a tool cannot honestly report package facts when the staged bytes cannot be read stably.
 *
 * @since  0.2.0
 */

### __construct

/**
     * Bind conformance to package inspection and shared static checks.
     *
     * @param  PackageInspector        $inspector    Immutable package snapshot producer.
     * @param  PackageCodeConformance  $conformance  Neutral code and reference checks.
     *
     * @since  0.2.0
     */

```php
public function __construct(Kumwe\Extension\Toolchain\PackageInspector $inspector, Kumwe\Extension\Package\PackageCodeConformance $conformance = \Kumwe\Extension\Package\PackageCodeConformance::__set_state(array(
)));
```

### run

/**
     * Inspect and statically validate one package.
     *
     * @param   string  $archiveFile  Canonical absolute package path.
     *
     * @return  ConformanceReport  Author-facing outcome over neutral coded findings.
     *
     * @throws  RuntimeException  When stable archive bytes or metadata cannot be read.
     *
     * @since   0.2.0
     */

```php
public function run(string $archiveFile): Kumwe\Extension\Toolchain\ConformanceReport;
```

