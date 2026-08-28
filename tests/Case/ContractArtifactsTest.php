<?php

/**
 * Proves the vendored contract artifacts and their verifier hold the frozen surface.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use Kumwe\Extension\Tests\TestCase;

/**
 * Exercises `tools/verify-contract.php` in both directions over the vendored artifacts.
 *
 * The green path proves a clean clone carries exactly the pinned bytes; the tampered paths prove
 * the verifier fails closed for the drift classes that matter — a changed artifact byte, a widened
 * frozen generation, a file the pin does not know, and a pinned file that went missing.
 *
 * @since  0.1.0
 */
final class ContractArtifactsTest extends TestCase
{
    /**
     * The committed artifacts verify green, and the summary names every promised surface.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testCommittedArtifactsVerify(): void
    {
        [$status, $output] = $this->verify(dirname(__DIR__, 2) . '/resources');

        $this->assertSame(0, $status, 'The committed contract artifacts must verify. ' . $output);
        $this->assertStringContains('6 manifest generations', $output, 'The verifier must count the generations.');
        $this->assertStringContains('4 SPI generations', $output, 'The verifier must count the SPI generations.');
        $this->assertStringContains('122 public types', $output, 'The verifier must count the classified types.');
    }

    /**
     * A changed artifact byte fails the digest sweep and names the drifted file.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testTamperedArtifactByteFailsTheSweep(): void
    {
        $root = $this->copyResources();
        $target = $root . '/fixtures/generations/manifest-1/kumwe.json';
        $bytes = (string) file_get_contents($target);
        file_put_contents($target, $bytes . "\n");

        [$status, $output] = $this->verify($root);

        $this->assertSame(1, $status, 'A tampered artifact must fail verification.');
        $this->assertStringContains(
            'Digest mismatch against the recorded source digest: fixtures/generations/manifest-1/kumwe.json',
            $output,
            'The sweep must name the drifted artifact.',
        );
        $this->removeTree($root);
    }

    /**
     * Widening a frozen generation entry fails the recomputed surface digest.
     *
     * The tampered copy re-pins the document's own file digest, so only the frozen-surface
     * recomputation can catch the widening — which is exactly the check being proven.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testWidenedFrozenGenerationFailsItsSurfaceDigest(): void
    {
        $root = $this->copyResources();
        $path = $root . '/contract/generations.json';
        $document = json_decode((string) file_get_contents($path), true);
        $this->assertTrue(is_array($document), 'The vendored generations document must decode.');
        $document['manifest_generations'][0]['types'][] = 'sneaky-new-type';
        file_put_contents(
            $path,
            json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
        );
        $this->repin($root);

        [$status, $output] = $this->verify($root);

        $this->assertSame(1, $status, 'A widened frozen generation must fail verification.');
        $this->assertStringContains(
            'promises something other than its recorded frozen surface',
            $output,
            'The failure must name the frozen-surface drift.',
        );
        $this->removeTree($root);
    }

    /**
     * A file the pin does not record is refused, so nothing can hide inside the artifact tree.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testUnpinnedFileIsRefused(): void
    {
        $root = $this->copyResources();
        file_put_contents($root . '/fixtures/pins/uninvited.json', "{}\n");

        [$status, $output] = $this->verify($root);

        $this->assertSame(1, $status, 'An unpinned file must fail verification.');
        $this->assertStringContains(
            'Unpinned file in the vendored contract: fixtures/pins/uninvited.json',
            $output,
            'The sweep must name the unpinned file.',
        );
        $this->removeTree($root);
    }

    /**
     * A pinned file that disappears is reported missing rather than silently skipped.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testMissingPinnedArtifactIsReported(): void
    {
        $root = $this->copyResources();
        unlink($root . '/fixtures/pins/extension-event-v1.json');

        [$status, $output] = $this->verify($root);

        $this->assertSame(1, $status, 'A missing pinned artifact must fail verification.');
        $this->assertStringContains(
            'Pinned artifact is missing: fixtures/pins/extension-event-v1.json',
            $output,
            'The sweep must name the missing artifact.',
        );
        $this->removeTree($root);
    }

    /**
     * Run the verifier against one artifact root and capture status plus combined output.
     *
     * @param   string  $root  Absolute artifact root to verify.
     *
     * @return  array{int, string}  Exit status and combined stdout/stderr.
     *
     * @since   0.1.0
     */
    private function verify(string $root): array
    {
        $tool = dirname(__DIR__, 2) . '/tools/verify-contract.php';
        exec(
            'php ' . escapeshellarg($tool) . ' --root=' . escapeshellarg($root) . ' 2>&1',
            $lines,
            $status,
        );

        return [$status, implode("\n", $lines)];
    }

    /**
     * Copy the committed artifacts into a private temporary root a test may tamper with.
     *
     * @return  string  Absolute path of the writable copy.
     *
     * @since   0.1.0
     */
    private function copyResources(): string
    {
        $source = dirname(__DIR__, 2) . '/resources';
        $root = sys_get_temp_dir() . '/kumwe-sdk-contract-' . bin2hex(random_bytes(8));
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($source) + 1);
            $destination = $root . '/' . $relative;
            if (!is_dir(dirname($destination))) {
                mkdir(dirname($destination), 0700, true);
            }
            copy($file->getPathname(), $destination);
        }

        return $root;
    }

    /**
     * Rewrite the copied pin so only the intended check can catch a document tamper.
     *
     * @param   string  $root  Writable artifact copy whose PIN.json is regenerated.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    private function repin(string $root): void
    {
        $pin = json_decode((string) file_get_contents($root . '/PIN.json'), true);
        $this->assertTrue(is_array($pin), 'The copied pin must decode.');
        foreach ($pin['files'] as $index => $entry) {
            $pin['files'][$index]['sha256'] = hash_file('sha256', $root . '/' . $entry['file']);
        }
        file_put_contents($root . '/PIN.json', json_encode($pin, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }

    /**
     * Remove one private temporary tree created by this test.
     *
     * @param   string  $root  Absolute path of the tree to remove.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    private function removeTree(string $root): void
    {
        if (!str_starts_with($root, sys_get_temp_dir() . '/kumwe-sdk-contract-')) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            if (!$entry instanceof \SplFileInfo) {
                continue;
            }
            $entry->isDir() && !$entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($root);
    }
}
