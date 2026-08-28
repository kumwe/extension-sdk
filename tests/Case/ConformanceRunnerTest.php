<?php

/**
 * Proves the conformance runner is self-contained and holds every promised generation.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use Kumwe\Extension\Package\PackageSafetyPolicy;
use Kumwe\Extension\Package\ZipArchiveReader;
use Kumwe\Extension\Tests\TestCase;
use Kumwe\Extension\Toolchain\DeterministicPackageBuilder;
use Kumwe\Extension\Toolchain\ExtensionPackageConformance;
use Kumwe\Extension\Toolchain\PackageInspector;
use Throwable;

/**
 * The E-4 obligation, executed: on a clean clone with no `kumwe/app` anywhere in the dependency
 * tree, the static conformance runner passes over all six vendored generation fixtures and
 * refuses the hostile corpus, and this package requires PHP and extensions only.
 *
 * @since  0.1.0
 */
final class ConformanceRunnerTest extends TestCase
{
    /**
     * The production-default facade passes every promised generation, built from vendored source.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testFacadePassesAllSixVendoredGenerations(): void
    {
        $work = $this->workspace();
        $facade = ExtensionPackageConformance::withProductionDefaults();
        $generations = glob(dirname(__DIR__, 2) . '/resources/fixtures/generations/manifest-*') ?: [];
        $this->assertSame(6, count($generations), 'All six generation fixtures are vendored.');
        foreach ($generations as $source) {
            $archive = $work . '/' . basename($source) . '.zip';
            (new DeterministicPackageBuilder($this->inspector()))->build($source, $archive);
            $report = $facade->run($archive);

            $this->assertTrue(
                $report->conforms(),
                sprintf('%s must conform: %s', basename($source), implode('; ', $report->violations)),
            );
            $this->assertSame(
                'kumwe-extension-conformance-v1',
                $report->toArray()['format'],
                'The report format is the stable one CI consumes.',
            );
        }
        $this->removeTree($work);
    }

    /**
     * The facade refuses every archive of the recorded hostile corpus.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testFacadeRefusesTheHostileCorpus(): void
    {
        $work = $this->workspace();
        $parity = json_decode(
            (string) file_get_contents(dirname(__DIR__) . '/Fixtures/app-parity.json'),
            true,
        );
        $this->assertTrue(is_array($parity), 'The parity evidence must decode.');
        $facade = ExtensionPackageConformance::withProductionDefaults();
        foreach ($parity['hostile'] as $name => $case) {
            $archive = $work . '/' . $name . '.zip';
            file_put_contents($archive, base64_decode($case['zip'], true));
            try {
                $report = $facade->run($archive);
                $this->assertTrue(
                    !$report->conforms(),
                    sprintf('Hostile archive %s must not conform.', $name),
                );
            } catch (Throwable $refusal) {
                $this->assertTrue(true, sprintf('Hostile archive %s was refused outright.', $name));
            }
        }
        $this->removeTree($work);
    }

    /**
     * Nothing in the library references the host application; the dependency arrow points one way.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testNoLibrarySourceReferencesTheHostApplication(): void
    {
        $root = dirname(__DIR__, 2) . '/src';
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );
        $scanned = 0;
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            $scanned++;
            $this->assertStringExcludes(
                'Kumwe\\App',
                (string) file_get_contents($file->getPathname()),
                sprintf('%s must not reference the host application.', $file->getPathname()),
            );
        }
        $this->assertTrue($scanned > 80, 'The whole library was scanned.');
    }

    /**
     * The package requires PHP and extensions only — the pin that made the in-tree SDK
     * unpublishable is dead.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testComposerRequiresPhpAndExtensionsOnly(): void
    {
        $composer = json_decode(
            (string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
            true,
        );
        $this->assertTrue(is_array($composer), 'composer.json must decode.');
        foreach (array_keys($composer['require']) as $requirement) {
            $this->assertTrue(
                $requirement === 'php' || str_starts_with((string) $requirement, 'ext-'),
                sprintf('Requirement %s must be PHP or a PHP extension.', (string) $requirement),
            );
        }
        $this->assertTrue(
            !isset($composer['require']['kumwe/app']),
            'The kumwe/app pin must not exist anywhere in the requirements.',
        );
        $this->assertTrue(
            !isset($composer['require-dev']),
            'The check lane is dependency-free; no development requirements exist either.',
        );
    }

    /**
     * Build the production-shaped package inspector.
     *
     * @return  PackageInspector  Inspector over the shipped reader and default safety limits.
     *
     * @since   0.1.0
     */
    private function inspector(): PackageInspector
    {
        return new PackageInspector(new ZipArchiveReader(), new PackageSafetyPolicy());
    }

    /**
     * Allocate a private working directory for one test.
     *
     * @return  string  Absolute path of the writable directory.
     *
     * @since   0.1.0
     */
    private function workspace(): string
    {
        $work = sys_get_temp_dir() . '/kumwe-sdk-conformance-' . bin2hex(random_bytes(8));
        mkdir($work, 0700);

        return $work;
    }

    /**
     * Remove one private working directory created by this test.
     *
     * @param   string  $root  Absolute path of the tree to remove.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    private function removeTree(string $root): void
    {
        if (!str_starts_with($root, sys_get_temp_dir() . '/kumwe-sdk-conformance-')) {
            return;
        }
        foreach (glob($root . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($root);
    }
}
