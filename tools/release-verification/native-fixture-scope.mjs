import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { spawnSync } from 'node:child_process';
import { nativeSelection } from './materialize-native-evidence.mjs';
import { nativeFixtureRuntimeProbe } from './build-native-fixture.mjs';

const need = (fact, message) => { if (!fact) throw new Error(message); };
const hash = bytes => crypto.createHash('sha256').update(bytes).digest('hex');
const json = file => JSON.parse(fs.readFileSync(file, 'utf8'));
const quote = value => `'${value.replaceAll("'", "'\\''")}'`;
const ordered = value => Array.isArray(value) ? value.map(ordered) : value && typeof value === 'object'
  ? Object.fromEntries(Object.keys(value).sort().map(key => [key, ordered(value[key])])) : value;

export function nativeSelectionBinding(result) {
  const selection = nativeSelection(result.selection);
  need(result.native?.extension_version === selection.extension.version, 'Native runtime version differs from its qualified selection.');
  for (const kind of ['engine', 'extension']) {
    const selected = selection[kind]; const native = result.native[kind];
    need(native?.package === selected.name && native.version === selected.version && native.tag === `v${selected.version}`
      && native.commit === selected.source_commit && native.archive_sha256 === selected.archive_sha256,
    'Native owner coordinates differ from the independently qualified selection.');
    need(native.attestation?.uri === selected.attestation.zip_uri
      && native.attestation.sha256 === selected.attestation.zip_sha256
      && native.attestation.member === selected.attestation.member
      && native.attestation.member_sha256 === selected.attestation.member_sha256,
    'Native owner attestation differs from the independently qualified selection.');
  }
  return selection;
}

export function packageSetNativeBinding(input, qualified) {
  need(input?.graph_mode === 'native' && JSON.stringify(ordered(input.native)) === JSON.stringify(ordered(qualified.native)),
    'Native package-set inputs differ from the independently qualified fixture selection.');
}

/** Apply a verified fixture to individual subprocesses; never mutate the base PHP environment. */
export function nativeFixtureScope(file, directory) {
  const result = json(file);
  need(result.schema === 'kumwe-qualified-native-fixture/v1' && result.status === 'passed', 'A qualified stable native fixture is required.');
  const selection = nativeSelectionBinding(result);
  const fixture = result.fixture;
  need(fixture?.schema === 'kumwe-native-build-fixture/v1' && fixture.status === 'passed'
    && fixture.package === selection.extension.name && fixture.version === selection.extension.version
    && fixture.source_commit === selection.extension.source_commit
    && fixture.source_archive_sha256 === selection.extension.archive_sha256 && fixture.release_attestation === false,
  'Native fixture source identity differs from its upstream qualification.');
  for (const [filePath, expected] of [[fixture.module, fixture.module_sha256],
    [fixture.expected_runtime_tuple, fixture.expected_runtime_tuple_sha256]]) {
    need(typeof filePath === 'string' && path.isAbsolute(filePath) && fs.realpathSync(filePath) === filePath
      && !fs.lstatSync(filePath).isSymbolicLink() && /^[a-f0-9]{64}$/.test(expected || '')
      && hash(fs.readFileSync(filePath)) === expected, 'Native fixture build bytes changed.');
  }
  need(typeof fixture.php_binary === 'string' && path.isAbsolute(fixture.php_binary)
    && fs.statSync(fixture.php_binary).isFile() && Object.keys(fixture.environment).sort().join(',') === 'KUMWE_NATIVE_EXPECTED_TUPLE,PHPRC',
  'Native fixture execution environment differs.');
  const generated = path.dirname(fixture.expected_runtime_tuple);
  for (const [variable, fileName, expected] of [['PHPRC', 'php.ini', fixture.php_ini_sha256],
    ['KUMWE_NATIVE_EXPECTED_TUPLE', 'expected-compatibility.json', fixture.expected_compatibility_sha256]]) {
    const value = fixture.environment[variable];
    need(value === path.join(generated, fileName) && fs.realpathSync(value) === value
      && !fs.lstatSync(value).isSymbolicLink() && fs.statSync(value).isFile()
      && /^[a-f0-9]{64}$/.test(expected || '') && hash(fs.readFileSync(value)) === expected,
    'Native fixture INI or expected compatibility bytes changed.');
  }
  need(!fs.existsSync(directory), 'Native PHP scope must be a fresh private directory.');
  fs.mkdirSync(directory, { mode: 0o700 });
  fs.writeFileSync(path.join(directory, 'php'), `#!/bin/sh\nexec ${quote(fixture.php_binary)} "$@"\n`, { mode: 0o700, flag: 'wx' });
  const environment = { ...fixture.environment, PATH: `${directory}${path.delimiter}${process.env.PATH}` };
  const probe = spawnSync(fixture.php_binary, ['-r', nativeFixtureRuntimeProbe],
    { env: { ...process.env, ...environment }, encoding: 'utf8' });
  need(probe.status === 0 && JSON.stringify(ordered(JSON.parse(probe.stdout))) === JSON.stringify(ordered(json(fixture.expected_runtime_tuple)))
    && JSON.stringify(ordered(result.native.compatibility_tuple)) === JSON.stringify(ordered(json(fixture.expected_runtime_tuple))),
  'Scoped actual native module differs from its source/build-derived expected tuple.');
  return { environment, result };
}
