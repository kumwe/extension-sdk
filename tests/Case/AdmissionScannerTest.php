<?php

/**
 * Proves install-time admission behaves exactly as the App's over SDK-built packages.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use Kumwe\Extension\Manifest\ExtensionManifest;
use Kumwe\Extension\Package\NonConformingPackage;
use Kumwe\Extension\Package\PackageAdmissionReport;
use Kumwe\Extension\Package\PackageAdmissionScanner;
use Kumwe\Extension\Package\PackageAttestationState;
use Kumwe\Extension\Package\PackageBillOfMaterials;
use Kumwe\Extension\Package\PackageCodeConformance;
use Kumwe\Extension\Package\PackageConformanceMode;
use Kumwe\Extension\Package\PackageProvenance;
use Kumwe\Extension\Package\PackageSafetyPolicy;
use Kumwe\Extension\Package\ZipArchiveContentReader;
use Kumwe\Extension\Package\ZipArchiveReader;
use Kumwe\Extension\Tests\TestCase;
use Kumwe\Extension\Toolchain\ComponentScaffolder;
use Kumwe\Extension\Toolchain\DeterministicPackageBuilder;
use Kumwe\Extension\Toolchain\PackageInspector;
use Kumwe\Extension\Toolchain\ScaffoldRequest;
use ZipArchive;

/**
 * Exercises install-time admission over real packages built by the shipped SDK builder.
 *
 * Every fixture is scaffolded and built rather than hand-assembled, so the tests prove what an
 * installation will actually meet: the attestations the builder embeds, the digests it records,
 * and the findings the shared static checks raise over generated code. Assertions are the App
 * suite's, unchanged in what they prove.
 *
 * @since  0.1.0
 */
final class AdmissionScannerTest extends TestCase
{
    /**
     * A package built by the SDK carries both attestations and reconciles against its own bytes.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testBuiltPackageCarriesVerifiableBillOfMaterialsAndProvenance(): void
    {
        $work = $this->workspace();
        [$archive, $manifest] = $this->build($work, 'acme/admission-clean', 'Acme\\AdmissionClean');
        $report = $this->scanner()->scan($archive, $manifest);

        $this->assertSame(PackageAttestationState::Verified, $report->sbomState, 'The inventory verifies.');
        $this->assertSame(PackageAttestationState::Verified, $report->provenanceState, 'The statement verifies.');
        $this->assertSame('passed', $report->conformanceState, 'A clean build passes the code scan.');
        $this->assertSame([], $report->blocking, 'A clean build raises no blocking finding.');
        $this->assertTrue($report->sbomComponents > 5, 'The inventory covers the generated files.');
        $this->assertSame(
            PackageProvenance::BUILDER_NAME . '@' . PackageProvenance::BUILDER_VERSION,
            $report->builderReference,
            'The provenance names the deterministic builder.',
        );
        $this->assertSame('CycloneDX', $report->sbom['bomFormat'] ?? null, 'The inventory is CycloneDX.');
        $this->assertSame(
            PackageBillOfMaterials::SPEC_VERSION,
            $report->sbom['specVersion'] ?? null,
            'The inventory declares the supported specification version.',
        );
        $this->assertSame('verified', $report->auditMetadata()['sbom'], 'The audit metadata echoes the state.');
        $this->removeTree($work);
    }

    /**
     * A packaged file the bill of materials does not describe refuses the install regardless of mode.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testUnlistedPackagedFileIsRefusedEvenWhenTheScanOnlyWarns(): void
    {
        $work = $this->workspace();
        [$archive, $manifest] = $this->build($work, 'acme/admission-smuggled', 'Acme\\AdmissionSmuggled');
        $this->addEntry($archive, 'src/Smuggled.php', "<?php\n\ndeclare(strict_types=1);\n");

        $failure = $this->assertThrows(
            fn (): PackageAdmissionReport
                => $this->scanner(PackageConformanceMode::Warn)->scan($archive, $manifest),
            NonConformingPackage::class,
            'A smuggled file must refuse the install even under warn.',
        );
        $this->assertStringContains(
            'does not describe this package',
            $failure->getMessage(),
            'The refusal names the disagreeing inventory.',
        );
        $this->removeTree($work);
    }

    /**
     * A packaged file whose bytes changed after the inventory was recorded refuses the install.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testRewrittenPackagedFileIsRefused(): void
    {
        $work = $this->workspace();
        [$archive, $manifest] = $this->build($work, 'acme/admission-rewritten', 'Acme\\AdmissionRewritten');
        $this->addEntry($archive, 'README.md', "# Replaced after the inventory was recorded\n");

        $failure = $this->assertThrows(
            fn (): PackageAdmissionReport => $this->scanner()->scan($archive, $manifest),
            NonConformingPackage::class,
            'A rewritten file must refuse the install.',
        );
        $this->assertStringContains(
            'records a different digest',
            $failure->getMessage(),
            'The refusal names the digest disagreement.',
        );
        $this->removeTree($work);
    }

    /**
     * A package with no attestations still installs, and is recorded as having claimed nothing.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testPackageWithoutAttestationsIsAdmittedAndRecordedAsAbsent(): void
    {
        $work = $this->workspace();
        [$archive, $manifest] = $this->build($work, 'acme/admission-legacy', 'Acme\\AdmissionLegacy');
        $this->removeEntries($archive, [PackageBillOfMaterials::PATH, PackageProvenance::PATH]);
        $report = $this->scanner()->scan($archive, $manifest);

        $this->assertSame(PackageAttestationState::Absent, $report->sbomState, 'No inventory means Absent.');
        $this->assertSame(PackageAttestationState::Absent, $report->provenanceState, 'No statement means Absent.');
        $this->assertSame(null, $report->sbomSha256, 'No inventory digest is recorded.');
        $this->assertSame('passed', $report->conformanceState, 'The code scan still passes.');
        $this->removeTree($work);
    }

    /**
     * A provenance statement with no bill of materials beside it cannot be bound and is refused.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testProvenanceWithoutABillOfMaterialsIsRefused(): void
    {
        $work = $this->workspace();
        [$archive, $manifest] = $this->build($work, 'acme/admission-unbound', 'Acme\\AdmissionUnbound');
        $this->removeEntries($archive, [PackageBillOfMaterials::PATH]);

        $failure = $this->assertThrows(
            fn (): PackageAdmissionReport => $this->scanner()->scan($archive, $manifest),
            NonConformingPackage::class,
            'An unbound provenance statement must refuse the install.',
        );
        $this->assertStringContains(
            'no bill of materials to bind it to',
            $failure->getMessage(),
            'The refusal names the missing binding.',
        );
        $this->removeTree($work);
    }

    /**
     * PHP that does not parse blocks the install under enforce and is recorded under warn.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testUnparseablePhpBlocksUnderEnforceAndWarnsUnderWarn(): void
    {
        $work = $this->workspace();
        $source = $work . '/broken';
        (new ComponentScaffolder())->scaffold(new ScaffoldRequest(
            'acme/admission-broken',
            'Acme\\AdmissionBroken',
            $source,
            'Broken Component',
        ));
        $path = $source . '/src/Application/OverviewService.php';
        $contents = (string) file_get_contents($path);
        file_put_contents($path, $contents . "\nfunction (\n", LOCK_EX);
        $archive = (new DeterministicPackageBuilder($this->inspector()))->build($source, $work . '/broken.zip');
        $manifest = $archive->inspection->manifest;

        $warned = $this->scanner(PackageConformanceMode::Warn)->scan($archive->archive, $manifest);
        $this->assertSame('warned', $warned->conformanceState, 'Warn records the findings and admits.');
        $this->assertSame(false, $warned->checks['static_php_syntax'], 'The syntax check fails.');
        $this->assertTrue($warned->blocking !== [], 'The blocking findings are recorded.');
        $this->assertSame(PackageAttestationState::Verified, $warned->sbomState, 'Attestations still verify.');

        $failure = $this->assertThrows(
            fn (): PackageAdmissionReport => $this->scanner()->scan($archive->archive, $manifest),
            NonConformingPackage::class,
            'Enforce must refuse the unparseable package.',
        );
        $this->assertStringContains(
            'failed install-time code conformance',
            $failure->getMessage(),
            'The refusal names the failed scan.',
        );
        $this->removeTree($work);
    }

    /**
     * Turning the scan off records `skipped`, and still verifies the attestations.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testDisabledScanStillVerifiesAttestations(): void
    {
        $work = $this->workspace();
        [$archive, $manifest] = $this->build($work, 'acme/admission-unscanned', 'Acme\\AdmissionUnscanned');
        $report = $this->scanner(PackageConformanceMode::Off)->scan($archive, $manifest);

        $this->assertSame('skipped', $report->conformanceState, 'Off records that no scan was taken.');
        $this->assertSame([], $report->checks, 'No checks are asserted when no scan ran.');
        $this->assertSame(PackageAttestationState::Verified, $report->sbomState, 'The inventory still verifies.');
        $this->assertSame(PackageAttestationState::Verified, $report->provenanceState, 'So does the statement.');
        $this->removeTree($work);
    }

    /**
     * A report for an installation with no scanner asserts nothing rather than a pass.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testNotTakenReportAssertsNothing(): void
    {
        $report = PackageAdmissionReport::notTaken();

        $this->assertSame('skipped', $report->conformanceState, 'Not taken is skipped, never passed.');
        $this->assertSame(PackageAttestationState::Absent, $report->sbomState, 'Nothing was claimed.');
        $this->assertSame(0, $report->auditMetadata()['blocking_findings'], 'Nothing was found.');
    }

    /**
     * Scaffold and build one package, returning its archive path and parsed manifest.
     *
     * @param   string  $work        Private directory for this test.
     * @param   string  $identifier  `vendor/name` identifier for the scaffolded component.
     * @param   string  $namespace   PSR-4 namespace prefix for the scaffolded component.
     *
     * @return  array{0: string, 1: ExtensionManifest}  Archive path and its parsed manifest.
     *
     * @since   0.1.0
     */
    private function build(string $work, string $identifier, string $namespace): array
    {
        $source = $work . '/' . str_replace('/', '-', $identifier);
        (new ComponentScaffolder())->scaffold(new ScaffoldRequest(
            $identifier,
            $namespace,
            $source,
            'Admission Fixture',
        ));
        $result = (new DeterministicPackageBuilder($this->inspector()))
            ->build($source, $work . '/' . str_replace('/', '-', $identifier) . '.zip');

        return [$result->archive, $result->inspection->manifest];
    }

    /**
     * Write one entry into an already-published package, simulating a post-build edit.
     *
     * @param   string  $archive   Absolute package path.
     * @param   string  $path      Package path to write.
     * @param   string  $contents  Entry bytes.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    private function addEntry(string $archive, string $path, string $contents): void
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($archive) === true, 'The package must reopen for the edit.');
        $this->assertTrue($zip->addFromString($path, $contents), 'The edit must be written.');
        $this->assertTrue($zip->close(), 'The edited package must close.');
    }

    /**
     * Delete entries from a published package, simulating a package built before attestations.
     *
     * @param   string        $archive  Absolute package path.
     * @param   list<string>  $paths    Package paths to remove.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    private function removeEntries(string $archive, array $paths): void
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($archive) === true, 'The package must reopen for the removal.');
        foreach ($paths as $path) {
            $this->assertTrue($zip->deleteName($path), sprintf('Entry %s must be removable.', $path));
        }
        $this->assertTrue($zip->close(), 'The edited package must close.');
    }

    /**
     * Build the admission scanner under one conformance posture.
     *
     * @param   PackageConformanceMode  $mode  Posture the code scan runs under.
     *
     * @return  PackageAdmissionScanner  Scanner bound to the shipped ZIP content reader.
     *
     * @since   0.1.0
     */
    private function scanner(PackageConformanceMode $mode = PackageConformanceMode::Enforce): PackageAdmissionScanner
    {
        return new PackageAdmissionScanner(
            new ZipArchiveContentReader(),
            new PackageCodeConformance(),
            $mode,
        );
    }

    /**
     * Build the production package inspector the SDK builder verifies through.
     *
     * @return  PackageInspector  Inspector bound to the shipped reader and safety policy.
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
        $work = sys_get_temp_dir() . '/kumwe-sdk-admission-' . bin2hex(random_bytes(8));
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
        if (!str_starts_with($root, sys_get_temp_dir() . '/kumwe-sdk-admission-')) {
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
