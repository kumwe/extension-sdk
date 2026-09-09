<?php

/** Install and exercise generated author code against the actual SDK archive. @since 0.3.0 */

declare(strict_types=1);

/**
 * Prove the scaffold's own Composer requirements, tests and production autoload map.
 *
 * The SDK is installed exclusively from its built ZIP. Explicit upstream source selections are
 * inherited from the existing source-candidate lane; they do not establish release verification.
 *
 * @param string $root Reviewed SDK checkout, used only to read selected dependency coordinates.
 * @param string $workspace Private workspace cleaned by the enclosing archive-consumer gate.
 * @param string $consumer Isolated SDK archive consumer used to generate the author project.
 * @param array<string, mixed> $archiveMetadata SDK metadata with its authoritative ZIP distribution.
 * @param ?array<string, mixed> $candidate Explicit upstream source candidate configuration.
 * @return void
 * @since 0.3.0
 */
function verifyScaffoldConsumer(
    string $root,
    string $workspace,
    string $consumer,
    array $archiveMetadata,
    ?array $candidate,
): void {
    $selected = json_decode((string) file_get_contents($root . '/resources/source-ci-dependencies.json'),
        true, 64, JSON_THROW_ON_ERROR);
    $computation = $selected['kumwe/computation']['version'] ?? null;
    if (!is_string($computation) || ($archiveMetadata['name'] ?? null) !== 'kumwe/extension-sdk') {
        throw new RuntimeException('Scaffold verification requires the SDK archive and selected Computation.');
    }
    // Generation explicitly installs its native composition dependency. The author runtime must
    // subsequently work after Composer removes this optional development-only dependency again.
    runConsumerCommand(['composer', '--working-dir=' . $consumer, 'require',
        'kumwe/computation:' . $computation, '--no-interaction', '--prefer-dist', '--no-scripts',
        '--no-plugins', '--classmap-authoritative', '--no-progress']);
    $source = $workspace . '/generated-component';
    $generate = <<<'GENERATE'
<?php
$loader = require __DIR__ . '/vendor/autoload.php';
$sdk = realpath(__DIR__ . '/vendor/kumwe/extension-sdk');
$scaffolderSource = (new ReflectionClass(Kumwe\Extension\Toolchain\ComponentScaffolder::class))->getFileName();
if (!$loader->isClassMapAuthoritative() || !is_string($sdk) || !is_string($scaffolderSource)
    || !str_starts_with(realpath($scaffolderSource), $sdk . '/')) {
    throw new RuntimeException('Scaffolding must execute the installed SDK archive.');
}
$path = getenv('KUMWE_NATIVE_EXPECTED_TUPLE');
if (!is_string($path) || !is_file($path) || !extension_loaded('kumwe_engine')) {
    throw new RuntimeException('Scaffold generation requires the actual native extension and expected tuple.');
}
$tuple = json_decode(file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
$encoder = new Kumwe\Computation\NativeCanonicalEncoder(new Kumwe\Engine\Runtime(),
    new Kumwe\Computation\NativeCompatibility(
        Kumwe\Computation\CapabilitySet::fromArray($tuple['capabilities']),
        $tuple['extension_version'], $tuple['embedded_engine_commit'],
        $tuple['embedded_source_sha256'], $tuple['binding_build_digest'],
    ));
(new Kumwe\Extension\Toolchain\ComponentScaffolder($encoder))->scaffold(
    new Kumwe\Extension\Toolchain\ScaffoldRequest(
        'acme/installed-component', 'Acme\\InstalledComponent', $argv[1], 'Installed Component',
    ),
);
GENERATE;
    file_put_contents($consumer . '/generate.php', $generate . "\n");
    runConsumerCommand(['php', $consumer . '/generate.php', $source]);
    $manifest = json_decode((string) file_get_contents($source . '/composer.json'), true, 64, JSON_THROW_ON_ERROR);
    if (($manifest['require']['kumwe/extension-sdk'] ?? null) !== $archiveMetadata['version']
        || ($manifest['require-dev']['kumwe/computation'] ?? null) !== $computation
        || ($manifest['minimum-stability'] ?? null) !== 'stable') {
        throw new RuntimeException('Generated dependency coordinates differ from the verified SDK candidate.');
    }
    // Only repository locations are supplied to the candidate. Generated requirements and test
    // configuration remain untouched, so their real Composer solver and PHPUnit failures surface.
    $manifest['repositories'] = array_merge(
        [['type' => 'package', 'package' => $archiveMetadata]],
        $candidate['repositories'] ?? [],
        $manifest['repositories'] ?? [],
    );
    file_put_contents($source . '/composer.json', json_encode($manifest,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    runConsumerCommand(['composer', '--working-dir=' . $source, 'install', '--no-interaction',
        '--prefer-dist', '--classmap-authoritative', '--no-scripts', '--no-plugins', '--no-progress']);
    runConsumerCommand(['composer', '--working-dir=' . $source, '--no-plugins', 'run-script', 'test', '--no-interaction']);
    runConsumerCommand(['composer', '--working-dir=' . $source, 'install', '--no-interaction',
        '--prefer-dist', '--no-dev', '--classmap-authoritative', '--no-scripts', '--no-plugins', '--no-progress']);
    $smoke = <<<'SMOKE'
<?php
$loader = require __DIR__ . '/vendor/autoload.php';
if (!$loader->isClassMapAuthoritative() || class_exists('PHPUnit\\Framework\\TestCase')
    || class_exists('Kumwe\\Computation\\NativeCanonicalEncoder')) {
    throw new RuntimeException('Generated production code must use authoritative autoloading without development dependencies.');
}
$sdk = realpath(__DIR__ . '/vendor/kumwe/extension-sdk');
$sdkSource = (new ReflectionClass(Kumwe\Extension\Toolchain\ComponentScaffolder::class))->getFileName();
if (!is_string($sdk) || !is_string($sdkSource) || !str_starts_with(realpath($sdkSource), $sdk . '/')) {
    throw new RuntimeException('Generated project resolved the SDK outside its installed archive.');
}
$count = 0;
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/src', FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $relative = substr($file->getPathname(), strlen(__DIR__ . '/src/'), -4);
    $type = 'Acme\\InstalledComponent\\' . str_replace('/', '\\', $relative);
    if (!class_exists($type) && !interface_exists($type) && !enum_exists($type) && !trait_exists($type)) {
        throw new RuntimeException('Generated production type does not autoload: ' . $type);
    }
    $count++;
}
$greeting = new Acme\InstalledComponent\Greeting();
if ($greeting->isBooted()) {
    throw new RuntimeException('Generated greeting must start unbooted.');
}
$greeting->boot();
if (!$greeting->isBooted() || $count < 10) {
    throw new RuntimeException('Generated production service or complete source inventory failed.');
}
$response = new Laminas\Diactoros\Response\HtmlResponse('Installed component', 200, ['Cache-Control' => 'no-store']);
if ((string) $response->getBody() !== 'Installed component' || $response->getHeaderLine('Cache-Control') !== 'no-store') {
    throw new RuntimeException('Generated delivery dependency failed to execute.');
}
echo "Generated project production consumer passed: {$count} types, real delivery dependency and no development autoloader.\n";
SMOKE;
    file_put_contents($source . '/production-smoke.php', $smoke . "\n");
    runConsumerCommand(['php', $source . '/production-smoke.php']);
}
