<?php

declare(strict_types=1);

namespace Kumwe\Extension\Package;

use InvalidArgumentException;

/**
 * Neutral, deterministic facts produced by inspecting one immutable package snapshot.
 *
 * Findings carry stable codes and messages but no severity or admission outcome. Hosts map those codes
 * to their own block, warn or off posture; author tooling may independently derive conformance.
 *
 * @since  0.2.0
 */
final readonly class PackageEvidenceReport
{
    /**
     * Retain all objective evidence and findings from one scan.
     *
     * @param   PackageEvidenceScope     $scope             Evidence depth actually executed.
     * @param   PackageAttestationState  $sbomState         Inventory inspection state.
     * @param   ?string                  $sbomSha256        SHA-256 of inventory bytes when present.
     * @param   int                      $sbomComponents    Verified file components.
     * @param   ?array<string, mixed>    $sbom              Readable CycloneDX document, or null.
     * @param   PackageAttestationState  $provenanceState   Provenance inspection state.
     * @param   ?string                  $provenanceSha256  SHA-256 of provenance bytes when present.
     * @param   ?string                  $builderReference  Publisher-asserted builder identity.
     * @param   ?array<string, mixed>    $provenance        Readable provenance document, or null.
     * @param   array<string, bool>      $checks            Named objective verification outcomes.
     * @param   list<PackageFinding>     $findings          Stable policy-neutral findings.
     *
     * @throws  InvalidArgumentException  When counts, checks or findings are malformed.
     *
     * @since   0.2.0
     */
    public function __construct(
        public PackageEvidenceScope $scope,
        public PackageAttestationState $sbomState,
        public ?string $sbomSha256,
        public int $sbomComponents,
        public ?array $sbom,
        public PackageAttestationState $provenanceState,
        public ?string $provenanceSha256,
        public ?string $builderReference,
        public ?array $provenance,
        public array $checks,
        public array $findings,
    ) {
        if ($sbomComponents < 0 || !array_is_list($findings)) {
            throw new InvalidArgumentException('Package evidence counts or finding lists are malformed.');
        }
        foreach ($checks as $name => $passed) {
            if (!is_string($name) || preg_match('/^[a-z][a-z0-9_]+$/D', $name) !== 1 || !is_bool($passed)) {
                throw new InvalidArgumentException('Package evidence checks must map stable names to booleans.');
            }
        }
        foreach ($findings as $finding) {
            if (!$finding instanceof PackageFinding) {
                throw new InvalidArgumentException('Every package evidence finding must be typed.');
            }
        }
    }

    /**
     * Export the policy-neutral evidence document.
     *
     * @return  array<string, mixed>  Stable JSON-compatible evidence fields.
     *
     * @since   0.2.0
     */
    public function toArray(): array
    {
        return [
            'format' => 'kumwe-extension-evidence-v2',
            'scope' => $this->scope->value,
            'sbom' => [
                'state' => $this->sbomState->value,
                'sha256' => $this->sbomSha256,
                'components' => $this->sbomComponents,
                'format' => 'CycloneDX/' . PackageBillOfMaterials::SPEC_VERSION,
            ],
            'provenance' => [
                'state' => $this->provenanceState->value,
                'sha256' => $this->provenanceSha256,
                'builder' => $this->builderReference,
            ],
            'checks' => $this->checks,
            'findings' => array_map(
                static fn (PackageFinding $finding): array => $finding->toArray(),
                $this->findings,
            ),
        ];
    }

    /**
     * Reduce evidence to concise policy-neutral audit metadata.
     *
     * @return  array{scope: string, sbom: string, provenance: string, checks_passed: bool, findings: int}
     *          Audit facts.
     *
     * @since   0.2.0
     */
    public function auditMetadata(): array
    {
        return [
            'scope' => $this->scope->value,
            'sbom' => $this->sbomState->value,
            'provenance' => $this->provenanceState->value,
            'checks_passed' => !in_array(false, $this->checks, true),
            'findings' => count($this->findings),
        ];
    }
}
