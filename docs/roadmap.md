# Extension SDK roadmap

Forward work only; delivered work moves to [`CHANGELOG.md`](../CHANGELOG.md) in the change that
completes it. A phase is claimed only when its named proof passes on a clean clone, and every
phase is its own reviewed change — extraction never lands as one big move.

## Position

E-1 through E-4 delivered. The frozen contract records, the manifest schema fixtures, the six
signed compatibility fixture generations, and the scaffold template are vendored under
`resources/` as digest-pinned verbatim artifacts, verified by `composer contract` inside the
check lane. The portable public contract types live under `Kumwe\Extension\` at their canonical
names, proven against the vendored pin fixtures, with the App-side alias plan generated into
`docs/alias-map.json`. The author toolchain — scaffolder, deterministic builder, signer,
inspector, and the shared findings implementation admission stands on — lives here, proven
byte-identical to the App's builds and findings by the recorded parity evidence in
`tests/Fixtures/app-parity.json`. The conformance runner is self-contained: an author installs
this package alone — PHP and extensions only, no `kumwe/app` anywhere in the dependency tree —
and runs the same static conformance admission enforces, with lifecycle conformance driven
through a platform-supplied adapter. Until a phase below lands, Kumwe App's in-tree
implementation remains the authority for that phase's surface, and the App's
`docs/extension-contract/` documents remain the authoritative contract record until E-5
re-points them.

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
