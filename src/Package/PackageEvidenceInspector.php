<?php

declare(strict_types=1);

namespace Kumwe\Extension\Package;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

/**
 * Inspects code and attestations from one immutable package snapshot without making an admission decision.
 *
 * Safety findings are returned without expanding entries. For a structurally clean snapshot, all static,
 * inventory and provenance facts are returned as deterministic coded findings. Transport and I/O failures
 * still throw because no honest evidence report can be produced from unreadable or changing bytes.
 *
 * @since  0.2.0
 */
final readonly class PackageEvidenceInspector
{
    /**
     * Bind evidence inspection to bounded content expansion and shared code checks.
     *
     * @param  ArchiveContentReader    $contents     Snapshot-bound content reader.
     * @param  PackageCodeConformance  $conformance  Code-free static checks.
     *
     * @since  0.2.0
     */
    public function __construct(
        private ArchiveContentReader $contents,
        private PackageCodeConformance $conformance,
    ) {
    }

    /**
     * Inspect one exact staged package snapshot.
     *
     * @param   InspectedPackage      $package  Archive identity, entries, manifest and limits from one read.
     * @param   PackageEvidenceScope  $scope    Package-only or complete authoring evidence.
     *
     * @return  PackageEvidenceReport  Neutral package evidence and coded findings.
     *
     * @throws  InvalidArgumentException  When the staged archive is no longer available.
     * @throws  RuntimeException  When bytes change or cannot be read completely.
     *
     * @since   0.2.0
     */
    public function inspect(
        InspectedPackage $package,
        PackageEvidenceScope $scope = PackageEvidenceScope::Authoring,
    ): PackageEvidenceReport
    {
        if (!$package->hasNoSafetyFindings()) {
            return new PackageEvidenceReport(
                $scope,
                PackageAttestationState::NotInspected,
                null,
                0,
                null,
                PackageAttestationState::NotInspected,
                null,
                null,
                null,
                ['archive_safety' => false],
                $package->safetyFindings,
            );
        }

        $digests = [];
        $paths = [];
        $sbomJson = null;
        $provenanceJson = null;
        $findings = [];
        $expandedBytes = 0;

        foreach ($this->contents->contents($package) as $path => $entry) {
            $paths[] = $path;
            if ($path === PackageBillOfMaterials::PATH) {
                $sbomJson = $entry;
                continue;
            }
            if ($path === PackageProvenance::PATH) {
                $provenanceJson = $entry;
                continue;
            }
            $digests[$path] = hash('sha256', $entry);
            $expandedBytes += strlen($entry);
            if ($this->conformance->isPhpPath($path)) {
                $findings = [...$findings, ...$this->conformance->phpFindings($path, $entry, $scope)];
            }
            if ($scope->includesAuthoring() && $this->conformance->isTextPath($path)) {
                $findings = [...$findings, ...$this->conformance->markerViolations($path, $entry)];
            }
        }

        $references = $this->conformance->referenceViolations($package->manifest, $paths);
        $findings = [...$findings, ...$references];
        if ($scope->includesAuthoring() && !in_array('README.md', $paths, true)) {
            $findings[] = new PackageFinding(
                'author.readme.missing',
                'The package carries no README.md for operators to read.',
                'README.md',
            );
        }

        $sbom = $this->inspectBillOfMaterials($sbomJson, $package, $digests);
        $findings = [...$findings, ...$sbom['findings']];
        $verifiedSbomSha256 = $sbom['state'] === PackageAttestationState::Verified ? $sbom['sha256'] : null;
        $provenance = $this->inspectProvenance(
            $provenanceJson,
            $package,
            $verifiedSbomSha256,
            count($digests),
            $expandedBytes,
        );
        $findings = [...$findings, ...$provenance['findings']];
        $this->sortFindings($findings);

        return new PackageEvidenceReport(
            $scope,
            $sbom['state'],
            $sbom['sha256'],
            $sbom['components'],
            $sbom['document'],
            $provenance['state'],
            $provenance['sha256'],
            $provenance['builder'],
            $provenance['document'],
            $this->checks($findings, $paths, $sbom['state'], $provenance['state'], $scope),
            $findings,
        );
    }

    /**
     * Reconcile the packaged bill of materials against manifest identity and actual entry digests.
     *
     * @param   ?string                $json     Raw inventory bytes, or null when absent.
     * @param   InspectedPackage       $package  Snapshot carrying the authoritative manifest.
     * @param   array<string, string>  $digests  Actual SHA-256 by non-attestation file path.
     *
     * @return  array{state: PackageAttestationState, sha256: ?string, components: int,
     *          document: ?array<string, mixed>, findings: list<PackageFinding>}  Inventory facts.
     *
     * @since   0.2.0
     */
    private function inspectBillOfMaterials(?string $json, InspectedPackage $package, array $digests): array
    {
        if ($json === null) {
            return [
                'state' => PackageAttestationState::Absent,
                'sha256' => null,
                'components' => 0,
                'document' => null,
                'findings' => [],
            ];
        }

        $sha256 = hash('sha256', $json);
        try {
            $document = PackageBillOfMaterials::fromJson($json);
            $mismatches = $document->reconcile($package->manifest, $digests);
            $components = $document->componentCount();
        } catch (JsonException | InvalidArgumentException $failure) {
            return [
                'state' => PackageAttestationState::Invalid,
                'sha256' => $sha256,
                'components' => 0,
                'document' => null,
                'findings' => [new PackageFinding(
                    'attestation.sbom.unreadable',
                    'The packaged bill of materials could not be read: ' . $failure->getMessage(),
                    PackageBillOfMaterials::PATH,
                )],
            ];
        }
        if ($mismatches !== []) {
            return [
                'state' => PackageAttestationState::Invalid,
                'sha256' => $sha256,
                'components' => $components,
                'document' => $document->document,
                'findings' => [new PackageFinding(
                    'attestation.sbom.mismatch',
                    'The packaged bill of materials does not describe this package: ' . implode(' ', $mismatches),
                    PackageBillOfMaterials::PATH,
                )],
            ];
        }

        return [
            'state' => PackageAttestationState::Verified,
            'sha256' => $sha256,
            'components' => $components,
            'document' => $document->document,
            'findings' => [],
        ];
    }

    /**
     * Reconcile provenance against a verified inventory and actual package measurements.
     *
     * @param   ?string           $json           Raw provenance bytes, or null when absent.
     * @param   InspectedPackage  $package        Snapshot carrying the authoritative manifest.
     * @param   ?string           $sbomSha256     Verified inventory digest, or null.
     * @param   int               $entryCount     Actual inventoried file count.
     * @param   int               $expandedBytes  Actual inventoried byte count.
     *
     * @return  array{state: PackageAttestationState, sha256: ?string, builder: ?string,
     *          document: ?array<string, mixed>, findings: list<PackageFinding>}  Provenance facts.
     *
     * @since   0.2.0
     */
    private function inspectProvenance(
        ?string $json,
        InspectedPackage $package,
        ?string $sbomSha256,
        int $entryCount,
        int $expandedBytes,
    ): array {
        if ($json === null) {
            return [
                'state' => PackageAttestationState::Absent,
                'sha256' => null,
                'builder' => null,
                'document' => null,
                'findings' => [],
            ];
        }

        $sha256 = hash('sha256', $json);
        try {
            $statement = PackageProvenance::fromJson($json);
        } catch (JsonException | InvalidArgumentException $failure) {
            return [
                'state' => PackageAttestationState::Invalid,
                'sha256' => $sha256,
                'builder' => null,
                'document' => null,
                'findings' => [new PackageFinding(
                    'attestation.provenance.unreadable',
                    'The packaged provenance statement could not be read: ' . $failure->getMessage(),
                    PackageProvenance::PATH,
                )],
            ];
        }
        if ($sbomSha256 === null) {
            return [
                'state' => PackageAttestationState::Invalid,
                'sha256' => $sha256,
                'builder' => $statement->builderReference(),
                'document' => $statement->statement,
                'findings' => [new PackageFinding(
                    'attestation.provenance.unbound',
                    'The extension package carries provenance without a verified bill of materials.',
                    PackageProvenance::PATH,
                )],
            ];
        }

        $mismatches = $statement->reconcile(
            $package->manifest,
            $sbomSha256,
            $entryCount,
            $expandedBytes,
        );
        if ($mismatches !== []) {
            return [
                'state' => PackageAttestationState::Invalid,
                'sha256' => $sha256,
                'builder' => $statement->builderReference(),
                'document' => $statement->statement,
                'findings' => [new PackageFinding(
                    'attestation.provenance.mismatch',
                    'The packaged provenance statement does not describe this package: ' . implode(' ', $mismatches),
                    PackageProvenance::PATH,
                )],
            ];
        }

        return [
            'state' => PackageAttestationState::Verified,
            'sha256' => $sha256,
            'builder' => $statement->builderReference(),
            'document' => $statement->statement,
            'findings' => [],
        ];
    }

    /**
     * Summarize objective check outcomes from stable finding codes.
     *
     * @param   list<PackageFinding>     $findings    Complete sorted finding list.
     * @param   list<string>             $paths       Expanded regular-file paths.
     * @param   PackageAttestationState  $sbom        Inventory state.
     * @param   PackageAttestationState  $provenance  Provenance state.
     * @param   PackageEvidenceScope     $scope       Evidence depth actually executed.
     *
     * @return  array<string, bool>  Named objective outcomes without host severity.
     *
     * @since   0.2.0
     */
    private function checks(
        array $findings,
        array $paths,
        PackageAttestationState $sbom,
        PackageAttestationState $provenance,
        PackageEvidenceScope $scope,
    ): array {
        $checks = [
            'archive_safety' => true,
            'static_php_syntax' => !$this->hasCode($findings, 'code.php.syntax'),
            'manifest_references' => !$this->hasCodePrefix($findings, 'manifest.reference.'),
        ];
        if ($scope->includesAuthoring()) {
            $checks = [
                ...$checks,
                'strict_types' => !$this->hasCode($findings, 'code.php.strict_types'),
                'complete_sources' => !$this->hasCode($findings, 'source.marker.unresolved'),
                'text_encoding' => !$this->hasCode($findings, 'source.text.encoding'),
                'authoring_readme' => in_array('README.md', $paths, true),
            ];
        }

        return [
            ...$checks,
            'sbom' => $sbom !== PackageAttestationState::Invalid,
            'provenance' => $provenance !== PackageAttestationState::Invalid,
        ];
    }

    /**
     * Report whether an exact finding code occurs.
     *
     * @param   list<PackageFinding>  $findings  Findings to scan.
     * @param   string                $code      Exact code.
     *
     * @return  bool  True when the code occurs.
     *
     * @since   0.2.0
     */
    private function hasCode(array $findings, string $code): bool
    {
        foreach ($findings as $finding) {
            if ($finding->code === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * Report whether any finding code begins with a stable namespace.
     *
     * @param   list<PackageFinding>  $findings  Findings to scan.
     * @param   string                $prefix    Code namespace including the trailing dot.
     *
     * @return  bool  True when a matching code occurs.
     *
     * @since   0.2.0
     */
    private function hasCodePrefix(array $findings, string $prefix): bool
    {
        foreach ($findings as $finding) {
            if (str_starts_with($finding->code, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sort findings deterministically without assigning a severity.
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
