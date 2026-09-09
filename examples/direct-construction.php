<?php

/** Compose per-operation SDK tools from a caller-selected canonical encoder. @since 0.3.0 */

declare(strict_types=1);

use Kumwe\CanonicalJson\CanonicalEncoder;
use Kumwe\Extension\Package\PackageLimits;
use Kumwe\Extension\Toolchain\ComponentScaffolder;
use Kumwe\Extension\Toolchain\DeterministicPackageBuilder;
use Kumwe\Extension\Toolchain\PackageInspector;
use Kumwe\Extension\Toolchain\StaticConformanceRunner;

require $argv[1] ?? dirname(__DIR__) . '/vendor/autoload.php';

$limits = new PackageLimits();

// The host/CLI owns the encoder implementation and its compatibility verification.
// Calling this factory creates tools; it does not read, write, sign or admit a package.
return static function (CanonicalEncoder $encoder) use ($limits): array {
    $inspector = new PackageInspector($encoder, $limits);

    return [
        'inspector' => $inspector,
        'scaffolder' => new ComponentScaffolder($encoder),
        'builder' => new DeterministicPackageBuilder($encoder, $inspector),
        'conformance' => new StaticConformanceRunner($inspector),
    ];
};
