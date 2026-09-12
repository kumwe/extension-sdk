# Dependency and toolchain contract

The SDK consumes canonical package contracts directly. [composer.json](../composer.json) is the
authority for exact runtime and development versions. `resources/source-ci-dependencies.json`
and `resources/source-candidate-dependencies.json` identify the selected source commits; the
dependency-selection gate checks both records, Composer requirements and CI checkouts.

Manifest parsing, inspection, deterministic builds and scaffolding receive a
`Kumwe\CanonicalJson\CanonicalEncoder` explicitly. PHPUnit conformance bridges require an
explicit `canonicalEncoder()` implementation. Producer retains its own Studio canonical profile.
`ManifestIdentifierPolicies` selects Contribution policies for SDK manifest surfaces.

## Optional native author tooling

The production PHP API and generated production extensions require only the encoder contract.
The CLI and development toolchain additionally require `kumwe/computation`, the actual
`kumwe_engine` extension and an independently supplied compatibility tuple. Set
`KUMWE_NATIVE_EXPECTED_TUPLE` to JSON containing `capabilities`, `extension_version`,
`embedded_engine_commit`, `embedded_source_sha256` and `binding_build_digest`. The build digest
binds PHP, Zend API, platform, compiler/flags, sanitizer mode and ABI metadata. Missing or
incompatible capabilities fail closed; expected values must not be copied from an unverified runtime.

## Consumer verification

The complete source gate builds the pinned native toolchain and runs SDK behavior, archive and
generated-project checks. Generated projects install their own dependencies, execute their own
PHPUnit suite, then reinstall production dependencies and load the real generated delivery code.
The separate published-dependency archive consumer runs without the native extension.

Within one CI job, production reinstallation may reuse the verified Composer plan and lock
after checking unchanged SDK inputs, dependency commits, trees and clean contents. Initial
installation verifies remote coordinates; stable tags are rechecked on reinstallation.
Snapshots from another job and changed inputs are refused.

Source tests and dependency installation do not independently attest a release. See
[release qualification](release-qualification.md) for exact archive and complete graph checks,
and [architecture](architecture.md) for construction, authority and lifetime boundaries.
