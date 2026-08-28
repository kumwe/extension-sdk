<?php

/**
 * Dependency-free test runner: discovers tests/Case/*Test.php, runs every
 * public method beginning with "test", and reports one line per file.
 *
 * Assertions come from Kumwe\Extension\Tests\TestCase. No framework, so the
 * suite runs on any supported PHP with no composer install. Zero discovered
 * test files is a passing state — the founding repository ships the harness
 * before the first extracted class — and the summary line says so honestly.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'Kumwe\\Extension\\Tests\\' => __DIR__ . '/',
        'Kumwe\\Extension\\' => dirname(__DIR__) . '/src/',
    ];
    foreach ($prefixes as $prefix => $base) {
        if (str_starts_with($class, $prefix)) {
            $path = $base . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($path)) {
                require $path;
            }
            return;
        }
    }
});

$caseDirectory = __DIR__ . '/Case';
$files = is_dir($caseDirectory) ? (glob($caseDirectory . '/*Test.php') ?: []) : [];
sort($files);

$totalTests = 0;
$totalAssertions = 0;
$failures = [];

foreach ($files as $file) {
    $class = 'Kumwe\\Extension\\Tests\\Case\\' . basename($file, '.php');
    if (!class_exists($class)) {
        $failures[] = "{$file} declares no {$class}.";
        continue;
    }
    $case = new $class();
    $ran = 0;
    foreach (get_class_methods($case) as $method) {
        if (!str_starts_with($method, 'test')) {
            continue;
        }
        $totalTests++;
        $ran++;
        try {
            $case->{$method}();
        } catch (\Throwable $error) {
            $failures[] = sprintf(
                '%s::%s — %s (%s:%d)',
                $class,
                $method,
                $error->getMessage(),
                basename($error->getFile()),
                $error->getLine()
            );
        }
    }
    $totalAssertions += $case->assertionCount();
    echo sprintf("%-52s %3d tests\n", basename($file), $ran);
}

if ($failures !== []) {
    fwrite(STDERR, "\nFailures:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "Extension SDK suite passed: {$totalTests} tests, {$totalAssertions} assertions.\n";
