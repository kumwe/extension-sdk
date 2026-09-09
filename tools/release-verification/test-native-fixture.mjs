import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import crypto from 'node:crypto';
import { validateNativeFixture, tupleValue } from './build-native-fixture.mjs';

// Synthetic metadata-only fixtures. They never execute a build, represent a published source
// bundle, verify a signature, issue an attestation or manufacture an actual runtime success.
const workspace = fs.mkdtempSync(path.join(os.tmpdir(), 'kumwe-native-fixture-tests-'));
const digest = value => crypto.createHash('sha256').update(value).digest('hex');
const write = (file, value) => {
  fs.mkdirSync(path.dirname(file), { recursive: true });
  fs.writeFileSync(file, typeof value === 'string' ? value : JSON.stringify(value));
};
let serial = 0;
function fixture() {
  const base = path.join(workspace, String(++serial));
  const root = path.join(base, 'source');
  const members = {
    'composer.json': { name: 'kumwe/kumwe-engine', type: 'php-ext' },
    'php_kumwe_engine.h': '#define PHP_KUMWE_ENGINE_VERSION "1.0.0"\n',
    'resources/compatibility/v1.json': { version: '1.0.0', state: 'stable', publication_allowed: true },
    'resources/engine-lock.json': { state: 'stable', release_verified: true, release: 'v1.0.0',
      external_attestation: { fixture_only: true } },
  };
  for (const [name, value] of Object.entries(members)) write(path.join(root, name), value);
  const sbom = { spdxVersion: 'SPDX-2.3', files: Object.keys(members).map(name => ({ fileName: './' + name,
    checksums: [{ algorithm: 'SHA256', checksumValue: digest(fs.readFileSync(path.join(root, name))) }] })) };
  write(path.join(base, 'source.spdx.json'), sbom);
  write(path.join(base, 'source.tar.gz'), 'metadata-validation-only fixture; never a real native bundle');
  const input = { schema: 'kumwe-verified-native-fixture-input/v1', package: 'kumwe/kumwe-engine',
    version: '1.0.0', source_commit: 'a'.repeat(40), source_directory: root,
    source_archive_path: path.join(base, 'source.tar.gz'), source_sbom_path: path.join(base, 'source.spdx.json'),
    source_record_path: path.join(base, 'source.json') };
  input.source_archive_sha256 = digest(fs.readFileSync(input.source_archive_path));
  input.source_sbom_sha256 = digest(fs.readFileSync(input.source_sbom_path));
  const record = { schema: 'kumwe-native-source-bundle/v1', package: input.package,
    source: { repository: 'https://github.com/kumwe/kumwe-engine', commit: input.source_commit },
    identity: { version: input.version }, archive: { sha256: input.source_archive_sha256 },
    sbom: { sha256: input.source_sbom_sha256 }, stable_source_blockers: [] };
  write(input.source_record_path, record);
  input.source_record_sha256 = digest(fs.readFileSync(input.source_record_path));
  return input;
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
