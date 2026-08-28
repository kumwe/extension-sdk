# Kumwe extension SDK

**The SDK builds, signs, verifies and reports. The App decides, trusts, activates and enforces.**

`kumwe/extension-sdk` is the whole author-facing SDK for building
[Kumwe App](https://github.com/kumwe/app) extensions. One package, two consumers:

- **Extension authors** get the contract — the manifest schemas across all generations (currently
  six, each frozen forever once published), the SPI interfaces an extension implements, the
  classification data that says exactly which types are public and what you do with each, and the
  signed compatibility fixtures that pin the promised surface — plus the toolchain an author runs
  in CI: scaffold, deterministic package builder, signer, inspector/verifier, and the conformance
  runner.
- **Kumwe App itself** consumes the same package and runs the same inspector at admission time.
  "The SDK and admission produce the same findings" is enforced by one shared implementation, not
  by keeping two copies in sync. What your CI proved is what admission finds.

## The boundary

The SDK carries the contract and the toolchain, and deliberately nothing else. Admission policy,
trust registries and trust state, lifecycle activation, and capability gating are the App's: the
App decides and enforces; the SDK verifies and reports. The SDK holds no authority, no storage,
and no App domain imports. The full statement of scope — including the three never-clauses — is
law in [`CHARTER.md`](CHARTER.md).

## The drop-in promise

The extension API this SDK carries already ships inside Kumwe App, pinned under `Kumwe\App\...`
fully qualified class names. Extraction changes where the code lives, never what an extension
observes:

- canonical names move to `Kumwe\Extension\...` in this repository;
- the App keeps a `class_alias` shim for every pinned `Kumwe\App\...` FQCN, permanently;
- the App's signed compatibility fixtures, across every manifest generation, are replayed
  unmodified as the proof that nothing observable changed.

No published extension recompiles, re-declares, or breaks. The App becomes consumer #1 of this
package, pinning it exactly; the agreement is recorded in
[`docs/app-agreement.md`](docs/app-agreement.md).

## Status

**Extracting** — the frozen contract artifacts are vendored and digest-verified under
`resources/` (phase E-1); the remaining extraction phases are tracked in
[`docs/roadmap.md`](docs/roadmap.md). Until a phase lands, Kumwe App's in-tree implementation
remains the authority for that phase's surface.

## Checking your work

The lane is dependency-free and runs on a clean clone with no composer install:

```bash
composer check        # lint + docs + contract + test, or run the four directly:
php tools/lint.php
php tools/check-docblocks.php
php tools/verify-contract.php
php tests/run.php
```

Every commit passes it. How the code is written — layers, documentation blocks, the
real-outcomes testing standard — is stated in
[`docs/engineering-standard.md`](docs/engineering-standard.md).

## License

Apache License 2.0. See [`LICENSE`](LICENSE).
