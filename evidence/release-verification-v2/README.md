# Preserved independent release evidence

This branch contains only external verification records and the original downloaded
GitHub Actions ZIP envelopes. It is not an SDK source or release branch.

Each version/run directory preserves the exact attestation YAML, its original ZIP,
the original complete evidence ZIP (including source archive, SPDX source inventory,
verification provenance and logs), and the unchanged verification JSON for inspection.
`index.json` binds every retained byte stream to its SHA256 and original run/artifact
URLs. No original record is rewritten to predict its preservation commit or claim a
digest of itself. Consumers pin the preservation commit in raw GitHub file URLs.

Actions URLs document the observed original publication and may later expire under
artifact retention. The identical retained ZIP bytes remain available in Git at the
pinned commit. Digest entries are accompanied by retained original bytes.

These framework PHP records qualify the specific unchanged releases listed; they do
not claim App adoption, combined package graph readiness, or native artifact
qualification. Native and composed graph evidence is preserved separately when its
actual required checks pass. No package source, main branch, tag or release page is
modified by this preservation branch.

## Superseded historical receipts

The first four preserved receipts are explicitly superseded. Their YAML used richer
objects than the authoritative external-attestation schema permits. Conversion0.1.4
also fails its authoritative capability schema and requires an actual source successor.
These original bytes are retained for audit and must not authorize downstream release
admission. New schema-valid receipts will be appended only after all gates pass.
