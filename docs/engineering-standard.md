# Engineering standard

The [charter](../CHARTER.md) defines the boundary; this document defines the package gate.

## Architecture

| Layer | Namespace | Responsibility |
| --- | --- | --- |
| Contract | `Kumwe\Extension\Contract` | Canonical identities and SDK-owned records |
| Manifest | `Kumwe\Extension\Manifest` | Strict grammar and one validated contribution graph |
| SPI | `Kumwe\Extension\Spi` | Host-neutral author ports and immutable values |
| Package | `Kumwe\Extension\Package` | Bounded archive inspection and typed findings |
| Toolchain | `Kumwe\Extension\Toolchain` | Scaffold, build, sign, inspect and conformance APIs |

Dependencies point toward narrower canonical contracts. Host policy, state and implementations never enter
the SDK. Contracts already owned by another Kumwe library are direct Composer dependencies, never copied,
wrapped or remapped.

## Code

- PHP 8.5, strict types, PSR-4 and PSR-12.
- Final classes by default; readonly immutable values where applicable.
- Native parameter, return and property types on every public member.
- One class-like declaration per file and no dynamic service location or mutable global state.
- Canonical JSON, validation, hashing and findings logic each have one implementation.
- Bounds are enforced before parsing, hashing, decompression or traversal of untrusted input.
- All runtime PHP extensions and libraries are declared in `composer.json`.

## Public API

The signed manifest is the sole declarative authority. Public callback values are complete immutable views
of validated declarations, not reduced shadow DTOs. A provider binds executable implementations to exact
declared identifiers; undeclared, foreign, wrong-kind, duplicate and missing required bindings fail closed.

Every class-like declaration, method, function, non-promoted property, constant and enum case has an
accurate documentation block with `@since`. Precise generics and array shapes are required. The gate is
`php tools/check-docblocks.php`.

## Testing

- Assert observable contracts and stable refusal codes, not private implementation details.
- Cover hostile inputs and all size, depth, count, identity and lifecycle boundaries.
- Exercise each fixture provider through a manifest-aware recording registrar and invoke its bindings.
- Prove deterministic output by comparing independently produced bytes.
- Test parse/export/reparse idempotence for canonical values.
- Run against Composer-installed dependencies; never rely on undeclared global classes.

## Complete gate

`composer check` runs Composer validation, strict autoload generation, lint, documentation verification,
contract verification, tests, max-level PHPStan and dependency audit. CI and release use the same command.
They then reinstall with `--no-dev` and run `composer smoke` to prove the published runtime surface.
