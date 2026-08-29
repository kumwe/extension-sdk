<?php

declare(strict_types=1);

namespace Kumwe\Extension\Toolchain;

use InvalidArgumentException;
use Kumwe\Extension\Package\PackageFinding;

/**
 * Stable author-tool result derived from neutral package findings.
 *
 * A host does not consume `conforms()` as admission policy. It consumes the underlying coded findings
 * and applies its own posture; this author-facing report treats every failed check as nonconforming.
 *
 * @since  0.2.0
 */
final readonly class ConformanceReport
{
    /**
     * Retain the optional snapshot, objective checks and neutral findings.
     *
     * @param   ?PackageInspection     $inspection  Snapshot, or null when package data was too malformed.
     * @param   array<string, bool>    $checks      Named objective outcomes.
     * @param   list<PackageFinding>   $findings    Stable neutral findings.
     *
     * @throws  InvalidArgumentException  When findings are not a typed list.
     *
     * @since   0.2.0
     */
    public function __construct(
        public ?PackageInspection $inspection,
        public array $checks,
        public array $findings,
    ) {
        if (!array_is_list($findings)) {
            throw new InvalidArgumentException('Conformance findings must be a list.');
        }
        foreach ($findings as $finding) {
            if (!$finding instanceof PackageFinding) {
                throw new InvalidArgumentException('Every conformance finding must be typed.');
            }
        }
    }

    /**
     * Derive the author-tool conformance outcome.
     *
     * @return  bool  True only when a complete snapshot has no finding or failed check.
     *
     * @since   0.2.0
     */
    public function conforms(): bool
    {
        return $this->inspection !== null
            && $this->findings === []
            && !in_array(false, $this->checks, true);
    }

    /**
     * Export the stable author-facing report.
     *
     * @return  array<string, mixed>  Package identity, checks and coded findings.
     *
     * @since   0.2.0
     */
    public function toArray(): array
    {
        return [
            'format' => 'kumwe-extension-conformance-v2',
            'conforms' => $this->conforms(),
            'package' => $this->inspection?->toArray(),
            'checks' => $this->checks,
            'findings' => array_map(
                static fn (PackageFinding $finding): array => $finding->toArray(),
                $this->findings,
            ),
        ];
    }
}
