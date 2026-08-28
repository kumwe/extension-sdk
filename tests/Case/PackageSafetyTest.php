<?php

/**
 * Proves the archive safety gate and the streaming content reader keep the App's contract.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use InvalidArgumentException;
use Kumwe\Extension\Package\ArchiveEntry;
use Kumwe\Extension\Package\ArchiveEntryType;
use Kumwe\Extension\Package\ArchivePackage;
use Kumwe\Extension\Package\PackagePath;
use Kumwe\Extension\Package\PackageSafetyPolicy;
use Kumwe\Extension\Package\UnsafePackage;
use Kumwe\Extension\Package\ZipArchiveContentReader;
use Kumwe\Extension\Tests\TestCase;
use ZipArchive;

/**
 * Assertions carried over from the App's safety-policy and ZIP content-reader suites.
 *
 * The safety policy is judged from the entry table alone; the content reader expands one bounded
 * entry at a time. Both are security surfaces, so the refusal paths are first-class here.
 *
 * @since  0.1.0
 */
final class PackageSafetyTest extends TestCase
{
    /**
     * A unique, bounded package with a root manifest passes the gate.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testAcceptsAUniqueBoundedPackageWithRootManifest(): void
    {
        $package = new ArchivePackage([
            $this->file('kumwe.json', 50, 100),
            $this->file('src/Provider.php', 100, 200),
        ]);

        (new PackageSafetyPolicy())->assertSafe($package);
        $this->assertTrue(true, 'A safe package returns without a refusal.');
    }

    /**
     * Links, case-colliding paths, and compression bombs are refused from the entry table alone.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testRejectsLinksDuplicatePathsAndCompressionBombs(): void
    {
        $unsafePackages = [
            'symbolic link' => new ArchivePackage([
                $this->file('kumwe.json', 50, 100),
                new ArchiveEntry(PackagePath::fromString('link'), ArchiveEntryType::SymbolicLink, 1, 1),
            ]),
            'case collision' => new ArchivePackage([
                $this->file('kumwe.json', 50, 100),
                $this->file('KUMWE.JSON', 50, 100),
            ]),
            'compression bomb' => new ArchivePackage([
                $this->file('kumwe.json', 1, 101),
            ]),
        ];

        foreach ($unsafePackages as $label => $package) {
            $this->assertThrows(
                static fn () => (new PackageSafetyPolicy())->assertSafe($package),
                UnsafePackage::class,
                sprintf('An archive with a %s must be refused.', $label),
            );
        }
    }

    /**
     * A directory entry claiming payload bytes is an impossible claim and refuses construction.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testRejectsImpossibleArchiveEntrySizes(): void
    {
        $this->assertThrows(
            static fn (): ArchiveEntry => new ArchiveEntry(
                PackagePath::fromString('directory'),
                ArchiveEntryType::Directory,
                1,
                0,
            ),
            InvalidArgumentException::class,
            'A directory with payload bytes must be refused.',
        );
    }

    /**
     * Retaining every expanded entry costs the entry bytes rather than the per-entry ceiling.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testRetainingEveryEntryCostsTheEntryBytesRatherThanTheCeiling(): void
    {
        $work = $this->workspace();
        $archive = $this->archive($work, [
            'kumwe.sbom.json' => str_repeat('s', 4_096),
            'kumwe.provenance.json' => str_repeat('p', 4_096),
            'a.php' => '<?php',
            'b.php' => '<?php',
            'c.php' => '<?php',
            'd.php' => '<?php',
            'e.php' => '<?php',
        ]);

        $before = memory_get_usage(true);
        $retained = [];
        foreach ((new ZipArchiveContentReader())->contents($archive) as $path => $entry) {
            $retained[$path] = $entry;
        }
        $growth = memory_get_usage(true) - $before;

        $this->assertSame(7, count($retained), 'Every regular entry is yielded.');
        $this->assertTrue(
            $growth < 8 * 1024 * 1024,
            sprintf('Retaining seven small entries must stay small in memory, grew %d bytes.', $growth),
        );
        $this->removeTree($work);
    }

    /**
     * An entry longer than one read chunk is reassembled exactly, in central-directory order.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testEntriesSpanningManyChunksAreReassembledByteForByte(): void
    {
        $work = $this->workspace();
        $long = random_bytes(700_000);
        $archive = $this->archive($work, [
            'first.txt' => 'first',
            'long.bin' => $long,
            'empty.txt' => '',
        ]);

        $read = [];
        foreach ((new ZipArchiveContentReader())->contents($archive) as $path => $entry) {
            $read[$path] = $entry;
        }

        $this->assertSame(['first.txt', 'long.bin', 'empty.txt'], array_keys($read), 'Order is the directory order.');
        $this->assertSame($long, $read['long.bin'], 'A multi-chunk entry reassembles byte for byte.');
        $this->assertSame('', $read['empty.txt'], 'An empty entry yields empty bytes.');
        $this->removeTree($work);
    }

    /**
     * Directory entries are skipped rather than yielded as empty files.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testDirectoryEntriesAreSkipped(): void
    {
        $work = $this->workspace();
        $archive = $work . '/dirs.zip';
        $zip = new ZipArchive();
        $zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addEmptyDir('src');
        $zip->addFromString('src/Thing.php', '<?php');
        $zip->close();

        $read = iterator_to_array((new ZipArchiveContentReader())->contents($archive));

        $this->assertSame(['src/Thing.php'], array_keys($read), 'Only the regular file entry is yielded.');
        $this->removeTree($work);
    }

    /**
     * A file that is not a ZIP archive is refused before anything is expanded.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testANonArchiveIsRefused(): void
    {
        $work = $this->workspace();
        $path = $work . '/not-a-zip.bin';
        file_put_contents($path, 'plainly not a zip archive');

        $this->assertThrows(
            static fn (): array => iterator_to_array((new ZipArchiveContentReader())->contents($path)),
            InvalidArgumentException::class,
            'A non-archive must be refused before expansion.',
        );
        $this->removeTree($work);
    }

    /**
     * Build one regular-file entry for a synthetic archive description.
     *
     * @param   string  $path          Package path of the entry.
     * @param   int     $compressed    Stored size the header claims.
     * @param   int     $uncompressed  Expanded size the header claims.
     *
     * @return  ArchiveEntry  The described entry.
     *
     * @since   0.1.0
     */
    private function file(string $path, int $compressed, int $uncompressed): ArchiveEntry
    {
        return new ArchiveEntry(
            PackagePath::fromString($path),
            ArchiveEntryType::File,
            $compressed,
            $uncompressed,
        );
    }

    /**
     * Build a ZIP archive holding the supplied entries.
     *
     * @param   string                 $work     Private directory to write into.
     * @param   array<string, string>  $entries  Entry bytes keyed by package path.
     *
     * @return  string  Absolute path of the written archive.
     *
     * @since   0.1.0
     */
    private function archive(string $work, array $entries): string
    {
        $path = $work . '/package.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($entries as $name => $bytes) {
            $zip->addFromString($name, $bytes);
        }
        $zip->close();

        return $path;
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
        $work = sys_get_temp_dir() . '/kumwe-sdk-safety-' . bin2hex(random_bytes(8));
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
        if (!str_starts_with($root, sys_get_temp_dir() . '/kumwe-sdk-safety-')) {
            return;
        }
        foreach (glob($root . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($root);
    }
}
