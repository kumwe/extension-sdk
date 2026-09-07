# Changelog

Notable changes to `kumwe/extension-sdk` are recorded here in
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) format.

## Unreleased

- Move portable SDK definitions to their canonical package owners and require explicit canonical encoder injection.
- Exercise the actual native runtime and exact dependency source graph in both SDK test lanes.
- Preserve the verified source graph and Composer lock across production reinstallation when branches are merged mid-job.
- A successor release number remains pending stable upstream dependency versions; this candidate is not part of v0.2.5.

## 0.2.5 — 2026-09-07

- Unify PR and post-rebase release gates, dynamic release identity, tested publication retries,
  and administrator setup across the package family. Preserve immutable release and dependency evidence requirements.

- Reject empty test suites/cases, own record-query complexity/cursor/projection and zoned-time boundary tests, and enforce package test ownership.
- Require protected main and immutable stable publication, with tested release-heading and integrity refusals.
- App composition, authorization, persistence, lifecycle and delivery tests remain host-owned.

## [0.2.4] - 2026-09-02

### Fixed

- `bin/kumwe-extension` composed its package inspector from constructors the 0.2.0 reset had
  deleted, so every real command — `build`, `inspect`, `evidence` and `conformance` — fatalled
  before reading its target and only `help` worked. The command now composes the same
  `PackageInspector`, `DeterministicPackageBuilder`, `PackageEvidenceInspector` and
  `StaticConformanceRunner` the PHP API exposes, and the suite executes every lane as a subprocess
  against scaffold output, holding each to its exit code and report.
- The complete-component scaffold generated two declaration mirrors, `Integration\IntegrationDefinitions`
  and `Definition\BusinessDefinitions`, importing five `Spi` types that do not exist
  (`EventSchemaDefinition`, `QueueContributionDefinition`, `ScheduleContributionDefinition`,
  `ReportDefinition`, `EntityTypeDefinition`), and the schema-4 generation fixture carried the same
  dead `Definitions` mirror. Those declarations are manifest sections the SDK validates, not values
  any SPI callback receives, so the mirrors are removed rather than the types invented. A new suite
  scaffolds and packages the component and autoloads, import-resolves and reflection-checks every
  generated class in the package and in all six generation fixtures.
- The scaffold's generated handlers called declaration methods the SDK does not define, so an
  installed component crashed on its first event, job and durable delivery: `ItemDomainListener`
  passed the event object to `DomainListenerDefinition::accepts()`, which takes the event type,
  schema version and sensitivity; `DigestJobHandler` called `JobContributionDefinition::type()`,
  which is `identifier()`; `ItemIntegrationConsumer` called a non-existent
  `EventConsumerDefinition::accepts()`, which is an `eventType()` comparison plus
  `acceptsVersion()`. The schema-4 fixture's listener, consumer, webhook transport, job and
  projection builder made the same calls. All are corrected, and the SDK suite now executes the
  generated listener, consumer, job handler and projection builder — and the fixture's handlers —
  against definitions built from the generated manifest and real event, context and writer values,
  asserting the ledger and projection effects and every refusal; the generated PHPUnit suite is
  run as well.

### Removed

- Dead SPI: `Spi\Presentation\{AdministratorRouteRenderer,PortalRouteRenderer,PortalRenderer}`, byte-identical
  duplicates of the `Spi\Binding\Http` renderers that nothing referenced, and
  `Spi\Runtime\{ExtensionEventRegistrar,ExtensionRouteRegistrar}`, the code-side registrars the
  0.2.0 reset had withdrawn but left classified public, documented against host classes that no
  longer exist. The contract records follow, and `docs/migration-map.json` is reconciled against
  kumwe/app `201ef18a`, the commit that completed adoption: the registrars join the `replaced` set,
  every retained type the App has since deleted now records its canonical successor, and the
  remaining retained closures are re-derived from that source.

### Added

- Unit coverage for `Spi\Identity\Domain\Capability`: the accepted grammar, trim and lowercase
  normalisation, value equality, and every bounds and grammar refusal.

## [0.2.3] - 2026-09-01

### Fixed

- Restored an exact-contract acceptance check on the projection declaration:
  `ProjectionDefinition::accepts(string $eventType, int $schemaVersion)` mirrors the listener and
  webhook definitions, so a builder can fail closed on an event outside its declared sources. The
  scaffold's generated `ItemProjectionBuilder` called exactly this check but 0.2.0 shipped the
  declaration without it, which made every scaffolded component crash on its first projected event.

## [0.2.2] - 2026-09-01

### Added

- KIS graphical route coverage at the graph boundary for schema-4 and newer packages: every
  administrator or portal GET route must be declared as an area-matched interface surface whose
  capabilities include the route capability, and every such surface must resolve back to its owned
  graphical GET route. The pre-extraction host enforced exactly this fail-closed rule; 0.2.0 and
  0.2.1 validated declared surfaces structurally but let a graphical package omit its interface
  declaration entirely.

## [0.2.1] - 2026-09-01

### Added

- Graph validation for the declarative content-publication section: `content.translation_groups`
  entries now prove closed shape, bounded unique locales, a fallback drawn from the declared
  locales, and — critically — that no signed package claims a content set outside its own
  namespace. The 0.2.0 validator only counted these declarations, so a manifest could claim a
  foreign vendor's content group.

### Fixed

- Admit executable event bindings — domain listeners, consumers, projection sources and webhook
  event types — to platform events in the host's `core.` namespace, whose schemas the host owns and
  enforces at activation. The 0.2.0 graph validator wrongly required every bound event type to be
  declared and owned by the signed manifest itself, which refused the canonical platform pattern of
  extensions observing `core.business_record.mutated`. Own events keep the full in-manifest schema
  requirement, and foreign vendor events remain inadmissible from a signed manifest.

## [0.2.0] - 2026-08-29

### Added

- Canonical `Kumwe\Extension` manifest, SPI, binding, package-inspection and author-tooling APIs.
- Complete signed-manifest-bound executable bindings for HTTP routes, field presentation, integration,
  automation, reporting, custom business surfaces, conversion providers and Studio previews.
- Typed immutable execution declarations, bounded business-record query values, idempotency identity,
  field-presentation constraints and host-neutral request/context values.
- Neutral immutable package inspection snapshots, archive limits, evidence and attestation reports, and
  domain-separated package signatures with hostile-input coverage.
- The `kumwe-extension` command line entry, installed as a Composer binary, reporting the same
  fact-only `build`, `inspect`, `evidence` and `conformance` lanes as the PHP API.
- Max-level PHPStan, Composer audit, strict autoload validation and production-only autoload smoke gates.

### Changed

- Reset contract, fixture and scaffold ownership to canonical SDK identities. Historical host API pins,
  parity ledgers and migration maps are no longer shipped as runtime or release authority.
- Made the manifest the sole declarative source; providers now bind executable behavior only to validated
  identifiers and hosts retain semantic admission policy.
- Declared canonical library dependencies explicitly instead of copying or translating their types.
- Adopted the released `kumwe/producer ^0.2` line as the Studio document-schema authority. The
  release chain completed in order — Studio's governed `studio-v0.1.0-beta.3` prerelease published
  its verified browser archive and detached checksum, Producer re-pinned from those public
  downloads and released 0.2.0 — so the requirement declares a published release rather than a
  development pin.

### Removed

- Code-side route, event and composition declaration registrars.
- Host-specific namespace records, compatibility bridges, aliases and dual declaration paths.

## [0.1.1] - 2026-08-28

### Added

- Initial release automation and direct canonical-namespace extraction plan.

## [0.1.0] - 2026-08-28

### Added

- Initial manifest artifacts, scaffold, package toolchain and conformance runner.
