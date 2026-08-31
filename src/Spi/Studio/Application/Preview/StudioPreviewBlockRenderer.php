<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\Studio\Application\Preview;

/** Safe executable bound to one manifest-declared Studio preview capability. @since 0.2.0 */
interface StudioPreviewBlockRenderer
{
    /** @since 0.2.0 */
    public function render(
        StudioPreviewBlock $block,
        StudioPreviewBindingResult $binding,
        string $viewport,
    ): StudioPreviewBlockFragment;
}
