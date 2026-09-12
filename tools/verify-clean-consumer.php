<?php

/** Prove the built ZIP as an authoritative no-dev dependency in a fresh consumer. @since 0.1.3 */
declare(strict_types=1);

$root = dirname(__DIR__);
if (count($argv) > 2 || (isset($argv[1]) && $argv[1] !== '--scaffold')) {
    throw new RuntimeException('Usage: verify-clean-consumer.php [--scaffold]');
}
$verifyScaffold = ($argv[1] ?? null) === '--scaffold';
$metadata = json_decode((string) file_get_contents($root . '/composer.json'), true, 64, JSON_THROW_ON_ERROR);
$ownership = json_decode((string) file_get_contents($root . '/tests/ownership.json'), true, 64, JSON_THROW_ON_ERROR);
$packageName = $metadata['name'];
$workspace = sys_get_temp_dir() . '/kumwe-archive-consumer-' . bin2hex(random_bytes(8));
mkdir($workspace, 0700, true);

/** @param list<string> $arguments Command and exact arguments. @since 0.1.3 */
function runConsumerCommand(array $arguments): void
{
    passthru(implode(' ', array_map('escapeshellarg', $arguments)), $status);
    if ($status !== 0) {
        throw new RuntimeException('Consumer command failed with status ' . $status);
    }
}

try {
    runConsumerCommand(['composer', '--working-dir=' . $root, 'archive', '--format=zip',
        '--dir=' . $workspace, '--file=candidate']);
    $archive = $workspace . '/candidate.zip';
    $zip = new ZipArchive();
    if ($zip->open($archive) !== true) {
        throw new RuntimeException('Cannot inspect the built archive.');
    }
    $archivedMetadata = $zip->getFromName('composer.json');
    $manifestBytes = $zip->getFromName($ownership['api_manifest']);
    if (!is_string($archivedMetadata) || !is_string($manifestBytes)) {
        throw new RuntimeException('Archive must ship Composer metadata and its public API.');
    }
    foreach ([
        'docs/release-record.md', 'CHARTER.md', 'CHANGELOG.md', 'README.md',
        'resources/public-api/v1.json', 'resources/public-api/signature-details-v1.json',
        'resources/capabilities/v1.json', 'resources/service-map/v1.json',
        'resources/contract/classification.json', 'resources/contract/generations.json', 'resources/PIN.json',
        'docs/public-api.md', 'docs/architecture.md', 'docs/host-integration.md',
        'docs/release-qualification.md', 'examples/direct-construction.php',
    ] as $path) {
        if (!is_file($root . '/' . $path) || $zip->getFromName($path) !== file_get_contents($root . '/' . $path)) {
            throw new RuntimeException('Archive is missing or changed a governed SDK handoff file: ' . $path);
        }
    }
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $path = $zip->getNameIndex($index);
        if (!is_string($path) || preg_match('~^(?:tests|vendor|evidence|\.github|\.git)/~D', $path) === 1) {
            throw new RuntimeException('Archive contains development state: ' . (string) $path);
        }
    }
    $zip->close();
    $archivedMetadata = json_decode($archivedMetadata, true, 64, JSON_THROW_ON_ERROR);
    if ($archivedMetadata !== $metadata) {
        throw new RuntimeException('Archive metadata differs from the reviewed checkout.');
    }
    $releaseLines = [];
    exec('bash ' . escapeshellarg($root . '/tools/read-release-record.sh') . ' < '
        . escapeshellarg($root . '/CHANGELOG.md'), $releaseLines, $releaseStatus);
    if ($releaseStatus !== 0 || count($releaseLines) !== 1) {
        throw new RuntimeException('One exact candidate release is required.');
    }
    $version = $releaseLines[0];
    $candidateConfig = getenv('KUMWE_SOURCE_CONSUMER_CONFIG');
    $candidate = null;
    if (is_string($candidateConfig) && $candidateConfig !== '') {
        $candidate = json_decode((string) file_get_contents($candidateConfig), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($candidate) || !is_array($candidate['repositories'] ?? null)
            || !is_array($candidate['require'] ?? null)) {
            throw new RuntimeException('Explicit source consumer configuration is invalid.');
        }
    }
    $archivedMetadata['version'] = $version;
    $archivedMetadata['dist'] = ['type' => 'zip', 'url' => 'file://' . $archive, 'shasum' => sha1_file($archive)];
    $consumer = $workspace . '/consumer';
    mkdir($consumer);
    file_put_contents($consumer . '/composer.json', json_encode([
        'name' => 'kumwe/isolated-consumer', 'license' => 'proprietary',
        'require' => [$packageName => $version] + ($candidate['require'] ?? []),
        'repositories' => array_merge([['type' => 'package', 'package' => $archivedMetadata]],
            $candidate['repositories'] ?? $metadata['repositories'] ?? []),
        'minimum-stability' => $candidate['minimum-stability'] ?? 'stable',
        'prefer-stable' => true,
        'config' => ['allow-plugins' => false],
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n");
    runConsumerCommand(['composer', '--working-dir=' . $consumer, 'install', '--no-interaction',
        '--prefer-dist', '--no-dev', '--classmap-authoritative', '--no-scripts', '--no-progress']);
    $apiPath = $ownership['api_manifest'];
    $smoke = <<<'SMOKE'
<?php
$loader = require __DIR__ . '/vendor/autoload.php';
if (!$loader->isClassMapAuthoritative() || class_exists('PHPUnit\Framework\TestCase')) {
    throw new RuntimeException('Consumer must have authoritative autoloading and no PHPUnit.');
}
$package = __DIR__ . '/vendor/' . $argv[1];
$api = json_decode(file_get_contents($package . '/' . $argv[2]), true, 64, JSON_THROW_ON_ERROR);
$types = $api['types'];
$names = array_is_list($types) ? array_column($types, 'type') : array_keys($types);
$optional = ['Kumwe\Extension\Toolchain\ExtensionConformanceTestCase',
    'Kumwe\Extension\Toolchain\ExtensionLifecycleTestCase'];
$loaded = 0;
foreach ($names as $name) {
    if (in_array($name, $optional, true)) {
        if (!is_file($package . '/src/Toolchain/' . substr($name, strrpos($name, chr(92)) + 1) . '.php')) {
            throw new RuntimeException('Optional consumer-PHPUnit bridge is missing.');
        }
        continue;
    }
    if (!class_exists($name) && !interface_exists($name) && !enum_exists($name)) {
        throw new RuntimeException('Public type does not autoload: ' . $name);
    }
    $file = (new ReflectionClass($name))->getFileName();
    if (!is_string($file) || !str_starts_with(realpath($file), realpath($package) . '/')) {
        throw new RuntimeException('Type resolved outside the installed dependency: ' . $name);
    }
    $loaded++;
}
$actual = match ($argv[1]) {
    'kumwe/conversion' => Kumwe\Conversion\Decimal\ExactDecimal::fromString('12.5', 8, 3)->value(),
    'kumwe/extension-sdk' => Kumwe\Extension\Manifest\ExtensionIdentifier::fromString('acme/example')->value(),
    'kumwe/producer' => Kumwe\Producer\Canonical\CanonicalJson::stringify((object) ['b' => 2, 'a' => 1]),
};
$expected = ['kumwe/conversion' => '12.500', 'kumwe/extension-sdk' => 'acme/example',
    'kumwe/producer' => '{"a":1,"b":2}'][$argv[1]];
if ($actual !== $expected) {
    throw new RuntimeException('Installed dependency behavior failed.');
}
if ($argv[1] === 'kumwe/extension-sdk') {
    $encoder = new class implements Kumwe\CanonicalJson\CanonicalEncoder {
        public function encode(mixed $value): string { throw new RuntimeException('No jobs require encoding in this fixture.'); }
        public function digest(mixed $value): string { throw new RuntimeException('No digest is requested by this fixture.'); }
    };
    $json = file_get_contents($package . '/resources/fixtures/generations/manifest-2/kumwe.json');
    $manifest = Kumwe\Extension\Manifest\ExtensionManifest::fromJson($encoder, $json);
    if ($manifest->identifier()->value() !== 'kumwe/contract-manifest-two') {
        throw new RuntimeException('Installed SDK failed to assemble its canonical contribution graph.');
    }
}
echo "True archive dependency consumer passed: {$loaded} runtime exports; no development autoloader.\n";
SMOKE;
    file_put_contents($consumer . '/smoke.php', $smoke . "\n");
    runConsumerCommand(['php', $consumer . '/smoke.php', $packageName, $apiPath]);
    if ($verifyScaffold) {
        require $root . '/tools/verify-scaffold-consumer.php';
        verifyScaffoldConsumer($root, $workspace, $consumer, $archivedMetadata, $candidate);
    }
    if ($candidate !== null) {
        echo 'Source candidate only; archive SHA-256 ' . hash_file('sha256', $archive) . "; no release attestation.\n";
    }
} finally {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($workspace, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($files as $file) {
        if ($file->isDir() && !$file->isLink()) {
            rmdir($file->getPathname());
        } else {
            unlink($file->getPathname());
        }
    }
    rmdir($workspace);
}
