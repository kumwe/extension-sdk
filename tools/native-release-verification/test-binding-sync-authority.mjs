import assert from 'node:assert/strict';
import { reviewedMerge, bindingSyncSources, bindingSyncPaths, bindingSyncRun,
  bindingSyncLog, bindingSyncTree } from './binding-sync-authority.mjs';

// Synthetic identities exercise the policy only; none is a release selection or attestation.
const source = '1'.repeat(40), parent = '2'.repeat(40), engine = '3'.repeat(40), digest = '4'.repeat(64);
const input = { name: 'kumwe/kumwe-engine', kind: 'php_extension', source_commit: source, version: '1.2.3' };
const commit = { sha: source, parents: [{ sha: parent }] };
const review = (sha, name) => ({ merged_at: '2026-01-01T00:00:00Z', merge_commit_sha: sha,
  base: { ref: 'main', repo: { full_name: name } } });
const parentReview = review(parent, input.name), engineReview = review(engine, 'kumwe/engine');
const lock = { schema: 'kumwe-embedded-engine/v2', repository: 'https://github.com/kumwe/engine',
  version: input.version, release: `v${input.version}`, commit: engine, archive_sha256: digest };
let checks = 0;
const pass = fn => { fn(); checks++; };
const refuse = fn => { assert.throws(fn); checks++; };
const sources = (c = commit, i = input, l = lock, p = [parentReview], e = [engineReview]) =>
  bindingSyncSources(c, i, l, p, e, 'main', 'main');
pass(() => assert.equal(sources().parent, parent));
refuse(() => sources({ ...commit, sha: parent }));
refuse(() => sources({ ...commit, parents: [] }));
refuse(() => sources({ ...commit, parents: [{ sha: parent }, { sha: engine }] }));
refuse(() => sources({ ...commit, parents: [{ sha: '../untrusted' }] }));
refuse(() => sources(commit, { ...input, name: 'kumwe/engine', kind: 'native_cpp' }));
for (const mutation of [{ repository: 'https://github.com/outsider/engine' }, { version: '1.2.4' },
  { release: 'v1.2.4' }, { commit: 'invalid' }, { archive_sha256: 'invalid' }, { schema: 'old/v1' }]) {
  refuse(() => sources(commit, input, { ...lock, ...mutation }));
}
refuse(() => sources(commit, input, lock, []));
refuse(() => sources(commit, input, lock, [parentReview], []));
for (const pr of [{ ...parentReview, merged_at: null }, { ...parentReview, merge_commit_sha: source },
  { ...parentReview, base: { ...parentReview.base, ref: 'feature' } },
  { ...parentReview, base: { ref: 'main', repo: { full_name: 'outsider/binding' } } }]) {
  refuse(() => sources(commit, input, lock, [pr]));
}
refuse(() => reviewedMerge(Array(100).fill(parentReview), parent, input.name, 'main'));
const paths = ['MIGRATION-HANDOFF.md', 'php_kumwe_engine.h', 'php_kumwe_engine_build.h',
  'resources/compatibility/v1.json', 'resources/engine-lock.json', 'vendor/engine/src/kernel.cpp'];
pass(() => bindingSyncPaths(paths));
for (const illegal of ['kumwe_engine.cpp', 'tools/sync-engine.php', '.github/workflows/engine-sync.yml',
  'vendor/engine/../../injected.php', 'vendor/engine//bad', 'vendor/engine/./bad', 'vendor/engine\\bad']) {
  refuse(() => bindingSyncPaths([...paths, illegal]));
}
refuse(() => bindingSyncPaths(paths.slice(1)));
refuse(() => bindingSyncPaths([...paths, paths[0]]));
const run = { id: 10, head_sha: parent, head_branch: 'main', event: 'workflow_dispatch',
  path: '.github/workflows/engine-sync.yml', status: 'completed', conclusion: 'success',
  repository: { full_name: input.name }, head_repository: { full_name: input.name } };
const jobs = [{ run_id: 10, name: 'sync', status: 'completed', conclusion: 'success' }];
for (const event of ['workflow_dispatch', 'repository_dispatch', 'schedule']) pass(() =>
  bindingSyncRun({ ...run, event }, jobs, input, parent, 'main'));
for (const mutation of [{ head_sha: source }, { head_branch: 'feature' }, { event: 'pull_request' },
  { path: '.github/workflows/ci.yml' }, { status: 'in_progress' }, { conclusion: 'failure' },
  { repository: { full_name: 'outsider/binding' } }, { head_repository: { full_name: 'outsider/binding' } }]) {
  refuse(() => bindingSyncRun({ ...run, ...mutation }, jobs, input, parent, 'main'));
}
for (const inventory of [[], [...jobs, jobs[0]], [{ ...jobs[0], run_id: 11 }],
  [{ ...jobs[0], name: 'unreviewed' }], [{ ...jobs[0], conclusion: 'skipped' }]]) {
  refuse(() => bindingSyncRun(run, inventory, input, parent, 'main'));
}
const lines = ['[command]/usr/bin/git checkout --progress --force -B main refs/remotes/origin/main',
  '[command]/usr/bin/git log -1 --format=%H', parent,
  `Embedded Engine v1.2.3 (1.2.3, ${engine}, 190 files, archive sha256 ${digest}); extension version is now 1.2.3.`,
  `Pushed ${source} to main`];
const log = lines.map(line => `2026-01-01T00:00:00.123Z ${line}`).join('\n');
pass(() => bindingSyncLog(log, input, parent, 'main', lock));
pass(() => bindingSyncLog(log.split('\n').map(line => `sync\tStep\t${line}`).join('\n'), input, parent, 'main', lock));
for (const changed of [log.replace(parent, source), log.replace(engine, source), log.replace(digest, '5'.repeat(64)),
  log.replace(`Pushed ${source}`, `Pushed ${parent}`), log.replace('Pushed ', 'echo "Pushed '),
  log.replace('git log -1 --format=%H', 'echo source'), log.replace('Engine v1.2.3', 'Engine v1.2.4'),
  [...lines].reverse().join('\n'), log.replace('refs/remotes/origin/main', 'refs/remotes/origin/feature')]) {
  refuse(() => bindingSyncLog(changed, input, parent, 'main', lock));
}
pass(() => bindingSyncTree('a'.repeat(40), 'a'.repeat(40)));
refuse(() => bindingSyncTree('a'.repeat(40), 'b'.repeat(40)));
refuse(() => bindingSyncTree('invalid', 'invalid'));
console.log(`${checks} deterministic binding sync authority checks passed.`);
