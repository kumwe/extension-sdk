<?php

declare(strict_types=1);

namespace @@PHP_NAMESPACE@@\Integration;

use InvalidArgumentException;
use Kumwe\Extension\Spi\BusinessReporting\Application\ProjectionBuilder;
use Kumwe\Extension\Spi\BusinessReporting\Application\ProjectionEvent;
use Kumwe\Extension\Spi\BusinessReporting\Application\ProjectionWriter;
use Kumwe\Extension\Spi\BusinessReporting\Domain\ProjectionDefinition;

/**
 * Deterministically derives one reporting row from each manifest-declared event.
 *
 * @since  2.0.0
 */
final readonly class ItemProjectionBuilder implements ProjectionBuilder
{
    /** @inheritDoc */
    public function apply(
        ProjectionDefinition $declaration,
        ProjectionEvent $event,
        ProjectionWriter $writer,
    ): void
    {
        $payload = $event->payload();
        $itemId = $payload['item_id'] ?? null;
        $title = $payload['title'] ?? null;
        if (
            $declaration->identifier() !== '@@EXTENSION_DOTTED@@.item_projection'
            || !$declaration->accepts($event)
            || !is_string($itemId)
            || $itemId === ''
            || mb_strlen($itemId) > 191
            || !is_string($title)
            || $title === ''
            || mb_strlen($title) > 191
        ) {
            throw new InvalidArgumentException('The item projection event is outside its signed contract.');
        }
        $writer->put(['item_id' => $itemId], ['item_id' => $itemId, 'title' => $title]);
    }
}
