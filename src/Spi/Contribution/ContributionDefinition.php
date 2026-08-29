<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\Contribution;

/**
 * Contract for one declarable thing an extension contributes, whatever kind it is.
 *
 * Every canonical contribution exposes a stable identifier and deterministic export, allowing a host
 * to index the manifest graph without reconstructing or translating the declaration.
 *
 * @since  0.1.0
 */
interface ContributionDefinition
{
    /**
     * The identifier this contribution claims within its owner's namespace.
     *
     * @return  string  Identifier unique among contributions of the same kind, such as `core.dashboard`.
     *
     * @since   0.1.0
     */
    public function identifier(): string;

    /**
     * Export the contribution as its deterministic manifest structure.
     *
     * @return  array<string, mixed>  Every declared field of this contribution, keyed by field name.
     *
     * @since   0.1.0
     */
    public function toArray(): array;
}
