<?php

/** Verify governed metadata and the v2 handoff without changing the stable runtime/profile API. @since 0.3.0 */

declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn (string $path): array => json_decode(
    (string) file_get_contents($root . '/' . $path),
    true,
    512,
    JSON_THROW_ON_ERROR,
);
$require = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('V2 metadata: ' . $message);
    }
};
$composer = $read('composer.json');
preg_match('/^## \[?([0-9]+\.[0-9]+\.[0-9]+)(?:\]|\s)/m', (string) file_get_contents($root . '/CHANGELOG.md'), $record);
$release = $record[1] ?? '';
$require($release !== '', 'one stable release record is required');
$classification = $read('resources/contract/classification.json');
$api = $read('resources/public-api/v1.json');
$require($api['schema'] === 'kumwe-package-public-api/v1', 'canonical public API schema');
$require($api['package'] === $composer['name'] && $api['release'] === $release, 'canonical public API identity');
$require($api['namespace'] === 'Kumwe\\Extension\\', 'canonical public namespace');
$symbols = $api['symbols'];
$require(array_keys($symbols) === array_column($classification['types'], 'type'), 'all classified exports must be projected exactly');
$capabilities = $read('resources/capabilities/v1.json');
$services = $read('resources/service-map/v1.json');
foreach (['capabilities' => $capabilities, 'service-map' => $services] as $kind => $manifest) {
    $require($manifest['schema'] === 'kumwe-package-' . $kind . '/v1', $kind . ' schema identity');
    $require($manifest['package'] === $composer['name'], $kind . ' package identity');
    $require($manifest['release'] === $release, $kind . ' release identity');
}
$require($capabilities['namespace'] === $api['namespace'], 'capability namespace');
$covered = [];
$ids = [];
foreach ($capabilities['capabilities'] as $capability) {
    $require(!isset($ids[$capability['id']]), 'duplicate capability identity');
    $ids[$capability['id']] = true;
    foreach ($capability['symbols'] as $symbol) {
        $require(isset($symbols[$symbol]), 'capability names a foreign symbol');
        $covered[$symbol] = true;
    }
    foreach ($capability['documentation'] as $path) {
        $require(is_file($root . '/' . $path), 'capability documentation missing');
    }
}
$require(array_diff_key($symbols, $covered) === [], 'every public symbol needs capability ownership');
$require($services['config_provider'] === null, 'no ambient encoder, signing authority or container provider is permitted');
$require($services['factories'] === [] && $services['aliases'] === [], 'direct construction has no service defaults');
$require($services['delegators'] === [] && $services['configuration_keys'] === [], 'no hidden host configuration');
$require(
    is_string($services['provider_absence_reason']) && $services['provider_absence_reason'] !== '',
    'provider rationale',
);
$handoffBytes = (string) file_get_contents($root . '/MIGRATION-HANDOFF.md');
$parts = explode("\n---\n", $handoffBytes, 2);
$require(str_starts_with($parts[0], "---\n") && count($parts) === 2, 'v2 handoff front matter');
// The handoff deliberately uses JSON-compatible YAML 1.2 for dependency-free verification.
$handoff = json_decode(substr($parts[0], 4), true, 512, JSON_THROW_ON_ERROR);
$require($handoff['schema'] === 'kumwe-migration-handoff/v2', 'handoff schema identity');
$require($handoff['framework_php']['composer_package'] === $composer['name'], 'handoff package identity');
$require($handoff['framework_php']['public_api_manifest'] === 'resources/public-api/v1.json', 'canonical API location');
$observed = [];
foreach ($handoff['ownership']['public_manifests'] as $manifest) {
    $path = $manifest['path'];
    $require(is_file($root . '/' . $path), 'handoff file is missing: ' . $path);
    $require(hash_file('sha256', $root . '/' . $path) === $manifest['sha256'], 'handoff hash drift: ' . $path);
    $require(!isset($observed[$path]), 'duplicate handoff file: ' . $path);
    $observed[$path] = true;
}
foreach (
    [
        'resources/public-api/v1.json', 'resources/public-api/signature-details-v1.json',
        'resources/capabilities/v1.json', 'resources/service-map/v1.json',
        'resources/contract/classification.json', 'resources/contract/generations.json', 'resources/PIN.json',
    ] as $path
) {
    $require(isset($observed[$path]), 'handoff must identify ' . $path);
}
$require($handoff['migration_id'] === 'KUMWE-MIG-2026-033' && $handoff['change_set'] === 'KUMWE-CS-2026-033', 'reserved SDK migration identity');
$require($handoff['state'] === 'draft_pr_open' && $handoff['governance']['completion_claim'] === false, 'handoff must not attest its own release');
$require($handoff['native_cpp'] === null && $handoff['php_extension'] === null, 'SDK is a PHP artifact');
$di = $handoff['framework_php']['dependency_injection'];
$require($di['mode'] === 'direct' && $di['provider'] === null && $di['factories'] === [] && $di['aliases'] === [], 'handoff direct construction agreement');
$require($di['provider_absence_reason'] === $services['provider_absence_reason'], 'handoff provider rationale agreement');
$extracted = [];
foreach ($handoff['framework_php']['extracted_symbols'] as $symbol) {
    $name = $symbol['new_fqcn'];
    $require(isset($symbols[$name]) && !isset($extracted[$name]), 'handoff export identity');
    $extracted[$name] = true;
    $shape = $symbols[$name];
    $require($symbol['old_fqcn'] === $name && $symbol['target_path'] === $shape['file'], 'SDK governance preserves owned names and paths');
    $require($symbol['kind'] === $shape['kind'], 'handoff symbol kind');
    foreach (['methods', 'properties', 'constants'] as $kind) {
        $require($symbol['public_' . $kind] === array_keys($shape[$kind]), 'handoff member drift: ' . $name);
    }
}
$require(array_keys($extracted) === array_keys($symbols), 'handoff must enumerate every SDK export');
$require(!isset($composer['require']['kumwe/computation']) && !isset($composer['require']['ext-kumwe_engine']), 'SDK runtime must remain native-optional');
$require(!isset($composer['extra']['laminas']['config-provider']), 'no implicit provider registration');
foreach (
    [
    'CHARTER.md', 'README.md', 'docs/public-api.md', 'docs/architecture.md', 'docs/host-integration.md',
    'docs/app-agreement.md', 'docs/canonical-package-candidate.md', 'docs/test-ownership.md',
    'examples/direct-construction.php',
    ] as $path
) {
    $require(is_file($root . '/' . $path) && filesize($root . '/' . $path) > 0, 'required shipped document: ' . $path);
}
echo "Governed SDK manifests, classified API exports, source documentation and v2 handoff hashes verified.\n";
