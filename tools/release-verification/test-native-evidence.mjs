import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import crypto from 'node:crypto';
import YAML from 'yaml';
import { durableUri, nativeSelection, validateNativeEnvelope } from './materialize-native-evidence.mjs';
import { nativeFixtureScope, nativeSelectionBinding, packageSetNativeBinding } from './native-fixture-scope.mjs';

const hash = bytes => crypto.createHash('sha256').update(bytes).digest('hex');
const commit = 'a'.repeat(40);
const prefix = `https://raw.githubusercontent.com/kumwe/extension-sdk/${commit}/evidence/synthetic/`;
assert.equal(durableUri(prefix + 'attestation.zip'), prefix + 'attestation.zip');
for (const uri of [prefix.replace(commit, 'main') + 'x.zip', prefix + '../x.zip', prefix + 'a//x.zip',
  prefix + '%2e%2e/x.zip', prefix + 'x.zip?query=1', prefix.replace('/kumwe/', '/foreign/') + 'x.zip']) {
  assert.throws(() => durableUri(uri));
}
const owner = name => ({ name, version: '1.0.0', source_commit: commit, archive_sha256: 'b'.repeat(64),
  attestation: { zip_uri: prefix + 'attestation.zip', zip_sha256: 'c'.repeat(64), member: 'RELEASE-ATTESTATION.yaml', member_sha256: 'd'.repeat(64) },
  evidence: { zip_uri: prefix + 'evidence.zip', zip_sha256: 'e'.repeat(64), verification_member: 'verification.json' } });
const selection = { schema: 'kumwe-verified-native-selection/v1', engine: owner('kumwe/engine'), extension: owner('kumwe/kumwe-engine') };
nativeSelection(selection);
const native = { extension_version: selection.extension.version, compatibility_tuple: {} };
for (const kind of ['engine', 'extension']) {
  const selected = selection[kind];
  native[kind] = { package: selected.name, version: selected.version, tag: `v${selected.version}`,
    commit: selected.source_commit, archive_sha256: selected.archive_sha256,
    attestation: { uri: selected.attestation.zip_uri, sha256: selected.attestation.zip_sha256,
      member: selected.attestation.member, member_sha256: selected.attestation.member_sha256 } };
}
const qualified = { selection, native };
nativeSelectionBinding(qualified); packageSetNativeBinding({ graph_mode: 'native', native }, qualified);
for (const edit of [r => { r.native.engine.archive_sha256 = '0'.repeat(64); },
  r => { r.native.extension.commit = '0'.repeat(40); }, r => { r.native.engine.attestation.uri = prefix + 'other/attestation.zip'; },
  r => { r.native.extension.attestation.member_sha256 = '0'.repeat(64); }]) {
  const changed = structuredClone(qualified); edit(changed);
  assert.throws(() => nativeSelectionBinding(changed));
  assert.throws(() => packageSetNativeBinding({ graph_mode: 'native', native: changed.native }, qualified));
}
for (const edit of [s => { s.engine.version = '0.0.0-dev'; }, s => { s.extension.source_commit = 'main'; },
  s => { s.engine.attestation.member = '../RELEASE-ATTESTATION.yaml'; }, s => { s.extension.evidence.zip_sha256 = null; }]) {
  const bad = structuredClone(selection); edit(bad); assert.throws(() => nativeSelection(bad));
}

const temporary = fs.mkdtempSync(path.join(os.tmpdir(), 'kumwe-synthetic-native-evidence-'));
try {
  const assets = path.join(temporary, 'publisher-assets'); fs.mkdirSync(assets);
  const names = ['kumwe-engine-source.tar.gz', 'source.json', 'source.spdx.json', 'source.provenance.json', 'SHA256SUMS'];
  const files = {};
  for (const name of [...names, 'build-provenance.sigstore.json', 'embedded-engine-source.tar']) {
    const relative = names.includes(name) ? `publisher-assets/${name}` : name;
    fs.writeFileSync(path.join(temporary, relative), `SYNTHETIC UNIT TEST ONLY: ${name}`);
    files[relative] = hash(fs.readFileSync(path.join(temporary, relative)));
  }
  const entry = owner('kumwe/engine'); entry.archive_sha256 = files['publisher-assets/kumwe-engine-source.tar.gz'];
  const record = { schema: 'kumwe-independent-native-release-verification/v1', status: 'passed',
    input: { name: entry.name, version: entry.version, source_commit: commit, archive_sha256: entry.archive_sha256,
      archive_name: names[0] }, verifier: { repository: 'kumwe/extension-sdk', source_commit: commit,
      run_url: 'https://github.com/kumwe/extension-sdk/actions/runs/1' }, source_tree: 'f'.repeat(40),
    signature_verification: { status: 'passed', repository: entry.name, source_commit: commit,
      source_ref: 'refs/heads/main', certificate_identity: 'https://github.com/kumwe/engine/.github/workflows/release.yml@refs/heads/main',
      deny_self_hosted_runners: true, bundle_sha256: files['build-provenance.sigstore.json'],
      subjects: names.map(name => ({ name, sha256: files[`publisher-assets/${name}`] })) },
    source_verification: { status: 'passed', reproduced_source_bundle: true, source_commit: commit,
      archive_sha256: entry.archive_sha256, source_tree: 'f'.repeat(40), full_handoff_schema: 'passed', upstream_attestation_schemas: 'passed' },
    build: { status: 'passed', network_disabled: true }, publisher: { conclusion: 'success' },
    assets: [...names, 'build-provenance.sigstore.json'].map(name => ({ identity: name,
      url: `https://github.com/kumwe/engine/releases/download/v1.0.0/${name}`,
      sha256: files[names.includes(name) ? `publisher-assets/${name}` : name] })),
    embedded_engine: { source_commit: commit, raw_tar_sha256: files['embedded-engine-source.tar'] } };
  const receipt = { schema: 'kumwe-release-attestation/v2', artifact_kind: 'native_cpp',
    migration_id: 'KUMWE-MIG-2026-999', change_set: 'KUMWE-CS-2026-999', repository: 'https://github.com/kumwe/engine',
    merge_commit: commit, version: '1.0.0', tag: 'v1.0.0', source_archive: { url: record.assets[0].url, sha256: entry.archive_sha256 },
    artifacts: record.assets, manifests_and_corpora: [], abi_and_capabilities: { abi_major: 1, capabilities: [], corpus_digests: [] },
    sbom: { url: record.assets[2].url, sha256: record.assets[2].sha256 },
    provenance: `SYNTHETIC UNIT TEST ONLY ${record.assets[5].url}; sha256=${record.assets[5].sha256}`,
    release_workflow: 'SYNTHETIC UNIT TEST ONLY', registry_or_pie_verification: [], clean_consumer_or_build_verification: [],
    verified_at: 'synthetic-test', verified_by: `Independent verifier ${record.verifier.run_url}; source ${record.verifier.source_commit}`, status: 'verified' };
  const receiptBytes = Buffer.from(YAML.stringify(receipt)); entry.attestation.member_sha256 = hash(receiptBytes);
  const writeRecord = value => {
    const bytes = JSON.stringify(value); fs.writeFileSync(path.join(temporary, 'verification.json'), bytes);
    fs.writeFileSync(path.join(temporary, 'evidence-files.json'), JSON.stringify({ ...files, 'verification.json': hash(bytes) }));
  };
  writeRecord(record); validateNativeEnvelope(entry, temporary, receiptBytes);
  for (const edit of [r => { r.status = 'failed'; }, r => { r.signature_verification.status = 'failed'; },
    r => { r.signature_verification.deny_self_hosted_runners = false; },
    r => { r.signature_verification.certificate_identity = 'https://example.com/untrusted'; },
    r => { r.signature_verification.subjects.pop(); }, r => { r.source_verification.reproduced_source_bundle = false; },
    r => { r.source_verification.full_handoff_schema = 'absent'; }, r => { r.build.network_disabled = false; },
    r => { r.embedded_engine.raw_tar_sha256 = entry.archive_sha256; }]) {
    const bad = structuredClone(record); edit(bad); writeRecord(bad);
    assert.throws(() => validateNativeEnvelope(entry, temporary, receiptBytes));
  }
  writeRecord(record);
  const badReceipt = Buffer.from(YAML.stringify({ ...receipt, verified_by: { name: 'wrong-shape' } }));
  assert.throws(() => validateNativeEnvelope({ ...entry, attestation: { ...entry.attestation, member_sha256: hash(badReceipt) } }, temporary, badReceipt));
  fs.appendFileSync(path.join(temporary, 'build-provenance.sigstore.json'), 'changed');
  assert.throws(() => validateNativeEnvelope(entry, temporary, receiptBytes));
} finally { fs.rmSync(temporary, { recursive: true, force: true }); }
const scopeRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'kumwe-synthetic-fixture-integrity-'));
try {
  const file = name => path.join(scopeRoot, name);
  for (const name of ['module.so', 'expected-runtime-tuple.json', 'php.ini', 'expected-compatibility.json']) fs.writeFileSync(file(name), '{}');
  const generated = { schema: 'kumwe-qualified-native-fixture/v1', status: 'passed', selection,
    fixture: { schema: 'kumwe-native-build-fixture/v1', status: 'passed', package: selection.extension.name,
      version: selection.extension.version, source_commit: commit, source_archive_sha256: selection.extension.archive_sha256,
      release_attestation: false, module: file('module.so'), module_sha256: hash('{}'), php_binary: process.execPath,
      expected_runtime_tuple: file('expected-runtime-tuple.json'), expected_runtime_tuple_sha256: hash('{}'),
      environment: { PHPRC: file('php.ini'), KUMWE_NATIVE_EXPECTED_TUPLE: file('expected-compatibility.json') },
      php_ini_sha256: hash('{}'), expected_compatibility_sha256: hash('{}') }, native };
  for (const edit of [r => { r.fixture.php_ini_sha256 = null; }, r => { r.fixture.expected_compatibility_sha256 = null; },
    r => { r.fixture.environment.PHPRC = file('expected-compatibility.json'); },
    r => { r.fixture.environment.KUMWE_NATIVE_EXPECTED_TUPLE = file('php.ini'); }]) {
    const changed = structuredClone(generated); edit(changed); fs.writeFileSync(file('fixture.json'), JSON.stringify(changed));
    assert.throws(() => nativeFixtureScope(file('fixture.json'), file('scope')), /INI or expected compatibility bytes changed/);
  }
  fs.writeFileSync(file('fixture.json'), JSON.stringify(generated));
  fs.writeFileSync(file('php.ini'), 'changed');
  assert.throws(() => nativeFixtureScope(file('fixture.json'), file('scope')), /INI or expected compatibility bytes changed/);
  fs.writeFileSync(file('php.ini'), '{}'); fs.writeFileSync(file('expected-compatibility.json'), 'changed');
  assert.throws(() => nativeFixtureScope(file('fixture.json'), file('scope')), /INI or expected compatibility bytes changed/);
} finally { fs.rmSync(scopeRoot, { recursive: true, force: true }); }
console.log('Native evidence validation passed synthetic inputs and 35 hostile checks; no native release is claimed.');
