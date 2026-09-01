<?php

/**
 * Proves package evidence is complete, deterministic, and policy-neutral.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use Kumwe\Extension\Package\InspectedPackage;
use Kumwe\Extension\Package\PackageAttestationState;
use Kumwe\Extension\Package\PackageBillOfMaterials;
use Kumwe\Extension\Package\PackageCodeConformance;
use Kumwe\Extension\Package\PackageEvidenceInspector;
use Kumwe\Extension\Package\PackageEvidenceScope;
use Kumwe\Extension\Package\PackageFinding;
use Kumwe\Extension\Package\PackageLimits;
use Kumwe\Extension\Package\PackageProvenance;
use Kumwe\Extension\Package\ZipArchiveContentReader;
use Kumwe\Extension\Tests\TestCase;
use Kumwe\Extension\Toolchain\ComponentScaffolder;
use Kumwe\Extension\Toolchain\DeterministicPackageBuilder;
use Kumwe\Extension\Toolchain\PackageInspector;
use Kumwe\Extension\Toolchain\ScaffoldRequest;
use ZipArchive;

/**
 * Exercises the shared findings implementation over SDK-built packages.
 *
 * Every discrepancy is returned in the report. These tests deliberately make no admission decision,
 * proving that a host must interpret the same report an author's CI receives.
 *
 * @since  0.2.0
 */
final class PackageEvidenceInspectorTest extends TestCase
{
    /**
     * A clean SDK build carries verified evidence and no findings.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function testBuiltPackageCarriesVerifiableEvidence(): void
    {
        $work = $this->workspace();
        [, $package] = $this->build($work, 'acme/evidence-clean', 'Acme\EvidenceClean');
        $report = $this->evidence()->inspect($package);

        $this->assertSame(PackageAttestationState::Verified, $report->sbomState, 'The inventory verifies.');
        $this->assertSame(PackageAttestationState::Verified, $report->provenanceState, 'The statement verifies.');
        $this->assertSame([], $report->findings, 'A clean build raises no finding.');
        $this->assertTrue(!in_array(false, $report->checks, true), 'Every objective check holds.');
        $this->assertTrue($report->sbomComponents > 5, 'The inventory covers the generated files.');
        $this->assertSame(
            PackageProvenance::BUILDER_NAME . '@' . PackageProvenance::BUILDER_VERSION,
            $report->builderReference,
            'The provenance names the deterministic builder.',
        );
        $this->assertSame('kumwe-extension-evidence-v2', $report->toArray()['format'], 'The neutral format is stable.');
        $this->removeTree($work);
    }

    /**
     * A file missing from the inventory is returned as invalid evidence, never thrown as policy.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function testUnlistedPackagedFileIsReportedWithoutAnAdmissionDecision(): void
    {
        $work = $this->workspace();
        [$archive] = $this->build($work, 'acme/evidence-smuggled', 'Acme\EvidenceSmuggled');
        $this->addEntry($archive, 'src/Smuggled.php', "<?php\n\ndeclare(strict_types=1);\n");

        $report = $this->evidence()->inspect($this->packageInspector()->inspect($archive)->package);

        $this->assertSame(PackageAttestationState::Invalid, $report->sbomState, 'The inventory is invalid.');
        $this->assertStringContains(
            'does not describe this package',
            $this->messages($report->findings),
            'The finding names the disagreeing inventory.',
        );
        $this->assertSame(false, $report->checks['sbom'], 'The inventory check fails.');
        $this->removeTree($work);
    }

    /**
     * Changed bytes are described by a deterministic digest finding.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function testRewrittenPackagedFileIsReported(): void
    {
        $work = $this->workspace();
        [$archive] = $this->build($work, 'acme/evidence-rewritten', 'Acme\EvidenceRewritten');
        $this->addEntry($archive, 'README.md', "# Replaced after inventory\n");

        $report = $this->evidence()->inspect($this->packageInspector()->inspect($archive)->package);

        $this->assertStringContains(
            'records a different digest',
            $this->messages($report->findings),
            'The finding names the digest disagreement.',
        );
        $this->removeTree($work);
    }

    /**
     * Missing optional attestations remain observable and produce no invented evidence.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function testPackageWithoutAttestationsIsRecordedAsAbsent(): void
    {
        $work = $this->workspace();
        [$archive] = $this->build($work, 'acme/evidence-absent', 'Acme\EvidenceAbsent');
        $this->removeEntries($archive, [PackageBillOfMaterials::PATH, PackageProvenance::PATH]);
        $report = $this->evidence()->inspect($this->packageInspector()->inspect($archive)->package);

        $this->assertSame(PackageAttestationState::Absent, $report->sbomState, 'No inventory means absent.');
        $this->assertSame(PackageAttestationState::Absent, $report->provenanceState, 'No statement means absent.');
        $this->assertSame(null, $report->sbomSha256, 'No inventory digest is invented.');
        $this->assertSame([], $report->findings, 'Absence alone is a fact, not a policy finding.');
        $this->removeTree($work);
    }

    /**
     * Unbound provenance is reported as invalid evidence.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function testProvenanceWithoutVerifiedInventoryIsReported(): void
    {
        $work = $this->workspace();
        [$archive] = $this->build($work, 'acme/evidence-unbound', 'Acme\EvidenceUnbound');
        $this->removeEntries($archive, [PackageBillOfMaterials::PATH]);

        $report = $this->evidence()->inspect($this->packageInspector()->inspect($archive)->package);

        $this->assertSame(PackageAttestationState::Invalid, $report->provenanceState, 'The statement is unbound.');
        $this->assertStringContains(
            'without a verified bill of materials',
            $this->messages($report->findings),
            'The finding names the missing binding.',
        );
        $this->removeTree($work);
    }

    /**
     * Provenance cannot verify through an unreadable or mismatched inventory digest.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function testProvenanceCannotBindToInvalidInventory(): void
    {
        $work = $this->workspace();
        [$archive] = $this->build($work, 'acme/evidence-invalid-sbom', 'Acme\EvidenceInvalidSbom');
        $this->addEntry($archive, PackageBillOfMaterials::PATH, "{\"invalid\":true}\n");

        $report = $this->evidence()->inspect($this->packageInspector()->inspect($archive)->package);

        $this->assertSame(PackageAttestationState::Invalid, $report->sbomState, 'The inventory is invalid.');
        $this->assertSame(
            PackageAttestationState::Invalid,
            $report->provenanceState,
            'Provenance cannot bind to the digest of an invalid inventory.',
        );
        $this->assertTrue(
            in_array('attestation.provenance.unbound', $this->codes($report->findings), true),
            'The finding records the missing verified binding.',
        );
        $this->removeTree($work);
    }

    /**
     * Unparseable PHP is returned as a coded finding without an admission outcome.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function testUnparseablePhpIsReportedPolicyNeutrally(): void
    {
        $work = $this->workspace();
        $source = $work . '/broken';
        (new ComponentScaffolder())->scaffold(new ScaffoldRequest(
            'acme/evidence-broken',
            'Acme\EvidenceBroken',
            $source,
            'Broken Extension',
        ));
        $path = $source . '/src/Provider.php';
        $contents = (string) file_get_contents($path);
        file_put_contents($path, $contents . "\nfunction (\n", LOCK_EX);
        $built = (new DeterministicPackageBuilder($this->packageInspector()))
            ->build($source, $work . '/broken.zip');

        $report = $this->evidence()->inspect($built->inspection->package);

        $this->assertSame(false, $report->checks['static_php_syntax'], 'The syntax check fails.');
        $this->assertStringContains(
            'PHP syntax failure',
            $this->messages($report->findings),
            'The syntax finding is returned to the caller.',
        );
        $this->assertSame(PackageAttestationState::Verified, $report->sbomState, 'Attestations still verify.');
        $this->removeTree($work);
    }

    /**
     * Package scope keeps executable and attestation checks while omitting authoring-only work.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function testPackageScopeOmitsOnlyAuthoringEvidence(): void
    {
        $work = $this->workspace();
        [$archive] = $this->build($work, 'acme/evidence-scope', 'Acme\EvidenceScope');
        $this->addEntry($archive, 'src/Provider.php', "<?php\n// TODO\nfunction (\n");
        $package = $this->packageInspector()->inspect($archive)->package;

        $packageReport = $this->evidence()->inspect($package, PackageEvidenceScope::Package);
        $packageCodes = $this->codes($packageReport->findings);
        $this->assertTrue(in_array('code.php.syntax', $packageCodes, true), 'PHP syntax always runs.');
        $this->assertTrue(
            !in_array('code.php.strict_types', $packageCodes, true),
            'Strict-types authoring evidence is omitted.',
        );
        $this->assertTrue(
            !in_array('source.marker.unresolved', $packageCodes, true),
            'Marker authoring evidence is omitted.',
        );
        $this->assertTrue(!isset($packageReport->checks['strict_types']), 'An unexecuted check is not reported as passed.');
        $this->assertSame(PackageAttestationState::Invalid, $packageReport->sbomState, 'Inventory still runs.');
        $this->assertSame(PackageAttestationState::Invalid, $packageReport->provenanceState, 'Provenance still runs.');
        $this->assertSame('package', $packageReport->toArray()['scope'], 'The executed scope is exported.');

        $authoringReport = $this->evidence()->inspect($package, PackageEvidenceScope::Authoring);
        $authoringCodes = $this->codes($authoringReport->findings);
        $this->assertTrue(
            in_array('code.php.strict_types', $authoringCodes, true),
            'Authoring scope adds strict-types evidence.',
        );
        $this->assertTrue(
            in_array('source.marker.unresolved', $authoringCodes, true),
            'Authoring scope adds marker evidence.',
        );
        $this->assertSame('authoring', $authoringReport->toArray()['scope'], 'The complete scope is exported.');
        $this->removeTree($work);
    }

    /**
     * Scaffold and build one package, returning its archive and immutable inspection snapshot.
     *
     * @param   string  $work        Private directory for this test.
     * @param   string  $identifier  Package identifier.
     * @param   string  $namespace   PSR-4 namespace prefix.
     *
     * @return  array{0: string, 1: InspectedPackage}  Archive path and immutable snapshot.
     *
     * @since   0.2.0
     */
    private function build(string $work, string $identifier, string $namespace): array
    {
        $source = $work . '/' . str_replace('/', '-', $identifier);
        (new ComponentScaffolder())->scaffold(new ScaffoldRequest(
            $identifier,
            $namespace,
            $source,
            'Evidence Fixture',
        ));
        $result = (new DeterministicPackageBuilder($this->packageInspector()))
            ->build($source, $work . '/' . str_replace('/', '-', $identifier) . '.zip');

        return [$result->archive, $result->inspection->package];
    }

    /**
     * Write one entry into a package, simulating a post-build edit.
     *
     * @param   string  $archive   Archive path.
     * @param   string  $path      Entry path.
     * @param   string  $contents  Entry bytes.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    private function addEntry(string $archive, string $path, string $contents): void
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($archive) === true, 'The package must reopen for the edit.');
        $this->assertTrue($zip->addFromString($path, $contents), 'The edit must be written.');
        $this->assertTrue($zip->close(), 'The edited package must close.');
    }

    /**
     * Delete entries from a package.
     *
     * @param   string        $archive  Archive path.
     * @param   list<string>  $paths    Paths to remove.
     *
     * @return  void
     *
     * @since   0.2.0
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
     * Build the canonical shared evidence implementation.
     *
     * @return  PackageEvidenceInspector  Neutral package evidence inspector.
     *
     * @since   0.2.0
     */
    private function evidence(): PackageEvidenceInspector
    {
        return new PackageEvidenceInspector(new ZipArchiveContentReader(), new PackageCodeConformance());
    }

    /**
     * Build the production archive inspector used by the package builder.
     *
     * @return  PackageInspector  Safe archive inspector.
     *
     * @since   0.2.0
     */
    private function packageInspector(): PackageInspector
    {
        return new PackageInspector(new PackageLimits());
    }

    /**
     * Flatten typed findings for human-readable test diagnostics.
     *
     * @param   list<PackageFinding>  $findings  Neutral coded findings.
     *
     * @return  string  Finding messages in report order.
     *
     * @since   0.2.0
     */
    private function messages(array $findings): string
    {
        return implode(' ', array_map(
            static fn (PackageFinding $finding): string => $finding->message,
            $findings,
        ));
    }

    /**
     * Select stable finding codes from typed findings.
     *
     * @param   list<PackageFinding>  $findings  Neutral coded findings.
     *
     * @return  list<string>  Finding codes in report order.
     *
     * @since   0.2.0
     */
    private function codes(array $findings): array
    {
        return array_map(
            static fn (PackageFinding $finding): string => $finding->code,
            $findings,
        );
    }

    /**
     * Allocate a private working directory.
     *
     * @return  string  Absolute writable directory.
     *
     * @since   0.2.0
     */
    private function workspace(): string
    {
        $work = sys_get_temp_dir() . '/kumwe-sdk-evidence-' . bin2hex(random_bytes(8));
        mkdir($work, 0700);

        return $work;
    }

    /**
     * Remove one private test directory.
     *
     * @param   string  $root  Exact private directory.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    private function removeTree(string $root): void
    {
        if (!str_starts_with($root, sys_get_temp_dir() . '/kumwe-sdk-evidence-')) {
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
