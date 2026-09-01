# Changelog

Notable changes to `kumwe/extension-sdk` are recorded here in
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) format.

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
