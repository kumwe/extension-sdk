# Extension SDK roadmap

Forward work only; delivered work moves to [`CHANGELOG.md`](../CHANGELOG.md) in the change that
completes it. A phase is claimed only when its named proof passes on a clean clone, and every
phase is its own reviewed change — extraction never lands as one big move.

## Position

E-1 through E-4 and E-6 delivered. The frozen contract records, the manifest schema fixtures,
the six signed compatibility fixture generations, and the scaffold template are vendored under
`resources/` as digest-pinned verbatim artifacts, verified by `composer contract` inside the
check lane. The portable public contract types live under `Kumwe\Extension\` at their canonical
names, proven against the vendored pin fixtures, with the migration map generated into
`docs/migration-map.json`. The author toolchain — scaffolder, deterministic builder, signer,
inspector, and the shared findings implementation admission stands on — lives here, proven
byte-identical to the App's builds and findings by the recorded parity evidence in
`tests/Fixtures/app-parity.json`. The conformance runner is self-contained: an author installs
this package alone — PHP and extensions only, no `kumwe/app` anywhere in the dependency tree —
and runs the same static conformance admission enforces, with lifecycle conformance driven
through a platform-supplied adapter. The package is live on Packagist with release automation:
merging a change whose changelog records a new version is the release. Until E-5 lands, Kumwe
App's in-tree implementation remains the authority for that surface, and the App's
`docs/extension-contract/` documents remain the authoritative contract record until E-5
re-points them.

## The shape of the extraction

Kumwe App already contains everything this package will carry: the frozen contract records in
`docs/extension-contract/` (six manifest generations, four SPI generations, the classification of
122 public types), the toolchain in `src/Extension/Development/` with its shared package checks in
`src/Extension/Application/Package/`, and the signed compatibility fixtures under
`tests/Fixtures/ExtensionApi/generations/`. The phases move those surfaces here one at a time,
each with a proof that nothing observable changed, ending with the App consuming this package and
deleting its in-tree copies. The direct-consumption mechanics — canonical `Kumwe\Extension\...`
names as the only names, every historical `Kumwe\App\...` reference migrated and retired in the
App's adoption change — are law in the [charter](../CHARTER.md); the consumer obligations are law
in [`app-agreement.md`](app-agreement.md).

## Phases

### E-5 — the App consumes the package

Kumwe App requires `kumwe/extension-sdk` at an exact pin from Packagist, migrates every
reference — imports, FQCN strings, docblocks, and its classification records — to the canonical
`Kumwe\Extension\...` names, retires every historical `Kumwe\App\...` name, deletes its in-tree
copies (including `sdk/extension-conformance`), wires its admission path to the SDK's shared
findings implementation, and re-points `docs/extension-contract/` at the vendored artifacts. The
classification's recorded FQCNs are re-recorded at canonical names in the same change, as the
one-time deliberate generation action the migration map's `moved` set prescribes — legitimate
because no third-party extension was ever published against the historical names. No translation
layer of any kind ships: canonical names are imported directly. This is the phase where the App
becomes consumer #1.

- **Proof**: the App's existing signed compatibility fixtures across all six manifest
  generations — frozen signed bytes, replayed — built twice for identical bytes, run through the
  static gate, signed, admitted through `PackageTrustPolicy` over the sodium verifier, then
  driven through install, activate, upgrade, disable, reactivate, and uninstall with the
  contributed surface compared at every step; the App's full `composer qa` green with its
  assertions unchanged, `extension:contract` included; the findings-equality test of
  [`app-agreement.md`](app-agreement.md) in the App's suite, replaying packages through the SDK
  entry point and the admission entry point and asserting identical findings.
- **Non-goals**: no behaviour change, no new generation, no manifest change, no surviving
  historical name — a diff in what any extension observes is a defect of this phase; admission
  policy, trust state, and activation stay in the App, now calling the SDK for findings only.

## Deferred by design

Anything the charter's never-clauses forbid — admission policy, trust registries and trust state,
lifecycle activation, capability gating — is not deferred work; it is out of scope permanently
and stays in the App.
