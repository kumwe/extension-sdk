import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';
import YAML from '../release-verification/node_modules/yaml/dist/index.js';
import Ajv from '../release-verification/node_modules/ajv/dist/2020.js';

const here = path.dirname(fileURLToPath(import.meta.url));
const parsers = path.resolve(here, '../release-verification');
const hash = bytes => crypto.createHash('sha256').update(bytes).digest('hex');
const json = file => JSON.parse(fs.readFileSync(file, 'utf8'));
const save = (file, value) => fs.writeFileSync(file, JSON.stringify(value, null, 2) + '\n');
export const requireFact = (fact, message) => { if (!fact) throw new Error(message); };
const ajv = new Ajv({ strict: false, allErrors: true });
const handoffSchema = ajv.compile(json(path.join(parsers, 'migration-handoff.schema.json')));
const attestationSchema = ajv.compile(json(path.join(parsers, 'release-attestation.v2.schema.json')));
const inventories = {
  'kumwe/engine': ['native (ubuntu-24.04, gcc, g++)', 'native (ubuntu-24.04, clang, clang++)',
    'native (macos-14, clang, clang++)', 'sanitizers-fuzz', 'thread-sanitizer', 'archive-and-faults'],
  'kumwe/kumwe-engine': ['source-release-preparation', 'binding', 'address-undefined-sanitizers',
    'clean-pie', 'whole-boundary-benchmarks'],
};

export function coordinate(raw) {
  requireFact(raw && Object.hasOwn(inventories, raw.name), 'Only the two native owner repositories are supported.');
  requireFact(typeof raw.version === 'string' && /^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/.test(raw.version)
    && raw.version !== '0.0.0', 'An exact stable native version is required.');
  requireFact(/^[a-f0-9]{40}$/.test(raw.source_commit || ''), 'An exact native source commit is required.');
  requireFact(/^[a-f0-9]{64}$/.test(raw.archive_sha256 || ''), 'An independently supplied archive digest is required.');
  return { name: raw.name, version: raw.version, source_commit: raw.source_commit,
    archive_sha256: raw.archive_sha256, tag: `v${raw.version}`,
    kind: raw.name === 'kumwe/engine' ? 'native_cpp' : 'php_extension',
    archive_name: raw.name === 'kumwe/engine' ? 'kumwe-engine-source.tar.gz' : 'kumwe-engine-php-source.tar.gz' };
}

export function safePath(value) {
  requireFact(typeof value === 'string' && value.length > 0 && !value.startsWith('/') && !value.includes('\\')
    && !/[\x00-\x1f]/.test(value) && !value.split('/').some(p => ['', '.', '..'].includes(p)), 'Unsafe source path.');
  return value;
}

export function handoff(text, input) {
  const match = /^---\r?\n([\s\S]*?)\r?\n---(?:\r?\n|$)/.exec(text);
  requireFact(match, 'Missing or unterminated handoff front matter.');
  const record = YAML.parse(match[1], { uniqueKeys: true, maxAliasCount: 20 });
  requireFact(handoffSchema(record), `Full handoff schema failed: ${JSON.stringify(handoffSchema.errors)}`);
  requireFact(record.artifact_kind === input.kind && record.target.repository === `https://github.com/${input.name}`,
    'Native handoff owner or kind differs.');
  requireFact(record.governance.completion_claim === false, 'Native extraction cannot claim App completion.');
  const headings = ['Migration/implementation summary', 'Public API and responsibility',
    'Capability reuse/semantic input review', 'Consumer inventory', 'Test ownership', 'Next-task execution notes',
    'Drift check', 'Validation recipe and observed local results'];
  let previous = -1;
  for (const heading of headings) {
    const offset = text.indexOf(`## ${heading}`);
    requireFact(offset > previous, `Required ordered handoff section is missing: ${heading}`);
    previous = offset;
  }
  return record;
}

export function validateAttestation(record) {
  requireFact(attestationSchema(record), `Full release attestation schema failed: ${JSON.stringify(attestationSchema.errors)}`);
  requireFact(record.status === 'verified' && (!record.known_gaps || record.known_gaps.length === 0),
    'Only a complete independently verified receipt can qualify an upstream.');
  return record;
}

export function qualityRun(run, jobs, input, branch) {
  requireFact(run.head_sha === input.source_commit && run.head_branch === branch && run.event === 'push'
    && run.path === '.github/workflows/ci.yml' && run.status === 'completed' && run.conclusion === 'success'
    && run.repository?.full_name === input.name && run.head_repository?.full_name === input.name,
  'Native quality run does not identify the exact successful default-branch source.');
  const expected = inventories[input.name];
  const names = jobs.map(job => job.name);
  requireFact(names.length === expected.length && new Set(names).size === names.length
    && expected.every(name => names.includes(name)) && jobs.every(job => job.status === 'completed'
      && job.conclusion === 'success'), 'Missing, skipped, duplicated or failed required native quality job.');
}

export function releaseAssets(release, input) {
  requireFact(release.tag_name === input.tag && release.published_at && !release.draft && !release.prerelease,
    'A published stable native release is required.');
  const names = [input.archive_name, 'source.json', 'source.spdx.json', 'source.provenance.json',
    'SHA256SUMS', 'build-provenance.sigstore.json'];
  requireFact(release.assets?.length === names.length && new Set(release.assets.map(a => a.name)).size === names.length
    && names.every(name => release.assets.some(a => a.name === name && a.state === 'uploaded' && a.size > 0)),
  'Published native release must contain the six complete original assets.');
  return names;
}

async function request(url, binary = false) {
  const origin = new URL(url);
  requireFact(origin.protocol === 'https:' && ['api.github.com', 'raw.githubusercontent.com'].includes(origin.hostname),
    'Unsupported release verification origin.');
  const headers = { 'User-Agent': 'Kumwe-independent-native-release-verifier', Accept: 'application/vnd.github+json' };
  if (origin.hostname === 'api.github.com' && process.env.GH_TOKEN) headers.Authorization = `Bearer ${process.env.GH_TOKEN}`;
  if (binary) headers.Accept = 'application/octet-stream';
  const response = await fetch(url, { headers, signal: AbortSignal.timeout(60000) });
  requireFact(response.ok, `Release verification HTTP ${response.status}: ${url}`);
  requireFact(['api.github.com', 'raw.githubusercontent.com', 'release-assets.githubusercontent.com',
    'objects.githubusercontent.com', 'github.com'].includes(new URL(response.url).hostname), 'Unexpected asset redirect.');
  const bytes = Buffer.from(await response.arrayBuffer());
  requireFact(bytes.length <= 150000000, 'Native release response exceeds the verification budget.');
  return binary ? bytes : JSON.parse(bytes.toString('utf8'));
}

function command(args, cwd, log, extra = {}) {
  const fd = fs.openSync(log, 'w');
  try {
    const result = spawnSync(args[0], args.slice(1), { cwd, env: { ...process.env, ...extra },
      stdio: ['ignore', fd, fd], timeout: 1800000 });
    if (result.status !== 0) console.error(fs.readFileSync(log, 'utf8').slice(-16000));
    requireFact(result.status === 0, `Native verification command failed: ${args.join(' ')}; see ${log}`);
  } finally { fs.closeSync(fd); }
}

function readCommand(args, cwd) {
  const result = spawnSync(args[0], args.slice(1), { cwd, encoding: 'utf8', timeout: 60000 });
  requireFact(result.status === 0, `Source identity command failed: ${args.join(' ')}`);
  return result.stdout.trim();
}

async function upstreamReceipt(reference, expected, output, index) {
  requireFact(reference && /^[a-f0-9]{64}$/.test(reference.sha256 || '')
    && /^https:\/\/raw\.githubusercontent\.com\/kumwe\/extension-sdk\/[a-f0-9]{40}\/.+/.test(reference.uri || ''),
  'Upstream receipt must identify an immutable external Git YAML and its own digest.');
  const bytes = await request(reference.uri, true);
  requireFact(hash(bytes) === reference.sha256, 'Upstream attestation digest differs.');
  const record = validateUpstreamReceipt(YAML.parse(bytes.toString('utf8'), { uniqueKeys: true, maxAliasCount: 20 }), expected);
  fs.writeFileSync(path.join(output, `upstream-${index}.yaml`), bytes);
  return { uri: reference.uri, sha256: reference.sha256, repository: record.repository,
    version: record.version, source_commit: record.merge_commit };
}

export function validateUpstreamReceipt(raw, expected) {
  const record = validateAttestation(raw);
  requireFact(record.repository === `https://github.com/${expected.name}` && record.version === expected.version
    && record.merge_commit === expected.commit && record.tag === `v${expected.version}`, 'Upstream receipt coordinate differs.');
  if (expected.archive_sha256) requireFact(record.source_archive.sha256 === expected.archive_sha256,
    'Upstream receipt archive digest differs.');
  for (const [p, digest] of Object.entries(expected.corpora || {})) requireFact(record.manifests_and_corpora.some(
    entry => entry.path === p && entry.sha256 === digest), `Upstream receipt lacks the exact corpus: ${p}`);
  return record;
}

async function verifyUpstreams(root, input, output) {
  const engineRoot = input.kind === 'native_cpp' ? root : path.join(root, 'vendor/engine');
  const contracts = json(path.join(engineRoot, 'resources/contracts.json'));
  const result = [];
  const baseline = contracts.computation_baseline;
  requireFact(baseline?.state === 'release-verified' && baseline.native_bindings_present === false,
    'A verified extension-free portable baseline is required.');
  result.push(await upstreamReceipt(baseline.attestation, { name: 'kumwe/computation', version: baseline.version,
    commit: baseline.commit, archive_sha256: baseline.archive_sha256, corpora: { ...baseline.corpus_digests,
      'resources/public-api/v1.json': baseline.api_digest, 'resources/capabilities/v1.json': baseline.capability_digest } },
  output, result.length));
  for (const module of contracts.modules) {
    const release = module.semantic_release;
    requireFact(module.release_verified === true && release?.external_attestation, 'Unverified semantic owner.');
    const corpora = { ...release.corpus_digests, [safePath(release.corpus_path)]: release.corpus_sha256 };
    for (const [field, file] of [['api_digest', 'resources/public-api/v1.json'],
      ['capability_digest', 'resources/capabilities/v1.json'], ['service_map_digest', 'resources/service-map/v1.json']]) {
      if (Object.hasOwn(release, field)) corpora[file] = release[field];
    }
    result.push(await upstreamReceipt(release.external_attestation, { name: release.repository,
      version: release.version, commit: release.commit, archive_sha256: release.archive_sha256,
      corpora }, output, result.length));
  }
  if (input.kind === 'php_extension') {
    const lock = json(path.join(root, 'resources/engine-lock.json'));
    requireFact(lock.release_verified === true, 'Embedded Engine has no independent verified release.');
    requireFact(lock.release === `v${lock.version}` && /^[a-f0-9]{64}$/.test(lock.release_archive_sha256 || ''),
      'Embedded Engine must distinguish its stable release and compressed release archive from the raw embedding TAR.');
    result.push(await upstreamReceipt(lock.external_attestation, { name: 'kumwe/engine',
      version: lock.version, commit: lock.commit, archive_sha256: lock.release_archive_sha256 }, output, result.length));
  }
  return result;
}

export async function verifyNative(raw, destination) {
  const input = coordinate(raw);
  const output = path.resolve(destination);
  requireFact(!fs.existsSync(output), 'Refusing to reuse an evidence directory.');
  fs.mkdirSync(output, { recursive: true });
  const api = `https://api.github.com/repos/${input.name}`;
  const get = suffix => request(api + suffix);
  const repository = await get('');
  requireFact(repository.full_name === input.name && !repository.private, 'Wrong or non-public native owner.');
  const branch = repository.default_branch;
  let tag = await get(`/git/ref/tags/${input.tag}`);
  for (let count = 0; tag.object?.type === 'tag'; count++) {
    requireFact(count < 8, 'Excessively nested annotated tag.');
    tag = await get(`/git/tags/${tag.object.sha}`);
  }
  requireFact(tag.object?.type === 'commit' && tag.object.sha === input.source_commit, 'Observed native tag source differs.');
  const release = await get(`/releases/tags/${input.tag}`);
  const assets = releaseAssets(release, input);
  const runResponse = await get(`/actions/runs?head_sha=${input.source_commit}&per_page=100`);
  requireFact(runResponse.total_count <= runResponse.workflow_runs.length, 'Incomplete source workflow inventory.');
  const runs = runResponse.workflow_runs;
  const sourceQuality = runs.find(run => run.path === '.github/workflows/ci.yml' && run.event === 'push'
    && run.head_sha === input.source_commit && run.head_branch === branch && run.conclusion === 'success');
  requireFact(sourceQuality, 'No successful actual default-branch native source gate.');
  const quality = await get(`/actions/runs/${sourceQuality.id}`);
  const jobs = await get(`/actions/runs/${sourceQuality.id}/jobs?per_page=100`);
  requireFact(jobs.total_count === jobs.jobs.length, 'Incomplete native quality job inventory.');
  qualityRun(quality, jobs.jobs, input, branch);
  const publisher = runs.find(run => run.path === '.github/workflows/release.yml' && run.head_sha === input.source_commit
    && run.head_branch === branch && ['workflow_run', 'workflow_dispatch'].includes(run.event)
    && run.status === 'completed' && run.conclusion === 'success');
  requireFact(publisher, 'No successful exact-source native publication workflow.');
  const publisherJobs = await get(`/actions/runs/${publisher.id}/jobs?per_page=100`);
  requireFact(publisherJobs.total_count === publisherJobs.jobs.length
    && ['publisher-tests', 'publish'].every(name => publisherJobs.jobs.some(job => job.name === name
      && job.status === 'completed' && job.conclusion === 'success')), 'Native publisher did not complete both required jobs.');
  const merged = await get(`/commits/${input.source_commit}/pulls`);
  requireFact(merged.some(pr => pr.merged_at && pr.merge_commit_sha === input.source_commit),
    'Native source does not identify an observed merged pull request.');
  save(path.join(output, 'github-observations.json'), { repository, tag, release, quality, jobs, publisher, publisherJobs, merged });
  const bundle = path.join(output, 'publisher-assets');
  fs.mkdirSync(bundle);
  for (const name of assets) {
    const asset = release.assets.find(item => item.name === name);
    const bytes = await request(`${api}/releases/assets/${asset.id}`, true);
    requireFact(bytes.length === asset.size, `Published asset size differs: ${name}`);
    if (asset.digest) requireFact(asset.digest === `sha256:${hash(bytes)}`, `GitHub asset digest differs: ${name}`);
    fs.writeFileSync(path.join(name === 'build-provenance.sigstore.json' ? output : bundle, name), bytes);
  }
  requireFact(hash(fs.readFileSync(path.join(bundle, input.archive_name))) === input.archive_sha256,
    'Downloaded published source archive differs from the supplied identity.');
  const checkout = path.join(output, 'identity-checkout');
  command(['git', 'clone', '--no-checkout', `https://github.com/${input.name}.git`, checkout], output,
    path.join(output, 'source-checkout.log'));
  command(['git', 'checkout', '--detach', input.source_commit], checkout, path.join(output, 'source-selection.log'));
  requireFact(readCommand(['git', 'rev-parse', 'HEAD'], checkout) === input.source_commit, 'Checkout source differs.');
  command(['git', 'merge-base', '--is-ancestor', input.source_commit, `origin/${branch}`], checkout,
    path.join(output, 'default-branch-ancestry.log'));
  const source = json(path.join(bundle, 'source.json'));
  requireFact(source.package === input.name && source.source.repository === `https://github.com/${input.name}`
    && source.source.commit === input.source_commit && source.source.tree === readCommand(['git', 'rev-parse', 'HEAD^{tree}'], checkout)
    && source.identity.version === input.version && source.archive.sha256 === input.archive_sha256,
  'Published native source metadata differs from the exact observed Git source.');
  const signature = path.join(output, 'build-provenance.sigstore.json');
  for (const name of assets.filter(name => name !== 'build-provenance.sigstore.json')) {
    command(['gh', 'attestation', 'verify', path.join(bundle, name), '--bundle', signature, '--repo', input.name,
      '--source-digest', input.source_commit, '--source-ref', `refs/heads/${branch}`,
      '--cert-identity', `https://github.com/${input.name}/.github/workflows/release.yml@refs/heads/${branch}`,
      '--deny-self-hosted-runners', '--format', 'json'], output, path.join(output, `provenance-${name}.json`));
  }
  command(['python3', 'tools/release-source.py', 'verify', bundle, '--expected-commit', input.source_commit,
    '--expected-sha256', input.archive_sha256, '--require-stable'], checkout, path.join(output, 'source-bundle-verification.log'));
  const archive = path.join(output, 'archive');
  command(['python3', path.join(here, 'extract-source.py'), path.join(bundle, input.archive_name), archive,
    input.kind === 'native_cpp' ? 'kumwe-engine' : 'kumwe-engine-php'], output, path.join(output, 'archive-extraction.log'));
  const root = path.join(archive, input.kind === 'native_cpp' ? 'kumwe-engine' : 'kumwe-engine-php');
  const handoffRecord = handoff(fs.readFileSync(path.join(root, 'MIGRATION-HANDOFF.md'), 'utf8'), input);
  const manifests = handoffRecord.ownership.public_manifests.map(entry => {
    const p = safePath(entry.path); const digest = hash(fs.readFileSync(path.join(root, p)));
    requireFact(digest === entry.sha256, `Released native handoff hash differs: ${p}`);
    return { path: p, sha256: digest };
  });
  manifests.push({ path: 'MIGRATION-HANDOFF.md', sha256: hash(fs.readFileSync(path.join(root, 'MIGRATION-HANDOFF.md'))) });
  const engineRoot = input.kind === 'native_cpp' ? root : path.join(root, 'vendor/engine');
  const caps = json(path.join(engineRoot, 'resources/capabilities.json'));
  for (const corpus of caps.corpora) {
    const p = safePath(corpus.path); const digest = hash(fs.readFileSync(path.join(engineRoot, p)));
    requireFact(corpus.sha256 === digest, `Native corpus differs: ${p}`);
    const recordedPath = input.kind === 'native_cpp' ? p : `vendor/engine/${p}`;
    if (!manifests.some(m => m.path === recordedPath)) manifests.push({ path: recordedPath, sha256: digest });
  }
  const upstreams = await verifyUpstreams(root, input, output);
  const engineCommit = input.kind === 'native_cpp' ? input.source_commit : json(path.join(root, 'resources/engine-lock.json')).commit;
  let engineCheckout = checkout;
  if (input.kind === 'php_extension') {
    engineCheckout = path.join(output, 'engine-identity-checkout');
    command(['git', 'clone', '--no-checkout', 'https://github.com/kumwe/engine.git', engineCheckout], output,
      path.join(output, 'engine-checkout.log'));
    command(['git', 'checkout', '--detach', engineCommit], engineCheckout, path.join(output, 'engine-selection.log'));
  }
  const rawTar = path.join(output, 'embedded-engine-source.tar');
  command(['git', 'archive', '--format=tar', '--output', rawTar, engineCommit], engineCheckout,
    path.join(output, 'engine-raw-archive.log'));
  const rawDigest = hash(fs.readFileSync(rawTar));
  if (input.kind === 'php_extension') requireFact(rawDigest === json(path.join(root, 'resources/engine-lock.json')).archive_sha256,
    'Raw embedded Engine TAR differs from the exact published Engine commit.');
  const build = path.join(output, 'build-evidence'); fs.mkdirSync(build);
  command(['sudo', 'unshare', '--net', '--', 'env', '-u', 'GH_TOKEN', '-u', 'GITHUB_TOKEN', '-u', 'COMPOSER_AUTH',
    `PATH=${process.env.PATH}`, 'COMPOSER_DISABLE_NETWORK=1', `KUMWE_PIE_PATH=${process.env.KUMWE_PIE_PATH || ''}`,
    `KUMWE_PARENT_NET_NS=${fs.readlinkSync('/proc/self/ns/net')}`,
    'bash', path.join(here, 'offline-build.sh'), input.kind, root, build], output, path.join(output, 'offline-build.log'));
  const buildResult = json(path.join(build, 'verification.json'));
  requireFact(buildResult.network_disabled === true && buildResult.status === 'passed', 'Native offline build evidence is absent.');
  if (input.kind === 'php_extension') requireFact(buildResult.extension_version === input.version,
    'The actually installed module version differs from its release.');
  save(path.join(output, 'verification.json'), { schema: 'kumwe-independent-native-release-verification/v1',
    status: 'passed', input, verified_at: new Date().toISOString(),
    verifier: { repository: process.env.GITHUB_REPOSITORY || null, source_commit: process.env.GITHUB_SHA || null,
      run_url: process.env.GITHUB_RUN_ID ? `https://github.com/${process.env.GITHUB_REPOSITORY}/actions/runs/${process.env.GITHUB_RUN_ID}` : null },
    source_tree: source.source.tree, handoff: handoffRecord, manifests_and_corpora: manifests,
    signature_verification: { status: 'passed', repository: input.name, source_commit: input.source_commit,
      source_ref: `refs/heads/${branch}`,
      certificate_identity: `https://github.com/${input.name}/.github/workflows/release.yml@refs/heads/${branch}`,
      deny_self_hosted_runners: true, bundle_sha256: hash(fs.readFileSync(signature)),
      subjects: assets.filter(name => name !== 'build-provenance.sigstore.json').map(name => ({ name,
        sha256: hash(fs.readFileSync(path.join(bundle, name))) })) },
    source_verification: { status: 'passed', reproduced_source_bundle: true,
      archive_sha256: input.archive_sha256, source_commit: input.source_commit, source_tree: source.source.tree,
      full_handoff_schema: 'passed', upstream_attestation_schemas: 'passed' },
    publisher: { url: publisher.html_url, conclusion: publisher.conclusion },
    release: { url: release.html_url, id: release.id, published_at: release.published_at, platform_immutable: release.immutable === true },
    assets: release.assets.map(asset => ({ identity: asset.name, url: asset.browser_download_url,
      sha256: hash(fs.readFileSync(path.join(asset.name === 'build-provenance.sigstore.json' ? output : bundle, asset.name))) })),
    abi_and_capabilities: { abi_major: caps.abi_major, capabilities: caps.capabilities,
      corpus_digests: caps.corpora.map(c => c.sha256) }, upstreams,
    embedded_engine: { source_commit: engineCommit, raw_tar_sha256: rawDigest,
      release_archive_sha256: input.kind === 'native_cpp' ? input.archive_sha256
        : json(path.join(root, 'resources/engine-lock.json')).release_archive_sha256 }, build: buildResult });
  console.log(`Independently verified actual ${input.name} ${input.version}; external attestation is finalized separately.`);
}

export function finalize(output, evidenceUrl) {
  const v = json(path.join(output, 'verification.json'));
  requireFact(v.status === 'passed' && /^https:\/\/github\.com\/kumwe\/extension-sdk\/actions\/runs\/\d+\/artifacts\/\d+$/.test(evidenceUrl),
    'Finalization requires passing observed verification and its uploaded external evidence.');
  const source = v.assets.find(a => a.identity === v.input.archive_name);
  const sbom = v.assets.find(a => a.identity === 'source.spdx.json');
  const provenance = v.assets.find(a => a.identity === 'build-provenance.sigstore.json');
  const attestation = validateAttestation({ schema: 'kumwe-release-attestation/v2', artifact_kind: v.input.kind,
    migration_id: v.handoff.migration_id, change_set: v.handoff.change_set,
    repository: `https://github.com/${v.input.name}`, merge_commit: v.input.source_commit,
    version: v.input.version, tag: v.input.tag, source_archive: { url: source.url, sha256: source.sha256 },
    artifacts: v.assets, manifests_and_corpora: v.manifests_and_corpora, abi_and_capabilities: v.abi_and_capabilities,
    sbom: { url: sbom.url, sha256: sbom.sha256 },
    provenance: `Five original publisher assets verified using GitHub OIDC: ${provenance.url}; sha256=${provenance.sha256}; ${evidenceUrl}`,
    release_workflow: `${v.publisher.url} completed successfully for ${v.input.source_commit}`,
    registry_or_pie_verification: [v.input.kind === 'native_cpp'
      ? `Published native source/CMake distribution and installed standalone C11 consumer: ${evidenceUrl}`
      : `Original published source installed by pinned PIE 1.4.10 with networking disabled: ${evidenceUrl}`],
    clean_consumer_or_build_verification: [`Fresh original-archive build in a network namespace: ${evidenceUrl}`,
      `Exact source tree ${v.source_tree}; embedded Engine raw TAR SHA256 ${v.embedded_engine.raw_tar_sha256}`,
      `Full ABI/capability/corpus and installed consumer verification: ${evidenceUrl}`],
    verified_at: v.verified_at, verified_by: `Independent verifier ${v.verifier.run_url}; source ${v.verifier.source_commit}`,
    status: 'verified' });
  const text = YAML.stringify(attestation, { lineWidth: 0 });
  validateAttestation(YAML.parse(text, { uniqueKeys: true }));
  fs.writeFileSync(path.join(output, 'RELEASE-ATTESTATION.yaml'), text);
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  try {
    if (process.argv[2] === 'verify') await verifyNative(json(process.argv[3]), process.argv[4]);
    else if (process.argv[2] === 'finalize') finalize(process.argv[3], process.argv[4]);
    else throw new Error('Usage: verify-native.mjs verify COORDINATE OUTPUT | finalize OUTPUT EVIDENCE_URL');
  } catch (error) { console.error(error.stack); process.exitCode = 1; }
}
