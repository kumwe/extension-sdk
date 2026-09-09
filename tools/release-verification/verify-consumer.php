<?php
/** Independently install the exact downloaded release archive, then replay its lock without network. */
declare(strict_types=1);
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
file_put_contents($root . '/composer.json', json_encode(['name' => 'kumwe/independent-release-consumer',
    'license' => 'proprietary', 'require' => [$input['name'] => $input['version']],
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
foreach ($classmap as $name => $file) {
    if (str_starts_with(realpath($file), realpath($package) . '/')) {
        if (!class_exists($name) && !interface_exists($name) && !trait_exists($name) && !enum_exists($name)) {
            throw new RuntimeException('Runtime class failed to load: ' . $name);
        }
        $classes[] = $name;
    }
}
if ($classes === []) {
    throw new RuntimeException('Package has no resolved runtime exports.');
}
foreach ($input['examples'] as $example) {
    if (str_ends_with($example, '.php')) {
        $run([PHP_BINARY, $package . '/' . $example, $root . '/vendor/autoload.php']);
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
    'runtime_classes' => $classes, 'examples' => $input['examples'], 'php' => PHP_VERSION,
    'php_zts' => PHP_ZTS, 'os' => PHP_OS_FAMILY, 'architecture' => php_uname('m'),
    'native_extension_loaded' => extension_loaded('kumwe_engine')], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
