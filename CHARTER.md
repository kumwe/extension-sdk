# The extension SDK charter

**kumwe/extension-sdk** is the whole author-facing SDK for building
[Kumwe App](https://github.com/kumwe/app) extensions. It is consumed by two audiences with one
surface: the extension author, who builds, signs, inspects and conformance-tests a package with it,
and Kumwe App itself, which runs the same inspection implementation at admission time. What an
author's CI proves and what the App's admission finds are the same findings by construction,
because they are one implementation.

This charter is normative for the repository. A change that contradicts it is a defect, whatever
tests it passes.

## What the SDK is

1. **The contract.** The extension manifest schemas across all generations — currently six, and a
   generation, once published, is a frozen-forever artifact — together with the SPI interfaces an
   extension implements, the classification data that says which types are public and what an
   author does with each, and the signed compatibility fixtures whose committed bytes pin the
   promised surface. The contract is carried as digest-verified artifacts; the digests recorded in
   the App's `extension:contract` gate and the digests verified here are the same digests.
2. **The toolchain.** What an author runs in CI: the scaffolder, the deterministic package builder
   (same input, identical archive bytes), the signer, the inspector/verifier, and the conformance
   runner. The inspector here is the inspection the App runs at admission time — "the SDK and
   admission produce the same findings" is enforced by one shared implementation, never by
   colocation, review discipline, or parallel maintenance.
3. **Proven, not trusted.** Every claim is demonstrated against the signed compatibility fixtures
   across every manifest generation. A check the fixtures do not exercise is not claimed.

## What the SDK must never contain

1. **No authority.** The SDK never decides. Admission policy — whether a package, having been
   inspected, may enter a site — is the App's. Capability gating, trust decisions, and every other
   "may this?" question belong to the App, which decides and enforces; the SDK verifies and
   reports. An SDK API that answers with a decision instead of findings is a defect.
2. **No storage and no runtime state.** Trust registries, trust stores and their state, revocation
   feeds and their synchronization, lifecycle activation and the record of what is installed or
   active — never here. The SDK owns no database, no mutable store, and no daemon; it holds frozen
   contract artifacts and computes findings over bytes it is handed.
3. **No App domain imports.** Nothing in this repository may import, assume, or special-case
   `Kumwe\App\...` or any other host application type. The App is this package's first consumer,
   never its owner and never its dependency. The dependency arrow points one way: the App requires
   the SDK.

## Canonical names, not mutation

The extension API this SDK carries already exists in Kumwe App, historically declared under
`Kumwe\App\...` fully qualified class names — the classification currently lists 122 public
types, 34 of them byte-pinned by compatibility fixtures. The extraction therefore works like
this, and only like this:

- The canonical namespace is `Kumwe\Extension\...`, owned by this repository, and the canonical
  names are the only names. The App carries no translation layer of any kind, ever: no
  historical name survives its adoption change, and no mechanism that would let one resolve will
  ever ship.
- The App consumes the canonical names directly. Its adoption change migrates every reference —
  imports, FQCN strings, docblocks, and its classification records — to canonical names, retires
  every historical `Kumwe\App\...` name, and deletes its in-tree copies. Retiring the historical
  names is legitimate because no third-party extension was ever published against them; the
  classification's recorded FQCNs are re-recorded at canonical names once, as a deliberate
  generation action.
- The proof that nothing observable changed is the App's existing signed compatibility fixtures
  across all manifest generations — six signed generations that remain frozen signed bytes —
  replayed: build, sign, admit, and drive the full lifecycle exactly as before the extraction,
  with the App's full suite green and its assertions unchanged.

The App becomes consumer #1 of this package. Extraction lands in reviewed phases recorded in
[`docs/roadmap.md`](docs/roadmap.md); until a phase lands, the App's in-tree code remains the
authority for that phase's surface.

## The one-implementation security invariant

There is exactly one implementation of package inspection findings. The author's CI calls it; the
App's admission calls it; neither carries a copy, a port, or a "kept in sync" twin. A divergence
between what an author was told and what admission enforces is a security defect, and the design
makes it structurally impossible rather than procedurally unlikely. This invariant is stated as a
testable obligation in [`docs/app-agreement.md`](docs/app-agreement.md).

## The boundary in one line

**The SDK builds, signs, verifies and reports. The App decides, trusts, activates and enforces.**

## Relationships

- **With Kumwe App as source**: the contract artifacts, SPI interfaces, and toolchain are extracted
  from the App under the phase plan in [`docs/roadmap.md`](docs/roadmap.md), with digest and
  fixture proofs at every step. The App's `docs/extension-contract/` documents remain authoritative
  until the phase that moves them lands.
- **With Kumwe App as consumer #1**: the App pins this package exactly; a contract change reaches
  the App only as a deliberate re-pin with its own review and evidence. The agreement — pin
  protocol, canonical-name contract, shared-inspector obligation — is recorded in
  [`docs/app-agreement.md`](docs/app-agreement.md), written so a second consumer needs no new
  agreement.

## Governance

Work is recorded in [`docs/roadmap.md`](docs/roadmap.md) while open and in
[`CHANGELOG.md`](CHANGELOG.md) when delivered; a claim states only what the check lane proves on a
clean clone. The check lane is `composer check`: lint, member documentation, and the
dependency-free test suite. Every commit passes it.
