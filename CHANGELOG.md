# Changelog

All notable changes to `kumwe/extension-sdk` are recorded here, in
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) format. Open work lives in
[`docs/roadmap.md`](docs/roadmap.md); a claim lands here only when `composer check` proves it on a
clean clone.

## [Unreleased]

### Added

- E-1: the frozen contract as verified artifacts. `resources/` vendors the contract records
  (`generations.json`, `classification.json`, the contract README), the four manifest schema pin
  fixtures, the ten compatibility pin documents, and the six signed compatibility fixture
  generations — every file a byte-identical copy of the Kumwe App original at commit
  `da19226`, pinned by `resources/PIN.json`. `tools/verify-contract.php` joins `composer check`
  as the `contract` script: it sweeps every artifact against its recorded source digest, refuses
  unpinned or missing files, recomputes every generation's `surface_digest` over its canonical
  bytes exactly as the App's `extension:contract` gate does, and holds the classification to the
  vendored pin fixtures. Tamper-direction tests prove the verifier fails closed for a changed
  byte, a widened frozen generation, an unpinned file, and a missing artifact.
- Founding: the charter, the inherited engineering standard, the package skeleton
  (`composer.json`, the dependency-free lint/docs/test lane), the extraction roadmap, and the
  Kumwe App consumer agreement.
