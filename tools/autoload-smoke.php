<?php

/**
 * Prove the production Composer install can load representative SDK entry points.
 *
 * The release workflows invoke this after `composer install --no-dev`. PHPUnit
 * bridge classes are intentionally not loaded: PHPUnit remains a development
 * dependency for SDK analysis and an optional dependency for consumers that use
 * those bridges.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

use Kumwe\Extension\Manifest\ExtensionManifest;
use Kumwe\Extension\Package\PackageLimits;
use Kumwe\Idempotency\IdempotencyKey;
use Kumwe\BusinessSurface\Contract\Presentation\Field\FieldPresentationConfiguration;
use Kumwe\Extension\Toolchain\PackageInspector;

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Production autoload smoke requires vendor/autoload.php.\n");
    exit(2);
}
require $autoload;

$types = [
    ExtensionManifest::class,
    PackageLimits::class,
    IdempotencyKey::class,
    FieldPresentationConfiguration::class,
    PackageInspector::class,
];
foreach ($types as $type) {
    if (!class_exists($type)) {
        fwrite(STDERR, sprintf("Production autoload failed for %s.\n", $type));
        exit(1);
    }
}

echo "Production Composer autoload smoke passed.\n";
