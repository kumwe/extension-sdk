# Changelog

Notable changes to `kumwe/extension-sdk` are recorded here in
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) format.

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
- Pinned the Studio document-schema authority to the exact `kumwe/producer` 0.2 development line
  (`dev-main as 0.2.x-dev`): Producer's governed 0.2.0 release is blocked until Studio publishes its
  exact browser-archive assets, and this record keeps that dependency decision visible instead of
  declaring a version that does not exist. The requirement returns to the released `^0.2` in the
  release that adopts it.

### Removed

- Code-side route, event and composition declaration registrars.
- Host-specific namespace records, compatibility bridges, aliases and dual declaration paths.

## [0.1.1] - 2026-08-28

### Added

- Initial release automation and direct canonical-namespace extraction plan.

## [0.1.0] - 2026-08-28

### Added

- Initial manifest artifacts, scaffold, package toolchain and conformance runner.
