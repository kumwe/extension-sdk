<?php

/** Verify the stable PHP dependency graph before any source installation. @since 0.3.0 */

declare(strict_types=1);

$root = dirname(__DIR__);

/** @return array<mixed> Decoded dependency metadata. @since 0.3.0 */
function dependencySelectionJson(string $path): array
{
    $data = json_decode((string) file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
        throw new RuntimeException('Dependency metadata must be an object or list: ' . $path);
    }
    return $data;
}

$composer = dependencySelectionJson($root . '/composer.json');
$selected = dependencySelectionJson($root . '/resources/source-ci-dependencies.json');
$records = dependencySelectionJson($root . '/resources/source-candidate-dependencies.json');
if ($selected === [] || array_is_list($selected) || !array_is_list($records)) {
    throw new RuntimeException('Dependency selections require a nonempty map and a record list.');
}
if (($composer['minimum-stability'] ?? 'stable') !== 'stable') {
    throw new RuntimeException('The SDK PHP package graph must require stable dependencies.');
}
$recordMap = [];
foreach ($records as $record) {
    $name = $record['package'] ?? null;
    if (!is_string($name) || isset($recordMap[$name])) {
        throw new RuntimeException('Dependency evidence names an invalid or duplicate package.');
    }
    $recordMap[$name] = $record;
}
$names = array_keys($selected);
$recordNames = array_keys($recordMap);
sort($names);
sort($recordNames);
if ($names !== $recordNames) {
    throw new RuntimeException('Source selections and dependency evidence list different packages.');
}
foreach ($selected as $name => $selection) {
    if (!is_string($name) || preg_match('~^kumwe/[a-z0-9]+(?:-[a-z0-9]+)*$~D', $name) !== 1
        || !is_array($selection)
        || !is_string($selection['version'] ?? null)
        || preg_match('/^(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)$/D', $selection['version']) !== 1
        || !is_string($selection['ref'] ?? null)
        || preg_match('/^[0-9a-f]{40}$/D', $selection['ref']) !== 1) {
        throw new RuntimeException('A PHP dependency needs an exact stable version and commit.');
    }
    $record = $recordMap[$name];
    if (($record['version'] ?? null) !== $selection['version']
        || ($record['requested_ref'] ?? null) !== $selection['ref']
        || ($record['repository'] ?? null) !== 'https://github.com/' . $name . '.git') {
        throw new RuntimeException('Dependency evidence differs from the selected source: ' . $name);
    }
    foreach (['ci.yml', 'source-candidate.yml'] as $workflow) {
        $bytes = (string) file_get_contents($root . '/.github/workflows/' . $workflow);
        $pattern = '~repository: ' . preg_quote($name, '~') . '\R\s+ref: ([0-9a-f]{40})(?:\R|$)~';
        preg_match_all($pattern, $bytes, $matches);
        if (($matches[1] ?? []) !== [$selection['ref']]) {
            throw new RuntimeException('Workflow checkout differs from selected source: ' . $workflow . ': ' . $name);
        }
    }
}
foreach (['require', 'require-dev'] as $section) {
    foreach ($composer[$section] ?? [] as $name => $constraint) {
        if (str_starts_with($name, 'kumwe/')) {
            if (!isset($selected[$name]) || $constraint !== $selected[$name]['version']) {
                throw new RuntimeException('Direct requirement must exactly match its stable selection: ' . $name);
            }
        }
    }
}
echo 'Stable PHP dependency selections and both workflow checkouts agree (' . count($selected) . " packages).\n";
