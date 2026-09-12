import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';

export const releaseRecordPath = 'docs/release-record.md';
export const legacyRecordPath = 'MIGRATION-HANDOFF.md';
const paths = [releaseRecordPath, legacyRecordPath];
const fact = (value, message) => { if (!value) throw new Error(message); };
const digest = bytes => crypto.createHash('sha256').update(bytes).digest('hex');

export function releaseRecordArtifact(root) {
  const found = paths.filter(p => fs.existsSync(path.join(root, p)));
  fact(found.length === 1, 'Exactly one current release record or legacy handoff is required.');
  const relative = found[0];
  fact(fs.lstatSync(path.join(root, relative)).isFile(), 'Release record must be a regular file.');
  const bytes = fs.readFileSync(path.join(root, relative));
  return { path: relative, bytes, sha256: digest(bytes) };
}

export function verifyRecordDigest(evidence) {
  // Old immutable verifier output predates the explicit path field.
  const expectedPath = evidence.release_record_path ?? legacyRecordPath;
  fact(paths.includes(expectedPath), 'Unsupported verified release record path.');
  const artifact = releaseRecordArtifact(evidence.input.package_root);
  const manifests = evidence.manifests_and_corpora.filter(m => paths.includes(m.path));
  fact(artifact.path === expectedPath && manifests.length === 1 && manifests[0].path === expectedPath
    && manifests[0].sha256 === evidence.handoff_sha256 && artifact.sha256 === evidence.handoff_sha256,
  'Attestation must retain the unchanged independently verified release record digest.');
  return artifact;
}
