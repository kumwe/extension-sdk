<?php

declare(strict_types=1);

namespace Kumwe\Extension\Package;

/**
 * Host-neutral depth of code-free evidence collection over one package snapshot.
 *
 * Package scope establishes archive, executable, reference and attestation facts. Authoring scope adds
 * source conventions useful to an author's CI. Neither scope assigns severity or an admission outcome.
 *
 * @since  0.2.0
 */
enum PackageEvidenceScope: string
{
    /**
     * Inspect package safety, PHP syntax, manifest references, inventory and provenance.
     *
     * @since  0.2.0
     */
    case Package = 'package';

    /**
     * Add strict types, unfinished markers, text encoding and README checks to package evidence.
     *
     * @since  0.2.0
     */
    case Authoring = 'authoring';

    /**
     * Report whether author-only source convention checks belong in this scan.
     *
     * @return  bool  True only for the complete authoring scope.
     *
     * @since   0.2.0
     */
    public function includesAuthoring(): bool
    {
        return $this === self::Authoring;
    }
}
