<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessSurface\Application\Custom;

/** Executable bound to one manifest-declared custom business view. @since 0.2.0 */
interface CustomBusinessViewHandler
{
    /** @param CustomBusinessViewQuery $query Validated view invocation selecting the records and parameters the view renders from. @since 0.2.0 */
    public function handle(CustomBusinessViewQuery $query): CustomBusinessViewResult;
}
