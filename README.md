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
The Studio document-schema authority is supplied by `kumwe/producer` in the 0.2 release line.

The author toolchain is a PHP API. Start with `Kumwe\Extension\Toolchain\ExtensionScaffolder`,
`DeterministicPackageBuilder`, `PackageSigner`, `PackageInspector` or
`ExtensionPackageConformance`. Host integration is described in
[`docs/host-integration.md`](docs/host-integration.md).

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
