<?php

/**
 * Proves committed SDK source, fixtures, records and documentation publish only canonical namespaces.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use Kumwe\Extension\Tests\TestCase;

/**
 * Sweeps every committed text file for the collapsed historical host namespace.
 *
 * Exactly three committed adoption records are exempt — `docs/app-agreement.md`,
 * `docs/migration-map.json` and `tools/generate-migration-map.php` — because they record the
 * historical-to-canonical adoption for the App: their whole purpose is to name historical FQCNs
 * next to their canonical replacements, and they are neither shipped runtime nor release
 * authority. Everything else committed — source, resources, tools, tests, workflows — must stay
 * canonical.
 *
 * @since  0.2.0
 */
final class CanonicalNamespaceBoundaryTest extends TestCase
{
    /**
     * No committed text file outside the three adoption records carries the historical namespace.
     *
     * Doubled backslashes are collapsed before matching so the escaped forms inside JSON strings
     * and PHP string literals cannot hide the historical prefix.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function testCommittedSdkPublishesNoHistoricalHostNamespace(): void
    {
        $root = dirname(__DIR__, 2);
        exec('git -C ' . escapeshellarg($root) . ' ls-files', $files, $status);
        $this->assertSame(0, $status, 'Committed SDK files must be enumerable.');
        $needle = 'Kumwe' . '\\' . 'App' . '\\';
        $exempt = [
            'docs/app-agreement.md',
            'docs/migration-map.json',
            'tools/generate-migration-map.php',
        ];
        $violations = [];
        foreach ($files as $relative) {
            if (in_array($relative, $exempt, true)) {
                continue;
            }
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
            'Committed SDK files outside the recorded adoption exemptions must not publish a historical host namespace.',
        );
    }
}
