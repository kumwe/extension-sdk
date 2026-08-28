# Extension SDK roadmap

Forward work only; delivered work moves to [`CHANGELOG.md`](../CHANGELOG.md) in the change that
completes it. A phase is claimed only when its named proof passes on a clean clone, and every
phase is its own reviewed change — extraction never lands as one big move.

## Position

Founded. The charter, the engineering standard, and the dependency-free check lane exist; no
extracted code ships yet. Until a phase below lands, Kumwe App's in-tree implementation remains
the authority for that phase's surface, and the App's `docs/extension-contract/` documents remain
the authoritative contract record.

## The shape of the extraction

Kumwe App already contains everything this package will carry: the frozen contract records in
`docs/extension-contract/` (six manifest generations, four SPI generations, the classification of
122 public types), the toolchain in `src/Extension/Development/` with its shared package checks in
`src/Extension/Application/Package/`, and the signed compatibility fixtures under
`tests/Fixtures/ExtensionApi/generations/`. The phases move those surfaces here one at a time,
each with a proof that nothing observable changed, ending with the App consuming this package and
deleting its in-tree copies. The drop-in mechanics — canonical `Kumwe\Extension\...` names,
permanent `class_alias` shims for every pinned `Kumwe\App\...` FQCN — are law in the
[charter](../CHARTER.md); the consumer obligations are law in
[`app-agreement.md`](app-agreement.md).

## Phases

### E-1 — the frozen contract as verified artifacts

Vendor the contract records — the manifest generation records and their schemas, the SPI
generation records, and the classification data — as artifacts under `resources/`, with a
dependency-free digest verifier joining `composer check` as the `contract` script. The generations
are frozen-forever artifacts: the verifier recomputes each generation's `surface_digest` over its
canonical bytes and refuses any drift, exactly as the App's `extension:contract` gate does.

- **Proof**: byte equality between the vendored artifacts and the App's
  `docs/extension-contract/generations.json` and `classification.json` at the recorded source
  commit; every recomputed `surface_digest` matches its recorded value; the App's own
  `composer extension:contract` stays green, untouched.
- **Non-goals**: no PHP types move; no manifest parser; the signed compatibility fixture packages
  stay in the App until E-4; the App's documents are not deleted — they remain authoritative
  until E-5 re-points them.

### E-2 — SPI interfaces under `Kumwe\Extension\`, with the App-side alias plan

Move the public contract types — the SPI interfaces an extension implements, the interfaces it
calls and resolves, and the value types core exchanges with it — to canonical names under
`Kumwe\Extension\`, exactly as the classification lists them. Produce the alias map as a generated
artifact: for every pinned `Kumwe\App\...` FQCN, the canonical name it will alias to, derived
mechanically from the classification so no type can be missed. The App-side `class_alias` shim
file is generated from that map and lands in the App at E-5.

- **Proof**: member-signature comparison of every moved type against the pinned fixture bytes
  (`public-interfaces-v2.json`, `extension-event-v1.json`, and the other pins named by
  `pinned_by`); a completeness check that the alias map covers every public type in the vendored
  classification; `composer check` green with the docblock gate now counting real members.
- **Non-goals**: no behaviour and no implementations — interfaces and value types only; no
  toolchain; nothing changes in the App yet; types the classification marks internal do not move
  and never will.

### E-3 — the toolchain, with byte-determinism proofs

Move the author toolchain: the scaffolder, the deterministic package builder, the signer and its
signature document, the inspector/verifier, and the shared findings implementation they and the
App's admission both stand on — archive reading, package safety limits, and the per-file code
conformance checks. This is the phase that lands the one-implementation security invariant in
code: after E-3 there is exactly one implementation of package findings, and it lives here.
`ext-json`, `ext-zip`, and `ext-sodium` are declared in `composer.json` as this code arrives.

- **Proof**: byte-determinism — each generation fixture package built twice yields identical
  archive bytes, and those bytes equal the App-built archive for the same input at the pinned
  source commit; a signer round-trip against the published fixture-key stem verifies with the
  same admission verifier primitive the App uses; findings equality — the inspector here and the
  App's in-tree inspector produce identical findings over the fixture corpus and a hostile-archive
  corpus.
- **Non-goals**: no trust store, no revocation feeds, no admission decision, no activation — the
  toolchain reports findings and produces artifacts, nothing more; no runtime Composer
  dependency arrives with it (the App's scaffolder and bill of materials currently derive
  name-based UUIDs via `ramsey/uuid`; the extraction reimplements that derivation
  dependency-free, proven byte-identical by fixture).

### E-4 — the conformance runner, self-contained

Make the conformance runner a package an author can actually install: static package conformance
fully self-contained here, and lifecycle conformance as a port — the SDK defines the runner and
the adapter interface, and a platform (the App first) supplies the adapter that drives its own
real deployment. This kills the `kumwe/app ^2.0` pin that makes the in-tree
`sdk/extension-conformance` package unpublishable: the runner an author's CI needs must not
require the App. The signed compatibility fixture packages for all six generations are vendored
here with digest verification, so the runner proves itself on a clean clone.

- **Proof**: on a clean clone with no `kumwe/app` anywhere in the dependency tree, the static
  conformance runner passes over all six vendored generation fixtures and refuses the hostile
  corpus; this package's `composer.json` requires PHP and extensions only.
- **Non-goals**: the SDK does not drive a live platform lifecycle itself — install, activate,
  upgrade, disable, reactivate, uninstall run only through a platform-supplied adapter; no
  PHPUnit or any other package joins `require`.

### E-5 — the App consumes the package

Kumwe App requires `kumwe/extension-sdk` at an exact pin, deletes its in-tree copies (including
`sdk/extension-conformance`), installs the generated `class_alias` shims preserving every pinned
`Kumwe\App\...` FQCN, wires its admission path to the SDK's shared findings implementation, and
re-points `docs/extension-contract/` at the vendored artifacts. This is the phase where the App
becomes consumer #1 and the drop-in promise is discharged.

- **Proof**: the App's existing signed compatibility fixtures across all six manifest
  generations, replayed unmodified — built twice for identical bytes, run through the static
  gate, signed, admitted through `PackageTrustPolicy` over the sodium verifier, then driven
  through install, activate, upgrade, disable, reactivate, and uninstall with the contributed
  surface compared at every step; the App's full `composer qa` green, `extension:contract`
  included; the findings-equality test of [`app-agreement.md`](app-agreement.md) in the App's
  suite, replaying packages through the SDK entry point and the admission entry point and
  asserting identical findings.
- **Non-goals**: no behaviour change, no new generation, no manifest change, no alias removal —
  a diff in what any extension observes is a defect of this phase; admission policy, trust
  state, and activation stay in the App, now calling the SDK for findings only.

### E-6 — Packagist

Publish `kumwe/extension-sdk` on Packagist. Tag `v0.x` under semantic versioning; the release
workflow re-proves the check lane on the tagged commit and refuses a version the changelog does
not record; the App moves its pin from a repository source to the published release — still
exact, per the pin protocol.

- **Proof**: a clean-room `composer require kumwe/extension-sdk` from Packagist followed by a
  green `composer check`; the App's CI green against the published version.
- **Non-goals**: no version ranges for the App while the SDK is `0.x`; no co-versioning with the
  App — alignment travels through the pin, never through matching version numbers.

## Deferred by design

Anything the charter's never-clauses forbid — admission policy, trust registries and trust state,
lifecycle activation, capability gating — is not deferred work; it is out of scope permanently
and stays in the App.
