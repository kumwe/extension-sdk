# Kumwe extension SDK

`kumwe/extension-sdk` is the canonical author-facing contract for Kumwe extensions. It provides:

- strict, versioned manifest parsing and a canonical contribution graph;
- host-neutral SPI types for executable extension bindings;
- independently reusable scaffold, build, inspection, signing and conformance components; and
- SDK-owned public API, generation and resource records verified on every build.

The namespace is `Kumwe\Extension`. A host consumes these types directly. The SDK ships no aliases,
namespace translation, compatibility bridge, host-domain implementation, admission decision or
runtime storage.

## Authority boundary

The signed manifest is the sole declarative authority. An extension binding provider binds executable
implementations to identifiers that already exist in the validated manifest graph. Code cannot add a
route, event, job, projection, policy or composition declaration.

The SDK reports bounded structural, archive, signature and conformance facts. A consuming host owns
admission policy, trust state, authorization, activation and persistence. The host must pass the same
SDK inspection report into its policy layer instead of implementing a parallel scanner.

## Installation

```bash
composer require kumwe/extension-sdk
```

Composer installs the canonical library contracts used by the SDK, including conversion value types.
The Studio document-schema authority is supplied by `kumwe/producer` at the released 0.3.0.

The author toolchain is a PHP API. Start with `Kumwe\Extension\Toolchain\ComponentScaffolder`,
`DeterministicPackageBuilder`, `PackageSigner`, `PackageInspector` or
`ExtensionPackageConformance`. The installed `vendor/bin/kumwe-extension` command exposes the same
fact-reporting `build`, `inspect`, `evidence` and `conformance` lanes. Host integration is described
in [`docs/host-integration.md`](docs/host-integration.md).

## Quality gate

```bash
composer install
composer check
composer install --no-dev --optimize-autoloader
composer smoke
```

`composer check` validates Composer metadata and strict autoloading, lints every PHP file, checks the
canonical contract records, runs the test suite, performs max-level PHPStan analysis and audits installed
dependencies. CI and release automation run the same gate, then prove a production-only install.

See [`CHARTER.md`](CHARTER.md), [`docs/engineering-standard.md`](docs/engineering-standard.md) and
[`CHANGELOG.md`](CHANGELOG.md).

## License

Apache License 2.0. See [`LICENSE`](LICENSE).

## Public API and construction

The canonical Version 2 manifests are [public API](resources/public-api/v1.json),
[capabilities](resources/capabilities/v1.json) and [service map](resources/service-map/v1.json).
The [complete API reference](docs/public-api.md) is generated from every declared public type,
member signature and source PHPDoc. Existing SDK classification, generation fixtures and resource
digest inventories remain authoritative SDK compatibility records and keep their existing paths.

The SDK uses explicit construction and has no ConfigProvider. An authoring operation receives its
canonical encoder, inspectors and signing collaborators from its caller; the library cannot select
host trust, signing authority or a compatible native tuple. See [architecture](docs/architecture.md)
for lifetimes and I/O, and [direct construction](examples/direct-construction.php) for composition.
The CLI verifies an explicitly supplied native tuple per invocation. The runtime PHP API and
generated production extensions depend on the portable encoder contract; native Computation is
required only by the optional CLI and development/generated test toolchain.

[MIGRATION-HANDOFF.md](MIGRATION-HANDOFF.md) records the prepublication ownership and next-consumer
contract. An independent external release attestation must identify actual published bytes before
release verification is claimed. This handoff does not perform App integration.
