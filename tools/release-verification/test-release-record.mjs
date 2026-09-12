import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { handoffRecord } from './verify-release.mjs';
import { releaseRecordArtifact, releaseRecordPath, legacyRecordPath, verifyRecordDigest } from './release-record.mjs';

const root = path.resolve(import.meta.dirname, '../..');
const input = { name: 'kumwe/extension-sdk' };
const current = fs.readFileSync(path.join(root, releaseRecordPath), 'utf8');
const record = handoffRecord(current, input, releaseRecordPath);
assert.equal(record.schema, 'kumwe-package-release-record/v1');
assert.throws(() => handoffRecord(current, input, legacyRecordPath));
assert.throws(() => handoffRecord(current, { name: 'kumwe/other' }, releaseRecordPath));
const encode = value => '---\n' + JSON.stringify(value) + '\n---\n';
for (const mutate of [r => { r.state = 'draft_pr_open'; }, r => { r.next_task = {}; },
  r => { r.target.branch = 'old-branch'; }, r => { r.governance.completion_claim = true; },
  r => { delete r.framework_php; }, r => { delete r.ownership.public_manifests; },
  r => { r.ownership.public_manifests[0].sha256 = 'invalid'; }]) {
  const changed = structuredClone(record); mutate(changed);
  assert.throws(() => handoffRecord(encode(changed), input, releaseRecordPath));
}
// Reconstruct a schema-only historical fixture; it is never release evidence.
const legacy = structuredClone(record);
legacy.schema = 'kumwe-migration-handoff/v2'; legacy.state = 'draft_pr_open';
legacy.source.active_related_pull_requests = [];
legacy.target.branch = 'synthetic-legacy'; legacy.target.pull_request = 'https://example.invalid/pr/1';
legacy.next_task = legacy.consumer_contract; delete legacy.consumer_contract; legacy.next_task.phase_name = 'synthetic';
legacy.concurrency = { likely_conflict_files: [], related_migrations: [], ownership_conflicts: [], integration_train: null, resolution_rule: 'semantic-preservation' };
Object.assign(legacy.governance, { roadmap_source_sha256: 'a'.repeat(64), roadmap_refs: [], non_roadmap_refs: [] });
handoffRecord(encode(legacy), input, legacyRecordPath);
assert.throws(() => handoffRecord(encode(legacy), input, releaseRecordPath));

const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'sdk-release-record-'));
try {
  assert.throws(() => releaseRecordArtifact(dir));
  fs.mkdirSync(path.join(dir, 'docs'));
  for (const relative of [legacyRecordPath, releaseRecordPath]) {
    fs.writeFileSync(path.join(dir, relative), relative === legacyRecordPath ? encode(legacy) : current);
    const artifact = releaseRecordArtifact(dir);
    const evidence = { input: { package_root: dir }, handoff_sha256: artifact.sha256,
      manifests_and_corpora: [{ path: relative, sha256: artifact.sha256 }] };
    if (relative === releaseRecordPath) evidence.release_record_path = relative;
    assert.equal(verifyRecordDigest(evidence).path, relative);
    assert.throws(() => verifyRecordDigest({ ...evidence, release_record_path: '../README.md' }));
    assert.throws(() => verifyRecordDigest({ ...evidence, handoff_sha256: '0'.repeat(64) }));
    assert.throws(() => verifyRecordDigest({ ...evidence, manifests_and_corpora: [...evidence.manifests_and_corpora, ...evidence.manifests_and_corpora] }));
    const other = relative === legacyRecordPath ? releaseRecordPath : legacyRecordPath;
    fs.writeFileSync(path.join(dir, other), 'ambiguous');
    assert.throws(() => releaseRecordArtifact(dir));
    assert.throws(() => verifyRecordDigest(evidence));
    fs.unlinkSync(path.join(dir, other));
    fs.appendFileSync(path.join(dir, relative), '\nchanged after verification');
    assert.throws(() => verifyRecordDigest(evidence));
    fs.unlinkSync(path.join(dir, relative));
  }
} finally { fs.rmSync(dir, { recursive: true, force: true }); }
console.log('Current and legacy release-record schema, discovery and attestation digest regressions passed.');
