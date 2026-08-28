# Releasing the extension SDK

The SDK versions independently under semantic versioning; alignment with Kumwe App travels
through the pinned contract in `resources/PIN.json` and the consumer obligations in
[`app-agreement.md`](app-agreement.md), never through matching version numbers.

Releasing is merging. Every Kumwe PHP library delivers the same way:

1. Land the work on `main` with its `CHANGELOG.md` section for the next version — the heading
   `## [X.Y.Z] - date` is the release record.
2. The `Release on record` workflow runs on every push to `main`: it re-proves the complete check
   lane, reads the newest recorded version, and when that version has no tag yet it creates
   `vX.Y.Z` through the repository API and publishes the GitHub release naming the exact App
   contract pin it carries. Nobody pushes a tag by hand; a push that records no new version is a
   verification-only run.
3. Packagist follows tags through its GitHub integration — submit `kumwe/extension-sdk` once at
   packagist.org and every later release appears without a credential in this repository.

Version policy:

- **Patch** — behaviour fixes that keep every finding, artifact byte, and refusal identical.
- **Minor** — new capability that no existing consumer must act on; a contract re-pin that adds
  a generation without moving any existing surface.
- **Major** — a change a consumer must act on; the one-implementation findings invariant and the
  App agreement's canonical-name contract make any observable difference a major by definition.
- While the extraction is settling, the SDK stays `0.x` and the App pins exactly.
