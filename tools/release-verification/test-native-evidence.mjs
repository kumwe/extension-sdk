import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import crypto from 'node:crypto';
import YAML from 'yaml';
import { gzipSync, gunzipSync } from 'node:zlib';
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

function syntheticEnvelope(directory, packageName) {
  fs.mkdirSync(path.join(directory, 'publisher-assets'), { recursive: true });
  const entry = owner(packageName);
  const engine = packageName === 'kumwe/engine';
  const engineArchive = gzipSync(Buffer.from('SYNTHETIC UNIT TEST ONLY: Engine source archive'));
  const archiveName = engine ? 'kumwe-engine-source.tar.gz' : 'kumwe-engine-php-source.tar.gz';
  const names = [archiveName, 'source.json', 'source.spdx.json', 'SHA256SUMS'];
  const files = {};
  const writeFile = (relative, bytes) => {
    fs.writeFileSync(path.join(directory, relative), bytes);
    files[relative] = hash(bytes);
  };
  for (const name of names) {
    writeFile(`publisher-assets/${name}`, name === archiveName
      ? engine ? engineArchive : gzipSync(Buffer.from('SYNTHETIC UNIT TEST ONLY: binding source archive'))
      : `SYNTHETIC UNIT TEST ONLY: ${name}`);
  }
  writeFile('build-provenance.sigstore.json', 'SYNTHETIC UNIT TEST ONLY: signature bundle');
  writeFile('embedded-engine-source.tar.gz', engineArchive);
  const qualityJobs = engine
    ? ['native (ubuntu-24.04, gcc, g++)', 'native (ubuntu-24.04, clang, clang++)',
      'native (macos-14, clang, clang++)', 'sanitizers-fuzz', 'thread-sanitizer', 'archive-and-faults']
    : ['source-release-preparation', 'binding', 'address-undefined-sanitizers',
      'binding-zts', 'clean-pie', 'whole-boundary-benchmarks'];
  const qualityCheckouts = qualityJobs.map((job, index) => {
    const jobId = index + 1;
    const relative = `quality-checkout-1-${jobId}.log`;
    const log = `SYNTHETIC UNIT TEST ONLY: ${job}\n`
      + `[command]/usr/bin/git checkout --progress --force ${commit}\n`
      + `[command]/usr/bin/git log -1 --format=%H\n2026-01-01T00:00:00Z ${commit}\n`;
    writeFile(relative, log);
    return { job, job_id: jobId, source_commit: commit, path: relative, sha256: hash(log) };
  });
  entry.archive_sha256 = files[`publisher-assets/${archiveName}`];
  const record = { schema: 'kumwe-independent-native-release-verification/v1', status: 'passed',
    input: { name: entry.name, version: entry.version, source_commit: commit, archive_sha256: entry.archive_sha256,
      archive_name: archiveName }, verifier: { repository: 'kumwe/extension-sdk', source_commit: commit,
      run_url: 'https://github.com/kumwe/extension-sdk/actions/runs/1' }, source_tree: 'f'.repeat(40),
    signature_verification: { status: 'passed', repository: entry.name, source_commit: commit,
      source_ref: 'refs/heads/main', certificate_identity: `https://github.com/${packageName}/.github/workflows/ci.yml@refs/heads/main`,
      deny_self_hosted_runners: true, bundle_sha256: files['build-provenance.sigstore.json'],
      subjects: names.map(name => ({ name, sha256: files[`publisher-assets/${name}`] })) },
    source_verification: { status: 'passed', reproduced_source_bundle: true, source_commit: commit,
      archive_sha256: entry.archive_sha256, source_tree: 'f'.repeat(40), full_handoff_schema: 'passed',
      upstream_attestation_schemas: 'passed', quality_checkouts: qualityCheckouts },
    build: { status: 'passed', network_disabled: true, ...(!engine ? { extension_version: entry.version,
      actual_tuple: { embedded_engine_commit: commit, embedded_source_sha256: hash(engineArchive) } } : {}) },
    publisher: { conclusion: 'success' },
    assets: [...names, 'build-provenance.sigstore.json'].map(name => ({ identity: name,
      url: `https://github.com/${packageName}/releases/download/v${entry.version}/${name}`,
      sha256: files[names.includes(name) ? `publisher-assets/${name}` : name] })),
    embedded_engine: { source_commit: commit, release_archive_sha256: hash(engineArchive) } };
  const signatureAsset = record.assets.find(asset => asset.identity === 'build-provenance.sigstore.json');
  const receipt = { schema: 'kumwe-release-attestation/v2', artifact_kind: engine ? 'native_cpp' : 'php_extension',
    migration_id: 'KUMWE-MIG-2026-999', change_set: 'KUMWE-CS-2026-999', repository: `https://github.com/${packageName}`,
    merge_commit: commit, version: entry.version, tag: `v${entry.version}`,
    source_archive: { url: record.assets[0].url, sha256: entry.archive_sha256 },
    artifacts: record.assets, manifests_and_corpora: [], abi_and_capabilities: { abi_major: 1, capabilities: [], corpus_digests: [] },
    sbom: { url: record.assets[2].url, sha256: record.assets[2].sha256 },
    provenance: `SYNTHETIC UNIT TEST ONLY ${signatureAsset.url}; sha256=${signatureAsset.sha256}`,
    release_workflow: 'SYNTHETIC UNIT TEST ONLY', registry_or_pie_verification: [], clean_consumer_or_build_verification: [],
    verified_at: 'synthetic-test', verified_by: `Independent verifier ${record.verifier.run_url}; source ${record.verifier.source_commit}`, status: 'verified' };
  const receiptBytes = Buffer.from(YAML.stringify(receipt)); entry.attestation.member_sha256 = hash(receiptBytes);
  const writeRecord = value => {
    const bytes = JSON.stringify(value); fs.writeFileSync(path.join(directory, 'verification.json'), bytes);
    fs.writeFileSync(path.join(directory, 'evidence-files.json'), JSON.stringify({ ...files, 'verification.json': hash(bytes) }));
  };
  return { entry, record, receipt, receiptBytes, writeRecord, writeFile, engineArchive };
}

const temporary = fs.mkdtempSync(path.join(os.tmpdir(), 'kumwe-synthetic-native-evidence-'));
try {
  for (const packageName of ['kumwe/engine', 'kumwe/kumwe-engine']) {
    const directory = path.join(temporary, packageName.split('/')[1]);
    const { entry, record, receipt, receiptBytes, writeRecord, writeFile, engineArchive } = syntheticEnvelope(directory, packageName);
    writeRecord(record); validateNativeEnvelope(entry, directory, receiptBytes);
    const rawSourceHash = hash(gunzipSync(engineArchive));
    assert.notEqual(rawSourceHash, record.embedded_engine.release_archive_sha256);
    for (const edit of [r => { r.status = 'failed'; }, r => { r.signature_verification.status = 'failed'; },
      r => { r.signature_verification.deny_self_hosted_runners = false; },
      r => { r.signature_verification.certificate_identity = 'https://example.com/untrusted'; },
      r => { r.signature_verification.certificate_identity = r.signature_verification.certificate_identity.replace('/ci.yml@', '/release.yml@'); },
      r => { r.signature_verification.subjects.pop(); },
      r => { r.signature_verification.subjects.push({ name: 'source.provenance.json', sha256: '0'.repeat(64) }); },
      r => { r.signature_verification.subjects[1] = r.signature_verification.subjects[0]; },
      r => { r.assets.push({ identity: 'source.provenance.json', sha256: '0'.repeat(64), url: 'https://example.com/fictitious' }); },
      r => { r.assets.pop(); },
      r => { r.source_verification.reproduced_source_bundle = false; },
      r => { r.source_verification.full_handoff_schema = 'absent'; },
      r => { r.source_verification.upstream_attestation_schemas = 'absent'; },
      r => { delete r.source_verification.quality_checkouts; },
      r => { r.source_verification.quality_checkouts.pop(); },
      r => { r.source_verification.quality_checkouts.push(r.source_verification.quality_checkouts[0]); },
      r => { r.source_verification.quality_checkouts[1] = r.source_verification.quality_checkouts[0]; },
      r => { r.source_verification.quality_checkouts[0].job = 'unrelated-successful-job'; },
      r => { r.source_verification.quality_checkouts[0].job_id = 0; },
      r => { r.source_verification.quality_checkouts[0].job_id = r.source_verification.quality_checkouts[1].job_id; },
      r => { r.source_verification.quality_checkouts[0].path = r.source_verification.quality_checkouts[1].path; },
      r => { r.source_verification.quality_checkouts[0].path = '../outside.log'; },
      r => { r.source_verification.quality_checkouts[0].path = 'quality-checkout-1-999.log'; },
      r => { r.source_verification.quality_checkouts[0].sha256 = '0'.repeat(64); },
      r => { r.source_verification.quality_checkouts[0].source_commit = '0'.repeat(40); },
      r => { r.build.network_disabled = false; },
      r => { r.embedded_engine.source_commit = '0'.repeat(40); },
      r => { r.embedded_engine.release_archive_sha256 = rawSourceHash; },
      r => { r.embedded_engine.raw_tar_sha256 = rawSourceHash; delete r.embedded_engine.release_archive_sha256; }]) {
      const bad = structuredClone(record); edit(bad); writeRecord(bad);
      assert.throws(() => validateNativeEnvelope(entry, directory, receiptBytes), packageName);
    }
    // Consistent envelope hashes cannot turn the run head or another checked-out commit into proof.
    const firstCheckout = record.source_verification.quality_checkouts[0];
    const checkoutLog = fs.readFileSync(path.join(directory, firstCheckout.path), 'utf8');
    for (const log of [checkoutLog.replace(`--force ${commit}`, `--force ${'0'.repeat(40)}`),
      checkoutLog.replace(`00:00:00Z ${commit}`, `00:00:00Z ${'0'.repeat(40)}`),
      `SYNTHETIC UNIT TEST ONLY: Observed run head ${commit}\n`]) {
      writeFile(firstCheckout.path, log);
      const bad = structuredClone(record); bad.source_verification.quality_checkouts[0].sha256 = hash(log);
      writeRecord(bad);
      assert.throws(() => validateNativeEnvelope(entry, directory, receiptBytes), /did not prove checkout/);
    }
    writeFile(firstCheckout.path, checkoutLog);
    writeRecord(record);
    const inventoryPath = path.join(directory, 'evidence-files.json');
    const incompleteInventory = JSON.parse(fs.readFileSync(inventoryPath, 'utf8'));
    delete incompleteInventory[firstCheckout.path];
    fs.writeFileSync(inventoryPath, JSON.stringify(incompleteInventory));
    assert.throws(() => validateNativeEnvelope(entry, directory, receiptBytes), /inventory is incomplete/);
    writeRecord(record);
    if (packageName === 'kumwe/kumwe-engine') {
      for (const edit of [r => { r.build.extension_version = '9.9.9'; },
        r => { delete r.build.actual_tuple; },
        r => { r.build.actual_tuple.embedded_engine_commit = '0'.repeat(40); },
        r => { r.build.actual_tuple.embedded_source_sha256 = rawSourceHash; },
        r => { r.build.actual_tuple.embedded_source_sha256 = entry.archive_sha256; }]) {
        const bad = structuredClone(record); edit(bad); writeRecord(bad);
        assert.throws(() => validateNativeEnvelope(entry, directory, receiptBytes), /actual binding build/);
      }
    } else {
      // A self-consistent replacement archive still cannot replace the selected published Engine asset.
      const otherArchive = gzipSync(Buffer.from('SYNTHETIC UNIT TEST ONLY: unrelated archive'));
      writeFile('embedded-engine-source.tar.gz', otherArchive);
      const bad = structuredClone(record); bad.embedded_engine.release_archive_sha256 = hash(otherArchive);
      writeRecord(bad);
      assert.throws(() => validateNativeEnvelope(entry, directory, receiptBytes), /selected published source/);
      writeFile('embedded-engine-source.tar.gz', engineArchive);
    }
    writeRecord(record);
    for (const edit of [r => { r.verified_by = { name: 'wrong-shape' }; },
      r => { r.verified_by = `Independent verifier https://github.com/kumwe/extension-sdk/actions/runs/2; source ${commit}`; },
      r => { r.provenance = 'SYNTHETIC UNIT TEST ONLY: missing bundle reference'; }]) {
      const bad = structuredClone(receipt); edit(bad);
      const badReceipt = Buffer.from(YAML.stringify(bad));
      assert.throws(() => validateNativeEnvelope({ ...entry, attestation: { ...entry.attestation, member_sha256: hash(badReceipt) } }, directory, badReceipt));
    }
    fs.appendFileSync(path.join(directory, 'build-provenance.sigstore.json'), 'changed');
    assert.throws(() => validateNativeEnvelope(entry, directory, receiptBytes));
  }
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
console.log('Native evidence validation passed synthetic Engine and binding inputs and hostile checks; no native release is claimed.');
