# Independent native release verification

This verifier checks actual published Engine and PHP binding source releases. It does not publish,
change tags, insert an artifact's own final identity into source, or qualify App integration.

`releases.json` contains only reviewed, already published coordinates: `name`, exact stable `version`,
full `source_commit`, and independently observed `archive_sha256`. Its initial empty list runs the
refusal tests without inventing a release. Add Engine only after publication, then the binding only
after its Engine dependency has an independent durable receipt. PR and default-branch workflows
run the same verifier. A successful source build alone cannot populate this list.
`fixtures/engine-handoff.md` is an implementation-stage handoff used solely to exercise the complete
native handoff schema. Synthetic test coordinates and receipts remain isolated regression inputs.

The verifier observes the exact tag, published six-asset release, merged PR, successful native
quality and publisher jobs, and default-branch ancestry. It verifies GitHub OIDC provenance for
each of the five original source-bundle assets against the exact repository, commit, branch and
publisher workflow; self-hosted signing runners are refused. The sixth file is the original
signature bundle. Publisher SPDX, checksums and unsigned source-assembly metadata are preserved
with their correct identities; the unsigned statement is never described as signed provenance.

The published archive is reproduced from its exact checkout using the published source verifier,
then extracted into a fresh build directory. Every handoff digest and complete native handoff
schema is checked. Native ABI/API/capability and corpus inventories are checked by their owning
source validators and the standalone/installed native consumers. PHP package manifest schemas
are not substituted for the native repositories' distinct manifest formats.

Every portable/semantic/Engine prerequisite receipt is fetched from its immutable external Git
YAML URI, checked against its exact own SHA256, and validated against the full authoritative
release-attestation schema. Portable API, capability and corpus commitments are matched to the
receipt. Artifact source coordinates and recorded archive digests must agree.

Clean builds run in a new Linux network namespace with only the loopback interface. Engine runs
its complete CMake tests and an installed standalone C11/CLI consumer. Binding uses the exact
PIE 1.4.10 installer, then runs real module reflection, complete source/build-derived runtime
tuple comparison, standalone Engine CLI parity, PHPT and lifecycle checks. Host compiler/PHP
identities are observed per build; a tuple from a different host is never reused as its expected
value. No PHP algorithm fallback or package workspace is used as an installed native consumer.

The compressed release `archive_sha256` and the raw embedding TAR digest are separate.
`verification.json.embedded_engine` records `source_commit`, `raw_tar_sha256` and the distinct
`release_archive_sha256`; the latter must match the actual Engine receipt. The exact raw
`embedded-engine-source.tar` bytes are retained. The full module tuple is in
`build-evidence/actual-tuple.json` and `verification.json.build.actual_tuple`. The final YAML's
`abi_and_capabilities` is the deliberately smaller projection required by the authoritative
schema: `abi_major`, string `capabilities`, and `corpus_digests`.

The first uploaded artifact preserves original publisher files, GitHub observations, upstream
YAML bytes, signature verification outputs, source identity checks and offline build results.
Only after all checks pass does finalization emit a full-schema-valid `RELEASE-ATTESTATION.yaml`
linked to that evidence artifact. These retention-governed uploads are subsequently preserved
byte for byte under archive-excluded `evidence/` paths on the existing SDK readiness PR branch. Their ZIP,
YAML member, and native source digests keep distinct names and meanings.

Run the offline verifier regressions with:

```sh
npm ci --prefix tools/release-verification --ignore-scripts --no-audit
node tools/native-release-verification/test-verifier.mjs
python3 tools/native-release-verification/test-extract-source.py
```
