# Canonical package boundary candidate

This source candidate removes the 88 duplicate SDK definitions recorded in `canonical-package-migration.json` and the SDK's generic PHP canonical encoder. Imports in retained SDK APIs, author templates and generation fixtures now name the owning packages directly. No aliases are published. Producer canonical composition documents retain their separate Producer profile.

Manifest parsing, archive inspection, deterministic builds and scaffolding require a `Kumwe\CanonicalJson\CanonicalEncoder` as their first argument. PHPUnit conformance bridges require an explicit `canonicalEncoder()` implementation. The schema-three presenter fixture obtains the same contract from the host-granted extension container. The SDK continues to own signed manifest assembly, package inspection, executable binding orchestration and its host-issued `Spi\Application\ExecutionContext` interface.

`ManifestIdentifierPolicies` selects explicit Contribution policies for SDK manifest surfaces. Graphical suffixes retain their strict dotted grammar; integration suffixes admit the documented `:` and `@` markers. Studio identities use slash policies with explicit core namespaces. The canonical Contribution package now also enforces bounded ASCII and valid suffixes on the non-graphical surfaces.

All direct Kumwe PHP dependencies use exact published versions. `resources/source-ci-dependencies.json` records the 21 selected tag commits, and the dependency-selection gate verifies Composer, source records and both CI workflows before installation. The combined graph selects Context 0.1.2, Access Control 0.1.2, Contribution 0.1.1, Conversion 0.1.5, Producer 0.2.2, Record Values 0.1.4, Record Query/Model 0.1.3, Reporting 0.1.3 and Business Surface Contract 0.1.3, with their exact published shared dependencies. CI checks each source against its live tag; source success is not an independent release attestation. The existing published Computation 0.2.1 development selection remains until the qualified native successor is actually released.

Only Access Control and Business Policy still need root VCS declarations for this SDK graph. Their Packagist endpoints return HTTP 404; Content Model is also absent from the wider 31-package graph. Other selected published dependencies resolve through their matching Packagist source/dist identities. The generated project retains those two declarations and the SDK candidate repository. Required registration and the native publication chain are tracked in `docs/readiness-review.md`.

The generated scaffold targets the recorded canonical SDK successor `0.3.0`, because its imports are incompatible with published `0.2.5`. Source and archive tests exercise this candidate version locally. Consumers may use the coordinate only after the maintainer merges this change and the release workflow actually publishes it. The PHP graph's stable status does not make the native candidate stable or accept App adoption.

The SDK runtime depends on the encoder contract only. The built-in CLI requires the optional `kumwe/computation` candidate and the actual `kumwe_engine` extension. Set `KUMWE_NATIVE_EXPECTED_TUPLE` to an explicit compatibility JSON containing `capabilities`, `extension_version`, `embedded_engine_commit`, `embedded_source_sha256`, and `binding_build_digest`. The build digest binds the independently recorded PHP patch, Zend API, platform, compiler and flags, sanitizer mode, and ABI manifest. The native adapter checks that exact tuple; unavailable or mismatched native implementations fail closed. The SDK does not derive expected values from the observed runtime or supply a PHP fallback.

The complete SDK behavioral suite likewise requires the actual native extension and that expected tuple. The focused dependency-boundary test uses a fixed-response protocol double solely to verify injection and refusal propagation; it does not claim canonical encoding behavior. The canonical packages own the removed value/grammar suites. SDK manifest, archive, fixture, generated-source, ownership, maximum-level static analysis and release integrity gates remain in place.

Both the required SDK source job and the additional native candidate job build the pinned native extension, install the explicit candidate graph, and run the complete SDK gate and fresh archive consumer. Release automation and the required aggregate Package gate remain unchanged.

The archive consumer also generates a complete author project from the installed SDK ZIP. It solves
the generated Composer requirements, runs the generated project's own PHPUnit configuration, then
reinstalls without development dependencies and executes its production autoload and delivery smoke.
The SDK checkout and development autoloader cannot satisfy missing author dependencies in this gate.
Source selections retain stable Composer stability when every selected coordinate is stable.

Within one CI job, production reinstallation reuses the successfully installed Composer plan and lock after checking the same SDK inputs and dependency commits, trees and clean contents. This keeps an already verified development checkout usable if its branch is merged and deleted during the job. Initial installation still verifies every remote coordinate; stable tags are checked again during reinstallation. Snapshots from another job or altered inputs are refused.

Every PHP dependency is selected by its published stable version and exact tag commit. Both native lanes build binding `7ad5cf913af6feb984fe5b85451104179634df83` with Engine `72fd09632e740f0bfd1bb09cc87749110ca21b90`.

The changelog records `0.3.0` as the canonical API successor. Version `0.2.5` remains the previously published API until maintainer merge and successful publication. Green source tests alone do not create that release.

The candidate includes bounded binary transport and optional opaque compiled results. The portable Computation 0.1.1 baseline is published and independently verified. Stable acceleration still requires verified Engine and extension releases, the native Computation successor and both complete 31-package consumer modes; current source checks do not establish those facts.
