import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';
import YAML from 'yaml';
import Ajv from 'ajv/dist/2020.js';
import { buildNativeFixture } from './build-native-fixture.mjs';
import { inventories, qualityCheckout, safePath } from '../native-release-verification/verify-native.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const json = file => JSON.parse(fs.readFileSync(file, 'utf8'));
const hash = bytes => crypto.createHash('sha256').update(bytes).digest('hex');
const save = (file, value) => fs.writeFileSync(file, JSON.stringify(value, null, 2) + '\n');
const need = (fact, message) => { if (!fact) throw new Error(message); };
const digest = value => typeof value === 'string' && /^[a-f0-9]{64}$/.test(value);
const ajv = new Ajv({ strict: false, allErrors: true });
const validateAttestation = ajv.compile(json(path.join(here, 'release-attestation.v2.schema.json')));

export function durableUri(value) {
  need(typeof value === 'string' && /^https:\/\/raw\.githubusercontent\.com\/kumwe\/extension-sdk\/[a-f0-9]{40}\/evidence\/[A-Za-z0-9._/-]+$/.test(value),
  'Native evidence requires an immutable SDK evidence Git URI.');
  const relative = value.split('/evidence/')[1];
  need(relative && !relative.split('/').some(part => ['', '.', '..'].includes(part)), 'Unsafe durable evidence path.');
  return value;
}

export function evidenceReference(reference) {
  need(reference && digest(reference.zip_sha256), 'Missing original native evidence ZIP digest.');
  need(Object.hasOwn(reference, 'zip_uri') !== Object.hasOwn(reference, 'actions_artifact'),
    'Select exactly one Git ZIP URI or original Actions artifact.');
  if (Object.hasOwn(reference, 'zip_uri')) durableUri(reference.zip_uri);
  else {
    const artifact = reference.actions_artifact;
    need(artifact?.repository === 'kumwe/extension-sdk'
      && Number.isSafeInteger(artifact.id) && artifact.id > 0
      && Number.isSafeInteger(artifact.run_id) && artifact.run_id > 0
      && /^[a-f0-9]{40}$/.test(artifact.verifier_commit || '')
      && /^native-release-evidence-(engine|kumwe-engine)-[0-9]+\.[0-9]+\.[0-9]+$/.test(artifact.name || ''),
    'Native evidence must identify an exact same-repository Actions artifact.');
  }
  return reference;
}

export function nativeSelection(raw) {
  need(raw?.schema === 'kumwe-verified-native-selection/v1', 'A verified native selection is required.');
  for (const [kind, name] of [['engine', 'kumwe/engine'], ['extension', 'kumwe/kumwe-engine']]) {
    const entry = raw[kind];
    need(entry?.name === name && /^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/.test(entry.version || '')
      && entry.version !== '0.0.0' && /^[a-f0-9]{40}$/.test(entry.source_commit || '') && digest(entry.archive_sha256),
    'Native selection must contain exact stable owner coordinates.');
    for (const reference of [entry.attestation, entry.evidence]) {
      evidenceReference(reference);
    }
    if (entry.evidence.actions_artifact) need(entry.evidence.actions_artifact.name
      === `native-release-evidence-${entry.name.slice(6)}-${entry.version}`, 'Native evidence artifact owner differs.');
    need(entry.attestation.member === 'RELEASE-ATTESTATION.yaml' && digest(entry.attestation.member_sha256)
      && entry.evidence.verification_member === 'verification.json', 'Unexpected native evidence member selection.');
    need(/^https:\/\/raw\.githubusercontent\.com\/kumwe\/extension-sdk\/[a-f0-9]{40}\/evidence\/(?:[A-Za-z0-9_-][A-Za-z0-9._-]*\/)+attestation\.zip$/.test(entry.attestation.zip_uri),
      'Native attestation ZIP must use the complete graph consumer durable URI convention.');
  }
  return raw;
}

function matchingFile(file, expected) {
  need(digest(expected) && fs.lstatSync(file).isFile() && !fs.lstatSync(file).isSymbolicLink()
    && hash(fs.readFileSync(file)) === expected, `Native evidence file digest differs: ${file}`);
}

export function validateNativeEnvelope(entry, envelope, attestationBytes) {
  need(hash(attestationBytes) === entry.attestation.member_sha256, 'Native attestation member digest differs.');
  const receipt = YAML.parse(attestationBytes.toString('utf8'), { uniqueKeys: true, maxAliasCount: 20 });
  need(validateAttestation(receipt), `Native receipt schema failed: ${JSON.stringify(validateAttestation.errors)}`);
  const kind = entry.name === 'kumwe/engine' ? 'native_cpp' : 'php_extension';
  need(receipt.status === 'verified' && (!receipt.known_gaps || receipt.known_gaps.length === 0)
    && receipt.artifact_kind === kind && receipt.repository === `https://github.com/${entry.name}`
    && receipt.version === entry.version && receipt.tag === `v${entry.version}`
    && receipt.merge_commit === entry.source_commit && receipt.source_archive.sha256 === entry.archive_sha256,
  'Native receipt does not qualify the selected stable source.');
  const record = json(path.join(envelope, 'verification.json'));
  need(record.schema === 'kumwe-independent-native-release-verification/v1' && record.status === 'passed'
    && record.input?.name === entry.name && record.input.version === entry.version
    && record.input.source_commit === entry.source_commit && record.input.archive_sha256 === entry.archive_sha256
    && record.verifier?.repository === 'kumwe/extension-sdk'
    && /^[a-f0-9]{40}$/.test(record.verifier.source_commit || '')
    && /^https:\/\/github\.com\/kumwe\/extension-sdk\/actions\/runs\/\d+$/.test(record.verifier.run_url || ''),
  'Native envelope is not a passing hosted verification of the selected source.');
  need(receipt.verified_by === `Independent verifier ${record.verifier.run_url}; source ${record.verifier.source_commit}`,
    'Native receipt and envelope identify different independent verifier runs.');
  if (entry.evidence.actions_artifact) {
    const artifact = entry.evidence.actions_artifact;
    need(record.verifier.run_url === `https://github.com/kumwe/extension-sdk/actions/runs/${artifact.run_id}`
      && record.verifier.source_commit === artifact.verifier_commit,
    'Native evidence envelope differs from its original Actions artifact run.');
  }
  const signature = record.signature_verification;
  need(signature?.status === 'passed' && signature.repository === entry.name
    && signature.source_commit === entry.source_commit && /^refs\/heads\/[A-Za-z0-9._/-]+$/.test(signature.source_ref || '')
    && signature.certificate_identity === `https://github.com/${entry.name}/.github/workflows/ci.yml@${signature.source_ref}`
    && signature.deny_self_hosted_runners === true && Array.isArray(signature.subjects)
    && signature.subjects.length === 4 && new Set(signature.subjects.map(s => s.name)).size === 4,
  'Mandatory upstream publisher signature verification is absent or differs.');
  matchingFile(path.join(envelope, 'build-provenance.sigstore.json'), signature.bundle_sha256);
  const source = record.source_verification;
  need(source?.status === 'passed' && source.reproduced_source_bundle === true
    && source.source_commit === entry.source_commit && source.archive_sha256 === entry.archive_sha256
    && /^[a-f0-9]{40}$/.test(source.source_tree || '') && source.source_tree === record.source_tree
    && source.full_handoff_schema === 'passed' && source.upstream_attestation_schemas === 'passed'
    && record.build?.status === 'passed' && record.build.network_disabled === true
    && record.publisher?.conclusion === 'success', 'Native source/schema/build verification is incomplete.');
  const archiveName = kind === 'native_cpp' ? 'kumwe-engine-source.tar.gz' : 'kumwe-engine-php-source.tar.gz';
  const names = [archiveName, 'source.json', 'source.spdx.json', 'SHA256SUMS'];
  need(record.input.archive_name === archiveName && record.assets?.length === 5
    && new Set(record.assets.map(asset => asset.identity)).size === 5, 'Native source asset inventory differs.');
  for (const name of names) {
    const subject = signature.subjects.find(s => s.name === name);
    const asset = record.assets.find(a => a.identity === name);
    need(subject && asset && asset.sha256 === subject.sha256
      && receipt.artifacts.some(a => a.identity === name && a.sha256 === asset.sha256 && a.url === asset.url),
    `Native signed source subject is missing or differs: ${name}`);
    matchingFile(path.join(envelope, 'publisher-assets', name), asset.sha256);
  }
  matchingFile(path.join(envelope, 'publisher-assets', archiveName), entry.archive_sha256);
  const signatureAsset = record.assets.find(a => a.identity === 'build-provenance.sigstore.json');
  need(signatureAsset?.sha256 === signature.bundle_sha256 && receipt.artifacts.some(a =>
    a.identity === signatureAsset.identity && a.sha256 === signatureAsset.sha256 && a.url === signatureAsset.url),
  'Native signature bundle is not bound by the external receipt.');
  need(receipt.provenance?.includes(signatureAsset.url) && receipt.provenance.includes(`sha256=${signatureAsset.sha256}`)
    && receipt.source_archive.url === record.assets.find(a => a.identity === archiveName).url,
  'Native receipt provenance/source references differ from the preserved envelope.');
  const inventory = json(path.join(envelope, 'evidence-files.json'));
  const present = [];
  const visit = (relative = '') => {
    for (const item of fs.readdirSync(path.join(envelope, relative), { withFileTypes: true })) {
      const child = relative ? `${relative}/${item.name}` : item.name;
      need(!item.isSymbolicLink(), 'Native envelope contains a link.');
      if (item.isDirectory()) visit(child);
      else { need(item.isFile(), 'Native envelope contains a special file.'); if (child !== 'evidence-files.json') present.push(child); }
    }
  };
  visit();
  need(JSON.stringify(present.sort()) === JSON.stringify(Object.keys(inventory).sort()), 'Native evidence envelope inventory is incomplete.');
  for (const [relative, expected] of Object.entries(inventory)) {
    need(relative && !relative.startsWith('/') && !relative.includes('\\')
      && !relative.split('/').some(part => ['', '.', '..'].includes(part)), 'Unsafe native envelope inventory path.');
    matchingFile(path.join(envelope, relative), expected);
  }
  const checkouts = source.quality_checkouts;
  const requiredJobs = inventories[entry.name];
  need(Array.isArray(checkouts) && checkouts.length === requiredJobs.length
    && checkouts.every(checkout => checkout && typeof checkout === 'object'
      && requiredJobs.includes(checkout.job) && Number.isSafeInteger(checkout.job_id) && checkout.job_id > 0
      && checkout.source_commit === entry.source_commit)
    && new Set(checkouts.map(checkout => checkout.job)).size === requiredJobs.length
    && new Set(checkouts.map(checkout => checkout.job_id)).size === requiredJobs.length
    && new Set(checkouts.map(checkout => checkout.path)).size === requiredJobs.length,
  'Native evidence must preserve every required quality job checkout exactly once.');
  for (const checkout of checkouts) {
    const relative = safePath(checkout.path);
    need(new RegExp(`^quality-checkout-[1-9][0-9]*-${checkout.job_id}\\.log$`).test(relative)
      && Object.hasOwn(inventory, relative) && inventory[relative] === checkout.sha256,
      'Native quality checkout log is not bound by the evidence inventory.');
    const file = path.join(envelope, relative);
    matchingFile(file, checkout.sha256);
    qualityCheckout(fs.readFileSync(file, 'utf8'), entry.source_commit);
  }
  need(record.embedded_engine && /^[a-f0-9]{40}$/.test(record.embedded_engine.source_commit || '')
    && digest(record.embedded_engine.release_archive_sha256), 'Native published Engine source identity is absent.');
  matchingFile(path.join(envelope, 'embedded-engine-source.tar.gz'), record.embedded_engine.release_archive_sha256);
  if (kind === 'native_cpp') need(record.embedded_engine.source_commit === entry.source_commit
    && record.embedded_engine.release_archive_sha256 === entry.archive_sha256,
  'Engine embedding archive differs from the selected published source.');
  else need(record.build.extension_version === entry.version && record.build.actual_tuple?.embedded_engine_commit === record.embedded_engine.source_commit
    && record.build.actual_tuple.embedded_source_sha256 === record.embedded_engine.release_archive_sha256,
  'Upstream actual binding build does not match its recorded Engine source.');
  return record;
}

export async function downloadNativeEvidence(reference, file, request = fetch, token = process.env.GH_TOKEN) {
  evidenceReference(reference);
  const options = { signal: AbortSignal.timeout(60000), redirect: 'error' };
  let response;
  let expectedSize;
  if (reference.actions_artifact) {
    need(typeof token === 'string' && token.length > 0, 'Reading original Actions evidence requires the existing read token.');
    const artifact = reference.actions_artifact;
    const api = `https://api.github.com/repos/kumwe/extension-sdk/actions/artifacts/${artifact.id}`;
    const headers = { Accept: 'application/vnd.github+json', Authorization: `Bearer ${token}` };
    const metadataResponse = await request(api, { ...options, headers });
    need(metadataResponse.ok, `Original native artifact metadata read failed: HTTP ${metadataResponse.status}`);
    const metadata = await metadataResponse.json();
    need(metadata.id === artifact.id && metadata.name === artifact.name && metadata.expired === false
      && metadata.digest === `sha256:${reference.zip_sha256}`
      && metadata.workflow_run?.id === artifact.run_id && metadata.workflow_run.head_sha === artifact.verifier_commit
      && Number.isSafeInteger(metadata.workflow_run.repository_id) && metadata.workflow_run.repository_id > 0
      && metadata.workflow_run.repository_id === metadata.workflow_run.head_repository_id
      && Number.isSafeInteger(metadata.size_in_bytes) && metadata.size_in_bytes > 0 && metadata.size_in_bytes <= 150000000
      && metadata.archive_download_url === `${api}/zip`,
    'Original native Actions artifact identity, run, expiry or digest differs.');
    expectedSize = metadata.size_in_bytes;
    const redirect = await request(`${api}/zip`, { ...options, headers, redirect: 'manual' });
    need(redirect.status === 302, 'Original native artifact download must use the GitHub storage redirect.');
    const target = new URL(redirect.headers.get('location') || '');
    need(target.protocol === 'https:' && /^[a-z0-9-]+\.blob\.core\.windows\.net$/.test(target.hostname)
      && target.pathname.startsWith('/actions-results/') && !target.username && !target.password && !target.hash,
    'Original native artifact redirected outside GitHub Actions storage.');
    // The repository token is sent only to api.github.com, never to the storage redirect.
    response = await request(target.href, options);
  } else response = await request(reference.zip_uri, options);
  need(response.ok, `Native evidence download failed: HTTP ${response.status}`);
  const bytes = Buffer.from(await response.arrayBuffer());
  need(expectedSize === undefined || bytes.length === expectedSize, 'Original native artifact ZIP size differs.');
  need(bytes.length <= 150000000 && hash(bytes) === reference.zip_sha256, 'Original native evidence ZIP digest differs.');
  fs.writeFileSync(file, bytes, { flag: 'wx' });
}

function command(args) {
  const result = spawnSync(args[0], args.slice(1), { stdio: 'inherit' });
  need(!result.error && result.status === 0, `Native evidence preparation failed: ${args.join(' ')}`);
}

export async function materializeNativeEvidence(raw, destination) {
  const selection = nativeSelection(raw);
  const output = path.resolve(destination);
  need(!fs.existsSync(output), 'Native evidence output must be fresh.');
  fs.mkdirSync(output, { recursive: true, mode: 0o700 });
  const owners = {};
  for (const kind of ['engine', 'extension']) {
    const entry = selection[kind];
    const directory = path.join(output, kind);
    fs.mkdirSync(directory);
    for (const category of ['attestation', 'evidence']) {
      const zip = path.join(directory, `${category}.zip`);
      await downloadNativeEvidence(entry[category], zip);
      command(['php', path.join(here, 'extract-archive.php'), zip, path.join(directory, category)]);
    }
    const member = path.join(directory, 'attestation', 'RELEASE-ATTESTATION.yaml');
    need(fs.readdirSync(path.dirname(member)).length === 1, 'Native attestation ZIP has unexpected members.');
    const record = validateNativeEnvelope(entry, path.join(directory, 'evidence'), fs.readFileSync(member));
    owners[kind] = { entry, directory, record, member };
  }
  need(owners.extension.record.embedded_engine.source_commit === selection.engine.source_commit
    && owners.extension.record.embedded_engine.release_archive_sha256 === selection.engine.archive_sha256
    && owners.engine.record.embedded_engine.release_archive_sha256 === selection.engine.archive_sha256,
  'Engine and binding evidence do not identify the same source and published compressed archive.');
  const binding = owners.extension;
  const assets = path.join(binding.directory, 'evidence', 'publisher-assets');
  const extracted = path.join(output, 'binding-source');
  command(['python3', path.resolve(here, '../native-release-verification/extract-source.py'),
    path.join(assets, binding.record.input.archive_name), extracted, 'kumwe-engine-php']);
  const fixtureInput = { schema: 'kumwe-verified-native-fixture-input/v1', package: selection.extension.name,
    version: selection.extension.version, source_commit: selection.extension.source_commit,
    source_directory: path.join(extracted, 'kumwe-engine-php'),
    release_attestation_path: binding.member,
    release_attestation_sha256: selection.extension.attestation.member_sha256 };
  for (const [key, name] of [['source_record', 'source.json'], ['source_archive', binding.record.input.archive_name], ['source_sbom', 'source.spdx.json']]) {
    fixtureInput[`${key}_path`] = path.join(assets, name);
    fixtureInput[`${key}_sha256`] = binding.record.assets.find(a => a.identity === name).sha256;
  }
  save(path.join(output, 'native-fixture-input.json'), fixtureInput);
  const fixture = buildNativeFixture(fixtureInput, path.join(output, 'fixture'));
  const native = { extension_version: selection.extension.version, compatibility_tuple: json(fixture.expected_runtime_tuple) };
  for (const kind of ['engine', 'extension']) {
    const owner = owners[kind];
    native[kind] = { package: owner.entry.name, version: owner.entry.version, tag: `v${owner.entry.version}`,
      commit: owner.entry.source_commit, archive_sha256: owner.entry.archive_sha256,
      attestation: { uri: owner.entry.attestation.zip_uri, sha256: owner.entry.attestation.zip_sha256,
        member: owner.entry.attestation.member, member_sha256: owner.entry.attestation.member_sha256,
        archive_path: path.join(owner.directory, 'attestation.zip'), member_path: owner.member } };
  }
  native.engine.embedding_archive_path = path.join(binding.directory, 'evidence', 'embedded-engine-source.tar.gz');
  native.engine.embedding_archive_sha256 = binding.record.embedded_engine.release_archive_sha256;
  const result = { schema: 'kumwe-qualified-native-fixture/v1', status: 'passed', selection, fixture, native,
    verification_scope: 'Preserved upstream release/signature/source/offline-build evidence validated; this fresh fixture uses a source/build-derived expected tuple.' };
  save(path.join(output, 'qualification-fixture.json'), result);
  return result;
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  need(process.argv.length === 4, 'Usage: materialize-native-evidence.mjs NATIVE_SELECTION_JSON OUTPUT_DIRECTORY');
  await materializeNativeEvidence(json(process.argv[2]), process.argv[3]);
}
