<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessSurface\Application\Custom;

/** Executable bound to one manifest-declared custom business action. @since 0.2.0 */
interface CustomBusinessActionHandler
{
    /** @since 0.2.0 */
    public function handle(CustomBusinessActionCommand $command): CustomBusinessActionResult;
}
