import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import assert from 'node:assert/strict';
import { fileURLToPath } from 'node:url';
import YAML from '../release-verification/node_modules/yaml/dist/index.js';
import { coordinate, safePath, handoff, validateAttestation, validateUpstreamReceipt,
  qualityRun, qualityCheckout, releaseAssets, finalize, receiptReference, selectReceipt, identicalInventory } from './verify-native.mjs';
import './test-binding-sync-authority.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
let checks = 0;
const pass = fn => { fn(); checks++; };
const refuse = fn => { assert.throws(fn); checks++; };
// These isolated identities exercise refusal and serialization only. They never enter the release matrix.
const input = coordinate({ name: 'kumwe/engine', version: '1.0.0', source_commit: '1'.repeat(40),
  archive_sha256: '2'.repeat(64) });
pass(() => assert.equal(input.kind, 'native_cpp'));
for (const changes of [{ name: 'kumwe/app' }, { version: '0.0.0' }, { version: '1.0.0-dev' },
  { version: '01.0.0' }, { source_commit: '1234567' }, { archive_sha256: 'abc' }]) {
  refuse(() => coordinate({ ...input, ...changes }));
}
pass(() => assert.equal(safePath('resources/capabilities.json'), 'resources/capabilities.json'));
for (const p of ['/tmp/x', '../x', 'a/../b', 'a//b', 'a\\b', 'a\nb']) refuse(() => safePath(p));

const handoffText = fs.readFileSync(path.join(here, 'fixtures/engine-handoff.md'), 'utf8');
const record = handoff(handoffText, input);
pass(() => assert.equal(record.artifact_kind, 'native_cpp'));
const replaceHandoff = value => '---\n' + YAML.stringify(value) + '---\n'
  + handoffText.replace(/^---\r?\n[\s\S]*?\r?\n---(?:\r?\n|$)/, '');
for (const alter of [r => { r.artifact_kind = 'framework_php'; },
  r => { r.target.repository = 'https://github.com/kumwe/app'; },
  r => { r.source.app.baseline_commit = '1234567'; },
  r => { r.governance.completion_claim = true; },
  r => { r.ownership.public_manifests = {}; }]) {
  const mutated = structuredClone(record); alter(mutated);
  refuse(() => handoff(replaceHandoff(mutated), input));
}
const currentRecord = structuredClone(record);
currentRecord.schema = 'kumwe-package-release-record/v1';
delete currentRecord.state; delete currentRecord.concurrency;
delete currentRecord.source.active_related_pull_requests;
delete currentRecord.target.branch; delete currentRecord.target.pull_request;
for (const key of ['roadmap_source_sha256', 'roadmap_refs', 'non_roadmap_refs']) delete currentRecord.governance[key];
currentRecord.consumer_contract = currentRecord.next_task; delete currentRecord.next_task;
delete currentRecord.consumer_contract.phase_name;
const currentHeadings = ['Package contract', 'Public API and responsibility', 'Dependencies and semantic inputs',
  'Consumer contract', 'Test ownership', 'Consumer verification', 'Compatibility and drift', 'Validation'];
const currentText = '---\n' + YAML.stringify(currentRecord) + '---\n'
  + currentHeadings.map(title => '## ' + title + '\nSynthetic contract fixture.\n').join('\n');
pass(() => handoff(currentText, input, 'docs/release-record.md'));
refuse(() => handoff(currentText, input));
refuse(() => handoff(handoffText, input, 'docs/release-record.md'));
refuse(() => handoff(currentText.replace('## Consumer contract', '## Missing section'), input, 'docs/release-record.md'));
refuse(() => handoff(handoffText.replace('## Consumer inventory', '## Missing section'), input));
refuse(() => handoff(handoffText.replace('---\n', '---\ninvalid: "unterminated\n'), input));
refuse(() => handoff(handoffText.replace('---\n', '---\nschema: duplicate\n'), input));

const names = ['native (ubuntu-24.04, gcc, g++)', 'native (ubuntu-24.04, clang, clang++)',
  'native (macos-14, clang, clang++)', 'sanitizers-fuzz', 'thread-sanitizer', 'archive-and-faults'];
const run = { head_sha: input.source_commit, head_branch: 'main', event: 'push', path: '.github/workflows/ci.yml',
  status: 'completed', conclusion: 'success', repository: { full_name: input.name },
  head_repository: { full_name: input.name } };
const jobs = names.map(name => ({ name, status: 'completed', conclusion: 'success' }));
pass(() => qualityRun(run, jobs, input, 'main'));
for (const changes of [{ head_sha: '3'.repeat(40) }, { head_branch: 'feature' }, { event: 'pull_request' },
  { conclusion: 'failure' }, { path: '.github/workflows/release.yml' },
  { head_repository: { full_name: 'outsider/engine' } }]) refuse(() => qualityRun({ ...run, ...changes }, jobs, input, 'main'));
refuse(() => qualityRun(run, jobs.slice(1), input, 'main'));
refuse(() => qualityRun(run, [...jobs, jobs[0]], input, 'main'));
refuse(() => qualityRun(run, [{ ...jobs[0], conclusion: 'skipped' }, ...jobs.slice(1)], input, 'main'));
const notification = { name: 'Trigger the PHP binding sync', status: 'completed', conclusion: 'failure' };
pass(() => qualityRun({ ...run, event: 'workflow_dispatch', conclusion: 'failure' }, [...jobs, notification], input, 'main'));
refuse(() => qualityRun({ ...run, event: 'workflow_dispatch', conclusion: 'failure' },
  [{ ...jobs[0], conclusion: 'failure' }, ...jobs.slice(1), notification], input, 'main'));
refuse(() => qualityRun({ ...run, conclusion: 'failure' }, [...jobs, { ...notification, name: 'Unknown quality gate' }], input, 'main'));
const bindingInput = coordinate({ ...input, name: 'kumwe/kumwe-engine' });
const bindingJobs = ['source-release-preparation', 'binding', 'binding-zts', 'address-undefined-sanitizers',
  'clean-pie', 'whole-boundary-benchmarks', 'Publish source release'].map(name => ({ name, status: 'completed', conclusion: 'success' }));
const bindingRun = { ...run, event: 'workflow_dispatch', repository: { full_name: bindingInput.name },
  head_repository: { full_name: bindingInput.name } };
pass(() => qualityRun(bindingRun, bindingJobs, bindingInput, 'main'));
refuse(() => qualityRun(bindingRun, bindingJobs.filter(job => job.name !== 'binding-zts'), bindingInput, 'main'));
const checkoutLog = `[command]/usr/bin/git checkout --progress --force ${input.source_commit}\n`
  + `[command]/usr/bin/git log -1 --format=%H\n2026-01-01T00:00:00Z ${input.source_commit}\n`;
pass(() => qualityCheckout(checkoutLog, input.source_commit));
refuse(() => qualityCheckout(checkoutLog.replaceAll(input.source_commit, '0'.repeat(40)), input.source_commit));
refuse(() => qualityCheckout(checkoutLog.replace('git log -1 --format=%H', 'echo unverified-source'), input.source_commit));
refuse(() => qualityCheckout(`Observed run head ${input.source_commit}`, input.source_commit));
const assetNames = [input.archive_name, 'source.json', 'source.spdx.json',
  'SHA256SUMS', 'build-provenance.sigstore.json'];
const release = { tag_name: input.tag, published_at: '2026-01-01T00:00:00Z', draft: false, prerelease: false,
  assets: assetNames.map(name => ({ name, state: 'uploaded', size: 1 })) };
pass(() => releaseAssets(release, input));
for (const changes of [{ draft: true }, { prerelease: true }, { tag_name: 'v0.9.0' },
  { published_at: null }, { assets: release.assets.slice(1) },
  { assets: [...release.assets.slice(1), release.assets[1]] }]) refuse(() => releaseAssets({ ...release, ...changes }, input));

const receipt = { schema: 'kumwe-release-attestation/v2', artifact_kind: 'native_cpp',
  migration_id: 'KUMWE-MIG-2026-001', change_set: 'KUMWE-CS-2026-001',
  repository: 'https://github.com/kumwe/engine', merge_commit: input.source_commit,
  version: input.version, tag: input.tag, source_archive: { url: 'https://example.invalid/source', sha256: input.archive_sha256 },
  artifacts: [], manifests_and_corpora: [{ path: 'corpus/example.json', sha256: '3'.repeat(64) }],
  abi_and_capabilities: { abi_major: 1, capabilities: ['fixture-only'], corpus_digests: ['3'.repeat(64)] },
  sbom: { url: 'https://example.invalid/sbom', sha256: '4'.repeat(64) }, provenance: 'fixture only',
  release_workflow: 'fixture only', registry_or_pie_verification: ['fixture only'],
  clean_consumer_or_build_verification: ['fixture only'], verified_at: '2026-01-01T00:00:00Z',
  verified_by: 'isolated regression fixture; never published evidence', status: 'verified' };
pass(() => validateAttestation(receipt));
for (const changes of [{ status: 'failed' }, { known_gaps: ['incomplete'] }, { provenance: {} },
  { registry_or_pie_verification: [{}] }, { verified_by: {} },
  { abi_and_capabilities: { ...receipt.abi_and_capabilities, full_tuple: {} } }]) {
  refuse(() => validateAttestation({ ...receipt, ...changes }));
}
const upstream = { name: input.name, version: input.version, commit: input.source_commit,
  archive_sha256: input.archive_sha256, corpora: { 'corpus/example.json': '3'.repeat(64) } };
pass(() => validateUpstreamReceipt(receipt, upstream));
refuse(() => validateUpstreamReceipt({ ...receipt, artifact_kind: 'framework_php' }, upstream));
for (const changes of [{ name: 'kumwe/kumwe-engine' }, { version: '1.0.1' }, { commit: '5'.repeat(40) },
  { archive_sha256: '6'.repeat(64) }, { corpora: { 'corpus/example.json': '7'.repeat(64) } },
  { corpora: { 'resources/public-api/v1.json': '3'.repeat(64) } }]) {
  refuse(() => validateUpstreamReceipt(receipt, { ...upstream, ...changes }));
}
const external = { uri: `https://raw.githubusercontent.com/kumwe/extension-sdk/${'8'.repeat(40)}/evidence/native/RELEASE-ATTESTATION.yaml`,
  sha256: '9'.repeat(64) };
pass(() => assert.deepEqual(receiptReference(external), external));
for (const reference of [{ ...external, sha256: 'wrong' }, { ...external, uri: external.uri.replace('8'.repeat(40), 'main') },
  { ...external, uri: external.uri.replace('/evidence/native/', '/evidence/../native/') }, null]) refuse(() => receiptReference(reference));
refuse(() => selectReceipt(input, upstream, null));
const laterReceipt = coordinate({ ...input, upstream_receipts: { [upstream.name]: external } });
pass(() => assert.deepEqual(selectReceipt(laterReceipt, upstream, null), external));
// Selection never rewrites the exact source/corpus expectations used by validation.
refuse(() => validateUpstreamReceipt(receipt, { ...upstream, commit: '0'.repeat(40) }));
pass(() => identicalInventory({ 'src/engine.cpp': '1'.repeat(64) }, { 'src/engine.cpp': '1'.repeat(64) }));
refuse(() => identicalInventory({ 'src/engine.cpp': '1'.repeat(64) }, { 'src/engine.cpp': '2'.repeat(64) }));
refuse(() => identicalInventory({ 'src/engine.cpp': '1'.repeat(64) }, { 'src/engine.cpp': '1'.repeat(64), extra: '2'.repeat(64) }));

const temporary = fs.mkdtempSync(path.join(os.tmpdir(), 'kumwe-native-verifier-test-'));
try {
  const verification = { status: 'passed', input, handoff: record, assets: assetNames.map(identity => ({ identity,
    url: `https://example.invalid/${identity}`, sha256: '3'.repeat(64) })), manifests_and_corpora: receipt.manifests_and_corpora,
    abi_and_capabilities: receipt.abi_and_capabilities, publisher: { url: 'https://example.invalid/run' },
    source_tree: '4'.repeat(40), embedded_engine: { release_archive_sha256: '5'.repeat(64) },
    verifier: { run_url: 'https://example.invalid/verifier', source_commit: '6'.repeat(40) }, verified_at: receipt.verified_at };
  fs.writeFileSync(path.join(temporary, 'verification.json'), JSON.stringify(verification));
  pass(() => finalize(temporary, 'https://github.com/kumwe/extension-sdk/actions/runs/1/artifacts/2'));
  pass(() => validateAttestation(YAML.parse(fs.readFileSync(path.join(temporary, 'RELEASE-ATTESTATION.yaml'), 'utf8'))));
  refuse(() => finalize(temporary, 'https://example.invalid/artifact'));
  verification.status = 'failed';
  fs.writeFileSync(path.join(temporary, 'verification.json'), JSON.stringify(verification));
  refuse(() => finalize(temporary, 'https://github.com/kumwe/extension-sdk/actions/runs/1/artifacts/2'));
} finally { fs.rmSync(temporary, { recursive: true, force: true }); }
console.log(`${checks} native evidence admission, refusal and complete-schema serialization checks passed.`);
