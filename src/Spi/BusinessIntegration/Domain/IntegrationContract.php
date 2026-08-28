<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessIntegration\Domain;

use Kumwe\Extension\Spi\Contribution\ContributionDefinition;

/**
 * Data-only declaration that may be compiled into a trusted runtime generation.
 *
 * @since  0.1.0
 */
interface IntegrationContract extends ContributionDefinition
{
    /**
     * Return the stable identifier for the integration contract.
     *
     * @return  string  Stable identifier used for ownership and collision checks.
     *
     * @since   0.1.0
     */
    public function identifier(): string;

    /**
     * Serialize the integration contract for durable storage or inspection.
     *
     * @return  array<string, mixed>  Canonical publication representation.
     *
     * @since   0.1.0
     */
    public function toArray(): array;
}
