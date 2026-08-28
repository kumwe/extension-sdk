<?php

declare(strict_types=1);

namespace Kumwe\Extension\Toolchain;

use PHPUnit\Framework\TestCase;

/**
 * Reusable PHPUnit contract that executes the complete Kumwe extension acceptance lifecycle.
 *
 * The SDK never drives a live platform itself: a consuming platform's suite extends this class and
 * supplies the adapter backed by its own real deployment, which is where install, activate,
 * upgrade, disable, reactivate and uninstall actually run. Like its static sibling, the bridge
 * activates only where `phpunit/phpunit` is already installed by the consuming suite.
 *
 * @since  0.1.0
 */
abstract class ExtensionLifecycleTestCase extends TestCase
{
    /**
     * Supply an adapter backed by the product's real test deployment.
     *
     * @return  LifecycleConformanceAdapter  Platform lifecycle adapter.
     *
     * @since   0.1.0
     */
    abstract protected function lifecycleAdapter(): LifecycleConformanceAdapter;

    /**
     * Supply the canonical absolute initial extension package path.
     *
     * @return  string  Initial extension package path.
     *
     * @since   0.1.0
     */
    abstract protected function basePackage(): string;

    /**
     * Supply the canonical absolute compatible-upgrade package path.
     *
     * @return  string  Upgrade extension package path.
     *
     * @since   0.1.0
     */
    abstract protected function upgradePackage(): string;

    /**
     * Run every static and stateful conformance gate in the defined order.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    final public function testExtensionLifecycleConformance(): void
    {
        $report = ExtensionPackageConformance::withProductionDefaults()->runLifecycle(
            $this->lifecycleAdapter(),
            $this->basePackage(),
            $this->upgradePackage(),
        );

        self::assertTrue($report->conforms(), implode("\n", $report->violations));
    }
}
