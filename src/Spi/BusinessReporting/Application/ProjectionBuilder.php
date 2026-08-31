<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessReporting\Application;

use Kumwe\Extension\Spi\BusinessReporting\Domain\ProjectionDefinition;

/** Deterministic executable bound to one manifest-declared projection. @since 0.2.0 */
interface ProjectionBuilder
{
    /** @since 0.2.0 */
    public function apply(
        ProjectionDefinition $definition,
        ProjectionEvent $event,
        ProjectionWriter $writer,
    ): void;
}
