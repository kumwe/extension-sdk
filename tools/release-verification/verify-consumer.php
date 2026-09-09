<?php
/** Independently install the exact downloaded release archive, then replay its lock without network. */
declare(strict_types=1);
require __DIR__ . '/example-command.php';
if ($argc !== 3) {
    fwrite(STDERR, "Usage: php verify-consumer.php INPUT_JSON REPORT_JSON\n");
    exit(2);
}
$input = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$root = dirname(realpath($argv[1])) . '/consumer';
if (file_exists($root)) {
    throw new RuntimeException('Consumer directory must be fresh.');
}
mkdir($root, 0700, true);
$metadata = $input['composer'];
$metadata['version'] = $input['version'];
unset($metadata['source']);
$metadata['dist'] = ['type' => 'zip', 'url' => 'file://' . $input['archive_path'],
    'reference' => $input['source_commit'], 'shasum' => sha1_file($input['archive_path'])];
$consumerRequirements = [$input['name'] => $input['version']];
// The published package's documented Laminas example needs an actual host container.
// Make that host dependency explicit; never import test frameworks or all require-dev.
if (isset($metadata['require-dev']['laminas/laminas-servicemanager'])
    && ($input['handoff']['framework_php']['dependency_injection']['mode'] ?? 'direct') !== 'direct') {
    $consumerRequirements['laminas/laminas-servicemanager'] = $metadata['require-dev']['laminas/laminas-servicemanager'];
}
file_put_contents($root . '/composer.json', json_encode(['name' => 'kumwe/independent-release-consumer',
    'license' => 'proprietary', 'require' => $consumerRequirements,
    'repositories' => [['type' => 'package', 'package' => $metadata]],
    'config' => ['allow-plugins' => false]], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
putenv('COMPOSER_CACHE_DIR=' . $root . '/cache');
$run = static function (array $args) use ($root): void {
    $process = proc_open($args, [0 => ['pipe', 'r'], 1 => STDOUT, 2 => STDERR], $pipes, $root);
    if (!is_resource($process)) {
        throw new RuntimeException('Consumer command did not start.');
    }
    fclose($pipes[0]);
    $code = proc_close($process);
    if ($code !== 0) {
        throw new RuntimeException('Consumer command failed: ' . $code . ' ' . implode(' ', $args));
    }
};
$install = ['composer', 'install', '--no-interaction', '--prefer-dist', '--no-dev',
    '--no-plugins', '--no-scripts', '--classmap-authoritative', '--no-progress'];
$run($install);
$run(['composer', 'audit', '--abandoned=fail', '--format=json']);
$lockDigest = hash_file('sha256', $root . '/composer.lock');
$remove = static function (string $directory): void {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory,
        FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        if ($file->isDir() && !$file->isLink()) {
            if (!rmdir($file->getPathname())) { throw new RuntimeException('Cannot remove vendor directory.'); }
        } else {
            if (!unlink($file->getPathname())) { throw new RuntimeException('Cannot remove vendor file.'); }
        }
    }
    if (!rmdir($directory)) { throw new RuntimeException('Cannot remove original vendor root.'); }
};
$remove($root . '/vendor');
putenv('COMPOSER_DISABLE_NETWORK=1');
putenv('COMPOSER_CACHE_READ_ONLY=1');
$run($install);
$run(['composer', 'dump-autoload', '--no-dev', '--no-plugins', '--no-scripts', '--classmap-authoritative', '--strict-psr', '--strict-ambiguous']);
if (hash_file('sha256', $root . '/composer.lock') !== $lockDigest) {
    throw new RuntimeException('Offline replay changed its immutable lock.');
}
$loader = require $root . '/vendor/autoload.php';
if (!$loader->isClassMapAuthoritative() || class_exists('PHPUnit\\Framework\\TestCase')) {
    throw new RuntimeException('Consumer autoloading contains development state.');
}
$package = $root . '/vendor/' . $input['name'];
$classmap = require $root . '/vendor/composer/autoload_classmap.php';
$classes = [];
$optionalBridges = [];
$phpunitBridges = ['Kumwe\\Extension\\Toolchain\\ExtensionConformanceTestCase',
    'Kumwe\\Extension\\Toolchain\\ExtensionLifecycleTestCase'];
foreach ($classmap as $name => $file) {
    if (str_starts_with(realpath($file), realpath($package) . '/')) {
        if ($input['name'] === 'kumwe/extension-sdk' && in_array($name, $phpunitBridges, true)
            && !class_exists('PHPUnit\\Framework\\TestCase')) {
            $optionalBridges[] = $name;
            continue;
        }
        if (!class_exists($name) && !interface_exists($name) && !trait_exists($name) && !enum_exists($name)) {
            throw new RuntimeException('Runtime class failed to load: ' . $name);
        }
        $classes[] = $name;
    }
}
$publicApi = json_decode(file_get_contents($package . '/' . $input['handoff']['framework_php']['public_api_manifest']),
    true, 512, JSON_THROW_ON_ERROR);
$declaredSymbols = $publicApi['symbols'];
foreach ($declaredSymbols as $name => $symbol) {
    $relative = $symbol['file'];
    if (str_starts_with($relative, '/') || str_contains($relative, '\\')
        || in_array('..', explode('/', $relative), true)) {
        throw new RuntimeException('Unsafe canonical API file path.');
    }
    $installedFile = realpath($package . '/' . $relative);
    $originalFile = realpath($input['package_root'] . '/' . $relative);
    if ($installedFile === false || $originalFile === false
        || !str_starts_with($installedFile, realpath($package) . '/')
        || !isset($classmap[$name]) || realpath($classmap[$name]) !== $installedFile
        || hash_file('sha256', $installedFile) !== hash_file('sha256', $originalFile)) {
        throw new RuntimeException('Canonical API export is missing, shadowed or changed: ' . $name);
    }
    if (in_array($name, $optionalBridges, true)) { continue; }
    if (!in_array($name, $classes, true)) {
        throw new RuntimeException('Canonical API symbol was not loaded: ' . $name);
    }
    $reflection = new ReflectionClass($name);
    $kind = $reflection->isEnum() ? 'enum' : ($reflection->isInterface() ? 'interface'
        : ($reflection->isTrait() ? 'trait' : 'class'));
    if ($kind !== $symbol['kind'] || realpath($reflection->getFileName()) !== $installedFile) {
        throw new RuntimeException('Canonical API runtime identity differs: ' . $name);
    }
}
$locked = json_decode(file_get_contents($root . '/composer.lock'), true, 512, JSON_THROW_ON_ERROR);
$selected = array_values(array_filter($locked['packages'], static fn (array $p): bool => $p['name'] === $input['name']));
if (count($selected) !== 1 || ltrim($selected[0]['version'], 'v') !== $input['version']
    || isset($selected[0]['source']) || ($selected[0]['dist']['url'] ?? null) !== 'file://' . $input['archive_path']
    || ($selected[0]['dist']['reference'] ?? null) !== $input['source_commit']
    || ($selected[0]['dist']['shasum'] ?? null) !== sha1_file($input['archive_path'])
    || Composer\InstalledVersions::getReference($input['name']) !== $input['source_commit']) {
    throw new RuntimeException('Consumer lock/installed identity differs from the original verified archive.');
}
if ($classes === []) {
    throw new RuntimeException('Package has no resolved runtime exports.');
}
foreach ($input['examples'] as $example) {
    if (str_ends_with($example, '.php')) {
        $run(releaseVerificationExampleCommand(PHP_BINARY, $package . '/' . $example, $root . '/vendor/autoload.php'));
    }
}
$run(['composer', 'check-platform-reqs', '--no-dev']);
if ($input['name'] === 'kumwe/computation' && str_starts_with($input['version'], '0.1.')
    && extension_loaded('kumwe_engine')) {
    throw new RuntimeException('Portable Computation must be verified without the native extension.');
}
file_put_contents($argv[2], json_encode(['schema' => 'kumwe-independent-release-consumer/v1',
    'status' => 'passed', 'name' => $input['name'], 'version' => $input['version'],
    'source_commit' => $input['source_commit'], 'archive_sha256' => $input['archive_sha256'],
    'no_dev' => true, 'classmap_authoritative' => true, 'offline_reinstall' => true,
    'composer_lock_sha256' => $lockDigest, 'runtime_types_loaded' => count($classes),
    'consumer_dependency_audit' => 'passed', 'canonical_api_exports_verified' => count($declaredSymbols), 'installed_dist_identity_verified' => true,
    'runtime_classes' => $classes, 'optional_phpunit_bridges' => $optionalBridges,
    'consumer_host_requirements' => array_diff_key($consumerRequirements, [$input['name'] => true]), 'examples' => $input['examples'],
    'example_autoload_preload' => 'actual no-dev consumer vendor/autoload.php', 'php' => PHP_VERSION,
    'php_zts' => PHP_ZTS, 'os' => PHP_OS_FAMILY, 'architecture' => php_uname('m'),
    'native_extension_loaded' => extension_loaded('kumwe_engine')], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
