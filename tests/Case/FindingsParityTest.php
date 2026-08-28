<?php

/**
 * Proves the extracted toolchain reproduces the App's builds and findings byte for byte.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use Kumwe\Extension\Contract\NameBasedUuid;
use Kumwe\Extension\Manifest\ExtensionManifest;
use Kumwe\Extension\Package\PackageAdmissionScanner;
use Kumwe\Extension\Package\PackageCodeConformance;
use Kumwe\Extension\Package\PackageSafetyPolicy;
use Kumwe\Extension\Package\PackageSignature;
use Kumwe\Extension\Package\SodiumEd25519Verifier;
use Kumwe\Extension\Package\ZipArchiveContentReader;
use Kumwe\Extension\Package\ZipArchiveReader;
use Kumwe\Extension\Tests\TestCase;
use Kumwe\Extension\Toolchain\DeterministicPackageBuilder;
use Kumwe\Extension\Toolchain\PackageInspector;
use Kumwe\Extension\Toolchain\PackageSigner;
use Kumwe\Extension\Toolchain\ProtectedSigningKeyReader;
use Kumwe\Extension\Toolchain\SignatureDocument;
use Kumwe\Extension\Toolchain\StaticConformanceRunner;
use Throwable;
use ZipArchive;

/**
 * The findings-equality harness of the one-inspector invariant, replayed against recorded evidence.
 *
 * `tests/Fixtures/app-parity.json` holds what the App's in-tree implementation produced at the
 * pinned source commit: the byte digest of each compatibility generation built by the App's
 * builder, the conformance and admission findings over those packages, and the exact bytes and
 * findings of a hostile-archive corpus. This suite replays every input through the extracted
 * implementation and asserts equality — same archives to the byte, same findings, same stable
 * reasons, same order. The comparison deliberately excludes only the absolute archive path and the
 * manifest's contribution inventory, whose deep export stays App-side by classification; every
 * finding, check, refusal, digest and attestation outcome is compared exactly.
 *
 * @since  0.1.0
 */
final class FindingsParityTest extends TestCase
{
    /**
     * Each generation builds twice to identical bytes, equal to the App-built archive's digest.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testGenerationsBuildByteIdenticalToTheAppBuilds(): void
    {
        $parity = $this->parity();
        $work = $this->workspace();
        foreach ($parity['generations'] as $generation => $expected) {
            $source = dirname(__DIR__, 2) . '/resources/fixtures/generations/' . $generation;
            $builder = new DeterministicPackageBuilder($this->inspector());
            $first = $builder->build($source, $work . '/' . $generation . '-first.zip');
            $second = $builder->build($source, $work . '/' . $generation . '-second.zip');

            $this->assertSame(
                (string) file_get_contents($first->archive),
                (string) file_get_contents($second->archive),
                sprintf('Two builds of %s must produce identical archive bytes.', $generation),
            );
            $this->assertSame(
                $expected['package_sha256'],
                (string) $first->inspection->checksum,
                sprintf('The SDK-built %s must equal the App-built archive digest.', $generation),
            );
        }
        $this->removeTree($work);
    }

    /**
     * The author-facing conformance findings equal the App's recorded findings on every generation.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testConformanceFindingsMatchTheAppOnEveryGeneration(): void
    {
        $parity = $this->parity();
        $work = $this->workspace();
        foreach ($parity['generations'] as $generation => $expected) {
            $source = dirname(__DIR__, 2) . '/resources/fixtures/generations/' . $generation;
            $archive = (new DeterministicPackageBuilder($this->inspector()))
                ->build($source, $work . '/' . $generation . '.zip')->archive;
            $report = (new StaticConformanceRunner($this->inspector()))->run($archive);

            $this->assertSame(
                $this->comparable($expected['conformance']),
                $this->comparable($this->trimmedConformance($report->toArray())),
                sprintf('Conformance findings for %s must equal the App findings.', $generation),
            );
        }
        $this->removeTree($work);
    }

    /**
     * The admission findings equal the App's recorded admission report on every generation.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testAdmissionFindingsMatchTheAppOnEveryGeneration(): void
    {
        $parity = $this->parity();
        $work = $this->workspace();
        foreach ($parity['generations'] as $generation => $expected) {
            $source = dirname(__DIR__, 2) . '/resources/fixtures/generations/' . $generation;
            $built = (new DeterministicPackageBuilder($this->inspector()))
                ->build($source, $work . '/' . $generation . '.zip');
            $admission = $this->scanner()->scan($built->archive, $built->inspection->manifest);

            $this->assertSame(
                $this->comparable($expected['admission']),
                $this->comparable($admission->toArray()),
                sprintf('Admission findings for %s must equal the App findings.', $generation),
            );
        }
        $this->removeTree($work);
    }

    /**
     * Every hostile archive produces exactly the findings or refusal the App produced.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testHostileCorpusFindingsMatchTheApp(): void
    {
        $parity = $this->parity();
        $work = $this->workspace();
        $this->assertSame(8, count($parity['hostile']), 'The recorded hostile corpus holds eight archives.');
        foreach ($parity['hostile'] as $name => $case) {
            $archive = $work . '/' . $name . '.zip';
            file_put_contents($archive, base64_decode($case['zip'], true));

            $static = null;
            try {
                $report = $this->trimmedConformance(
                    (new StaticConformanceRunner($this->inspector()))->run($archive)->toArray(),
                );
                $static = ['report' => $report];
            } catch (Throwable $failure) {
                $static = [
                    'refusal' => (new \ReflectionClass($failure))->getShortName(),
                    'message' => $failure->getMessage(),
                ];
            }
            $this->assertSame(
                $this->comparable($case['expected']['static']),
                $this->comparable($static),
                sprintf('Static findings for hostile archive %s must equal the App findings.', $name),
            );

            $admission = null;
            try {
                $raw = null;
                $zip = new ZipArchive();
                if ($zip->open($archive, ZipArchive::RDONLY) === true) {
                    $raw = $zip->getFromName('kumwe.json');
                    $zip->close();
                }
                $manifest = ExtensionManifest::fromJson((string) $raw);
                $admission = ['report' => $this->scanner()->scan($archive, $manifest)->toArray()];
            } catch (Throwable $failure) {
                $admission = [
                    'refusal' => (new \ReflectionClass($failure))->getShortName(),
                    'message' => $failure->getMessage(),
                ];
            }
            $this->assertSame(
                $this->comparable($case['expected']['admission']),
                $this->comparable($admission),
                sprintf('Admission findings for hostile archive %s must equal the App findings.', $name),
            );
        }
        $this->removeTree($work);
    }

    /**
     * A signature made with the published fixture-key stem verifies through the admission primitive.
     *
     * The seed derivation, key identifier and verification primitive are the ones the frozen
     * contract publishes and the App's admission uses, so an author's signing round-trip and the
     * platform's verification meet on the same bytes.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testFixtureKeySigningRoundTripsThroughTheAdmissionVerifier(): void
    {
        $contract = json_decode(
            (string) file_get_contents(dirname(__DIR__, 2) . '/resources/contract/generations.json'),
            true,
        );
        $this->assertTrue(is_array($contract), 'The vendored generations document must decode.');
        $signing = $contract['signing'];
        $this->assertSame('ed25519', $signing['algorithm'], 'The fixture key is Ed25519.');
        $this->assertSame('sha256(seed_stem, raw)', $signing['seed_derivation'], 'The derivation is published.');

        $work = $this->workspace();
        $source = dirname(__DIR__, 2) . '/resources/fixtures/generations/manifest-1';
        $inspector = $this->inspector();
        $built = (new DeterministicPackageBuilder($inspector))->build($source, $work . '/signed.zip');
        $seed = hash('sha256', (string) $signing['seed_stem'], true);
        $keyFile = $work . '/fixture.seed';
        file_put_contents($keyFile, bin2hex($seed), LOCK_EX);
        chmod($keyFile, 0600);

        $signer = new PackageSigner(new ProtectedSigningKeyReader(), $inspector);
        $document = $signer->sign($built->archive, (string) $signing['identifier'], $keyFile);
        $sidecar = $work . '/signed.signature.json';
        $signer->write($document, $sidecar);
        $decoded = SignatureDocument::fromJson((string) file_get_contents($sidecar));
        $this->assertSame(
            (string) $built->inspection->checksum,
            $decoded->packageSha256,
            'The sidecar signs the built package digest.',
        );

        $publicKey = base64_encode(sodium_crypto_sign_publickey(sodium_crypto_sign_seed_keypair($seed)));
        $verifier = new SodiumEd25519Verifier([(string) $signing['identifier'] => $publicKey]);
        $this->assertTrue(
            $verifier->verify(
                $built->inspection->checksum,
                PackageSignature::ed25519($decoded->keyId, $decoded->base64Signature),
            ),
            'The admission verification primitive must accept the author-side signature.',
        );
        $this->assertTrue(
            !$verifier->verify(
                $built->inspection->checksum,
                PackageSignature::ed25519('unknown-key', $decoded->base64Signature),
            ),
            'An unknown key identifier must fail verification rather than error.',
        );
        $this->removeTree($work);
    }

    /**
     * The dependency-free UUID derivation reproduces the App's ramsey-derived values exactly.
     *
     * The expected strings were produced by `Ramsey\Uuid\Uuid::uuid5()` in the App's dependency
     * tree at the pinned source commit; the byte-identical archives asserted above additionally
     * cover every serial number embedded in a built bill of materials.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testNameBasedUuidReproducesTheRamseyDerivedValues(): void
    {
        $vectors = [
            '86488cea-eccf-54fe-a876-00e94aa8658b' => [
                NameBasedUuid::NAMESPACE_URL,
                'https://kumwe.dev/extensions/acme/quality-component/entity/item',
            ],
            '019ffe32-d261-56a6-8b2e-ddb86499565c' => [
                NameBasedUuid::NAMESPACE_URL,
                'https://kumwe.dev/extensions/kumwe/contract-manifest-one/entity/item',
            ],
            'a7250ce6-5b39-5950-a2dd-801bf9f007bd' => [
                NameBasedUuid::NAMESPACE_URL,
                'kumwe-extension-sbom:acme/quality-component:1.0.0:deadbeef',
            ],
            '8b2faf5a-b1ee-5b5b-ab9e-296954856595' => [
                '018f22e2-7c8b-7ab0-8f3a-88e8026bc101',
                '1|default|acme/blog|acme.blog.articles',
            ],
        ];
        foreach ($vectors as $expected => [$namespace, $name]) {
            $this->assertSame(
                $expected,
                NameBasedUuid::v5($namespace, $name),
                sprintf('The derivation of "%s" must match the ramsey-derived value.', $name),
            );
        }
    }

    /**
     * Load the recorded App parity evidence.
     *
     * @return  array{generations: array<string, array{package_sha256: string,
     *          conformance: array<string, mixed>, admission: array<string, mixed>}>,
     *          hostile: array<string, array{zip: string, expected: array<string, mixed>}>}  Evidence.
     *
     * @since   0.1.0
     */
    private function parity(): array
    {
        $path = dirname(__DIR__) . '/Fixtures/app-parity.json';
        $parity = json_decode((string) file_get_contents($path), true);
        $this->assertTrue(is_array($parity), 'tests/Fixtures/app-parity.json must decode.');
        $this->assertSame(
            'kumwe-extension-sdk-app-parity-v1',
            $parity['format'] ?? null,
            'The parity evidence must declare its format.',
        );
        $this->assertSame(6, count($parity['generations']), 'All six generations are recorded.');

        /** @var array{generations: array<string, array{package_sha256: string,
         *      conformance: array<string, mixed>, admission: array<string, mixed>}>,
         *      hostile: array<string, array{zip: string, expected: array<string, mixed>}>} $parity */
        return $parity;
    }

    /**
     * Strip the environment-dependent archive path and the App-side contribution inventory.
     *
     * @param   array<string, mixed>  $report  Conformance report export.
     *
     * @return  array<string, mixed>  The report reduced to the compared surface.
     *
     * @since   0.1.0
     */
    private function trimmedConformance(array $report): array
    {
        unset($report['package']['archive']);
        unset($report['package']['manifest']['contributions']);

        return $report;
    }

    /**
     * Normalise a structure through JSON so both sides compare as identical decoded values.
     *
     * @param   mixed  $value  Report structure from either side.
     *
     * @return  mixed  JSON round-tripped value.
     *
     * @since   0.1.0
     */
    private function comparable(mixed $value): mixed
    {
        return json_decode(json_encode($value, JSON_UNESCAPED_SLASHES) ?: 'null', true);
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
     * Build the admission scanner exactly as the App wires it for enforced installs.
     *
     * @return  PackageAdmissionScanner  Scanner over the shipped content reader and shared checks.
     *
     * @since   0.1.0
     */
    private function scanner(): PackageAdmissionScanner
    {
        return new PackageAdmissionScanner(new ZipArchiveContentReader(), new PackageCodeConformance());
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
        $work = sys_get_temp_dir() . '/kumwe-sdk-parity-' . bin2hex(random_bytes(8));
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
        if (!str_starts_with($root, sys_get_temp_dir() . '/kumwe-sdk-parity-')) {
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
