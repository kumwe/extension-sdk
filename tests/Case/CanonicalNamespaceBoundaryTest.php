<?php

/**
 * Proves committed SDK source, fixtures, records and documentation publish only canonical namespaces.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use Kumwe\Extension\Tests\TestCase;

/** @since 0.2.0 */
final class CanonicalNamespaceBoundaryTest extends TestCase
{
    /** @since 0.2.0 */
    public function testCommittedSdkPublishesNoHistoricalHostNamespace(): void
    {
        $root = dirname(__DIR__, 2);
        exec('git -C ' . escapeshellarg($root) . ' ls-files', $files, $status);
        $this->assertSame(0, $status, 'Committed SDK files must be enumerable.');
        $needle = 'Kumwe' . '\\' . 'App' . '\\';
        $violations = [];
        foreach ($files as $relative) {
            if (preg_match('/\.(?:php|md|json|tpl|xml|ya?ml)$/D', $relative) !== 1) {
                continue;
            }
            $bytes = file_get_contents($root . '/' . $relative);
            if (!is_string($bytes)) {
                $violations[] = $relative . ' could not be read';
                continue;
            }
            do {
                $before = $bytes;
                $bytes = str_replace('\\\\', '\\', $bytes);
            } while ($bytes !== $before);
            if (str_contains($bytes, $needle)) {
                $violations[] = $relative;
            }
        }

        $this->assertSame(
            [],
            $violations,
            'Committed SDK files must not publish a historical host namespace.',
        );
    }
}
