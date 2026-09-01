<?php

declare(strict_types=1);

namespace Kumwe\Extension\Toolchain;

use Kumwe\Extension\Package\InvalidPackage;
use Kumwe\Extension\Package\PackageAttestationState;
use Kumwe\Extension\Package\PackageCodeConformance;
use Kumwe\Extension\Package\PackageEvidenceInspector;
use Kumwe\Extension\Package\PackageEvidenceScope;
use Kumwe\Extension\Package\PackageFinding;
use Kumwe\Extension\Package\ZipArchiveContentReader;
use RuntimeException;
use ZipArchive;

/**
 * Performs bounded, code-free author conformance over the same neutral evidence a host can inspect.
 *
 * Invalid package data is represented as coded findings. Filesystem and transport failures still throw,
 * because a tool cannot honestly report package facts when the staged bytes cannot be read stably.
 *
 * @since  0.2.0
 */
final readonly class StaticConformanceRunner
{
    /**
     * Timestamp assigned by the deterministic package builder.
     *
     * @var    int
     * @since  0.2.0
     */
    private const int ZIP_EPOCH = 315_532_800;

    /**
     * Bind conformance to package inspection and shared static checks.
     *
     * @param  PackageInspector        $inspector    Immutable package snapshot producer.
     * @param  PackageCodeConformance  $conformance  Neutral code and reference checks.
     *
     * @since  0.2.0
     */
    public function __construct(
        private PackageInspector $inspector,
        private PackageCodeConformance $conformance = new PackageCodeConformance(),
    ) {
    }

    /**
     * Inspect and statically validate one package.
     *
     * @param   string  $archiveFile  Canonical absolute package path.
     *
     * @return  ConformanceReport  Author-facing outcome over neutral coded findings.
     *
     * @throws  RuntimeException  When stable archive bytes or metadata cannot be read.
     *
     * @since   0.2.0
     */
    public function run(string $archiveFile): ConformanceReport
    {
        try {
            $inspection = $this->inspector->inspect($archiveFile);
        } catch (InvalidPackage $failure) {
            return new ConformanceReport(
                null,
                ['package_snapshot' => false],
                [$failure->finding],
            );
        }

        $package = $inspection->package;
        if (!$package->hasNoSafetyFindings()) {
            return new ConformanceReport(
                $inspection,
                ['package_snapshot' => true, 'archive_safety' => false],
                $package->safetyFindings,
            );
        }

        $findings = [];
        $paths = $package->paths();
        $sortedPaths = $paths;
        sort($sortedPaths, SORT_STRING);
        if ($paths !== $sortedPaths) {
            $findings[] = new PackageFinding(
                'archive.metadata.entry_order',
                'Archive entries are not sorted bytewise for deterministic packaging.',
            );
        }

        $metadataNormalized = $this->metadataFindings($package->archive, $paths, $findings);
        $package->assertCurrentArchiveIdentity();

        $evidence = (new PackageEvidenceInspector(
            new ZipArchiveContentReader(),
            $this->conformance,
        ))->inspect($package, PackageEvidenceScope::Authoring);
        $findings = [...$findings, ...$evidence->findings];
        if ($evidence->sbomState === PackageAttestationState::Absent) {
            $findings[] = new PackageFinding(
                'attestation.sbom.missing',
                'The package bill of materials is missing; rebuild with the SDK package builder.',
                'kumwe.sbom.json',
            );
        }
        if ($evidence->provenanceState === PackageAttestationState::Absent) {
            $findings[] = new PackageFinding(
                'attestation.provenance.missing',
                'The package provenance statement is missing; rebuild with the SDK package builder.',
                'kumwe.provenance.json',
            );
        }
        $this->sortFindings($findings);

        return new ConformanceReport(
            $inspection,
            [
                'package_snapshot' => true,
                'archive_safety' => true,
                'manifest_schema' => true,
                'deterministic_entry_order' => $paths === $sortedPaths,
                'deterministic_entry_metadata' => $metadataNormalized,
                ...$evidence->checks,
                'package_bill_of_materials' => $evidence->sbomState === PackageAttestationState::Verified,
                'package_provenance' => $evidence->provenanceState === PackageAttestationState::Verified,
            ],
            $findings,
        );
    }

    /**
     * Check deterministic ZIP metadata without expanding entry contents.
     *
     * @param   string                $archiveFile  Stable inspected archive path.
     * @param   list<string>          $paths        Expected central-directory paths.
     * @param   list<PackageFinding>  $findings     Finding list appended in place.
     *
     * @return  bool  True when every entry has canonical metadata.
     *
     * @throws  RuntimeException  When the archive cannot be reopened or its directory changed.
     *
     * @since   0.2.0
     */
    private function metadataFindings(string $archiveFile, array $paths, array &$findings): bool
    {
        $zip = new ZipArchive();
        if ($zip->open($archiveFile, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('The inspected extension package could not be reopened.');
        }
        if ($zip->numFiles !== count($paths)) {
            $zip->close();
            throw new RuntimeException('The extension package changed before metadata checks began.');
        }

        $valid = true;
        try {
            foreach ($paths as $index => $path) {
                $stat = $zip->statIndex($index, ZipArchive::FL_UNCHANGED);
                $attributes = $this->externalAttributes($zip, $index);
                $entryValid = is_array($stat)
                    && ($stat['name'] ?? null) === $path
                    && ($stat['comp_method'] ?? null) === ZipArchive::CM_STORE
                    && ($stat['mtime'] ?? null) === self::ZIP_EPOCH
                    && $attributes !== null
                    && $attributes['operating_system'] === ZipArchive::OPSYS_UNIX
                    && (($attributes['attributes'] >> 16) & 0xFFFF) === 0100644;
                if (!$entryValid) {
                    $findings[] = new PackageFinding(
                        'archive.metadata.entry',
                        sprintf('Archive metadata for %s is not deterministic.', $path),
                        $path,
                    );
                }
                $valid = $valid && $entryValid;
            }
        } finally {
            $zip->close();
        }

        return $valid;
    }

    /**
     * Read external attributes through the mutation-based ZipArchive API.
     *
     * @param   ZipArchive  $zip    Open archive handle.
     * @param   int         $index  Central-directory position.
     *
     * @return  ?array{operating_system: int, attributes: int}  Attributes, or null when unavailable.
     *
     * @since   0.2.0
     */
    private function externalAttributes(ZipArchive $zip, int $index): ?array
    {
        $operatingSystem = 0;
        $attributes = 0;
        if (!$zip->getExternalAttributesIndex($index, $operatingSystem, $attributes)) {
            return null;
        }
        if (!is_int($operatingSystem) || !is_int($attributes)) {
            return null;
        }

        return ['operating_system' => $operatingSystem, 'attributes' => $attributes];
    }

    /**
     * Sort neutral findings deterministically.
     *
     * @param   list<PackageFinding>  $findings  Finding list sorted in place.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    private function sortFindings(array &$findings): void
    {
        usort($findings, static fn (PackageFinding $left, PackageFinding $right): int => [
            $left->code,
            $left->path ?? '',
            $left->message,
        ] <=> [
            $right->code,
            $right->path ?? '',
            $right->message,
        ]);
    }
}
