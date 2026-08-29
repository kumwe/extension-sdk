<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessReporting\Application;

use Kumwe\Extension\Spi\BusinessReporting\Domain\ProjectionDefinition;

/** Deterministic executable bound to one manifest-declared projection. @since 0.2.0 */
interface ProjectionBuilder
{
    /**
     * @param ProjectionDefinition $definition Signed, owner-checked projection definition selected by its binding.
     * @param ProjectionEvent $event Host-validated event admitted by the definition's source graph.
     * @param ProjectionWriter $writer Bounded host writer for this projection's derived rows.
     *
     * @since 0.2.0
     */
    public function apply(
        ProjectionDefinition $definition,
        ProjectionEvent $event,
        ProjectionWriter $writer,
    ): void;
}
