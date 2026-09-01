# Extension SDK charter

This repository owns the canonical, independently reusable Kumwe extension contract and author tooling.
Every change must preserve these boundaries.

## The SDK owns

1. Versioned manifest grammar, strict parsing and canonical validated contribution values.
2. Host-neutral author SPI and executable binding contracts.
3. Deterministic scaffold, package build, signing, inspection and conformance tooling.
4. Typed, stable findings over bounded package bytes.
5. SDK-owned public API, generation and resource records.

## The SDK does not own

1. Admission, trust, authorization or activation decisions.
2. Host storage, registries, deployment state or application-domain implementations.
3. A second namespace, aliases, remapping, adapters or a historical runtime path.
4. A second declaration channel in executable code.
5. Copies of contracts owned by another Kumwe library.

## Core invariants

- `Kumwe\Extension` is the only SDK namespace an extension or host consumes.
- The validated manifest is the sole declarative authority. Providers bind behavior to declared IDs.
- Inspection is implemented once in this package. Hosts apply policy to its report without rewriting it.
- Canonical values are validated once, exported deterministically and consumed directly by hosts.
- Attacker-influenced bytes are bounded before expensive work and never executed during inspection.
- Runtime dependencies are explicit Composer requirements and production autoloading is release-tested.

The executable standard is `composer check`; release additionally proves `composer install --no-dev` and
`composer smoke` on the exact commit being tagged.
