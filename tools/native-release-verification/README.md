# Independent native release verification

This verifier checks actual published Engine and PHP binding source releases. It does not publish,
change tags, insert an artifact's own final identity into source, or qualify App integration.

`releases.json` contains only reviewed, already published coordinates: `name`, exact stable `version`,
full `source_commit`, independently observed `archive_sha256`, and optional `upstream_receipts` keyed
by prerequisite repository. Each external receipt identifies an immutable SDK evidence URI and its
own SHA256. These later receipts establish verification of the exact source-time commitments without
rewriting immutable source-time observation flags. Its initial empty list runs the
refusal tests without inventing a release. Add Engine only after publication, then the binding only
after its Engine dependency has an independent durable receipt. PR and default-branch workflows
run the same verifier. A successful source build alone cannot populate this list.
`fixtures/engine-handoff.md` is an implementation-stage handoff used solely to exercise the complete
native handoff schema. Synthetic test coordinates and receipts remain isolated regression inputs.

The verifier observes the exact tag, published five-asset release, reviewed source, successful native
quality and publisher jobs, and default-branch ancestry. Push and manual default-branch runs must
execute every required native lane successfully. A separately failed downstream binding notification
is recorded without erasing successful quality/publication evidence; failed or skipped quality lanes
are never accepted. It verifies GitHub OIDC provenance for each of the four original source-bundle
assets against the exact repository, commit, branch and `ci.yml` publisher workflow; self-hosted signing
runners are refused. The fifth file is the original signature bundle. Publisher SPDX, source metadata
and checksums remain original signed payloads; no nonexistent provenance file is fabricated.

Engine source must be the exact result of a merged default-branch PR. Binding source may be
that same direct proof, or the documented deterministic Engine sync performed on the exact
result of a merged binding PR. The latter requires a separately merged Engine PR and its
independently verified exact archive receipt. A successful `engine-sync.yml` run must identify
that reviewed binding parent, and preserved job logs must prove its actual checkout, the
exact Engine archive identity and the pushed release commit. Only the five generated binding
identity files and `vendor/engine/` may change. The verifier replays the reviewed parent's
sync tool against the original verified Engine archive with networking disabled and requires
the entire resulting Git tree, including all paths and modes, to equal the released tree.
The observed workflow, reviews, logs, digests and replay results are preserved in the evidence
envelope. An unreviewed parent, unrelated direct push, changed workflow or extra source edit
cannot qualify through this route; all native quality, signature and offline consumer checks
still run against the exact released source.

The published archive is reproduced from its exact checkout using Engine's release-bundle shell tool
or the binding's PHP source verifier,
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

The binding's `kumwe-embedded-engine/v2` lock names the original compressed release archive.
`verification.json.embedded_engine` records `source_commit` and `release_archive_sha256`, which
must match the actual Engine receipt. The exact original `embedded-engine-source.tar.gz` bytes
are retained; every extracted file must match both the lock and the embedded source tree. A raw
Git TAR digest is never substituted for the compressed published archive identity. The full module tuple is in
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
