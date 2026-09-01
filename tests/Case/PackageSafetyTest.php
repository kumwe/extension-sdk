<?php

/**
 * Proves neutral archive findings and snapshot-bound expansion fail closed on hostile ZIP metadata.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use InvalidArgumentException;
use Kumwe\Extension\Package\ArchiveEntry;
use Kumwe\Extension\Package\ArchiveEntryType;
use Kumwe\Extension\Package\ArchivePackage;
use Kumwe\Extension\Package\InspectedPackage;
use Kumwe\Extension\Package\InvalidPackage;
use Kumwe\Extension\Package\PackageBillOfMaterials;
use Kumwe\Extension\Package\PackageLimits;
use Kumwe\Extension\Package\PackagePath;
use Kumwe\Extension\Package\PackageProvenance;
use Kumwe\Extension\Package\PackageFinding;
use Kumwe\Extension\Package\PackageSafetyInspector;
use Kumwe\Extension\Package\ZipArchiveContentReader;
use Kumwe\Extension\Package\ZipArchiveReader;
use Kumwe\Extension\Tests\TestCase;
use Kumwe\Extension\Toolchain\DeterministicPackageBuilder;
use Kumwe\Extension\Toolchain\PackageInspector;
use RuntimeException;
use LogicException;
use ZipArchive;

/**
 * Exercises every archive direction before extraction or execution.
 *
 * @since  0.2.0
 */
final class PackageSafetyTest extends TestCase
{
    /**
     * Unsafe archive metadata is returned as stable facts without an SDK admission outcome.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function testSafetyInspectorReturnsCodedFindings(): void
    {
        $package = new ArchivePackage([
            $this->file('kumwe.json', 10, 10),
            new ArchiveEntry(PackagePath::fromString('link'), ArchiveEntryType::SymbolicLink, 1, 1),
            $this->file('A', 1, 1),
            $this->file('a', 1, 1),
            $this->file('parent', 1, 1),
            $this->file('parent/child', 1, 1),
            new ArchiveEntry(PackagePath::fromString('secret.bin'), ArchiveEntryType::File, 1, 1, true),
            $this->file(
                PackageBillOfMaterials::PATH,
                PackageBillOfMaterials::MAXIMUM_BYTES + 1,
                PackageBillOfMaterials::MAXIMUM_BYTES + 1,
            ),
            $this->file(
                PackageProvenance::PATH,
                PackageProvenance::MAXIMUM_BYTES + 1,
                PackageProvenance::MAXIMUM_BYTES + 1,
            ),
        ]);

        $findings = (new PackageSafetyInspector())->findings($package, new PackageLimits());
        $codes = array_map(static fn (PackageFinding $finding): string => $finding->code, $findings);

        $this->assertTrue(in_array('archive.entry.symbolic_link', $codes, true), 'A link is observable.');
        $this->assertTrue(in_array('archive.entry.encrypted', $codes, true), 'Encryption is observable.');
        $this->assertTrue(in_array('archive.path.collision', $codes, true), 'A case collision is observable.');
        $this->assertTrue(in_array('archive.path.file_ancestor', $codes, true), 'A file-parent conflict is observable.');
        $this->assertTrue(
            in_array('attestation.sbom.expanded_limit', $codes, true),
            'The inventory-specific expansion cap is observable before content reads.',
        );
        $this->assertTrue(
            in_array('attestation.provenance.expanded_limit', $codes, true),
            'The provenance-specific expansion cap is observable before content reads.',
        );
    }

    /**
     * The package path profile rejects traversal, device names, ambiguous punctuation and Unicode aliases.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function testPackagePathsUseOnePortableProfile(): void
    {
        foreach (['../escape', 'NUL.txt', 'trailing.', 'has space.txt', "caf\xC3\xA9.txt"] as $path) {
            $this->assertThrows(
                static fn (): PackagePath => PackagePath::fromString($path),
                InvalidArgumentException::class,
                sprintf('Unsafe path %s must be refused.', $path),
            );
        }
        $this->assertSame(
            'src/Provider.php',
            PackagePath::fromString('src/Provider.php')->value(),
            'Portable source paths survive unchanged.',
        );
    }

    /**
     * The staged archive cap is checked before package bytes are hashed or opened as ZIP data.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function testArchiveFileLimitProducesANeutralFinding(): void
    {
        $work = $this->workspace();
        $archive = $this->archive($work, ['kumwe.json' => $this->manifest()]);
        $failure = $this->assertThrows(
            static fn (): InspectedPackage => InspectedPackage::inspect(
                $archive,
                new PackageLimits(maximumArchiveBytes: 64),
            ),
            InvalidPackage::class,
            'An oversized staged ZIP must be described before hashing or central-directory reads.',
        );
        $this->assertSame(
            'archive.file.limit',
            $failure instanceof InvalidPackage ? $failure->finding->code : '',
            'The refusal is exposed as a neutral stable finding.',
        );
        $this->removeTree($work);
    }

    /**
     * Every later digest refuses an oversized or non-regular replacement before following its bytes.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function testSnapshotIdentityRechecksCurrentFileTypeAndArchiveLimit(): void
    {
        $work = $this->workspace();
        $archive = $this->archive($work, ['kumwe.json' => $this->manifest()]);
        $archiveBytes = filesize($archive);
        $this->assertTrue(is_int($archiveBytes), 'The staged archive has a measurable size.');
        $package = InspectedPackage::inspect(
            $archive,
            new PackageLimits(maximumArchiveBytes: is_int($archiveBytes) ? $archiveBytes : 1),
        );
        file_put_contents($archive, 'x', FILE_APPEND | LOCK_EX);
        $this->assertThrows(
            static fn (): null => $package->assertCurrentArchiveIdentity(),
            RuntimeException::class,
            'A digest read must refuse bytes beyond the snapshot archive cap.',
        );

        $symlinkWork = $this->workspace();
        $symlinkArchive = $this->archive($symlinkWork, ['kumwe.json' => $this->manifest()]);
        $symlinkPackage = InspectedPackage::inspect($symlinkArchive);
        $target = $symlinkWork . '/retained.zip';
        $this->assertTrue(rename($symlinkArchive, $target), 'The original file is retained under a private path.');
        $this->assertTrue(symlink($target, $symlinkArchive), 'A link replaces the inspected pathname.');
        $this->assertThrows(
            static fn (): null => $symlinkPackage->assertCurrentArchiveIdentity(),
            RuntimeException::class,
            'A digest read must reject a symlink even when its target has identical bytes.',
        );

        $this->removeTree($work);
        $this->removeTree($symlinkWork);
    }

    /**
     * A trailing-slash entry with payload is malformed data, not a zero-byte directory.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function testReaderDoesNotEraseDirectoryPayloadSizes(): void
    {
        $work = $this->workspace();
        $archive = $this->archive($work, ['payloadX' => 'not-a-directory']);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($archive) === true, 'The hostile ZIP reopens for mode mutation.');
        $this->assertTrue(
            $zip->setExternalAttributesName('payloadX', ZipArchive::OPSYS_UNIX, 0040755 << 16),
            'The entry claims a directory mode while retaining payload.',
        );
        $this->assertTrue($zip->close(), 'The mode-mutated ZIP closes.');
        $bytes = (string) file_get_contents($archive);
        $hostile = str_replace('payloadX', 'payload/', $bytes, $replacements);
        $this->assertSame(2, $replacements, 'Both local and central entry names are made directory-shaped.');
        file_put_contents($archive, $hostile, LOCK_EX);

        $failure = $this->assertThrows(
            static fn (): ArchivePackage => (new ZipArchiveReader())->inspect($archive, new PackageLimits()),
            InvalidPackage::class,
            'A payload-bearing directory record must be reported as malformed.',
        );
        $this->assertSame(
            'archive.directory.payload',
            $failure instanceof InvalidPackage ? $failure->finding->code : '',
            'The finding preserves the hostile direction.',
        );
        $this->removeTree($work);
    }

    /**
     * Expanding a package costs the bytes it holds, not a per-entry ceiling allocation.
     *
     * The reader once asked the archive for the maximum entry size plus one on every entry, so a
     * handful of kilobyte files cost hundreds of mebibytes and took package admission down under a
     * bounded memory limit. Random bytes keep every entry inside the compression-ratio limit, so the
     * snapshot is clean and the reader genuinely expands them.
     *
     * @return  void
     *
     * @since   0.2.4
     */
    public function testReaderGrowsMemoryByTheBytesItExpandsNotAPerEntryCeiling(): void
    {
        $work = $this->workspace();
        $entries = ['kumwe.json' => $this->manifest()];
        for ($index = 0; $index < 7; $index++) {
            $entries[sprintf('blob-%d.bin', $index)] = random_bytes(4_096);
        }
        $archive = $this->archive($work, $entries);
        $package = InspectedPackage::inspect($archive, new PackageLimits());
        $this->assertTrue($package->hasNoSafetyFindings(), 'Random bytes stay inside the compression-ratio limit.');

        $before = memory_get_usage(true);
        $retained = [];
        foreach ((new ZipArchiveContentReader())->contents($package) as $path => $contents) {
            $retained[$path] = $contents;
        }
        $growth = memory_get_usage(true) - $before;

        $this->assertSame(
            array_map(static fn (string $bytes): int => strlen($bytes), $entries),
            array_map(static fn (string $bytes): int => strlen($bytes), $retained),
            'Every regular entry expands once, in order, to exactly its inspected size.',
        );
        $this->assertTrue(
            $growth < 8 * 1024 * 1024,
            sprintf(
                'Retaining a manifest and seven random-bytes entries grew memory by %d bytes; the contract is under 8 MiB.'
                    . ' The per-entry ceiling read that once cost 448 MiB here is what took the deployment down.',
                $growth,
            ),
        );
        $this->removeTree($work);
    }

    /**
     * Encryption and Unix special-file modes survive central-directory classification as findings.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function testReaderSurfacesEncryptionAndSpecialTypes(): void
    {
        $work = $this->workspace();
        $archive = $work . '/hostile.zip';
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 'ZIP opens.');
        $this->assertTrue($zip->addFromString('kumwe.json', $this->manifest()), 'Manifest is added.');
        $this->assertTrue($zip->addFromString('fifo', 'x'), 'Special entry is added.');
        $this->assertTrue(
            $zip->setExternalAttributesName('fifo', ZipArchive::OPSYS_UNIX, 0010644 << 16),
            'FIFO mode is recorded.',
        );
        $this->assertTrue($zip->addFromString('secret.txt', 'secret'), 'Encrypted entry is added.');
        $this->assertTrue($zip->addFromString('dos-directory-claim', 'x'), 'DOS attribute entry is added.');
        $this->assertTrue(
            $zip->setExternalAttributesName('dos-directory-claim', ZipArchive::OPSYS_DOS, 0x10),
            'A contradictory DOS directory attribute is recorded.',
        );
        $this->assertTrue($zip->setPassword('test-password'), 'ZIP password is configured.');
        $this->assertTrue(
            $zip->setEncryptionName('secret.txt', ZipArchive::EM_AES_256),
            'Entry encryption is recorded.',
        );
        $this->assertTrue($zip->close(), 'ZIP closes.');

        $limits = new PackageLimits();
        $entries = (new ZipArchiveReader())->inspect($archive, $limits);
        $findings = (new PackageSafetyInspector())->findings($entries, $limits);
        $codes = array_map(static fn (PackageFinding $finding): string => $finding->code, $findings);
        $specialPaths = array_map(
            static fn (PackageFinding $finding): ?string => $finding->code === 'archive.entry.special'
                ? $finding->path
                : null,
            $findings,
        );

        $this->assertTrue(in_array('archive.entry.special', $codes, true), 'The FIFO is not a regular file.');
        $this->assertTrue(
            in_array('dos-directory-claim', $specialPaths, true),
            'A DOS directory bit cannot contradict a file-shaped entry name.',
        );
        $this->assertTrue(in_array('archive.entry.encrypted', $codes, true), 'The encrypted entry is observable.');
        $this->removeTree($work);
    }

    /**
     * Content expansion consumes the inspected checksum and refuses bytes changed afterwards.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function testContentReaderIsBoundToTheInspectedSnapshot(): void
    {
        $work = $this->workspace();
        $archive = $this->archive($work, [
            'README.md' => "# Snapshot\n",
            'kumwe.json' => $this->manifest(),
        ]);
        $inspection = $this->inspector()->inspect($archive);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($archive) === true, 'Inspected ZIP reopens for mutation.');
        $this->assertTrue($zip->addFromString('README.md', "# Replaced\n"), 'Entry is replaced.');
        $this->assertTrue($zip->close(), 'Mutated ZIP closes.');

        $this->assertThrows(
            static fn (): array => iterator_to_array(
                (new ZipArchiveContentReader())->contents($inspection->package),
            ),
            RuntimeException::class,
            'A changed archive must not be read under an old entry table or manifest.',
        );
        $this->removeTree($work);
    }

    /**
     * Callers cannot manufacture a clean snapshot from caller-selected metadata or findings.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function testSnapshotConstructionIsClosedAndUnsafeBytesRemainReported(): void
    {
        $constructor = (new \ReflectionClass(InspectedPackage::class))->getConstructor();
        $this->assertTrue(
            $constructor !== null && $constructor->isPrivate(),
            'The same-bytes snapshot constructor must not be callable by package consumers.',
        );

        $work = $this->workspace();
        $archive = $this->archive($work, [
            'A.php' => "<?php\n",
            'a.php' => "<?php\n",
            'kumwe.json' => $this->manifest(),
        ]);
        $snapshot = InspectedPackage::inspect($archive);
        $this->assertTrue(
            !$snapshot->hasNoSafetyFindings(),
            'The only public factory derives case-collision findings from the archive itself.',
        );
        $codes = array_map(
            static fn (PackageFinding $finding): string => $finding->code,
            $snapshot->safetyFindings,
        );
        $this->assertTrue(
            in_array('archive.path.collision', $codes, true),
            'An unsafe archive cannot be re-described as a clean snapshot.',
        );
        $this->assertThrows(
            static fn (): string => serialize($snapshot),
            LogicException::class,
            'Serialization cannot bypass or outlive the same-bytes inspection boundary.',
        );
        $this->removeTree($work);
    }

    /**
     * Configured limits are carried into expansion rather than replaced by hard-coded defaults.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function testOneLimitObjectControlsInspectionAndExpansion(): void
    {
        $work = $this->workspace();
        $archive = $this->archive($work, [
            'README.md' => str_repeat('x', 200),
            'kumwe.json' => $this->manifest(),
        ]);
        $limits = new PackageLimits(
            maximumEntryBytes: 512,
            maximumExpandedBytes: 1_024,
            maximumCompressedBytes: 1_024,
            maximumArchiveBytes: 8_192,
            maximumManifestBytes: 512,
            maximumBillOfMaterialsBytes: 512,
            maximumProvenanceBytes: 512,
            readChunkBytes: 32,
        );
        $inspection = (new PackageInspector($limits))->inspect($archive);
        $contents = iterator_to_array((new ZipArchiveContentReader())->contents($inspection->package));

        $this->assertSame(32, $inspection->package->limits->readChunkBytes, 'The snapshot retains the exact budget.');
        $this->assertSame(200, strlen($contents['README.md']), 'Expansion succeeds under that same budget.');
        $this->removeTree($work);
    }

    /**
     * Protocol-document readers cannot be configured outside the shared expansion budget.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function testProtocolDocumentLimitsStayInsideEntryAndTotalCaps(): void
    {
        foreach (['manifest', 'sbom', 'provenance'] as $document) {
            $arguments = [
                'maximumEntryBytes' => 512,
                'maximumExpandedBytes' => 1_536,
                'maximumManifestBytes' => 512,
                'maximumBillOfMaterialsBytes' => 512,
                'maximumProvenanceBytes' => 512,
                'readChunkBytes' => 128,
            ];
            $arguments[match ($document) {
                'manifest' => 'maximumManifestBytes',
                'sbom' => 'maximumBillOfMaterialsBytes',
                'provenance' => 'maximumProvenanceBytes',
            }] = 513;
            $this->assertThrows(
                static fn (): PackageLimits => new PackageLimits(...$arguments),
                InvalidArgumentException::class,
                sprintf('The %s cap cannot exceed the shared per-entry ceiling.', $document),
            );
        }
        $this->assertThrows(
            static fn (): PackageLimits => new PackageLimits(
                maximumEntryBytes: 512,
                maximumExpandedBytes: 512,
                maximumManifestBytes: 513,
                maximumBillOfMaterialsBytes: 512,
                maximumProvenanceBytes: 512,
                readChunkBytes: 128,
            ),
            InvalidArgumentException::class,
            'A protocol-document cap cannot exceed the shared total expansion ceiling.',
        );

        $limits = new PackageLimits(
            maximumEntryBytes: 512,
            maximumExpandedBytes: 1_536,
            maximumManifestBytes: 512,
            maximumBillOfMaterialsBytes: 512,
            maximumProvenanceBytes: 512,
            readChunkBytes: 128,
        );
        $this->assertSame(512, $limits->maximumManifestBytes, 'Exact shared-limit boundaries remain valid.');
    }

    /**
     * Generated evidence entries count against both entry and expanded-byte builder ceilings.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function testBuilderReservesAttestationsInsideConfiguredCaps(): void
    {
        $work = $this->workspace();
        $source = $work . '/source';
        mkdir($source, 0700);
        file_put_contents($source . '/kumwe.json', $this->manifest(), LOCK_EX);

        $entryLimits = new PackageLimits(maximumEntries: 3);
        $entryInspector = new PackageInspector($entryLimits);
        $built = (new DeterministicPackageBuilder($entryInspector))->build($source, $work . '/three.zip');
        $this->assertSame(
            3,
            count($built->inspection->package->paths()),
            'One authored manifest and two generated attestations fill the configured ceiling.',
        );
        file_put_contents($source . '/README.md', "# Overflow\n", LOCK_EX);
        $this->assertThrows(
            static fn () => (new DeterministicPackageBuilder($entryInspector))
                ->build($source, $work . '/too-many.zip'),
            RuntimeException::class,
            'A second source file cannot consume an attestation-reserved entry.',
        );

        $byteLimits = new PackageLimits(
            maximumEntryBytes: 4_194_304,
            maximumExpandedBytes: 4_194_304,
        );
        $byteInspector = new PackageInspector($byteLimits);
        $this->assertThrows(
            static fn () => (new DeterministicPackageBuilder($byteInspector))
                ->build($source, $work . '/too-large.zip'),
            RuntimeException::class,
            'The builder reserves the maximum generated evidence bytes inside the total ceiling.',
        );
        $this->removeTree($work);
    }

    /**
     * Build one synthetic regular-file entry.
     *
     * @param   string  $path        Portable package path.
     * @param   int     $compressed  Declared compressed bytes.
     * @param   int     $expanded    Declared expanded bytes.
     *
     * @return  ArchiveEntry  Entry description.
     *
     * @since   0.2.0
     */
    private function file(string $path, int $compressed, int $expanded): ArchiveEntry
    {
        return new ArchiveEntry(
            PackagePath::fromString($path),
            ArchiveEntryType::File,
            $compressed,
            $expanded,
        );
    }

    /**
     * Write a test ZIP from path-to-bytes entries.
     *
     * @param   string                 $work     Private test directory.
     * @param   array<string, string>  $entries  Entry bytes keyed by ZIP path.
     *
     * @return  string  Canonical archive path.
     *
     * @since   0.2.0
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
     * Return a minimal strict schema-one manifest.
     *
     * @return  string  Valid manifest JSON.
     *
     * @since   0.2.0
     */
    private function manifest(): string
    {
        return (string) file_get_contents(
            dirname(__DIR__, 2) . '/resources/fixtures/generations/manifest-1/kumwe.json',
        );
    }

    /**
     * Build the production-neutral package inspector.
     *
     * @return  PackageInspector  Snapshot producer using SDK defaults.
     *
     * @since   0.2.0
     */
    private function inspector(): PackageInspector
    {
        return new PackageInspector(new PackageLimits());
    }

    /**
     * Allocate a private test directory.
     *
     * @return  string  Canonical writable directory.
     *
     * @since   0.2.0
     */
    private function workspace(): string
    {
        $work = sys_get_temp_dir() . '/kumwe-sdk-safety-' . bin2hex(random_bytes(8));
        mkdir($work, 0700);

        return $work;
    }

    /**
     * Remove one private directory allocated by this test.
     *
     * @param   string  $root  Exact test directory.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    private function removeTree(string $root): void
    {
        if (!str_starts_with($root, sys_get_temp_dir() . '/kumwe-sdk-safety-')) {
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
