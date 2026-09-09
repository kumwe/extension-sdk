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

The source-selection records and both workflows now select the same 21 exact published PHP versions and tag commits. Their complete Kumwe runtime constraints agree. They include the final Context, Access, Contribution, Conversion 0.1.5, Producer 0.2.2, Record Values 0.1.4, Record Query/Model/Reporting 0.1.3 and Business Surface Contract 0.1.3 train. The dependency gate rejects ranges, development coordinates, duplicate records and stale checkouts; installation also checks live tag identity.

Three actual Packagist registrations remain outstanding: `kumwe/access-control` 0.1.2, `kumwe/business-policy` 0.1.1 and `kumwe/content-model` 0.1.2. All three endpoints returned HTTP 404 in the 31-package registry audit captured at 2026-09-08 17:13:45 UTC. The unchanged observation is retained on this PR branch at `evidence/registry/selected-tuple-20260908T171345Z.json`; it records published SDK 0.2.5 and portable Computation 0.1.1, not a completed SDK 0.3.0/native graph. Repository evidence is explicitly excluded from both SDK archive formats.

Register the three canonical GitHub repositories with Packagist using the authorized maintainer account, then verify that the exact published versions and source/dist commits are indexed. SDK consumers retain the two necessary root VCS declarations for Access Control and Business Policy; Content Model is outside the SDK dependency closure. The independent registry-only Reporting consumer and final package matrix remain blocked until registration. VCS source success does not substitute for that registry qualification.

The portable Computation 0.1.1 release has independent source/archive/consumer evidence. Stable acceleration still needs the remaining semantic owner receipts, independently accepted Engine publication, the extension re-embedded against that actual Engine release, independent native source/signature/offline-build verification, native Computation successor publication, and the SDK's final native development pin. Computation 0.2.1 and the documented native candidate remain the existing source-test fixture only. No future release coordinate is selected as though it already exists.

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
The required published-dependency consumer additionally installs the SDK ZIP with the shipped
root VCS declarations and stable dependency coordinates, without source-path repositories or the
native extension. Composer does not inherit a dependency's root repository declarations, so the
archive consumer explicitly preserves them. This lane proves the optional native dependency is
not required to load and consume the core SDK contracts; it does not attest to a published SDK ZIP.
The SDK now ships the standard Version 2 API, capability and service manifests, source-derived API
documentation and a prepublication ownership handoff. Existing SDK compatibility records retain their
paths and semantics; the governed projection adds no runtime aliases. The
SDK-owned classification and resource manifests remain independently enforced by its source gates.

SDK 0.3.0 remains unmerged and unpublished until the remaining registry and native requirements pass and both complete 31-package graphs are verified. Generated project and normal published-dependency archive checks must pass at the final reviewed SDK head. Existing releases/tags must remain unchanged. Merge this PR using **Rebase and merge**, following the package release standard. The original evidence commit identities remain reachable through `archive/pr-15-before-rebase-20260909` at `18a0f7097f5402b7620f0a3127392f69642e6ced`; retain that archival branch and the unchanged historical receipt bytes. The rebased branch retains the same evidence files, while historical receipt references continue to identify the original preserved commits.
No App runtime cutover or duplicate App test removal is claimed by this package PR.
