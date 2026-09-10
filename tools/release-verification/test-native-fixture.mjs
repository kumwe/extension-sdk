import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import crypto from 'node:crypto';
import { validateNativeFixture, tupleValue } from './build-native-fixture.mjs';

// Synthetic metadata-only fixtures. They never execute a build, represent a published source
// bundle, verify a signature, issue an attestation or manufacture an actual runtime success.
// The canonical repository names exercise owner checks; all versions, commits, receipt contents
// and example.invalid artifact URLs below are test-only and are never release coordinates.
const workspace = fs.mkdtempSync(path.join(os.tmpdir(), 'kumwe-native-fixture-tests-'));
const fixtureVersion = '9.8.7';
const fixtureEvidence = 'SYNTHETIC METADATA-ONLY TEST FIXTURE; NEVER PUBLISHED RELEASE EVIDENCE';
const digest = value => crypto.createHash('sha256').update(value).digest('hex');
const write = (file, value) => {
  fs.mkdirSync(path.dirname(file), { recursive: true });
  fs.writeFileSync(file, typeof value === 'string' ? value : JSON.stringify(value));
};
let serial = 0;
function fixture() {
  const base = path.join(workspace, String(++serial));
  const root = path.join(base, 'source');
  const engine = { version: fixtureVersion, commit: 'c'.repeat(40), archive_sha256: 'd'.repeat(64) };
  const members = {
    'composer.json': { name: 'kumwe/kumwe-engine', type: 'php-ext' },
    'php_kumwe_engine.h': `#define PHP_KUMWE_ENGINE_VERSION "${fixtureVersion}"\n`,
    'resources/compatibility/v1.json': { schema: 'kumwe-zend-compatibility/v1',
      version: fixtureVersion, thread_models: ['NTS', 'ZTS'], fallback: false },
    'resources/engine-lock.json': { schema: 'kumwe-embedded-engine/v2',
      repository: 'https://github.com/kumwe/engine', release: `v${fixtureVersion}`, ...engine },
  };
  for (const [name, value] of Object.entries(members)) write(path.join(root, name), value);
  const sbom = { spdxVersion: 'SPDX-2.3', files: Object.keys(members).map(name => ({ fileName: './' + name,
    checksums: [{ algorithm: 'SHA256', checksumValue: digest(fs.readFileSync(path.join(root, name))) }] })) };
  write(path.join(base, 'source.spdx.json'), sbom);
  write(path.join(base, 'source.tar.gz'), 'metadata-validation-only fixture; never a real native bundle');
  const input = { schema: 'kumwe-verified-native-fixture-input/v1', package: 'kumwe/kumwe-engine',
    version: fixtureVersion, source_commit: 'a'.repeat(40), source_directory: root,
    source_archive_path: path.join(base, 'source.tar.gz'), source_sbom_path: path.join(base, 'source.spdx.json'),
    source_record_path: path.join(base, 'source.json'), release_attestation_path: path.join(base, 'test-only-receipt.json') };
  input.source_archive_sha256 = digest(fs.readFileSync(input.source_archive_path));
  input.source_sbom_sha256 = digest(fs.readFileSync(input.source_sbom_path));
  const record = { schema: 'kumwe-engine-php-source-release/v1', package: input.package,
    source: { repository: 'https://github.com/kumwe/kumwe-engine', commit: input.source_commit },
    version: input.version, tag: `v${input.version}`, archive: { sha256: input.source_archive_sha256 },
    sbom: { sha256: input.source_sbom_sha256 }, engine, stable_source_blockers: [] };
  write(input.source_record_path, record);
  input.source_record_sha256 = digest(fs.readFileSync(input.source_record_path));
  const receipt = { schema: 'kumwe-release-attestation/v2', artifact_kind: 'php_extension',
    migration_id: 'KUMWE-MIG-2099-999', change_set: 'KUMWE-CS-2099-999',
    repository: record.source.repository, merge_commit: input.source_commit,
    version: input.version, tag: record.tag,
    source_archive: { url: 'https://example.invalid/test-only/source.tar.gz', sha256: input.source_archive_sha256 },
    artifacts: [{ identity: 'source.json', url: 'https://example.invalid/test-only/source.json',
      sha256: input.source_record_sha256 }], manifests_and_corpora: [], abi_and_capabilities: null,
    sbom: { url: 'https://example.invalid/test-only/source.spdx.json', sha256: input.source_sbom_sha256 },
    provenance: fixtureEvidence, release_workflow: fixtureEvidence,
    registry_or_pie_verification: [fixtureEvidence], clean_consumer_or_build_verification: [fixtureEvidence],
    verified_at: '2099-01-01T00:00:00Z', verified_by: fixtureEvidence, status: 'verified' };
  write(input.release_attestation_path, receipt);
  input.release_attestation_sha256 = digest(fs.readFileSync(input.release_attestation_path));
  return input;
}

function mutateReceipt(input, mutate) {
  const receipt = JSON.parse(fs.readFileSync(input.release_attestation_path, 'utf8'));
  mutate(receipt);
  write(input.release_attestation_path, receipt);
  input.release_attestation_sha256 = digest(fs.readFileSync(input.release_attestation_path));
}

const failures = [
  ['candidate version', input => { input.version = '1.0.0-dev'; }, /exact stable version/],
  ['floating source', input => { input.source_commit = 'main'; }, /exact stable version/],
  ['wrong package', input => { input.package = 'kumwe/engine'; }, /canonical binding/],
  ['changed archive', input => { fs.appendFileSync(input.source_archive_path, 'changed'); }, /digest differs/],
  ['changed source', input => { fs.appendFileSync(path.join(input.source_directory, 'php_kumwe_engine.h'), 'changed'); }, /complete published inventory/],
  ['extra source', input => { write(path.join(input.source_directory, 'untracked.php'), '<?php'); }, /complete published inventory/],
  ['source symlink', input => { fs.symlinkSync(input.source_record_path, path.join(input.source_directory, 'link')); }, /symlink/],
  ['stable blocker', input => {
    const record = JSON.parse(fs.readFileSync(input.source_record_path, 'utf8'));
    record.stable_source_blockers = ['source is still a candidate'];
    write(input.source_record_path, record);
    input.source_record_sha256 = digest(fs.readFileSync(input.source_record_path));
  }, /selected verified stable bundle/],
  ['source identity mismatch', input => {
    const record = JSON.parse(fs.readFileSync(input.source_record_path, 'utf8'));
    record.source.commit = 'b'.repeat(40);
    write(input.source_record_path, record);
    input.source_record_sha256 = digest(fs.readFileSync(input.source_record_path));
  }, /selected verified stable bundle/],
  ['absent receipt', input => {
    delete input.release_attestation_path;
    delete input.release_attestation_sha256;
  }, /canonical regular file/],
  ['changed receipt', input => { fs.appendFileSync(input.release_attestation_path, 'changed'); }, /digest differs/],
  ['failed receipt', input => mutateReceipt(input, receipt => { receipt.status = 'failed'; }),
    /complete independently verified receipt/],
  ['incomplete receipt', input => mutateReceipt(input, receipt => { receipt.known_gaps = ['fixture gap']; }),
    /complete independently verified receipt/],
  ['malformed receipt', input => mutateReceipt(input, receipt => { delete receipt.verified_by; }),
    /Full release attestation schema failed/],
  ['wrong receipt commit', input => mutateReceipt(input, receipt => { receipt.merge_commit = 'b'.repeat(40); }),
    /Upstream receipt coordinate or artifact kind differs/],
  ['wrong receipt archive', input => mutateReceipt(input, receipt => { receipt.source_archive.sha256 = 'e'.repeat(64); }),
    /Upstream receipt archive digest differs/],
  ['wrong receipt kind', input => mutateReceipt(input, receipt => { receipt.artifact_kind = 'native_cpp'; }),
    /Upstream receipt coordinate or artifact kind differs/],
  ['wrong receipt inventory', input => mutateReceipt(input, receipt => { receipt.sbom.sha256 = 'f'.repeat(64); }),
    /receipt does not bind/],
  ['wrong receipt source metadata', input => mutateReceipt(input, receipt => { receipt.artifacts[0].sha256 = '0'.repeat(64); }),
    /receipt does not bind/],
];

try {
  assert.equal(validateNativeFixture(fixture()).input.package, 'kumwe/kumwe-engine');
  for (const [label, mutate, expected] of failures) {
    const input = fixture();
    mutate(input);
    assert.throws(() => validateNativeFixture(input), expected, label);
  }
  assert.deepEqual(tupleValue({ a: 1, b: [true, '2'] }), tupleValue({ b: [true, '2'], a: 1 }));
  assert.notDeepEqual(tupleValue({ a: 1 }), tupleValue({ a: '1' }));
  assert.notDeepEqual(tupleValue({ a: [1, 2] }), tupleValue({ a: [2, 1] }));
  console.log(`Native fixture metadata checks passed: one synthetic input and ${failures.length} hostile cases; no native release claim.`);
} finally {
  fs.rmSync(workspace, { recursive: true, force: true });
}
