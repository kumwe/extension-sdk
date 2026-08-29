<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessSurface\Application\Custom;

/** Bounded result returned by a custom business view. @since 0.2.0 */
final readonly class CustomBusinessViewResult
{
    /** @param array<string, mixed> $data @since 0.2.0 */
    public function __construct(public array $data)
    {
        CustomBusinessPayload::assertObject($data, 'view result');
    }
}
