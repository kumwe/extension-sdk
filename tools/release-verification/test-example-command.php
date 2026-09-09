<?php

declare(strict_types=1);

require __DIR__ . '/example-command.php';

$root = sys_get_temp_dir() . '/kumwe-example-bootstrap-' . bin2hex(random_bytes(8));
$package = $root . '/vendor/fixture/guarded';
mkdir($package . '/src', 0700, true);
mkdir($package . '/examples', 0700);
$run = static function (array $arguments) use ($root): array {
    $process = proc_open($arguments, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
    if (!is_resource($process)) { throw new RuntimeException('Fixture subprocess did not start.'); }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
    return [proc_close($process), $stdout, $stderr];
};
try {
    file_put_contents($root . '/composer.json', json_encode([
        'name' => 'fixture/consumer', 'version' => '1.0.0', 'license' => 'proprietary',
        'autoload' => ['psr-4' => ['InstalledExampleFixture\\' => 'vendor/fixture/guarded/src/']],
        'config' => ['allow-plugins' => false],
    ], JSON_THROW_ON_ERROR));
    file_put_contents($package . '/src/Contract.php', '<?php namespace InstalledExampleFixture; final class Contract {}');
    [$status, , $error] = $run(['composer', 'dump-autoload', '--no-dev', '--no-plugins', '--no-scripts',
        '--classmap-authoritative', '--strict-psr', '--strict-ambiguous', '--no-interaction']);
    if ($status !== 0) { throw new RuntimeException('Actual Composer fixture generation failed: ' . $error); }
    $autoload = $root . '/vendor/autoload.php';
    $example = $package . '/examples/guarded.php';
    file_put_contents($example, <<<'PHP'
<?php
declare(strict_types=1);
if (!class_exists(InstalledExampleFixture\Contract::class)) {
    require dirname(__DIR__) . '/vendor/autoload.php';
}
if (!class_exists(InstalledExampleFixture\Contract::class)
    || !class_exists(Composer\Autoload\ClassLoader::class, false)
    || ($argv[1] ?? null) === null) { throw new RuntimeException('Host Composer context missing.'); }
echo "guarded installed example passed\n";
PHP);
    $before = hash_file('sha256', $example);
    [$withoutPreload] = $run([PHP_BINARY, $example, $autoload]);
    if ($withoutPreload === 0) { throw new RuntimeException('Fixture did not reproduce the missing host loader.'); }
    [$status, $stdout, $stderr] = $run(releaseVerificationExampleCommand(PHP_BINARY, $example, $autoload));
    if ($status !== 0 || $stdout !== "guarded installed example passed\n") {
        throw new RuntimeException('Unchanged guarded installed example failed: ' . $stderr);
    }
    if (hash_file('sha256', $example) !== $before || file_exists($package . '/vendor')) {
        throw new RuntimeException('Fixture altered source or manufactured a nested vendor directory.');
    }
    $broken = $package . '/examples/unconditional.php';
    file_put_contents($broken, '<?php require dirname(__DIR__) . "/vendor/autoload.php";');
    [$status] = $run(releaseVerificationExampleCommand(PHP_BINARY, $broken, $autoload));
    if ($status === 0) { throw new RuntimeException('Preloading masked an unconditional wrong autoloader path.'); }
    echo "Actual Composer guarded-example bootstrap passed; missing preload and unconditional wrong paths still fail.\n";
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        if ($file->isDir() && !$file->isLink()) { rmdir($file->getPathname()); }
        else { unlink($file->getPathname()); }
    }
    rmdir($root);
}
