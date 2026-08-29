<?php

/**
 * Proves package output, evidence and signatures are deterministic without a host implementation oracle.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use InvalidArgumentException;
use Kumwe\Extension\Package\PackageAttestationState;
use Kumwe\Extension\Package\PackageBillOfMaterials;
use Kumwe\Extension\Package\PackageCodeConformance;
use Kumwe\Extension\Package\PackageEvidenceInspector;
use Kumwe\Extension\Package\PackageLimits;
use Kumwe\Extension\Package\PackageProvenance;
use Kumwe\Extension\Package\PackageSignature;
use Kumwe\Extension\Package\SodiumEd25519Verifier;
use Kumwe\Extension\Package\ZipArchiveContentReader;
use Kumwe\Extension\Tests\TestCase;
use Kumwe\Extension\Toolchain\DeterministicPackageBuilder;
use Kumwe\Extension\Toolchain\PackageInspector;
use Kumwe\Extension\Toolchain\PackageSigner;
use Kumwe\Extension\Toolchain\ProtectedSigningKeyReader;
use Kumwe\Extension\Toolchain\SignatureDocument;
use ZipArchive;

/**
 * Exercises SDK-owned invariants directly; no application snapshot or parity pin is authoritative.
 *
 * @since  0.2.0
 */
final class PackageDeterminismTest extends TestCase
{
    /**
     * Every supported generation builds reproducibly and yields identical neutral evidence.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function testAllGenerationsBuildAndInspectDeterministically(): void
    {
        $work = $this->workspace();
        $sources = glob(dirname(__DIR__, 2) . '/resources/fixtures/generations/manifest-*') ?: [];
        sort($sources, SORT_STRING);
        $this->assertSame(6, count($sources), 'All six declared generations are exercised.');
        foreach ($sources as $source) {
            $name = basename($source);
            $builder = new DeterministicPackageBuilder($this->inspector());
            $first = $builder->build($source, $work . '/' . $name . '-first.zip');
            $second = $builder->build($source, $work . '/' . $name . '-second.zip');

            $this->assertSame(
                (string) file_get_contents($first->archive),
                (string) file_get_contents($second->archive),
                sprintf('Two %s builds must have identical bytes.', $name),
            );
            $evidence = $this->evidence();
            $firstReport = $evidence->inspect($first->inspection->package);
            $secondReport = $evidence->inspect($first->inspection->package);
            $this->assertSame(
                $firstReport->toArray(),
                $secondReport->toArray(),
                sprintf('%s evidence must be deterministic.', $name),
            );
            $this->assertSame(
                PackageAttestationState::Verified,
                $firstReport->sbomState,
                sprintf('%s inventory must verify.', $name),
            );
            $this->assertSame(
                PackageAttestationState::Verified,
                $firstReport->provenanceState,
                sprintf('%s provenance must verify.', $name),
            );
        }
        $this->removeTree($work);
    }

    /**
     * Author signing and configured verification share one domain-separated SDK protocol.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function testSigningRoundTripsThroughCanonicalVerifier(): void
    {
        $work = $this->workspace();
        $source = dirname(__DIR__, 2) . '/resources/fixtures/generations/manifest-1';
        $inspector = $this->inspector();
        $built = (new DeterministicPackageBuilder($inspector))->build($source, $work . '/signed.zip');
        $seed = random_bytes(SODIUM_CRYPTO_SIGN_SEEDBYTES);
        $keyFile = $work . '/signing.seed';
        file_put_contents($keyFile, bin2hex($seed), LOCK_EX);
        chmod($keyFile, 0600);

        $document = (new PackageSigner(new ProtectedSigningKeyReader(), $inspector))
            ->sign($built->archive, 'test-release-key', $keyFile);
        $keypair = sodium_crypto_sign_seed_keypair($seed);
        $verifier = new SodiumEd25519Verifier([
            'test-release-key' => base64_encode(sodium_crypto_sign_publickey($keypair)),
        ]);
        $signature = PackageSignature::ed25519($document->keyId, $document->base64Signature);

        $this->assertTrue(
            $verifier->verify($built->inspection->package->checksum, $signature),
            'The SDK verifier accepts its SDK signer output.',
        );
        $this->assertTrue(
            !$verifier->verify(
                $built->inspection->package->checksum,
                PackageSignature::ed25519('unknown-release-key', $document->base64Signature),
            ),
            'An unknown key identifier is a verification miss, not an alternate trust path.',
        );
        $this->removeTree($work);
    }

    /**
     * Signature sidecars have exactly one byte representation.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function testSignatureDocumentRejectsNoncanonicalJson(): void
    {
        $document = new SignatureDocument(
            'test-release-key',
            str_repeat('a', 64),
            base64_encode(str_repeat('s', SODIUM_CRYPTO_SIGN_BYTES)),
        );
        $canonical = $document->toJson();
        $this->assertSame($canonical, SignatureDocument::fromJson($canonical)->toJson(), 'Canonical JSON round-trips.');
        $compact = json_encode($document->toArray(), JSON_UNESCAPED_SLASHES);
        $this->assertThrows(
            static fn (): SignatureDocument => SignatureDocument::fromJson((string) $compact),
            InvalidArgumentException::class,
            'Alternate whitespace must not create a second signed sidecar representation.',
        );
    }

    /**
     * Embedded evidence rejects alternate object ordering as a second document representation.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function testAttestationsRejectNoncanonicalJsonOrdering(): void
    {
        $work = $this->workspace();
        $source = dirname(__DIR__, 2) . '/resources/fixtures/generations/manifest-1';
        $built = (new DeterministicPackageBuilder($this->inspector()))
            ->build($source, $work . '/canonical.zip');

        $sbom = json_decode($this->entry($built->archive, PackageBillOfMaterials::PATH), true);
        $this->assertTrue(is_array($sbom), 'The SDK inventory decodes for the ordering test.');
        $reorderedSbom = ['specVersion' => $sbom['specVersion']] + $sbom;
        $this->assertThrows(
            static fn (): PackageBillOfMaterials => PackageBillOfMaterials::fromJson(
                (string) json_encode($reorderedSbom, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
            ),
            InvalidArgumentException::class,
            'A reordered inventory object is not canonical SDK JSON.',
        );

        $provenance = json_decode($this->entry($built->archive, PackageProvenance::PATH), true);
        $this->assertTrue(is_array($provenance), 'The SDK provenance decodes for the ordering test.');
        $reorderedProvenance = ['builder' => $provenance['builder']] + $provenance;
        $this->assertThrows(
            static fn (): PackageProvenance => PackageProvenance::fromJson(
                (string) json_encode($reorderedProvenance, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
            ),
            InvalidArgumentException::class,
            'A reordered provenance object is not canonical SDK JSON.',
        );
        $this->removeTree($work);
    }

    /**
     * Build the SDK-default snapshot producer.
     *
     * @return  PackageInspector  Host-neutral package inspector.
     *
     * @since   0.2.0
     */
    private function inspector(): PackageInspector
    {
        return new PackageInspector(new PackageLimits());
    }

    /**
     * Build the canonical neutral evidence inspector.
     *
     * @return  PackageEvidenceInspector  Snapshot-bound evidence implementation.
     *
     * @since   0.2.0
     */
    private function evidence(): PackageEvidenceInspector
    {
        return new PackageEvidenceInspector(new ZipArchiveContentReader(), new PackageCodeConformance());
    }

    /**
     * Read one named entry from a test archive.
     *
     * @param   string  $archive  Canonical ZIP path.
     * @param   string  $path     Exact entry name.
     *
     * @return  string  Complete entry bytes.
     *
     * @since   0.2.0
     */
    private function entry(string $archive, string $path): string
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($archive, ZipArchive::RDONLY) === true, 'The package reopens.');
        $contents = $zip->getFromName($path, 0, ZipArchive::FL_UNCHANGED);
        $this->assertTrue($zip->close(), 'The package closes.');
        $this->assertTrue(is_string($contents), sprintf('The package carries %s.', $path));

        return (string) $contents;
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
        $work = sys_get_temp_dir() . '/kumwe-sdk-determinism-' . bin2hex(random_bytes(8));
        mkdir($work, 0700);

        return $work;
    }

    /**
     * Remove one private test tree.
     *
     * @param   string  $root  Exact private directory.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    private function removeTree(string $root): void
    {
        if (!str_starts_with($root, sys_get_temp_dir() . '/kumwe-sdk-determinism-')) {
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
