// A deterministic Engine embedding inherits authority from two reviewed sources.
// This is deliberately narrower than accepting an arbitrary successful direct push.
const fact = (value, message) => { if (!value) throw new Error(message); };
const sha = value => typeof value === 'string' && /^[a-f0-9]{40}$/.test(value);
const escape = value => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

export function reviewedMerge(pulls, commit, repository, branch) {
  fact(Array.isArray(pulls) && pulls.length < 100, 'Incomplete reviewed source inventory.');
  return pulls.find(pr => pr.merged_at && pr.merge_commit_sha === commit
    && pr.base?.repo?.full_name === repository && pr.base.ref === branch) ?? null;
}

export function bindingSyncSources(commit, input, lock, parentPulls, enginePulls, branch, engineBranch) {
  fact(input.name === 'kumwe/kumwe-engine' && input.kind === 'php_extension'
    && commit.sha === input.source_commit && commit.parents?.length === 1
    && sha(commit.parents[0].sha), 'Only a single-parent binding sync can inherit source authority.');
  fact(lock.schema === 'kumwe-embedded-engine/v2' && lock.repository === 'https://github.com/kumwe/engine'
    && lock.version === input.version && lock.release === `v${input.version}` && sha(lock.commit)
    && /^[a-f0-9]{64}$/.test(lock.archive_sha256 || ''), 'Binding sync Engine coordinate differs.');
  const parent = commit.parents[0].sha;
  const bindingReview = reviewedMerge(parentPulls, parent, input.name, branch);
  const engineReview = reviewedMerge(enginePulls, lock.commit, 'kumwe/engine', engineBranch);
  fact(bindingReview && engineReview, 'Binding sync requires both its reviewed parent and reviewed Engine source.');
  return { parent, binding_review: bindingReview, engine_review: engineReview };
}

export function bindingSyncPaths(paths) {
  const records = ['docs/release-record.md', 'MIGRATION-HANDOFF.md'];
  const recordPaths = Array.isArray(paths) ? records.filter(p => paths.includes(p)) : [];
  const generated = [...recordPaths, 'php_kumwe_engine.h', 'php_kumwe_engine_build.h',
    'resources/compatibility/v1.json', 'resources/engine-lock.json'];
  fact(Array.isArray(paths) && recordPaths.length === 1 && new Set(paths).size === paths.length && generated.every(p => paths.includes(p))
    && paths.every(p => typeof p === 'string' && !/[\\\x00-\x1f]/.test(p)
      && !p.split('/').some(segment => ['', '.', '..'].includes(segment))
      && (generated.includes(p) || p.startsWith('vendor/engine/'))),
  'Binding sync changed a path outside the generated embedding.');
}

export function bindingSyncRun(run, jobs, input, parent, branch) {
  fact(run.head_sha === parent && run.head_branch === branch
    && ['workflow_dispatch', 'repository_dispatch', 'schedule'].includes(run.event)
    && run.path === '.github/workflows/engine-sync.yml' && run.status === 'completed' && run.conclusion === 'success'
    && run.repository?.full_name === input.name && run.head_repository?.full_name === input.name,
  'Binding sync did not run the exact reviewed default-branch workflow.');
  fact(jobs.length === 1 && jobs[0].name === 'sync' && jobs[0].run_id === run.id
    && jobs[0].status === 'completed' && jobs[0].conclusion === 'success',
  'Binding sync has missing or unsuccessful jobs.');
}

export function bindingSyncLog(log, input, parent, branch, lock) {
  fact(typeof log === 'string', 'Missing binding sync log.');
  const lines = log.replace(/\x1b\[[0-9;]*m/g, '').split(/\r?\n/)
    .map(line => line.replace(/^(?:[^\t]*\t[^\t]*\t)?\d{4}-\d\d-\d\dT[\d:.]+Z /, ''));
  const checkout = lines.findIndex(line => new RegExp(`^\\[command\\].*?/git checkout --progress --force -B ${escape(branch)} refs/remotes/origin/${escape(branch)}$`).test(line));
  const selected = lines.findIndex((line, i) => i > checkout
    && /^\[command\].*?\/git log -1 --format=%H$/.test(line) && lines[i + 1] === parent);
  const embedded = lines.findIndex((line, i) => i > selected && new RegExp(`^Embedded Engine v${escape(input.version)} \\(${escape(input.version)}, ${lock.commit}, [1-9][0-9]* files, archive sha256 ${lock.archive_sha256}\\); extension version is now ${escape(input.version)}\\.$`).test(line));
  const pushed = lines.findIndex((line, i) => i > embedded && line === `Pushed ${input.source_commit} to ${branch}`);
  fact(checkout >= 0 && selected > checkout && embedded > selected && pushed > embedded,
    'Binding sync log does not prove reviewed checkout, exact Engine archive, and released commit push.');
}

export function bindingSyncTree(reproduced, released) {
  fact(sha(reproduced) && sha(released) && reproduced === released,
    'Reviewed binding sync did not reproduce the entire released Git tree.');
}
