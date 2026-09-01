<?php

/**
 * Proves canonical SDK contract records match source, fixtures and shipped resources.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use Kumwe\Extension\Tests\TestCase;

/** @since 0.2.0 */
final class ContractArtifactsTest extends TestCase
{
    /** @since 0.2.0 */
    public function testCommittedCanonicalArtifactsVerify(): void
    {
        [$status, $output] = $this->verify(dirname(__DIR__, 2) . '/resources');

        $this->assertSame(0, $status, 'The canonical SDK artifacts must verify. ' . $output);
        $this->assertStringContains('Canonical SDK contract verified:', $output, 'The verifier identifies its authority.');
        $this->assertStringContains('6 manifest generations', $output, 'All manifest generations are recorded.');
        $this->assertStringContains('4 SPI generations', $output, 'All contribution SPI generations are recorded.');
    }

    /** @since 0.2.0 */
    public function testTamperedMissingAndUnpinnedResourcesFailClosed(): void
    {
        $tampered = $this->copyResources();
        $manifest = $tampered . '/fixtures/generations/manifest-1/kumwe.json';
        file_put_contents($manifest, (string) file_get_contents($manifest) . "\n");
        [$status, $output] = $this->verify($tampered);
        $this->assertSame(1, $status, 'A changed canonical resource must fail.');
        $this->assertStringContains(
            'Canonical resource digest mismatch: fixtures/generations/manifest-1/kumwe.json',
            $output,
            'The changed resource is named.',
        );
        $this->removeTree($tampered);

        $missing = $this->copyResources();
        unlink($missing . '/fixtures/generations/manifest-1/src/Greeting.php');
        [$status, $output] = $this->verify($missing);
        $this->assertSame(1, $status, 'A missing canonical resource must fail.');
        $this->assertStringContains(
            'Pinned canonical resource is missing: fixtures/generations/manifest-1/src/Greeting.php',
            $output,
            'The missing resource is named.',
        );
        $this->removeTree($missing);

        $unpinned = $this->copyResources();
        file_put_contents($unpinned . '/fixtures/generations/uninvited.php', "<?php\n");
        [$status, $output] = $this->verify($unpinned);
        $this->assertSame(1, $status, 'An unpinned canonical resource must fail.');
        $this->assertStringContains(
            'Unpinned file in the canonical resource tree: fixtures/generations/uninvited.php',
            $output,
            'The unpinned resource is named.',
        );
        $this->removeTree($unpinned);
    }

    /** @since 0.2.0 */
    public function testRepinnedHostNamespaceAndApiDriftStillFail(): void
    {
        $hostCoupling = $this->copyResources();
        $readme = $hostCoupling . '/contract/README.md';
        $historical = implode('\\', ['Kumwe', 'App', 'Forbidden']);
        file_put_contents($readme, (string) file_get_contents($readme) . "\n{$historical}\n");
        $this->repin($hostCoupling, 'contract/README.md');
        [$status, $output] = $this->verify($hostCoupling);
        $this->assertSame(1, $status, 'A repinned private host namespace must still fail.');
        $this->assertStringContains(
            'Historical host namespace is published by resource: contract/README.md',
            $output,
            'Namespace purity is independent of resource digests.',
        );
        $this->removeTree($hostCoupling);

        $apiDrift = $this->copyResources();
        $classificationPath = $apiDrift . '/contract/classification.json';
        $classification = json_decode((string) file_get_contents($classificationPath), true, 64, JSON_THROW_ON_ERROR);
        $this->assertTrue(is_array($classification), 'The public API record decodes.');
        array_pop($classification['types']);
        file_put_contents(
            $classificationPath,
            json_encode($classification, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
        );
        $this->repin($apiDrift, 'contract/classification.json');
        [$status, $output] = $this->verify($apiDrift);
        $this->assertSame(1, $status, 'A repinned narrowed API record must still fail.');
        $this->assertStringContains(
            'classification.json differs from the SDK-owned canonical public API',
            $output,
            'Source type membership is the API authority.',
        );
        $this->removeTree($apiDrift);
    }

    /** @since 0.2.0 */
    public function testRepinnedGenerationDriftStillFails(): void
    {
        $root = $this->copyResources();
        $path = $root . '/contract/generations.json';
        $document = json_decode((string) file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
        $this->assertTrue(is_array($document), 'The generation record decodes.');
        $document['manifest_generations'][0]['executable_bindings']['job_handler'] = ['kumwe.hidden.job'];
        file_put_contents(
            $path,
            json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
        );
        $this->repin($root, 'contract/generations.json');

        [$status, $output] = $this->verify($root);
        $this->assertSame(1, $status, 'A repinned generation mutation must fail.');
        $this->assertStringContains(
            'generations.json differs from canonical SDK fixtures',
            $output,
            'The fixture-derived generation authority catches the mutation.',
        );
        $this->removeTree($root);
    }

    /** @return array{int, string} Process status and combined output. @since 0.2.0 */
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

    /** @return string Writable resource copy. @since 0.2.0 */
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

    /** @since 0.2.0 */
    private function repin(string $root, string $relative): void
    {
        $path = $root . '/PIN.json';
        $pin = json_decode((string) file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
        $this->assertTrue(is_array($pin), 'The resource pin decodes.');
        foreach ($pin['files'] as &$entry) {
            if (($entry['file'] ?? null) === $relative) {
                $entry['sha256'] = hash_file('sha256', $root . '/' . $relative);
            }
        }
        unset($entry);
        file_put_contents(
            $path,
            json_encode($pin, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
        );
    }

    /** @since 0.2.0 */
    private function removeTree(string $root): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            if ($entry->isDir()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($root);
    }
}
