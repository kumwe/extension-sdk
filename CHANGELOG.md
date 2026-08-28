# Changelog

All notable changes to `kumwe/extension-sdk` are recorded here, in
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) format. Open work lives in
[`docs/roadmap.md`](docs/roadmap.md); a claim lands here only when `composer check` proves it on a
clean clone.

## [Unreleased]

### Added

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
  namespaces and `@since` adapted and behaviour untouched. `docs/alias-map.json` is generated
  mechanically from the vendored classification by `tools/generate-alias-map.php`: every
  classified type lands in exactly one of `aliases` (43, the App-side `class_alias` plan for E-5)
  or `skipped` (79, each recording the unclassified closure members that keep it App-side).
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
