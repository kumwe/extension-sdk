<?php

/** Independently install the complete portable extraction graph from verified ZIPs. @since 0.3.0 */

declare(strict_types=1);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    verifyPackageSetMain($argv);
}

/** @return list<string> Required PHP extraction owners in the Version 2 repository catalog. @since 0.3.0 */
function packageSetTargets(): array
{
    return array_map(static fn (string $name): string => 'kumwe/' . $name, [
        'access-context', 'access-control', 'administrator-contract', 'approval', 'audit', 'automation',
        'business-definition', 'business-policy', 'business-schema', 'business-surface-contract',
        'canonical-json', 'computation', 'content-model', 'contribution', 'conversion-extension',
        'idempotency', 'integration', 'interface-standard', 'localization', 'navigation', 'portal-contract',
        'record-model', 'record-query', 'record-values', 'reporting', 'secret-envelope', 'sequence', 'transaction',
    ]);
}

/**
 * Validate the external verifier's complete archive set before starting Composer.
 *
 * @param mixed $input Decoded external verification input.
 * @return array<string, array<string, mixed>> Packages indexed by their canonical Composer name.
 * @since 0.3.0
 */
function packageSetInput(mixed $input): array
{
    if (!is_array($input) || ($input['schema'] ?? null) !== 'kumwe-verified-php-package-set/v1'
        || !is_array($input['packages'] ?? null) || !array_is_list($input['packages'])) {
        throw new RuntimeException('Expected a verified PHP package set with the supported schema.');
    }
    $packages = [];
    foreach ($input['packages'] as $entry) {
        if (!is_array($entry) || !is_string($entry['name'] ?? null)
            || preg_match('~^kumwe/[a-z0-9]+(?:-[a-z0-9]+)*$~D', $entry['name']) !== 1
            || isset($packages[$entry['name']])
            || !is_string($entry['version'] ?? null)
            || preg_match('/^(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)$/D', $entry['version']) !== 1
            || !is_string($entry['source_commit'] ?? null)
            || preg_match('/^[0-9a-f]{40}$/D', $entry['source_commit']) !== 1
            || !is_string($entry['archive_sha256'] ?? null)
            || preg_match('/^[0-9a-f]{64}$/D', $entry['archive_sha256']) !== 1
            || !is_array($entry['composer'] ?? null)
            || !is_array($entry['composer']['require'] ?? null)
            || ($entry['composer']['name'] ?? null) !== $entry['name']) {
            throw new RuntimeException('A package needs one canonical name, exact stable version and verified source/archive identity.');
        }
        foreach (['archive_path', 'package_root'] as $field) {
            if (!is_string($entry[$field] ?? null) || !str_starts_with($entry[$field], '/')
                || realpath($entry[$field]) !== $entry[$field] || is_link($entry[$field])) {
                throw new RuntimeException('Verified archives and extracted roots must use canonical local paths.');
            }
        }
        if (!is_file($entry['archive_path']) || !is_dir($entry['package_root'])
            || hash_file('sha256', $entry['archive_path']) !== $entry['archive_sha256']) {
            throw new RuntimeException('Verified archive contents have changed: ' . $entry['name']);
        }
        $metadataPath = $entry['package_root'] . '/composer.json';
        if (!is_file($metadataPath) || json_decode((string) file_get_contents($metadataPath), true,
            64, JSON_THROW_ON_ERROR) !== $entry['composer']) {
            throw new RuntimeException('Verified extracted Composer metadata differs: ' . $entry['name']);
        }
        foreach ($entry['composer']['require'] ?? [] as $name => $constraint) {
            if ($name === 'ext-kumwe_engine') {
                throw new RuntimeException('The portable extraction graph must not require the native extension.');
            }
            if (str_starts_with($name, 'kumwe/')
                && (!is_string($constraint)
                    || preg_match('/^(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)$/D', $constraint) !== 1)) {
                throw new RuntimeException('Kumwe runtime dependencies must have exact stable coordinates: ' . $name);
            }
        }
        $packages[$entry['name']] = $entry;
    }
    $missing = array_diff(packageSetTargets(), array_keys($packages));
    if ($missing !== []) {
        throw new RuntimeException('The portable extraction set is incomplete: ' . implode(', ', $missing));
    }
    foreach ($packages as $entry) {
        foreach ($entry['composer']['require'] ?? [] as $name => $constraint) {
            if (str_starts_with($name, 'kumwe/')
                && (!isset($packages[$name]) || $packages[$name]['version'] !== $constraint)) {
                throw new RuntimeException('The selected graph does not satisfy ' . $entry['name'] . ' -> ' . $name . ' ' . $constraint);
            }
        }
    }
    ksort($packages);
    return $packages;
}

/**
 * @param list<string> $arguments Exact executable arguments.
 * @param array<string, string> $environment Explicit overrides preserving the caller's environment.
 * @return void
 * @since 0.3.0
 */
function packageSetCommand(array $arguments, array $environment = []): void
{
    $process = proc_open($arguments, [STDIN, STDOUT, STDERR], $pipes, null,
        $environment === [] ? null : array_replace(getenv(), $environment));
    if (!is_resource($process) || proc_close($process) !== 0) {
        throw new RuntimeException('Package set consumer command failed: ' . $arguments[0]);
    }
}

/** @param string $root Private consumer directory to remove, without following links. @return void @since 0.3.0 */
function packageSetRemoveDirectory(string $root): void
{
    if (!str_starts_with($root, sys_get_temp_dir() . '/kumwe-package-set-') || is_link($root)) {
        throw new RuntimeException('Refusing to remove a directory outside the private consumer.');
    }
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,
        FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($root);
}

/** @param list<string> $arguments Command line arguments. @return void @since 0.3.0 */
function verifyPackageSetMain(array $arguments): void
{
    if (count($arguments) !== 3 || !is_file($arguments[1])) {
        throw new RuntimeException('Usage: verify-package-set-consumer.php VERIFIED_PACKAGE_SET_JSON OUTPUT_JSON');
    }
    if (file_exists($arguments[2]) || is_link($arguments[2])) {
        throw new RuntimeException('Consumer evidence output must not already exist.');
    }
    if (extension_loaded('kumwe_engine')) {
        throw new RuntimeException('Portable package qualification must execute without the native extension.');
    }
    $packages = packageSetInput(json_decode((string) file_get_contents($arguments[1]), true, 128, JSON_THROW_ON_ERROR));
    $workspace = sys_get_temp_dir() . '/kumwe-package-set-' . bin2hex(random_bytes(12));
    if (!mkdir($workspace, 0700)) {
        throw new RuntimeException('Cannot create the isolated package set consumer.');
    }
    try {
        $require = [];
        $repositories = [];
        foreach ($packages as $name => $entry) {
            $metadata = $entry['composer'];
            unset($metadata['source']);
            $metadata['version'] = $entry['version'];
            $metadata['dist'] = ['type' => 'zip', 'url' => 'file://' . $entry['archive_path'],
                'reference' => $entry['source_commit'], 'shasum' => sha1_file($entry['archive_path'])];
            $repositories[] = ['type' => 'package', 'package' => $metadata];
            $require[$name] = $entry['version'];
        }
        file_put_contents($workspace . '/composer.json', json_encode([
            'name' => 'kumwe/independent-extraction-consumer', 'license' => 'proprietary',
            'require' => $require, 'repositories' => $repositories,
            'minimum-stability' => 'stable', 'prefer-stable' => true,
            'config' => ['allow-plugins' => false],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        $composerEnvironment = ['COMPOSER_CACHE_DIR' => $workspace . '/composer-cache'];
        packageSetCommand(['composer', '--working-dir=' . $workspace, 'install', '--no-dev',
            '--classmap-authoritative', '--prefer-dist', '--no-scripts', '--no-plugins', '--no-interaction', '--no-progress'],
            $composerEnvironment);
        packageSetCommand(['composer', '--working-dir=' . $workspace, 'dump-autoload', '--no-dev',
            '--classmap-authoritative', '--strict-psr', '--strict-ambiguous', '--no-scripts', '--no-plugins', '--no-interaction'],
            $composerEnvironment);
        packageSetCommand(['composer', '--working-dir=' . $workspace, 'check-platform-reqs', '--no-dev', '--no-interaction'],
            $composerEnvironment);
        packageSetCommand(['composer', '--working-dir=' . $workspace, 'audit', '--locked', '--no-dev', '--abandoned=fail', '--no-interaction'],
            $composerEnvironment);
        $lock = json_decode((string) file_get_contents($workspace . '/composer.lock'), true, 128, JSON_THROW_ON_ERROR);
        if (($lock['packages-dev'] ?? []) !== []) {
            throw new RuntimeException('The isolated consumer lock contains development dependencies.');
        }
        $installed = [];
        foreach ($lock['packages'] ?? [] as $package) {
            if (!str_starts_with($package['name'], 'kumwe/')) {
                continue;
            }
            $entry = $packages[$package['name']] ?? null;
            if ($entry === null || ltrim($package['version'], 'v') !== $entry['version']
                || isset($package['source'])
                || ($package['dist']['reference'] ?? null) !== $entry['source_commit']
                || ($package['dist']['url'] ?? null) !== 'file://' . $entry['archive_path']) {
                throw new RuntimeException('Installed package identity or distribution differs from verified evidence.');
            }
            $installed[$package['name']] = true;
        }
        if (array_diff_key($packages, $installed) !== []) {
            throw new RuntimeException('Composer did not install every selected package.');
        }
        // Replay the exact lock from an empty vendor tree. Only this consumer's warmed, read-only
        // cache and the independently verified ZIPs remain available to Composer's offline mode.
        $lockDigest = hash_file('sha256', $workspace . '/composer.lock');
        packageSetRemoveDirectory($workspace . '/vendor');
        $offlineEnvironment = $composerEnvironment + ['COMPOSER_DISABLE_NETWORK' => '1', 'COMPOSER_CACHE_READ_ONLY' => '1'];
        packageSetCommand(['composer', '--working-dir=' . $workspace, 'install', '--no-dev',
            '--classmap-authoritative', '--prefer-dist', '--no-scripts', '--no-plugins', '--no-interaction', '--no-progress'],
            $offlineEnvironment);
        if (hash_file('sha256', $workspace . '/composer.lock') !== $lockDigest) {
            throw new RuntimeException('Offline package reinstallation changed the qualified Composer lock.');
        }
        $loader = require $workspace . '/vendor/autoload.php';
        if (!$loader->isClassMapAuthoritative() || class_exists('PHPUnit\\Framework\\TestCase')
            || extension_loaded('kumwe_engine')) {
            throw new RuntimeException('Qualification requires authoritative, development-free and native-free runtime loading.');
        }
        $classmap = $loader->getClassMap();
        $report = [];
        foreach ($packages as $name => $entry) {
            $root = realpath($workspace . '/vendor/' . $name);
            if (!is_string($root) || is_link($workspace . '/vendor/' . $name)
                || json_decode((string) file_get_contents($root . '/composer.json'), true,
                    64, JSON_THROW_ON_ERROR) !== $entry['composer']) {
                throw new RuntimeException('Installed archive Composer metadata differs: ' . $name);
            }
            $loaded = 0;
            $optional = 0;
            foreach ($classmap as $type => $file) {
                $file = realpath($file);
                if (!is_string($file) || !str_starts_with($file, $root . '/')) {
                    continue;
                }
                if ($name === 'kumwe/extension-sdk' && in_array($type, [
                    'Kumwe\\Extension\\Toolchain\\ExtensionConformanceTestCase',
                    'Kumwe\\Extension\\Toolchain\\ExtensionLifecycleTestCase',
                ], true)) {
                    $optional++;
                    continue;
                }
                if ((!class_exists($type) && !interface_exists($type) && !enum_exists($type) && !trait_exists($type))
                    || (new ReflectionClass($type))->getName() !== $type
                    || realpath((string) (new ReflectionClass($type))->getFileName()) !== $file) {
                    throw new RuntimeException('Installed runtime type has an invalid owner or cannot load: ' . $type);
                }
                $loaded++;
            }
            if ($loaded === 0 || hash_file('sha256', $entry['archive_path']) !== $entry['archive_sha256']) {
                throw new RuntimeException('Package exports are absent or the verified archive changed: ' . $name);
            }
            $report[] = ['name' => $name, 'version' => $entry['version'],
                'source_commit' => $entry['source_commit'], 'archive_sha256' => $entry['archive_sha256'],
                'runtime_types_loaded' => $loaded, 'optional_phpunit_bridges' => $optional,
                'runtime_requirements' => $entry['composer']['require'] ?? []];
        }
        $result = ['schema' => 'kumwe-php-package-set-consumer/v1', 'status' => 'passed',
            'graph' => 'portable-extraction', 'required_extraction_count' => count(packageSetTargets()),
            'package_count' => count($report), 'php_version' => PHP_VERSION,
            'native_extension_loaded' => false, 'no_dev' => true, 'classmap_authoritative' => true,
            'source_fallback' => false, 'source_fallback_scope' => 'verified-kumwe-archives',
            'offline_reinstall' => true, 'offline_mode' => 'COMPOSER_DISABLE_NETWORK=1',
            'composer_cache_scope' => 'isolated-consumer', 'composer_lock_sha256' => $lockDigest,
            'packages' => $report];
        $bytes = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        if (file_put_contents($arguments[2], $bytes, LOCK_EX) !== strlen($bytes)) {
            throw new RuntimeException('Cannot write complete package consumer evidence.');
        }
        echo 'Portable extraction package consumer passed: ' . count($report) . ' packages, all 28 extraction owners.' . "\n";
    } finally {
        packageSetRemoveDirectory($workspace);
    }
}
