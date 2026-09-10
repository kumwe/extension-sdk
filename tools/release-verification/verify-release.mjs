import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';
import YAML from 'yaml';
import Ajv from 'ajv/dist/2020.js';
import { nativeFixtureScope } from './native-fixture-scope.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const sha256 = bytes => crypto.createHash('sha256').update(bytes).digest('hex');
const json = p => JSON.parse(fs.readFileSync(p, 'utf8'));
const write = (p, value) => fs.writeFileSync(p, JSON.stringify(value, null, 2) + '\n');
const requireFact = (fact, description) => { if (!fact) throw new Error(description); };
const ajv = new Ajv({ strict: false, allErrors: true });
const validateSchema = ajv.compile(json(path.join(here, 'migration-handoff.schema.json')));
const validateAttestation = ajv.compile(json(path.join(here, 'release-attestation.v2.schema.json')));
const canonicalSchemas = { public_api_manifest: 'package-public-api.v1.schema.json',
  capability_manifest: 'package-capabilities.v1.schema.json', service_map: 'package-service-map.v1.schema.json' };
const canonicalValidators = Object.fromEntries(Object.entries(canonicalSchemas).map(([key, file]) =>
  [key, ajv.compile(json(path.join(here, file)))]));

export function serializeAttestation(record) {
  requireFact(validateAttestation(record), `Attestation schema failed: ${JSON.stringify(validateAttestation.errors)}`);
  const text = YAML.stringify(record, { lineWidth: 0, blockQuote: false });
  requireFact(JSON.stringify(YAML.parse(text, { uniqueKeys: true })) === JSON.stringify(record),
    'Attestation serialization changed its validated values.');
  return text;
}

export function canonicalDocument(key, document) {
  const validate = canonicalValidators[key];
  requireFact(validate && validate(document), `Canonical ${key} schema failed: ${JSON.stringify(validate?.errors)}`);
}

export function canonicalManifests(packageRoot, handoff, expectedVersion = null) {
  return Object.entries(canonicalValidators).map(([key, validate]) => {
    const p = safePath(handoff.framework_php[key]);
    const document = json(path.join(packageRoot, p));
    canonicalDocument(key, document);
    requireFact(document.package === handoff.framework_php.composer_package, `Canonical package identity drift: ${p}`);
    requireFact(expectedVersion === null || document.release.replace(/^v/, '') === expectedVersion, `Canonical release identity drift: ${p}`);
    requireFact(!document.namespace || document.namespace.replace(/\\$/, '') === handoff.framework_php.canonical_namespace.replace(/\\$/, ''), `Canonical namespace identity drift: ${p}`);
    return { path: p, schema: canonicalSchemas[key], sha256: sha256(fs.readFileSync(path.join(packageRoot, p))) };
  });
}

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
      if (!response.ok) {
        const reason = (await response.text()).slice(0, 1500);
        const limits = { limit: response.headers.get('x-ratelimit-limit'), resource: response.headers.get('x-ratelimit-resource'), remaining: response.headers.get('x-ratelimit-remaining'), reset: response.headers.get('x-ratelimit-reset'), retry_after: response.headers.get('retry-after') };
        const error = new Error(`HTTP ${response.status} for ${url}; ${JSON.stringify(limits)}; ${reason}`);
        if (response.status === 429 || (response.status === 403 && limits.remaining === '0')) {
          const retry = Number(limits.retry_after);
          error.waitSeconds = retry > 0 ? retry : Math.max(1, Number(limits.reset) - Math.floor(Date.now() / 1000) + 2);
        }
        throw error;
      }
      const bytes = Buffer.from(await response.arrayBuffer());
      requireFact(bytes.length <= 150000000, 'Response exceeds verification budget.');
      return binary ? bytes : JSON.parse(bytes.toString('utf8'));
    } catch (error) {
      last = error;
      if (attempt < 2) {
        if (error.waitSeconds) {
          requireFact(Number.isFinite(error.waitSeconds) && error.waitSeconds <= 900, `Rate-limit reset exceeds this bounded retry budget; ${error.message}`);
          let remaining = error.waitSeconds;
          while (remaining > 0) {
            console.log(`Respecting GitHub rate-limit reset; retry in ${remaining} seconds.`);
            const pause = Math.min(remaining, 60);
            await new Promise(resolve => setTimeout(resolve, pause * 1000));
            remaining -= pause;
          }
        } else await new Promise(resolve => setTimeout(resolve, 1000 * (attempt + 1)));
      }
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

export async function verifyRelease(raw, output, nativeFixture = null) {
  const input = coordinate(raw);
  const verifierHead = spawnSync('git', ['rev-parse', 'HEAD'], { cwd: path.resolve(here, '../..'), encoding: 'utf8' });
  requireFact(verifierHead.status === 0 && /^[a-f0-9]{40}$/.test(verifierHead.stdout.trim()), 'The verifier source commit cannot be established.');
  const api = `https://api.github.com/repos/${input.repository}`;
  fs.mkdirSync(output, { recursive: true });
  const get = suffix => request(api + suffix);
  let tag = await get(`/git/ref/tags/${input.tag}`);
  while (tag.object.type === 'tag') tag = await get(`/git/tags/${tag.object.sha}`);
  requireFact(tag.object.type === 'commit' && tag.object.sha === input.source_commit, 'Tag does not identify the supplied source commit.');
  const release = await get(`/releases/tags/${input.tag}`);
  requireFact(!release.draft && !release.prerelease && release.tag_name === input.tag && release.published_at, 'Release is not published stable.');
  const allRuns = (await get(`/actions/runs?head_sha=${input.source_commit}&per_page=100`)).workflow_runs;
  const releaseRuns = allRuns.filter(r => r.head_sha === input.source_commit && r.path === '.github/workflows/release-on-record.yml'
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
  command(['php', '-r', 'if(extension_loaded("kumwe_engine")){fwrite(STDERR,"Base verification PHP must be native-free.\\n");exit(1);}'],
    output, path.join(output, 'base-php-verification.log'));
  const nativeRuntime = Object.hasOwn(input.composer.require || {}, 'ext-kumwe_engine');
  const nativeDevelopment = Object.hasOwn(input.composer['require-dev'] || {}, 'ext-kumwe_engine')
    || (input.name === 'kumwe/extension-sdk' && Object.hasOwn(input.composer['require-dev'] || {}, 'kumwe/computation'));
  const nativeRequired = nativeRuntime || nativeDevelopment;
  requireFact(nativeRequired === Boolean(nativeFixture), 'Actual native source requirements and supplied verified fixture do not agree.');
  const nativeScope = nativeRequired ? nativeFixtureScope(path.resolve(nativeFixture), path.resolve(output, 'native-php-scope')) : null;
  if (nativeRuntime) requireFact(input.composer.require['ext-kumwe_engine'] === nativeScope.result.selection.extension.version,
    'Published native requirement differs from the actual verified stable extension.');
  if (nativeScope) write(path.join(output, 'native-fixture-verification.json'), nativeScope.result);
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
  const canonicalValidation = canonicalManifests(input.package_root, handoff, input.version);
  write(path.join(output, 'canonical-schema-verification.json'), { status: 'passed', validators: json(path.join(here, 'schema-sources.json')), manifests: canonicalValidation });
  const manifests = handoff.ownership.public_manifests.map(m => {
    const p = safePath(m.path); const actual = sha256(fs.readFileSync(path.join(input.package_root, p)));
    requireFact(actual === m.sha256, `Released handoff digest drift: ${p}`);
    return { path: p, sha256: actual };
  });
  const handoffSha256 = sha256(fs.readFileSync(path.join(input.package_root, 'MIGRATION-HANDOFF.md')));
  requireFact(!manifests.some(m => m.path === 'MIGRATION-HANDOFF.md'), 'A handoff cannot self-declare its own digest.');
  manifests.push({ path: 'MIGRATION-HANDOFF.md', sha256: handoffSha256 });
  for (const key of ['public_api_manifest', 'capability_manifest', 'service_map']) {
    const p = handoff.framework_php[key];
    requireFact(manifests.some(m => m.path === p), `Handoff does not bind ${key}.`);
  }
  const licensePath = ['LICENSE', 'LICENSE.md', 'LICENSE.txt'].find(p => archiveFiles.includes(p));
  requireFact(licensePath && input.composer.license, 'License declaration/inventory is absent.');
  input.examples = handoff.documentation.examples.map(safePath);
  // tests.corpora is a list of descriptions, not path-schema fields. Corpus bytes are
  // checked by complete Git export equality, bound manifests and the corpus inventory.
  for (const p of [...['charter', 'readme', 'public_api', 'architecture', 'integration_or_consumer'].map(key => handoff.documentation[key]), ...input.examples]) {
    requireFact(fs.statSync(path.join(input.package_root, safePath(p))).isFile(), `Declared handoff artifact is absent: ${p}`);
  }
  input.handoff = handoff;
  write(path.join(output, 'prepared-package.json'), input);
  write(path.join(output, 'github-evidence.json'), { tag, release, release_runs: releaseRuns, merge_pull_requests: mergePulls,
    registry: version, source_tree_sha: sourceTree.sha, source_tree_complete: true });
  const checkout = path.resolve(output, 'source-checkout');
  command(['git', '-c', 'advice.detachedHead=false', 'clone', '--depth=1', '--branch', input.tag,
    `https://github.com/${input.name}.git`, checkout], output, path.join(output, 'source-checkout.log'));
  const head = spawnSync('git', ['rev-parse', 'HEAD'], { cwd: checkout, encoding: 'utf8' });
  requireFact(head.status === 0 && head.stdout.trim() === input.source_commit, 'Verification checkout moved from immutable source.');
  const exportZip = path.resolve(output, 'reference-export.zip');
  command(['git', '-c', 'core.attributesfile=/dev/null', 'archive', '--format=zip', '--prefix=reference/',
    `--output=${exportZip}`, input.source_commit], checkout, path.join(output, 'reference-export.log'), { GIT_ATTR_NOSYSTEM: '1' });
  const exportDirectory = path.resolve(output, 'reference-export');
  command(['php', path.join(here, 'extract-archive.php'), exportZip, exportDirectory], output, path.join(output, 'reference-export-extract.log'));
  const exportRoot = path.join(exportDirectory, 'reference');
  const expectedFiles = filesBelow(exportRoot);
  requireFact(JSON.stringify(expectedFiles) === JSON.stringify(archiveFiles), 'Published archive differs from the complete git export inventory.');
  const exportInventory = expectedFiles.map(p => {
    const expected = fs.readFileSync(path.join(exportRoot, p));
    requireFact(expected.equals(fs.readFileSync(path.join(input.package_root, p))), `Published archive differs from complete git export bytes: ${p}`);
    return { path: p, sha256: sha256(expected), bytes: expected.length };
  });
  write(path.join(output, 'archive-inventory-verification.json'), { status: 'passed', source_commit: input.source_commit,
    export_ignore_honored: true, omitted_files: [], additional_files: [], files: exportInventory });
  fs.unlinkSync(exportZip);
  for (const directory of ['tools/governance', 'tools/schema-validator', 'tools/release-verification']) {
    if (fs.existsSync(path.join(checkout, directory, 'package-lock.json'))) {
      command(['npm', 'ci', '--ignore-scripts', '--no-audit'], path.join(checkout, directory),
        path.join(output, directory.replaceAll('/', '-') + '-npm-install.log'));
    }
  }
  const sourceEnvironment = nativeScope?.environment || {};
  command(['composer', 'install', '--no-interaction', '--prefer-dist', '--no-progress'], checkout, path.join(output, 'package-install.log'), sourceEnvironment);
  command(['composer', '--no-plugins', 'check'], checkout, path.join(output, 'package-check.log'), sourceEnvironment);
  command(['composer', 'audit', '--abandoned=fail', '--format=json'], checkout, path.join(output, 'security-audit.log'), sourceEnvironment);
  command(['php', path.join(here, 'verify-consumer.php'), path.resolve(output, 'prepared-package.json'),
    path.resolve(output, 'consumer-verification.json')], output, path.join(output, 'consumer-verification.log'), nativeRuntime ? sourceEnvironment : {});
  command(['git', 'diff', '--exit-code', 'HEAD', '--'], checkout, path.join(output, 'source-unchanged.log'));
  let finalTag = await get(`/git/ref/tags/${input.tag}`);
  while (finalTag.object.type === 'tag') finalTag = await get(`/git/tags/${finalTag.object.sha}`);
  requireFact(finalTag.object.sha === input.source_commit, 'Tag changed during independent verification.');
  const observed = new Date().toISOString().replace(/\.\d{3}Z$/, 'Z');
  write(path.join(output, 'source.spdx.json'), spdx(input, archiveFiles, input.package_root, observed));
  const corpusFiles = archiveFiles.filter(p => p.startsWith('resources/conformance/') || p.startsWith('resources/corpus/'));
  const evidence = { schema: 'kumwe-independent-release-verification/v1', status: 'passed', input,
    canonical_schema_validation: canonicalValidation,
    manifests_and_corpora: [...manifests, ...corpusFiles.filter(p => !manifests.some(m => m.path === p))
      .map(p => ({ path: p, sha256: sha256(fs.readFileSync(path.join(input.package_root, p))) }))],
    archived_files: archiveFiles.length, complete_git_export_matched: true, handoff_sha256: handoffSha256,
    license: { declaration: input.composer.license, path: licensePath, sha256: sha256(fs.readFileSync(path.join(input.package_root, licensePath))) },
    release_workflow: releaseRuns[0].html_url, merged_pull_request: mergePulls[0].html_url,
    platform_immutable_flag: release.immutable === true, observed_at: observed,
    verifier: { repository: process.env.GITHUB_REPOSITORY || 'local-verifier', commit: verifierHead.stdout.trim(),
      workflow_event_commit: process.env.GITHUB_SHA || null,
      run_url: process.env.GITHUB_RUN_ID ? `https://github.com/${process.env.GITHUB_REPOSITORY}/actions/runs/${process.env.GITHUB_RUN_ID}` : null },
    consumer: json(path.join(output, 'consumer-verification.json')) };
  evidence.native_fixture = nativeScope ? { source_checks: 'qualified stable native fixture',
    archive_consumer: nativeRuntime ? 'qualified stable native fixture' : 'original native-free PHP',
    extension: nativeScope.result.native.extension, engine: nativeScope.result.native.engine } : null;
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
  requireFact(evidence.complete_git_export_matched === true, 'Complete git export inventory must match before attesting.');
  requireFact(evidence.consumer.installed_dist_identity_verified === true
    && Number.isInteger(evidence.consumer.canonical_api_exports_verified) && evidence.consumer.canonical_api_exports_verified > 0
    && evidence.consumer.consumer_dependency_audit === 'passed', 'Complete canonical API and installed consumer identity/security must pass before attesting.');
  const canonicalProof = json(path.join(output, 'canonical-schema-verification.json'));
  requireFact(canonicalProof.status === 'passed' && canonicalProof.manifests?.length === 3
    && evidence.canonical_schema_validation?.length === 3, 'All canonical manifest schemas must pass before attesting.');
  canonicalManifests(input.package_root, input.handoff, input.version);
  requireFact(sha256(fs.readFileSync(input.archive_path)) === input.archive_sha256, 'Source archive changed before attestation.');
  const handoffManifests = evidence.manifests_and_corpora.filter(m => m.path === 'MIGRATION-HANDOFF.md');
  requireFact(handoffManifests.length === 1 && handoffManifests[0].sha256 === evidence.handoff_sha256
    && sha256(fs.readFileSync(path.join(input.package_root, 'MIGRATION-HANDOFF.md'))) === evidence.handoff_sha256,
  'Attestation must retain the unchanged independently verified handoff digest.');
  const artifact = p => `${evidenceUrl}#${p}`;
  const record = { schema: 'kumwe-release-attestation/v2', artifact_kind: 'framework_php',
    migration_id: input.handoff.migration_id, change_set: input.handoff.change_set,
    repository: `https://github.com/${input.name}`, merge_commit: input.source_commit, version: input.version,
    tag: input.tag, source_archive: { url: input.archive_url, sha256: input.archive_sha256 },
    artifacts: [{ identity: `${input.name}:${input.version}`, url: input.archive_url, sha256: input.archive_sha256 }],
    manifests_and_corpora: evidence.manifests_and_corpora, abi_and_capabilities: null,
    sbom: { url: artifact('source.spdx.json'), sha256: sha256(fs.readFileSync(path.join(output, 'source.spdx.json'))) },
    provenance: `Independent verification, not publisher build provenance: ${artifact('verification-provenance.json')}; sha256=${sha256(fs.readFileSync(path.join(output, 'verification-provenance.json')))}`,
    release_workflow: `${evidence.release_workflow}; success at source ${input.source_commit}`,
    registry_or_pie_verification: [`https://repo.packagist.org/p2/${input.name}.json; source/dist=${input.source_commit}; evidence=${artifact('github-evidence.json')}`],
    clean_consumer_or_build_verification: [`${artifact('consumer-verification.json')}; sha256=${sha256(fs.readFileSync(path.join(output, 'consumer-verification.json')))}; no-dev=true; classmap-authoritative=true; fresh-offline-reinstall=true; runtime-types=${evidence.consumer.runtime_types_loaded}`,
      `${artifact('canonical-schema-verification.json')}; all three canonical manifests and complete handoff schema passed`,
      `${artifact('archive-inventory-verification.json')}; complete git export path/byte inventory matched; canonical API exports=${evidence.consumer.canonical_api_exports_verified}`],
    verified_at: evidence.observed_at, verified_by: `${evidence.verifier.repository}@${evidence.verifier.commit}; ${evidence.verifier.run_url}`, status: 'verified' };
  fs.writeFileSync(path.join(output, 'RELEASE-ATTESTATION.yaml'), serializeAttestation(record));
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const [mode, input, output, nativeFixture] = process.argv.slice(2);
  try {
    if (mode === 'verify') await verifyRelease(json(input), path.resolve(output), nativeFixture || null);
    else if (mode === 'finalize') finalize(path.resolve(input), output);
    else throw new Error('Usage: node verify-release.mjs verify PACKAGE_JSON OUTPUT [QUALIFIED_NATIVE_FIXTURE_JSON] | finalize OUTPUT UPLOADED_EVIDENCE_URL');
  } catch (error) {
    const folder = mode === 'verify' ? output : input;
    if (folder) { fs.mkdirSync(folder, { recursive: true }); write(path.join(folder, 'FAILED-VERIFICATION.json'),
      { status: 'failed', reason: error.message, observed_at: new Date().toISOString() }); }
    console.error(error.stack); process.exitCode = 1;
  }
}
