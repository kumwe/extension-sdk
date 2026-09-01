<?php

declare(strict_types=1);

namespace Kumwe\Extension\Toolchain;

use Kumwe\Extension\Package\PackageFinding;
use PHPUnit\Framework\TestCase;

/**
 * PHPUnit base class providing one assertion for installable extension fixtures.
 *
 * This bridge activates only where an author's own suite already installs `phpunit/phpunit`
 * (suggested, deliberately never required — the SDK's own check lane stays dependency-free); the
 * class is simply never autoloaded otherwise. The assertion runs the SDK's self-contained author
 * conformance over neutral package findings.
 *
 * @since  0.1.0
 */
abstract class ExtensionConformanceTestCase extends TestCase
{
    /**
     * Assert that an absolute package path passes every static conformance check.
     *
     * @param   string  $archiveFile  Canonical absolute extension ZIP path.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    final protected function assertExtensionPackageConforms(string $archiveFile): void
    {
        $report = ExtensionPackageConformance::withProductionDefaults()->run($archiveFile);

        self::assertTrue($report->conforms(), implode("\n", array_map(
            static fn (PackageFinding $finding): string => $finding->message,
            $report->findings,
        )));
    }
}
