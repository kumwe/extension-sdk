<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\Studio\Application\Preview;

/** Safe executable bound to one manifest-declared Studio preview capability. @since 0.2.0 */
interface StudioPreviewBlockRenderer
{
    /**
     * Produce the fixed safe presentation fragment for one block at one preview width.
     *
     * @param   StudioPreviewBlock          $block     Read-only view of the validated contributed block.
     * @param   StudioPreviewBindingResult  $binding   Resolved Blueprint value binding for the block's content.
     * @param   string                      $viewport  Semantic preview width, such as `compact` or `expanded`.
     *
     * @return  StudioPreviewBlockFragment  Safe allowlisted fragment the preview surface may emit.
     *
     * @since   0.2.0
     */
    public function render(
        StudioPreviewBlock $block,
        StudioPreviewBindingResult $binding,
        string $viewport,
    ): StudioPreviewBlockFragment;
}
