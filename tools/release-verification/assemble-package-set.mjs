import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { coordinate } from './verify-release.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const [selectionPath, source, destination, fixturePath] = process.argv.slice(2);
const selected = JSON.parse(fs.readFileSync(selectionPath, 'utf8')).map(coordinate);
if (!selected.length || new Set(selected.map(p => p.name)).size !== selected.length) {
  throw new Error('Independent package selection is empty or duplicated.');
}
const out = path.resolve(destination);
if (fs.existsSync(out)) throw new Error('Package-set output must be fresh.');
fs.mkdirSync(out, { recursive: true });
const packages = [];
for (const release of selected) {
  const slug = release.name.slice('kumwe/'.length);
  const envelope = path.resolve(source, `release-evidence-${slug}-${release.version}`);
  const record = JSON.parse(fs.readFileSync(path.join(envelope, 'verification.json'), 'utf8'));
  if (record.status !== 'passed' || record.input.name !== release.name
    || record.input.version !== release.version || record.input.source_commit !== release.source_commit
    || record.complete_git_export_matched !== true || record.canonical_schema_validation?.length !== 3
    || record.consumer?.status !== 'passed' || record.consumer.installed_dist_identity_verified !== true
    || record.consumer.consumer_dependency_audit !== 'passed' || !Number.isInteger(record.consumer.canonical_api_exports_verified)) {
    throw new Error(`Independent evidence does not qualify ${release.name}.`);
  }
  const archivePath = path.join(envelope, 'source.zip');
  const digest = crypto.createHash('sha256').update(fs.readFileSync(archivePath)).digest('hex');
  if (digest !== record.input.archive_sha256) throw new Error(`Original archive digest drift: ${release.name}`);
  const extracted = path.join(out, slug);
  const result = spawnSync('php', [path.join(here, 'extract-archive.php'), archivePath, extracted], { stdio: 'inherit' });
  if (result.status !== 0) throw new Error(`Original archive extraction failed: ${release.name}`);
  const roots = fs.readdirSync(extracted);
  if (roots.length !== 1 || !fs.statSync(path.join(extracted, roots[0])).isDirectory()) throw new Error('Invalid archive root.');
  packages.push({ name: release.name, version: release.version, source_commit: release.source_commit,
    archive_path: archivePath, archive_sha256: digest, package_root: path.join(extracted, roots[0]),
    composer: record.input.composer });
}
const fixture = fixturePath ? JSON.parse(fs.readFileSync(fixturePath, 'utf8')) : null;
if (fixture && (fixture.schema !== 'kumwe-qualified-native-fixture/v1' || fixture.status !== 'passed')) {
  throw new Error('Native graph assembly requires its qualified actual-build fixture.');
}
fs.writeFileSync(path.join(out, 'verified-package-set.json'), JSON.stringify({
  schema: 'kumwe-verified-php-package-set/v1', graph_mode: fixture ? 'native' : 'portable', packages,
  ...(fixture ? { native: fixture.native } : {}),
}, null, 2) + '\n');
console.log(`Prepared ${packages.length} independently verified original source ZIPs.`);
