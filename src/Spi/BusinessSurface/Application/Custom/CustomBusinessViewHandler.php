<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessSurface\Application\Custom;

/** Executable bound to one manifest-declared custom business view. @since 0.2.0 */
interface CustomBusinessViewHandler
{
    /** @since 0.2.0 */
    public function handle(CustomBusinessViewQuery $query): CustomBusinessViewResult;
}
