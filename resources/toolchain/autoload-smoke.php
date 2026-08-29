<?php

/**
 * Prove a production-only Composer install can load the complete recorded SDK and scaffold a component.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

use Kumwe\Extension\Toolchain\ComponentScaffolder;
use Kumwe\Extension\Toolchain\ScaffoldRequest;

$root = dirname(__DIR__, 2);
$autoload = $root . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Production autoload smoke requires vendor/autoload.php.\n");
    exit(2);
}
require $autoload;

$profilePath = $root . '/resources/contract/classification.json';
$profileBytes = file_get_contents($profilePath);
if (!is_string($profileBytes)) {
    fwrite(STDERR, "Canonical public API profile is unavailable.\n");
    exit(1);
}
$profile = json_decode($profileBytes, true, 32, JSON_THROW_ON_ERROR);
if (
    !is_array($profile)
    || ($profile['format'] ?? null) !== 'kumwe-extension-sdk-public-api-v1'
    || ($profile['package'] ?? null) !== 'kumwe/extension-sdk'
    || !is_array($profile['types'] ?? null)
    || !array_is_list($profile['types'])
) {
    fwrite(STDERR, "Canonical public API profile is malformed.\n");
    exit(1);
}

$loaded = 0;
foreach ($profile['types'] as $entry) {
    if (!is_array($entry) || !is_string($entry['type'] ?? null) || !is_string($entry['kind'] ?? null)) {
        fwrite(STDERR, "Canonical public API profile contains a malformed type.\n");
        exit(1);
    }
    $type = $entry['type'];
    $exists = match ($entry['kind']) {
        'class' => class_exists($type),
        'enum' => enum_exists($type),
        'interface' => interface_exists($type),
        default => false,
    };
    if (!$exists) {
        fwrite(STDERR, sprintf("Production autoload failed for %s %s.\n", $entry['kind'], $type));
        exit(1);
    }
    ++$loaded;
}

$temporary = sys_get_temp_dir() . '/kumwe-extension-sdk-smoke-' . bin2hex(random_bytes(12));
if (!mkdir($temporary, 0700)) {
    fwrite(STDERR, "Production scaffold smoke could not allocate a private directory.\n");
    exit(1);
}
$target = $temporary . '/component';
try {
    $result = (new ComponentScaffolder())->scaffold(new ScaffoldRequest(
        'smoke/component',
        'Smoke\\Component',
        $target,
        'Smoke Component',
        '1.0.0',
    ));
    if (
        $result->directory !== $target
        || $result->fileCount < 1
        || !is_file($target . '/kumwe.json')
        || !is_file($target . '/src/Provider.php')
    ) {
        throw new RuntimeException('The shipped component scaffold is incomplete.');
    }
} finally {
    removeSmokeTree($temporary);
}

echo sprintf(
    "Production Composer smoke passed: %d canonical types and complete component scaffold.\n",
    $loaded,
);

/**
 * Remove only the private smoke directory allocated by this process.
 *
 * @param string $root Private absolute smoke directory.
 *
 * @since 0.2.0
 */
function removeSmokeTree(string $root): void
{
    if (!is_dir($root) || is_link($root) || !str_starts_with(basename($root), 'kumwe-extension-sdk-smoke-')) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $item) {
        if (!$item instanceof SplFileInfo) {
            continue;
        }
        $path = $item->getPathname();
        if ($item->isDir() && !$item->isLink()) {
            rmdir($path);
        } else {
            unlink($path);
        }
    }
    rmdir($root);
}
