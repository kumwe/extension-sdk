# Canonical SDK integration handoff

This review is implemented in [PR #15](https://github.com/kumwe/extension-sdk/pull/15), branch
`codex/extraction-readiness-20260907`. Its recorded successor is `0.3.0`; published `0.2.5`
remains the previous API until the maintainer merges the change and publication succeeds.
The stable Surface and Reporting repair from PR #14 is preserved. Its rebase into main was
merged into this branch; conflicts were resolved by retaining those exact published commits,
the `0.3.0` successor record and the coherent dependency selections, then regenerating resource
digests and verifying both workflow selections.

## Implementation and ownership

The SDK owns manifest parsing, the canonical signed contribution graph, author-facing executable
bindings, archive inspection, deterministic package builds, scaffolding, signing and conformance
tooling. The canonical source exports 96 public types. It does not provide host admission,
trust activation, persistence, authorization or a second copy of extracted library definitions.

`canonical-package-migration.json` records the 88 former SDK symbols and their canonical package
owners against SDK source commit `e8ec23f155c5836c6bd083f154a8efb6e50aec66`. The earlier
`migration-map.json` preserves the App-to-SDK extraction history. The next integration task must
compare those baselines with the then-current sources before deleting anything; this review
inspected App commit `24ecf956423c18933e824b43cea1bfb9127a79a9` without changing App.

Manifest parsing and author tooling receive the owning Canonical JSON encoder contract explicitly.
The built-in CLI uses Computation and the native extension with an independently configured exact
compatibility tuple. A missing or incompatible native capability fails closed. Producer's Studio
canonical profile remains Producer-owned and separate from the generic engine profile.

## Dependencies and release order

The source-selection records and both workflows describe the same 21 exact stable PHP package
coordinates. The dependency-selection gate refuses ranges, development coordinates, duplicate
evidence and stale workflow commits; installation additionally checks live tag identity and lets
Composer resolve the complete transitive graph. Producer now uses published `0.2.1`.

Some released packages require older exact shared-library versions. Upgrading the SDK alone cannot
override them. Release updated direct dependents first, then their dependents, and update the SDK
selection only when the entire graph resolves using published versions. In particular, Conversion,
Contribution, Context, Localization and Sequence updates must move through their Record, Business
Definition, Access, Integration and surface consumers before the SDK can select a new combined
graph. Access Control's GitHub release currently requires the explicit root VCS configuration
where Packagist does not expose it.

The native source remains a candidate, not a stable release. Engine release verification, exact
extension coupling and Computation's stable native requirement remain separate prerequisites for
production native adoption. This SDK release record does not publish or attest to any native artifact.

## Test ownership and next-task gates

The SDK owns its manifest, signature, archive, lifecycle-conformance protocol, generated-source,
resource and public-binding tests. Removed value/grammar behavior belongs in the canonical libraries.
App retains composition, trust, authorization, persistence, delivery, lifecycle and recovery tests.
The package's test ownership gate checks the actual test runner's discovered methods.

Both CI lanes run `composer check` with the exact native candidate, followed by production-only
installation and a fresh authoritative archive consumer. The archive consumer then generates a
complete component using the installed SDK ZIP and actual native encoder, installs that component's
own Composer requirements, runs its generated PHPUnit suite and removes development dependencies.
Its final authoritative production smoke loads every generated runtime type and executes the real
delivery dependency without PHPUnit or Computation in the author runtime. Source dependency
selections remain explicit and stable; these checks do not qualify unpublished native artifacts.
Both lanes must pass at the final reviewed head.
The standard App package reader recognizes this pre-Version-2 SDK format as `legacy-unmanifested`,
as it does Conversion and Producer; no partial Version 2 manifest claim is introduced here. The
SDK-owned classification and resource manifests remain independently enforced by its source gates.

Prefer completing the upstream release train and repinning this branch to its jointly resolvable
published versions before publishing `0.3.0`. If the current coherent older graph is published
first, record a new SDK successor for later pin changes; never rewrite the `0.3.0` tag or archive.
After publication, verify the actual tag, release, archive and generated scaffold installation.
Complete native qualification before the separate App integration task.
No App runtime cutover or duplicate App test removal is claimed by this package PR.
