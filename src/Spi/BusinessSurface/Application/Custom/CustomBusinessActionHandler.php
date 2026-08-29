<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessSurface\Application\Custom;

/** Executable bound to one manifest-declared custom business action. @since 0.2.0 */
interface CustomBusinessActionHandler
{
    /**
     * @param CustomBusinessActionCommand $command Host-authorized command validated against its signed declaration.
     *
     * @since 0.2.0
     */
    public function handle(CustomBusinessActionCommand $command): CustomBusinessActionResult;
}
