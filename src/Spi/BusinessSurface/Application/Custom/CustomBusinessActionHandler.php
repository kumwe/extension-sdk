<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessSurface\Application\Custom;

/** Executable bound to one manifest-declared custom business action. @since 0.2.0 */
interface CustomBusinessActionHandler
{
    /** @param CustomBusinessActionCommand $command Validated, replay-aware invocation naming the target record and carrying the action payload. @since 0.2.0 */
    public function handle(CustomBusinessActionCommand $command): CustomBusinessActionResult;
}
