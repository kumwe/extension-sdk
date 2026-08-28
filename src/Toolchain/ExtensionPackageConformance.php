<?php

declare(strict_types=1);

namespace Kumwe\Extension\Toolchain;

use Kumwe\Extension\Package\PackageSafetyPolicy;
use Kumwe\Extension\Package\ZipArchiveReader;

/**
 * Public SDK facade for repeatable code-free extension package conformance.
 *
 * This is the entry point an author's CI installs and calls, and it is fully self-contained: the
 * production defaults wire the same archive reader, safety limits and shared static checks the
 * platform's admission runs, from this package alone — no host application appears anywhere in the
 * dependency tree. Static package conformance runs entirely here; lifecycle conformance is a port,
 * driven through whatever `LifecycleConformanceAdapter` the platform under test supplies.
 *
 * @since  0.1.0
 */
final readonly class ExtensionPackageConformance
{
    /**
     * Bind the facade to a configured core conformance runner.
     *
     * @param  StaticConformanceRunner  $runner  Configured core conformance service.
     *
     * @since  0.1.0
     */
    public function __construct(private StaticConformanceRunner $runner)
    {
    }

    /**
     * Create a facade using the exact archive reader and default limits used by Kumwe installation.
     *
     * @return  self  Ready-to-run conformance facade.
     *
     * @since   0.1.0
     */
    public static function withProductionDefaults(): self
    {
        return new self(new StaticConformanceRunner(new PackageInspector(
            new ZipArchiveReader(),
            new PackageSafetyPolicy(),
        )));
    }

    /**
     * Inspect one canonical absolute package path without executing its code.
     *
     * @param   string  $archiveFile  Canonical absolute extension ZIP path.
     *
     * @return  ConformanceReport  Stable package inventory and all static violations.
     *
     * @since   0.1.0
     */
    public function run(string $archiveFile): ConformanceReport
    {
        return $this->runner->run($archiveFile);
    }

    /**
     * Execute static checks and every platform-backed lifecycle acceptance gate.
     *
     * @param   LifecycleConformanceAdapter  $adapter         Real platform test-environment adapter.
     * @param   string                       $basePackage     Canonical absolute initial package path.
     * @param   string                       $upgradePackage  Canonical absolute upgrade package path.
     *
     * @return  LifecycleConformanceReport  Ordered gate and recovery verdicts.
     *
     * @since   0.1.0
     */
    public function runLifecycle(
        LifecycleConformanceAdapter $adapter,
        string $basePackage,
        string $upgradePackage,
    ): LifecycleConformanceReport {
        return (new LifecycleConformanceRunner($this->runner))->run($adapter, $basePackage, $upgradePackage);
    }
}
