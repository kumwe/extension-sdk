<?php

declare(strict_types=1);

namespace Kumwe\Extension\Toolchain;

use Kumwe\Extension\Package\InspectedPackage;
use Kumwe\Extension\Package\PackageLimits;

/**
 * Toolchain facade over the package domain's closed same-bytes snapshot constructor.
 *
 * Callers must retain input archives in a privately owned, non-shared staging directory while the
 * resulting snapshot is in use. Current-file identity checks supplement, but cannot replace, that
 * ownership boundary for pathname operations.
 *
 * @since  0.2.0
 */
final readonly class PackageInspector
{
    /**
     * Bind every inspection to one immutable resource budget.
     *
     * @param  PackageLimits  $limits  Exact limits carried into each resulting snapshot.
     *
     * @since  0.2.0
     */
    public function __construct(private PackageLimits $limits = new PackageLimits())
    {
    }

    /**
     * Inspect one canonical ZIP through the non-forgeable package snapshot factory.
     *
     * @param   string  $archiveFile  Canonical absolute path in caller-controlled private staging.
     *
     * @return  PackageInspection  Toolchain view over the immutable inspected snapshot.
     *
     * @since   0.2.0
     */
    public function inspect(string $archiveFile): PackageInspection
    {
        return new PackageInspection(InspectedPackage::inspect($archiveFile, $this->limits));
    }

    /**
     * Return the exact resource budget used and embedded in every snapshot.
     *
     * @return  PackageLimits  Immutable shared package limits.
     *
     * @since   0.2.0
     */
    public function limits(): PackageLimits
    {
        return $this->limits;
    }
}
