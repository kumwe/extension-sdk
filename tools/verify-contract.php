<?php

/**
 * Verify the SDK-owned public API, generation fixtures and resource inventory.
 *
 * Usage: `php tools/verify-contract.php [--root=PATH]`. The root override points at a copied resources
 * tree for refusal tests; canonical source/API expectations always come from this repository.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

require_once __DIR__ . '/record-contract.php';

$repository = dirname(__DIR__);
$resources = $repository . '/resources';
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--root=')) {
        $resources = rtrim(substr($argument, strlen('--root=')), '/');
        continue;
    }
    fwrite(STDERR, "Unknown argument {$argument}. Usage: php tools/verify-contract.php [--root=PATH]\n");
    exit(1);
}

$errors = [];
$pin = contractObject($resources . '/PIN.json', $errors);
$classification = contractObject($resources . '/contract/classification.json', $errors);
$generations = contractObject($resources . '/contract/generations.json', $errors);

verifyNamespacePurity($resources, $errors);
verifyPin($resources, $pin, $errors);
verifyClassification($repository, $classification, $errors);
verifyGenerations($repository, $resources, $generations, $errors);

if ($errors !== []) {
    foreach (array_values(array_unique($errors)) as $error) {
        fwrite(STDERR, 'Canonical contract: ' . $error . PHP_EOL);
    }
    exit(1);
}

fwrite(
    STDOUT,
    sprintf(
        "Canonical SDK contract verified: %d public types, %d manifest generations, %d SPI generations, %d pinned resources.\n",
        count($classification['types']),
        count($generations['manifest_generations']),
        count($generations['spi_generations']),
        count($pin['files']),
    ),
);

/**
 * @param string $path JSON document path.
 * @param list<string> $errors Failure accumulator.
 *
 * @return array<string, mixed> Decoded object, empty after a failure.
 *
 * @since 0.2.0
 */
function contractObject(string $path, array &$errors): array
{
    $bytes = is_file($path) ? file_get_contents($path) : false;
    if (!is_string($bytes)) {
        $errors[] = 'Required contract document is missing: ' . $path;
        return [];
    }
    try {
        $document = json_decode($bytes, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        $errors[] = 'Contract document is invalid JSON: ' . $path;
        return [];
    }
    if (!is_array($document) || array_is_list($document)) {
        $errors[] = 'Contract document must be an object: ' . $path;
        return [];
    }

    return $document;
}

/**
 * @param string $resources Resource root.
 * @param list<string> $errors Failure accumulator.
 *
 * @since 0.2.0
 */
function verifyNamespacePurity(string $resources, array &$errors): void
{
    if (!is_dir($resources)) {
        $errors[] = 'Resource root is missing.';
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($resources, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }
        if ($file->isLink()) {
            $errors[] = 'A resource is a symbolic link: ' . $file->getPathname();
            continue;
        }
        $bytes = file_get_contents($file->getPathname());
        if (!is_string($bytes)) {
            $errors[] = 'A resource cannot be read: ' . $file->getPathname();
            continue;
        }
        if (containsHistoricalAppNamespace($bytes)) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($resources) + 1));
            $errors[] = 'Historical host namespace is published by resource: ' . $relative;
        }
    }
}

/** @param string $bytes Candidate text. @return bool Whether a collapsed private namespace occurs. @since 0.2.0 */
function containsHistoricalAppNamespace(string $bytes): bool
{
    do {
        $before = $bytes;
        $bytes = str_replace('\\\\', '\\', $bytes);
    } while ($bytes !== $before);

    $historical = implode('\\', ['Kumwe', 'App']) . '\\';

    return str_contains($bytes, $historical);
}

/**
 * @param string $resources Resource root.
 * @param array<string, mixed> $pin Digest record.
 * @param list<string> $errors Failure accumulator.
 *
 * @since 0.2.0
 */
function verifyPin(string $resources, array $pin, array &$errors): void
{
    if (($pin['format'] ?? null) !== 'kumwe-extension-sdk-resource-pin-v2') {
        $errors[] = 'PIN.json does not declare the canonical resource-pin-v2 format.';
        return;
    }
    $entries = $pin['files'] ?? null;
    if (!is_array($entries) || !array_is_list($entries)) {
        $errors[] = 'PIN.json files must be a list.';
        return;
    }
    $pinned = [];
    foreach ($entries as $entry) {
        if (
            !is_array($entry)
            || !is_string($entry['file'] ?? null)
            || !is_string($entry['sha256'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $entry['sha256']) !== 1
            || isset($pinned[$entry['file']])
        ) {
            $errors[] = 'PIN.json carries a malformed or duplicate file entry.';
            continue;
        }
        $pinned[$entry['file']] = $entry['sha256'];
    }
    ksort($pinned, SORT_STRING);

    $present = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($resources, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile() || $file->isLink()) {
            continue;
        }
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($resources) + 1));
        if ($relative === 'PIN.json') {
            continue;
        }
        $present[$relative] = hash_file('sha256', $file->getPathname());
    }
    ksort($present, SORT_STRING);

    foreach ($pinned as $relative => $digest) {
        if (!isset($present[$relative])) {
            $errors[] = 'Pinned canonical resource is missing: ' . $relative;
        } elseif (!hash_equals($digest, $present[$relative])) {
            $errors[] = 'Canonical resource digest mismatch: ' . $relative;
        }
    }
    foreach ($present as $relative => $_digest) {
        if (!isset($pinned[$relative])) {
            $errors[] = 'Unpinned file in the canonical resource tree: ' . $relative;
        }
    }
}

/**
 * @param string $repository Repository root.
 * @param array<string, mixed> $classification Recorded public API.
 * @param list<string> $errors Failure accumulator.
 *
 * @since 0.2.0
 */
function verifyClassification(string $repository, array $classification, array &$errors): void
{
    if (($classification['format'] ?? null) !== 'kumwe-extension-sdk-public-api-v1') {
        $errors[] = 'classification.json does not declare the canonical public-api-v1 format.';
        return;
    }
    $expected = recordPublicApi($repository);
    if ($classification !== $expected) {
        $errors[] = 'classification.json differs from the SDK-owned canonical public API; run tools/record-contract.php.';
    }
    $types = $classification['types'] ?? null;
    if (!is_array($types) || !array_is_list($types)) {
        $errors[] = 'classification.json types must be a list.';
        return;
    }
    $seen = [];
    foreach ($types as $entry) {
        $type = is_array($entry) ? ($entry['type'] ?? null) : null;
        if (!is_string($type) || !str_starts_with($type, 'Kumwe\\Extension\\') || isset($seen[$type])) {
            $errors[] = 'classification.json carries a foreign, malformed or duplicate type.';
            continue;
        }
        $seen[$type] = true;
    }
}

/**
 * @param string $repository Repository root.
 * @param string $resources Resource root under verification.
 * @param array<string, mixed> $generations Recorded generations.
 * @param list<string> $errors Failure accumulator.
 *
 * @since 0.2.0
 */
function verifyGenerations(string $repository, string $resources, array $generations, array &$errors): void
{
    if (($generations['format'] ?? null) !== 'kumwe-extension-sdk-generations-v1') {
        $errors[] = 'generations.json does not declare the canonical generations-v1 format.';
        return;
    }
    $expected = recordGenerations($repository);
    if ($generations !== $expected) {
        $errors[] = 'generations.json differs from canonical SDK fixtures; run tools/record-contract.php.';
    }
    $entries = $generations['manifest_generations'] ?? null;
    if (!is_array($entries) || !array_is_list($entries) || count($entries) !== 6) {
        $errors[] = 'Exactly six canonical manifest generations must be recorded.';
        return;
    }
    foreach ($entries as $offset => $entry) {
        if (!is_array($entry)) {
            $errors[] = 'A manifest generation is malformed.';
            continue;
        }
        $schema = $offset + 1;
        $fixture = $entry['fixture'] ?? null;
        if (
            ($entry['id'] ?? null) !== 'manifest-' . $schema
            || ($entry['schema'] ?? null) !== $schema
            || !is_string($fixture)
            || str_starts_with($fixture, '/')
            || str_contains($fixture, '..')
        ) {
            $errors[] = 'A manifest generation has a malformed identity or resource-relative fixture path.';
            continue;
        }
        $directory = $resources . '/' . $fixture;
        $manifestPath = $directory . '/kumwe.json';
        $manifestBytes = is_file($manifestPath) ? file_get_contents($manifestPath) : false;
        if (!is_string($manifestBytes)) {
            $errors[] = 'A canonical generation fixture has no manifest: ' . $fixture;
            continue;
        }
        try {
            $manifest = decodeObject($manifestBytes, $fixture . '/kumwe.json');
        } catch (Throwable $exception) {
            $errors[] = 'A canonical generation fixture manifest is malformed: ' . $fixture;
            continue;
        }
        if (
            ($manifest['schema'] ?? null) !== $schema
            || ($manifest['name'] ?? null) !== ($entry['identifier'] ?? null)
            || !hash_equals((string) ($entry['manifest_sha256'] ?? ''), hash('sha256', $manifestBytes))
        ) {
            $errors[] = 'A canonical generation fixture no longer matches its record: ' . $fixture;
        }
        $surfaceDigest = $entry['surface_digest'] ?? null;
        $withoutDigest = $entry;
        unset($withoutDigest['surface_digest']);
        if (!is_string($surfaceDigest) || !hash_equals($surfaceDigest, canonicalDigest($withoutDigest))) {
            $errors[] = 'A manifest generation surface digest is invalid: manifest-' . $schema;
        }
    }
    $spiEntries = $generations['spi_generations'] ?? null;
    if (!is_array($spiEntries) || !array_is_list($spiEntries) || count($spiEntries) !== 4) {
        $errors[] = 'Exactly four canonical contribution SPI generations must be recorded.';
        return;
    }
    foreach ($spiEntries as $entry) {
        if (!is_array($entry)) {
            $errors[] = 'A contribution SPI generation is malformed.';
            continue;
        }
        $surfaceDigest = $entry['surface_digest'] ?? null;
        unset($entry['surface_digest']);
        if (!is_string($surfaceDigest) || !hash_equals($surfaceDigest, canonicalDigest($entry))) {
            $errors[] = 'A contribution SPI generation surface digest is invalid.';
        }
    }
}
