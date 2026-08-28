# Changelog

All notable changes to `kumwe/extension-sdk` are recorded here, in
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) format. Open work lives in
[`docs/roadmap.md`](docs/roadmap.md); a claim lands here only when `composer check` proves it on a
clean clone.

## [0.1.1] - 2026-08-28

### Changed

- The migration map replaces the alias plan. Kumwe App consumes the canonical
  `Kumwe\Extension\...` names directly — its adoption change (E-5) migrates every reference
  (imports, FQCN strings, docblocks, and its classification records) to canonical names and
  retires every historical `Kumwe\App\...` name; no alias layer exists in the App, and no
  `class_alias` will ever ship. `docs/alias-map.json` is renamed to `docs/migration-map.json`
  and its sets to what they are: `moved` records, for each historical FQCN, the canonical name
  the adoption change renames it to; `retained` records every type that stays in the App with
  the closure members that block it. The generator (`tools/generate-migration-map.php`) and the
  suite's map proofs move with it; the map stays mechanically generated, and the regenerated
  artifact is content-identical to its predecessor apart from the renamed keys and note. The
  charter, the App agreement, the roadmap, and the README state the direct-consumption doctrine
  throughout; the six signed compatibility fixture generations remain frozen signed bytes.

### Added

- E-6: Packagist. `kumwe/extension-sdk` is live on Packagist with release automation on the
  record: every push to `main` re-proves the check lane, a changelog heading that records a new
  version becomes the tag and the GitHub release, and Packagist follows the tag through its
  GitHub integration — no registry credential in this repository.

## [0.1.0] - 2026-08-28

### Added

- E-4: the conformance runner, self-contained. `ExtensionPackageConformance` is the entry point
  an author's CI installs and calls: its production defaults wire the same archive reader,
  safety limits and shared static checks admission runs, from this package alone. The
  `kumwe/app ^2.0` pin that made the in-tree `sdk/extension-conformance` package unpublishable
  is dead: `composer.json` requires PHP and extensions only, and the suite proves both that no
  library source references the host application and that the facade passes all six vendored
  generation fixtures while refusing the recorded hostile corpus on a clean clone. Lifecycle
  conformance stays a port — the SDK defines the runner and the adapter interface, and a
  platform supplies the adapter that drives its own real deployment. The two PHPUnit bridge
  base classes travel along and activate only where a consuming suite installs
  `phpunit/phpunit` (suggested, never required).
- E-3: the author toolchain, with byte-determinism and findings-equality proofs. The Manifest
  layer carries the frozen grammar, the manifest value types, and a bounded structural parse of
  the contribution surfaces that accepts every vendored fixture generation and reproduces the
  App's closed key sets, ownership assertions and duplicate refusals — deep semantic validation
  of business, interface, integration and canonical Studio declarations stays the App's by
  classification. The Package layer carries the archive reader and streaming content reader, the
  safety policy, the shared per-file code conformance checks, the admission scanner with its
  attestation verification (bill of materials and provenance), and the Ed25519 verification
  primitive. The Toolchain layer carries the scaffolder (template vendored and pinned), the
  deterministic package builder, the signer with its protected key reader and signature document,
  the inspector, the static conformance runner, and the lifecycle conformance runner. Proofs:
  each fixture generation builds twice to identical bytes whose digest equals the App-built
  archive at the pinned commit; the static and admission findings over all six generations and an
  eight-case hostile corpus equal the App's recorded findings exactly
  (`tests/Fixtures/app-parity.json`); the fixture-key signing round-trip verifies through the
  same sodium primitive admission uses; and the dependency-free UUIDv5 reproduces the App's
  ramsey-derived values. `ext-json`, `ext-sodium` and `ext-zip` are declared with the code that
  needs them.
- E-2: the portable public contract types under `Kumwe\Extension\`. Forty-three of the 122
  classified types move to canonical names — the SPI contribution definitions and registrars, the
  runtime interfaces (`ExtensionContainer`, `ExtensionEvent`, `ExtensionRouteRegistrar`,
  `RuntimeExtension`, `ExtensionEventRegistrar`), `ExtensionServiceProvider`, the lifecycle
  conformance adapter, and the policy, presentation, preview and integration value types — with
  namespaces and `@since` adapted and behaviour untouched. The migration map (so named since
  0.1.1, which records the renames) is generated mechanically from the vendored classification:
  every classified type lands in exactly one of the moved set (43, re-recorded at canonical
  names in the App's adoption change, E-5) or the retained set (79, each recording the
  unclassified closure members that keep it App-side).
  `Kumwe\Extension\Contract\NameBasedUuid` replaces `ramsey/uuid` for name-based derivation,
  proven byte-identical against the RFC 4122 reference vector and the pinned translation-group
  example value. Moved pinned types are held to their vendored pin fixtures by member-signature
  and enum-case comparison in the suite.
- E-1: the frozen contract as verified artifacts. `resources/` vendors the contract records
  (`generations.json`, `classification.json`, the contract README), the four manifest schema pin
  fixtures, the ten compatibility pin documents, and the six signed compatibility fixture
  generations — every file a byte-identical copy of the Kumwe App original at commit
  `da19226`, pinned by `resources/PIN.json`. `tools/verify-contract.php` joins `composer check`
  as the `contract` script: it sweeps every artifact against its recorded source digest, refuses
  unpinned or missing files, recomputes every generation's `surface_digest` over its canonical
  bytes exactly as the App's `extension:contract` gate does, and holds the classification to the
  vendored pin fixtures. Tamper-direction tests prove the verifier fails closed for a changed
  byte, a widened frozen generation, an unpinned file, and a missing artifact.
- Founding: the charter, the inherited engineering standard, the package skeleton
  (`composer.json`, the dependency-free lint/docs/test lane), the extraction roadmap, and the
  Kumwe App consumer agreement.
