<?php

declare(strict_types=1);

namespace KumweContract\ManifestFour\Integration;

use InvalidArgumentException;
use Kumwe\Extension\Spi\BusinessReporting\Application\ProjectionBuilder;
use Kumwe\Extension\Spi\BusinessReporting\Application\ProjectionEvent;
use Kumwe\Extension\Spi\BusinessReporting\Application\ProjectionWriter;
use Kumwe\Extension\Spi\BusinessReporting\Domain\ProjectionDefinition;

/** Rebuildable projection executable for the schema-four fixture. @since 2.0.0 */
final readonly class ObservationProjectionBuilder implements ProjectionBuilder
{
    /** @inheritDoc */
    public function apply(
        ProjectionDefinition $declaration,
        ProjectionEvent $event,
        ProjectionWriter $writer,
    ): void
    {
        if (
            $declaration->identifier() !== 'kumwe.contract-manifest-four.activity'
            || !$declaration->accepts($event->type(), $event->schemaVersion())
        ) {
            throw new InvalidArgumentException('The compatibility projection received an undeclared source event.');
        }
        $message = $event->payload()['message'] ?? null;
        if (!is_string($message) || $message === '') {
            throw new InvalidArgumentException('The compatibility projection source payload is invalid.');
        }
        $writer->put(
            ['aggregate_id' => $event->id()],
            ['aggregate_id' => $event->id(), 'message' => $message],
        );
    }
}
