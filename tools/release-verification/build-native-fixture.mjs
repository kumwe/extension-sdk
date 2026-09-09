import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

// A build fixture over an already independently verified stable source bundle. This helper does
// not download releases, verify publisher signatures, issue attestations, or mutate GITHUB_ENV.
const requireFact = (condition, message) => { if (!condition) throw new Error(message); };
const digest = bytes => crypto.createHash('sha256').update(bytes).digest('hex');
const json = file => JSON.parse(fs.readFileSync(file, 'utf8'));
const stable = value => typeof value === 'string' && /^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/.test(value)
  && value !== '0.0.0';

function canonicalFile(file, expected) {
  requireFact(typeof file === 'string' && path.isAbsolute(file) && fs.realpathSync(file) === file
    && !fs.lstatSync(file).isSymbolicLink() && fs.statSync(file).isFile(), 'A native fixture input must be a canonical regular file.');
  requireFact(typeof expected === 'string' && /^[a-f0-9]{64}$/.test(expected)
    && digest(fs.readFileSync(file)) === expected, 'Native fixture input digest differs.');
  return file;
}

function sourceInventory(directory) {
  const files = {};
  const visit = relative => {
    for (const name of fs.readdirSync(path.join(directory, relative)).sort()) {
      const child = relative ? `${relative}/${name}` : name;
      const absolute = path.join(directory, child);
      const stat = fs.lstatSync(absolute);
      requireFact(!stat.isSymbolicLink(), `Native fixture source contains a symlink: ${child}`);
      if (stat.isDirectory()) visit(child);
      else {
        requireFact(stat.isFile(), `Native fixture source contains a special file: ${child}`);
        files[child] = digest(fs.readFileSync(absolute));
      }
    }
  };
  visit('');
  return files;
}

export function tupleValue(value) {
  if (Array.isArray(value)) return value.map(tupleValue);
  if (value !== null && typeof value === 'object') {
    return Object.fromEntries(Object.keys(value).sort().map(key => [key, tupleValue(value[key])]));
  }
  return value;
}
const equal = (left, right) => JSON.stringify(tupleValue(left)) === JSON.stringify(tupleValue(right));

export function validateNativeFixture(input) {
  requireFact(input?.schema === 'kumwe-verified-native-fixture-input/v1'
    && input.package === 'kumwe/kumwe-engine' && stable(input.version)
    && typeof input.source_commit === 'string' && /^[a-f0-9]{40}$/.test(input.source_commit),
  'Native fixture requires a canonical binding package, exact stable version and source commit.');
  const root = input.source_directory;
  requireFact(typeof root === 'string' && path.isAbsolute(root) && fs.realpathSync(root) === root
    && fs.statSync(root).isDirectory() && !fs.lstatSync(root).isSymbolicLink(), 'Native fixture source directory is not canonical.');
  for (const key of ['source_record', 'source_archive', 'source_sbom']) {
    canonicalFile(input[`${key}_path`], input[`${key}_sha256`]);
  }
  const record = json(input.source_record_path);
  requireFact(record.schema === 'kumwe-native-source-bundle/v1' && record.package === input.package
    && record.source?.repository === 'https://github.com/kumwe/kumwe-engine'
    && record.source?.commit === input.source_commit && record.identity?.version === input.version
    && record.archive?.sha256 === input.source_archive_sha256 && record.sbom?.sha256 === input.source_sbom_sha256
    && Array.isArray(record.stable_source_blockers) && record.stable_source_blockers.length === 0,
  'Native fixture source record differs from the selected verified stable bundle.');
  const sbom = json(input.source_sbom_path);
  const inventory = {};
  requireFact(sbom.spdxVersion === 'SPDX-2.3' && Array.isArray(sbom.files) && sbom.files.length > 0,
    'Native fixture needs its complete published source SPDX inventory.');
  for (const entry of sbom.files) {
    requireFact(typeof entry.fileName === 'string' && entry.fileName.startsWith('./'), 'Invalid SPDX source path.');
    const name = entry.fileName.slice(2);
    requireFact(name && !name.includes('\\') && !name.startsWith('/')
      && !name.split('/').some(part => ['', '.', '..'].includes(part)) && !/[\x00-\x1f]/.test(name)
      && !Object.hasOwn(inventory, name), 'Unsafe or duplicate SPDX source path.');
    const sums = entry.checksums?.filter(sum => sum.algorithm === 'SHA256') ?? [];
    requireFact(sums.length === 1 && /^[a-f0-9]{64}$/.test(sums[0].checksumValue), 'Invalid SPDX source checksum.');
    inventory[name] = sums[0].checksumValue;
  }
  requireFact(equal(sourceInventory(root), inventory), 'Native fixture extracted source differs from the complete published inventory.');
  const compatibility = json(path.join(root, 'resources/compatibility/v1.json'));
  const lock = json(path.join(root, 'resources/engine-lock.json'));
  const composer = json(path.join(root, 'composer.json'));
  const header = fs.readFileSync(path.join(root, 'php_kumwe_engine.h'), 'utf8');
  requireFact(header.match(/^#define PHP_KUMWE_ENGINE_VERSION "([^"]+)"$/m)?.[1] === input.version
    && composer.name === input.package && composer.type === 'php-ext'
    && compatibility.version === input.version && compatibility.state !== 'candidate'
    && compatibility.publication_allowed === true && lock.state !== 'candidate'
    && lock.release_verified === true && lock.release && lock.external_attestation,
  'Native fixture source is not the exact stable, verified-Engine binding selection.');
  return { input, record, inventory };
}

function command(executable, args, cwd, environment = process.env, capture = false) {
  const result = spawnSync(executable, args, { cwd, env: environment,
    stdio: capture ? ['ignore', 'pipe', 'inherit'] : 'inherit', encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
  requireFact(!result.error && result.status === 0, `Native fixture command failed: ${executable} ${args.join(' ')}`);
  return capture ? result.stdout : '';
}

export function expectedNativeFixtureTuple(build, expected, packageName, version) {
  const sourceCaps = json(path.join(build, 'engine-build/generated/capabilities.json'));
  const api = json(path.join(build, 'resources/api/v1.json'));
  const bindingSource = fs.readFileSync(path.join(build, 'src/kumwe_engine.c'), 'utf8');
  const maximumPlans = bindingSource.match(/^#define BINDING_MAX_PLANS (\d+)$/m)?.[1];
  const maximumBytes = bindingSource.match(/^#define BINDING_MAX_BYTES \(\(size_t\)(\d+)\)$/m)?.[1];
  requireFact(maximumPlans && maximumBytes && equal(sourceCaps.computation, expected.capabilities),
    'Source/build expected tuple cannot be derived without ambiguity.');
  // This complete expectation is assembled from verified source and the recorded build before
  // loading the module. The observed native handshake is never its own expected value.
  return { ...sourceCaps, binding_build: expected.binding_build,
    binding_build_digest: expected.binding_build_digest, extension_package: packageName,
    extension_module: 'kumwe_engine', extension_version: version,
    embedded_engine_commit: expected.embedded_engine_commit, embedded_source_sha256: expected.embedded_source_sha256,
    binding_features: api.binding_features, binding_max_plans: Number(maximumPlans), binding_max_bytes: Number(maximumBytes) };
}

export const nativeFixtureRuntimeProbe = 'if(!extension_loaded("kumwe_engine")||!(new ReflectionClass("Kumwe\\\\Engine\\\\Runtime"))->isInternal()){throw new RuntimeException("Actual module required.");}echo json_encode((new Kumwe\\Engine\\Runtime())->capabilities(),JSON_THROW_ON_ERROR);';

export function buildNativeFixture(input, destination) {
  const verified = validateNativeFixture(input);
  requireFact(typeof destination === 'string' && path.isAbsolute(destination)
    && path.resolve(destination) === destination && !fs.existsSync(destination)
    && fs.realpathSync(path.dirname(destination)) === path.dirname(destination)
    && !destination.startsWith(input.source_directory + '/'), 'Native fixture output must be a fresh canonical directory outside source.');
  fs.mkdirSync(destination, { mode: 0o700 });
  const build = path.join(destination, 'source');
  fs.cpSync(input.source_directory, build, { recursive: true, errorOnExist: true, force: false });
  requireFact(equal(sourceInventory(build), verified.inventory), 'Native fixture private source copy differs.');
  command('php', ['-r', 'if(extension_loaded("kumwe_engine")){fwrite(STDERR,"Base PHP must remain native-free.\\n");exit(1);}'], build);
  const phpBinary = command('php', ['-r', 'echo PHP_BINARY;'], build, process.env, true).trim();
  const baseIni = command('php', ['-r', 'echo php_ini_loaded_file() ?: "";'], build, process.env, true).trim();
  for (const script of ['generate-arginfo.php', 'verify-binding.php', 'verify-engine.php']) {
    command('php', [`tools/${script}`, ...(script === 'generate-arginfo.php' ? ['--check'] : [])], build);
  }
  command('phpize', [], build);
  command('./configure', ['--enable-kumwe_engine'], build);
  command('make', ['-j2'], build);
  const expectedPath = path.join(destination, 'expected-compatibility.json');
  const expectedBytes = command('php', ['tools/expected-tuple.php'], build, process.env, true);
  const expected = JSON.parse(expectedBytes);
  requireFact(expected.extension_version === input.version, 'Built expected tuple has the wrong stable extension version.');
  fs.writeFileSync(expectedPath, expectedBytes, { flag: 'wx', mode: 0o600 });
  const module = path.join(build, 'modules/kumwe_engine.so');
  requireFact(fs.statSync(module).isFile(), 'Native fixture module was not built.');
  const ini = path.join(destination, 'php.ini');
  fs.writeFileSync(ini, (baseIni ? fs.readFileSync(baseIni, 'utf8') : '')
    + `\nextension=${JSON.stringify(module)}\n`, { flag: 'wx', mode: 0o600 });
  const environment = { PHPRC: ini, KUMWE_NATIVE_EXPECTED_TUPLE: expectedPath };
  const scoped = { ...process.env, ...environment };
  const fullExpected = expectedNativeFixtureTuple(build, expected, input.package, input.version);
  const fullExpectedPath = path.join(destination, 'expected-runtime-tuple.json');
  fs.writeFileSync(fullExpectedPath, JSON.stringify(fullExpected, null, 2) + '\n', { flag: 'wx' });
  const observed = JSON.parse(command(phpBinary, ['-r', nativeFixtureRuntimeProbe], build, scoped, true));
  requireFact(equal(observed, fullExpected), 'Native fixture actual module tuple differs from its source/build expectation.');
  requireFact(equal(sourceInventory(input.source_directory), verified.inventory), 'Verified original source changed during fixture build.');
  for (const key of ['source_record', 'source_archive', 'source_sbom']) canonicalFile(input[`${key}_path`], input[`${key}_sha256`]);
  const result = { schema: 'kumwe-native-build-fixture/v1', status: 'passed', release_attestation: false,
    source_verification: 'independent upstream verifier; this helper checks source bytes and builds the test fixture',
    package: input.package, version: input.version, source_commit: input.source_commit,
    source_archive_sha256: input.source_archive_sha256, module, module_sha256: digest(fs.readFileSync(module)),
    php_binary: phpBinary, environment, expected_runtime_tuple: fullExpectedPath,
    expected_runtime_tuple_sha256: digest(fs.readFileSync(fullExpectedPath)) };
  fs.writeFileSync(path.join(destination, 'environment.json'), JSON.stringify(environment, null, 2) + '\n', { flag: 'wx' });
  fs.writeFileSync(path.join(destination, 'fixture.json'), JSON.stringify(result, null, 2) + '\n', { flag: 'wx' });
  return result;
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  requireFact(process.argv.length === 4, 'Usage: build-native-fixture.mjs VERIFIED_NATIVE_INPUT_JSON OUTPUT_DIRECTORY');
  const result = buildNativeFixture(json(process.argv[2]), process.argv[3]);
  console.log(JSON.stringify(result, null, 2));
}
