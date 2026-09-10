# Stable native fixture for SDK package checks

Run `node tools/release-verification/build-native-fixture.mjs INPUT_JSON OUTPUT_DIRECTORY` only
after the independent native verifier has qualified the actual stable binding source bundle,
publisher provenance/signatures, embedded Engine and supported platform. This helper performs no
network access or native publication and never writes `GITHUB_ENV`.

The input schema is `kumwe-verified-native-fixture-input/v1` with:

- `package`: `kumwe/kumwe-engine`;
- `version` and `source_commit`: exact independently verified stable version and full commit;
- `source_directory`: canonical absolute extracted source root, with no checkout/build additions;
- `source_record_path` and `source_record_sha256`: the verified bundle's `source.json`;
- `source_archive_path` and `source_archive_sha256`: its original published source archive;
- `source_sbom_path` and `source_sbom_sha256`: its published `source.spdx.json`.
- `release_attestation_path` and `release_attestation_sha256`: the independently verified binding
  receipt, validated against the complete release-attestation schema and exact source/archive/metadata/SPDX identities.

All paths must be canonical absolute paths. The helper binds those bytes and the complete extracted
file inventory to the source record, and refuses candidate versions, source blockers or an absent,
failed, incomplete or mismatched independent receipt. The immutable source's older observation flags
are preserved; verification comes from the externally supplied receipt. It copies the source to a new private output directory, verifies arginfo,
binding and Engine ownership, then executes actual `phpize`, configure and make commands.

The expected Computation constructor tuple comes from the binding's `tools/expected-tuple.php`.
The complete expected Runtime tuple is independently assembled from generated Engine capabilities,
recorded build identity, the binding API and source limits before querying the actual module.
Every typed field must equal the actual internal Runtime handshake. Original source and bundle
bytes are checked again after building.

`OUTPUT_DIRECTORY/environment.json` contains only scoped `PHPRC` and
`KUMWE_NATIVE_EXPECTED_TUPLE` values. Apply these overrides exclusively to SDK Composer/full-package
check subprocesses. Keep subsequent portable consumers in the original native-free environment.
`fixture.json` records the actual module digest, selected source identity, real PHP executable and
expected tuple location. This is build-fixture evidence, explicitly not a release attestation.
Use the reported `php_binary` if a local PHP wrapper overrides `PHPRC`.

`node tools/release-verification/test-native-fixture.mjs` exercises input refusal paths using
explicit synthetic metadata only. A successful stable module build still requires the real
independently qualified source bundle.
