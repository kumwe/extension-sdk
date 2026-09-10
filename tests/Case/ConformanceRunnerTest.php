<?php

/**
 * Proves the conformance runner is independently reusable and holds every promised generation.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use Kumwe\Extension\Package\PackageFinding;
use Kumwe\Extension\Package\PackageLimits;
use Kumwe\Extension\Tests\TestCase;
use Kumwe\Extension\Toolchain\DeterministicPackageBuilder;
use Kumwe\Extension\Toolchain\ExtensionPackageConformance;
use Kumwe\Extension\Toolchain\PackageInspector;
use ZipArchive;

/**
 * With canonical Composer dependencies installed, the static conformance runner passes over all
 * six SDK-owned generations and reports hostile packages without a host implementation.
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
        $facade = ExtensionPackageConformance::withProductionDefaults(self::encoder());
        $generations = glob(dirname(__DIR__, 2) . '/resources/fixtures/generations/manifest-*') ?: [];
        $this->assertSame(6, count($generations), 'All six generation fixtures are vendored.');
        foreach ($generations as $source) {
            $archive = $work . '/' . basename($source) . '.zip';
            (new DeterministicPackageBuilder(self::encoder(), $this->inspector()))->build($source, $archive);
            $report = $facade->run($archive);

            $this->assertTrue(
                $report->conforms(),
                sprintf('%s must conform: %s', basename($source), $this->messages($report->findings)),
            );
            $this->assertSame(
                'kumwe-extension-conformance-v2',
                $report->toArray()['format'],
                'The report format is the stable one CI consumes.',
            );
        }
        $this->removeTree($work);
    }

    /**
     * The author-facing facade reports every locally generated hostile direction as nonconforming.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testFacadeReportsTheHostileCorpusAsNonconforming(): void
    {
        $work = $this->workspace();
        $archives = $this->hostileArchives($work);
        $facade = ExtensionPackageConformance::withProductionDefaults(self::encoder());
        foreach ($archives as $name => $archive) {
            $report = $facade->run($archive);
            $this->assertTrue(
                !$report->conforms(),
                sprintf('Hostile archive %s must not conform.', $name),
            );
            $this->assertTrue($report->findings !== [], sprintf('%s must produce a coded finding.', $name));
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
                implode('\\', ['Kumwe', 'App']),
                (string) file_get_contents($file->getPathname()),
                sprintf('%s must not reference the host application.', $file->getPathname()),
            );
        }
        $this->assertTrue($scanned > 80, 'The whole library was scanned.');
    }

    /**
     * Canonical library dependencies are explicit and no host implementation is required.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testComposerDeclaresCanonicalLibraryDependenciesOnly(): void
    {
        $composer = json_decode(
            (string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
            true,
        );
        $this->assertTrue(is_array($composer), 'composer.json must decode.');
        $requirements = $composer['require'] ?? null;
        $this->assertTrue(is_array($requirements), 'Runtime requirements must be an object.');
        $this->assertSame('0.1.5', $requirements['kumwe/conversion'] ?? null,
            'Conversion matches the coherent released transitive dependency graph.');
        $this->assertSame('0.3.0', $requirements['kumwe/producer'] ?? null, 'Producer schemas use the selected stable release.');
        $this->assertTrue(
            !isset($requirements['kumwe/' . 'app']),
            'A host application must not be a runtime dependency.',
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
        return new PackageInspector(self::encoder(), new PackageLimits());
    }

    /**
     * Generate hostile archives without treating any host implementation as an oracle.
     *
     * @param   string  $work  Private test directory.
     *
     * @return  array<string, string>  Hostile direction to archive path.
     *
     * @since   0.2.0
     */
    private function hostileArchives(string $work): array
    {
        $manifest = (string) file_get_contents(
            dirname(__DIR__, 2) . '/resources/fixtures/generations/manifest-1/kumwe.json',
        );
        $cases = [
            'path-traversal' => ['kumwe.json' => $manifest, '../escape.php' => '<?php'],
            'case-collision' => ['kumwe.json' => $manifest, 'A.php' => '<?php', 'a.php' => '<?php'],
        ];
        $archives = [];
        foreach ($cases as $name => $entries) {
            $archive = $work . '/' . $name . '.zip';
            $zip = new ZipArchive();
            $this->assertTrue(
                $zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true,
                sprintf('%s ZIP opens.', $name),
            );
            foreach ($entries as $path => $bytes) {
                $this->assertTrue($zip->addFromString($path, $bytes), sprintf('%s adds %s.', $name, $path));
            }
            $this->assertTrue($zip->close(), sprintf('%s ZIP closes.', $name));
            $archives[$name] = $archive;
        }

        return $archives;
    }

    /**
     * Flatten typed findings for test diagnostics.
     *
     * @param   list<PackageFinding>  $findings  Neutral coded findings.
     *
     * @return  string  Finding messages in order.
     *
     * @since   0.2.0
     */
    private function messages(array $findings): string
    {
        return implode('; ', array_map(
            static fn (PackageFinding $finding): string => $finding->message,
            $findings,
        ));
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
