import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';
import YAML from 'yaml';
import Ajv from 'ajv/dist/2020.js';

const here = path.dirname(fileURLToPath(import.meta.url));
const sha256 = bytes => crypto.createHash('sha256').update(bytes).digest('hex');
const json = p => JSON.parse(fs.readFileSync(p, 'utf8'));
const write = (p, value) => fs.writeFileSync(p, JSON.stringify(value, null, 2) + '\n');
const requireFact = (fact, description) => { if (!fact) throw new Error(description); };
const validateSchema = new Ajv({ strict: false, allErrors: true }).compile(json(path.join(here, 'migration-handoff.schema.json')));

export function coordinate(input) {
  requireFact(input && /^kumwe\/[a-z][a-z0-9-]*$/.test(input.name), 'Invalid package identity.');
  requireFact(/^\d+\.\d+\.\d+$/.test(input.version), 'Release must be an exact stable version.');
  requireFact(/^[a-f0-9]{40}$/.test(input.source_commit), 'A full immutable source commit is required.');
  return { ...input, repository: input.name, tag: `v${input.version}` };
}

export function safePath(p) {
  requireFact(typeof p === 'string' && p.length > 0 && !p.startsWith('/') && !p.includes('\\')
    && !p.split('/').some(x => ['', '.', '..'].includes(x)) && !/[\x00-\x1f]/.test(p), 'Unsafe manifest path.');
  return p;
}

export function handoffRecord(text, input) {
  const blocks = text.split(/^---\s*$/m);
  requireFact(blocks.length >= 3 && blocks[0].trim() === '', 'Missing YAML front matter.');
  const record = YAML.parse(blocks[1], { uniqueKeys: true, maxAliasCount: 20 });
  requireFact(validateSchema(record), `Handoff schema failed: ${JSON.stringify(validateSchema.errors)}`);
  requireFact(record.artifact_kind === 'framework_php', 'This verifier only qualifies framework PHP packages.');
  requireFact(record.target.repository === `https://github.com/${input.name}`
    && record.framework_php.composer_package === input.name, 'Handoff repository/package identity drift.');
  requireFact(record.governance.completion_claim === false, 'Extraction handoff cannot claim composed App completion.');
  return record;
}

async function request(url, binary = false) {
  const initial = new URL(url);
  requireFact(['api.github.com', 'repo.packagist.org'].includes(initial.hostname), 'Unsupported verification origin.');
  let last;
  for (let attempt = 0; attempt < 3; attempt++) {
    try {
      const headers = { 'User-Agent': 'Kumwe-independent-release-verifier', 'Accept': 'application/vnd.github+json' };
      if (initial.hostname === 'api.github.com' && process.env.GH_TOKEN) headers.Authorization = `Bearer ${process.env.GH_TOKEN}`;
      const response = await fetch(url, { headers, signal: AbortSignal.timeout(60000) });
      requireFact(['api.github.com', 'repo.packagist.org', 'codeload.github.com', 'github.com'].includes(new URL(response.url).hostname), 'Unapproved artifact redirect.');
      if (!response.ok) throw new Error(`HTTP ${response.status} for ${url}`);
      const bytes = Buffer.from(await response.arrayBuffer());
      requireFact(bytes.length <= 150000000, 'Response exceeds verification budget.');
      return binary ? bytes : JSON.parse(bytes.toString('utf8'));
    } catch (error) {
      last = error;
      if (attempt < 2) await new Promise(resolve => setTimeout(resolve, 1000 * (attempt + 1)));
    }
  }
  throw last;
}

function command(args, cwd, log, env = {}) {
  const fd = fs.openSync(log, 'w');
  try {
    const result = spawnSync(args[0], args.slice(1), { cwd, env: { ...process.env, ...env },
      stdio: ['ignore', fd, fd], timeout: 1200000 });
    if (result.status !== 0) console.error(fs.readFileSync(log, 'utf8').slice(-16000));
    requireFact(result.status === 0, `Command failed (${result.status}): ${args.join(' ')}; see ${log}`);
  } finally { fs.closeSync(fd); }
}

function filesBelow(directory, relative = '') {
  const result = [];
  for (const entry of fs.readdirSync(path.join(directory, relative), { withFileTypes: true })) {
    const p = relative ? `${relative}/${entry.name}` : entry.name;
    requireFact(!entry.isSymbolicLink(), 'Source archive contains a symlink.');
    if (entry.isDirectory()) result.push(...filesBelow(directory, p));
    else { requireFact(entry.isFile(), 'Source archive contains a special file.'); result.push(p); }
  }
  return result.sort();
}

function spdx(input, archiveFiles, packageRoot, timestamp) {
  const files = archiveFiles.map((p, index) => ({ SPDXID: `SPDXRef-File-${index + 1}`, fileName: `./${p}`,
    checksums: [{ algorithm: 'SHA256', checksumValue: sha256(fs.readFileSync(path.join(packageRoot, p))) },
      { algorithm: 'SHA1', checksumValue: crypto.createHash('sha1').update(fs.readFileSync(path.join(packageRoot, p))).digest('hex') }],
    licenseConcluded: 'NOASSERTION', licenseInfoInFiles: ['NOASSERTION'], copyrightText: 'NOASSERTION' }));
  const declared = Array.isArray(input.composer.license) ? input.composer.license.join(' OR ') : input.composer.license;
  const verificationCode = crypto.createHash('sha1').update(files.map(f => f.checksums[1].checksumValue).sort().join('')).digest('hex');
  return { spdxVersion: 'SPDX-2.3', dataLicense: 'CC0-1.0', SPDXID: 'SPDXRef-DOCUMENT',
    name: `${input.name}-${input.version}-independent-source-inventory`,
    documentNamespace: `https://github.com/kumwe/extension-sdk/release-verification/${input.source_commit}/${sha256(Buffer.from(timestamp)).slice(0, 16)}`,
    creationInfo: { created: timestamp, creators: ['Tool: kumwe-independent-release-verifier'],
      comment: 'Generated by the independent verifier from the unchanged published source ZIP. This is not publisher build provenance; per-file licenses remain NOASSERTION unless separately established.' },
    documentDescribes: ['SPDXRef-Package'], packages: [{ name: input.name, SPDXID: 'SPDXRef-Package',
      versionInfo: input.version, downloadLocation: input.archive_url, filesAnalyzed: true,
      packageVerificationCode: { packageVerificationCodeValue: verificationCode },
      checksums: [{ algorithm: 'SHA256', checksumValue: input.archive_sha256 }],
      licenseConcluded: 'NOASSERTION', licenseDeclared: declared || 'NOASSERTION', copyrightText: 'NOASSERTION',
      externalRefs: [{ referenceCategory: 'PACKAGE-MANAGER', referenceType: 'purl',
        referenceLocator: `pkg:composer/${input.name}@${input.version}` }] }], files,
    relationships: files.map(f => ({ spdxElementId: 'SPDXRef-Package', relationshipType: 'CONTAINS', relatedSpdxElement: f.SPDXID })) };
}

export async function verifyRelease(raw, output) {
  const input = coordinate(raw);
  const api = `https://api.github.com/repos/${input.repository}`;
  fs.mkdirSync(output, { recursive: true });
  const get = suffix => request(api + suffix);
  let tag = await get(`/git/ref/tags/${input.tag}`);
  while (tag.object.type === 'tag') tag = await get(`/git/tags/${tag.object.sha}`);
  requireFact(tag.object.type === 'commit' && tag.object.sha === input.source_commit, 'Tag does not identify the supplied source commit.');
  const release = await get(`/releases/tags/${input.tag}`);
  requireFact(!release.draft && !release.prerelease && release.tag_name === input.tag && release.published_at, 'Release is not published stable.');
  const allRuns = (await get(`/actions/runs?head_sha=${input.source_commit}&per_page=100`)).workflow_runs;
  const releaseRuns = allRuns.filter(r => r.head_sha === input.source_commit && /release/i.test(r.name)
    && r.status === 'completed' && r.conclusion === 'success' && ['push', 'workflow_dispatch'].includes(r.event));
  requireFact(releaseRuns.length > 0, 'No successful release-on-record workflow for the actual released source.');
  const mergePulls = (await get(`/commits/${input.source_commit}/pulls`)).filter(p => p.merged_at && p.merge_commit_sha === input.source_commit);
  requireFact(mergePulls.length > 0, 'Published source has no observed merged pull request.');
  const registry = await request(`https://repo.packagist.org/p2/${input.name}.json`);
  const registered = registry.packages[input.name].filter(p => p.version === input.tag || p.version === input.version);
  requireFact(registered.length === 1, 'Registry has no unique exact release.');
  const version = registered[0];
  requireFact(version.source?.reference === input.source_commit && version.dist?.reference === input.source_commit,
    'Registry source/dist differs from tag.');
  requireFact(version.dist.type === 'zip' && version.dist.url === `${api}/zipball/${input.source_commit}`, 'Unexpected Composer dist coordinate.');
  input.archive_url = version.dist.url;
  input.archive_path = path.resolve(output, 'source.zip');
  const archive = await request(input.archive_url, true);
  input.archive_sha256 = sha256(archive);
  fs.writeFileSync(input.archive_path, archive);
  const extracted = path.resolve(output, 'archive');
  command(['php', path.join(here, 'extract-archive.php'), input.archive_path, extracted], output, path.join(output, 'archive-extraction.log'));
  const roots = fs.readdirSync(extracted);
  requireFact(roots.length === 1 && fs.statSync(path.join(extracted, roots[0])).isDirectory(), 'Archive requires one root.');
  input.package_root = path.join(extracted, roots[0]);
  input.composer = json(path.join(input.package_root, 'composer.json'));
  requireFact(input.composer.name === input.name, 'Archived Composer package identity differs.');
  const sourceTree = await get(`/git/trees/${input.source_commit}?recursive=1`);
  requireFact(!sourceTree.truncated, 'Source tree response is incomplete.');
  const blobs = new Map(sourceTree.tree.filter(e => e.type === 'blob').map(e => [e.path, e]));
  const archiveFiles = filesBelow(input.package_root);
  for (const p of archiveFiles) {
    const bytes = fs.readFileSync(path.join(input.package_root, p));
    const object = crypto.createHash('sha1').update(`blob ${bytes.length}\0`).update(bytes).digest('hex');
    requireFact(blobs.get(p)?.sha === object, `Published archive content differs from source: ${p}`);
  }
  const handoff = handoffRecord(fs.readFileSync(path.join(input.package_root, 'MIGRATION-HANDOFF.md'), 'utf8'), input);
  const manifests = handoff.ownership.public_manifests.map(m => {
    const p = safePath(m.path); const actual = sha256(fs.readFileSync(path.join(input.package_root, p)));
    requireFact(actual === m.sha256, `Released handoff digest drift: ${p}`);
    return { path: p, sha256: actual };
  });
  for (const key of ['public_api_manifest', 'capability_manifest', 'service_map']) {
    const p = handoff.framework_php[key];
    requireFact(manifests.some(m => m.path === p), `Handoff does not bind ${key}.`);
  }
  const licensePath = ['LICENSE', 'LICENSE.md', 'LICENSE.txt'].find(p => archiveFiles.includes(p));
  requireFact(licensePath && input.composer.license, 'License declaration/inventory is absent.');
  input.examples = handoff.documentation.examples.map(safePath);
  input.handoff = handoff;
  write(path.join(output, 'prepared-package.json'), input);
  write(path.join(output, 'github-evidence.json'), { tag, release, release_runs: releaseRuns, merge_pull_requests: mergePulls,
    registry: version, source_tree_sha: sourceTree.sha, source_tree_complete: true });
  const checkout = path.resolve(output, 'source-checkout');
  command(['git', '-c', 'advice.detachedHead=false', 'clone', '--depth=1', '--branch', input.tag,
    `https://github.com/${input.name}.git`, checkout], output, path.join(output, 'source-checkout.log'));
  const head = spawnSync('git', ['rev-parse', 'HEAD'], { cwd: checkout, encoding: 'utf8' });
  requireFact(head.status === 0 && head.stdout.trim() === input.source_commit, 'Verification checkout moved from immutable source.');
  command(['composer', 'install', '--no-interaction', '--prefer-dist', '--no-progress'], checkout, path.join(output, 'package-install.log'));
  command(['composer', '--no-plugins', 'check'], checkout, path.join(output, 'package-check.log'));
  command(['composer', 'audit', '--abandoned=fail', '--format=json'], checkout, path.join(output, 'security-audit.log'));
  command(['php', path.join(here, 'verify-consumer.php'), path.resolve(output, 'prepared-package.json'),
    path.resolve(output, 'consumer-verification.json')], output, path.join(output, 'consumer-verification.log'));
  command(['git', 'diff', '--exit-code', 'HEAD', '--'], checkout, path.join(output, 'source-unchanged.log'));
  let finalTag = await get(`/git/ref/tags/${input.tag}`);
  while (finalTag.object.type === 'tag') finalTag = await get(`/git/tags/${finalTag.object.sha}`);
  requireFact(finalTag.object.sha === input.source_commit, 'Tag changed during independent verification.');
  const observed = new Date().toISOString().replace(/\.\d{3}Z$/, 'Z');
  write(path.join(output, 'source.spdx.json'), spdx(input, archiveFiles, input.package_root, observed));
  const corpusFiles = archiveFiles.filter(p => p.startsWith('resources/conformance/') || p.startsWith('resources/corpus/'));
  const evidence = { schema: 'kumwe-independent-release-verification/v1', status: 'passed', input,
    manifests_and_corpora: [...manifests, ...corpusFiles.filter(p => !manifests.some(m => m.path === p))
      .map(p => ({ path: p, sha256: sha256(fs.readFileSync(path.join(input.package_root, p))) }))],
    archived_files: archiveFiles.length, handoff_sha256: sha256(fs.readFileSync(path.join(input.package_root, 'MIGRATION-HANDOFF.md'))),
    license: { declaration: input.composer.license, path: licensePath, sha256: sha256(fs.readFileSync(path.join(input.package_root, licensePath))) },
    release_workflow: releaseRuns[0].html_url, merged_pull_request: mergePulls[0].html_url,
    platform_immutable_flag: release.immutable === true, observed_at: observed,
    verifier: { repository: process.env.GITHUB_REPOSITORY || 'local-verifier', commit: process.env.GITHUB_SHA || null,
      run_url: process.env.GITHUB_RUN_ID ? `https://github.com/${process.env.GITHUB_REPOSITORY}/actions/runs/${process.env.GITHUB_RUN_ID}` : null },
    consumer: json(path.join(output, 'consumer-verification.json')) };
  write(path.join(output, 'verification-provenance.json'), { _type: 'https://in-toto.io/Statement/v1',
    subject: [{ name: input.archive_url, digest: { sha256: input.archive_sha256 } }],
    predicateType: 'https://kumwe.dev/attestations/independent-release-verification/v1',
    predicate: { verification: 'Unchanged published source archive verified; not publisher build provenance.',
      verifier: evidence.verifier, source_commit: input.source_commit, release_workflow: evidence.release_workflow,
      package_gate: 'passed', security_audit: 'passed', direct_archive_consumer: evidence.consumer,
      source_files_matched_to_git: archiveFiles.length, observed_at: observed } });
  write(path.join(output, 'verification.json'), evidence);
  console.log(`${input.name} ${input.version}: independent source, package and offline consumer verification passed.`);
  return evidence;
}

export function finalize(output, evidenceUrl) {
  requireFact(/^https:\/\/github\.com\/kumwe\/extension-sdk\/actions\/runs\/\d+\/artifacts\/\d+$/.test(evidenceUrl), 'A real uploaded evidence artifact URL is required.');
  const evidence = json(path.join(output, 'verification.json'));
  requireFact(evidence.status === 'passed' && evidence.verifier.run_url, 'Only a completed hosted verification can attest.');
  const { input } = evidence;
  const artifact = p => `${evidenceUrl}#${p}`;
  const record = { schema: 'kumwe-release-attestation/v2', artifact_kind: 'framework_php',
    migration_id: input.handoff.migration_id, change_set: input.handoff.change_set,
    repository: `https://github.com/${input.name}`, merge_commit: input.source_commit, version: input.version,
    tag: input.tag, source_archive: { url: input.archive_url, sha256: input.archive_sha256 },
    artifacts: [{ identity: `${input.name}:${input.version}`, url: input.archive_url, sha256: input.archive_sha256 }],
    manifests_and_corpora: evidence.manifests_and_corpora, abi_and_capabilities: null,
    sbom: { url: artifact('source.spdx.json'), sha256: sha256(fs.readFileSync(path.join(output, 'source.spdx.json'))) },
    provenance: { url: artifact('verification-provenance.json'), sha256: sha256(fs.readFileSync(path.join(output, 'verification-provenance.json'))),
      kind: 'independent-verification', publisher_build_provenance: false },
    release_workflow: { url: evidence.release_workflow, result: 'success', source_commit: input.source_commit },
    registry_or_pie_verification: [{ registry: `https://repo.packagist.org/p2/${input.name}.json`,
      source_commit: input.source_commit, dist_commit: input.source_commit, evidence: artifact('github-evidence.json') }],
    clean_consumer_or_build_verification: [{ evidence: artifact('consumer-verification.json'),
      sha256: sha256(fs.readFileSync(path.join(output, 'consumer-verification.json'))), no_dev: true,
      classmap_authoritative: true, offline_reinstall: true, runtime_types_loaded: evidence.consumer.runtime_types_loaded }],
    verified_at: evidence.observed_at, verified_by: evidence.verifier, status: 'verified' };
  fs.writeFileSync(path.join(output, 'RELEASE-ATTESTATION.yaml'), YAML.stringify(record));
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const [mode, input, output] = process.argv.slice(2);
  try {
    if (mode === 'verify') await verifyRelease(json(input), path.resolve(output));
    else if (mode === 'finalize') finalize(path.resolve(input), output);
    else throw new Error('Usage: node verify-release.mjs verify PACKAGE_JSON OUTPUT | finalize OUTPUT UPLOADED_EVIDENCE_URL');
  } catch (error) {
    const folder = mode === 'verify' ? output : input;
    if (folder) { fs.mkdirSync(folder, { recursive: true }); write(path.join(folder, 'FAILED-VERIFICATION.json'),
      { status: 'failed', reason: error.message, observed_at: new Date().toISOString() }); }
    console.error(error.stack); process.exitCode = 1;
  }
}
