# Kumwe extension SDK

[![Packagist version][badge-packagist-version-image]][badge-packagist-version-link]
[![CI][badge-ci-image]][badge-ci-link]
[![PHP][badge-php-image]][badge-php-link]
[![License][badge-license-image]][badge-license-link]

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

Requires PHP 8.5 with JSON, mbstring, Sodium and ZIP. Composer installs the exact canonical
library contracts declared in [composer.json](composer.json). Studio document-schema authority
belongs to `kumwe/producer`. Core and other consumers qualify and pin an exact SDK release.

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

## Compatibility, releases and support

The [host integration contract](docs/host-integration.md) and [App agreement](docs/app-agreement.md)
define Core responsibilities, exact version pins, generation compatibility and shared inspection.
The [release contract record](docs/release-record.md) binds exported APIs, manifest digests and
consumer verification obligations. Published versions, source CI and Core integration are distinct
states; passing CI alone does not establish independent release qualification.

See [releases](https://github.com/kumwe/extension-sdk/releases), [release policy](docs/releasing.md),
[graph qualification](docs/release-qualification.md) and [test ownership](docs/test-ownership.md).
Report defects or missing contract capabilities in [GitHub issues](https://github.com/kumwe/extension-sdk/issues).

[badge-packagist-version-image]: https://img.shields.io/packagist/v/kumwe/extension-sdk
[badge-packagist-version-link]: https://packagist.org/packages/kumwe/extension-sdk
[badge-ci-image]: https://github.com/kumwe/extension-sdk/actions/workflows/ci.yml/badge.svg?branch=main
[badge-ci-link]: https://github.com/kumwe/extension-sdk/actions/workflows/ci.yml
[badge-php-image]: https://img.shields.io/packagist/dependency-v/kumwe/extension-sdk/php
[badge-php-link]: composer.json
[badge-license-image]: https://img.shields.io/packagist/l/kumwe/extension-sdk
[badge-license-link]: LICENSE
