<?php

declare(strict_types=1);

namespace KumweContract\ManifestFour\Integration;

use Kumwe\Extension\Spi\Application\ExecutionContext;
use Kumwe\Extension\Spi\BusinessIntegration\Application\IntegrationEventHandler;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\EventConsumerDefinition;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\IntegrationEvent;

/**
 * Durable, queue-backed consumer half of the manifest-4 compatibility package.
 *
 * @since  2.0.0
 */
final readonly class ObservationConsumer implements IntegrationEventHandler
{
    /**
     * Bind the executable consumer to the declaration the manifest signed.
     *
     * @param  ObservationLedger  $ledger  Process-local evidence sink.
     *
     * @since  2.0.0
     */
    public function __construct(private ObservationLedger $ledger)
    {
    }

    /**
     * Record that the durable consumer ran, without any external effect.
     *
     * @param   IntegrationEvent  $event    Delivered event, already checked against its schema.
     * @param   ExecutionContext  $context  Authorization context the worker built for the event's owner.
     *
     * @return  void
     *
     * @since   2.0.0
     */
    public function handle(
        EventConsumerDefinition $declaration,
        IntegrationEvent $event,
        ExecutionContext $context,
    ): void
    {
        if (
            $declaration->eventType() !== $event->eventType()
            || !$declaration->acceptsVersion($event->schemaVersion())
            || !$event->sensitivity()->allowedBy($declaration->sensitivityCeiling())
        ) {
            throw new \InvalidArgumentException('The fixture consumer received an undeclared event.');
        }
        $this->ledger->record('consumer');
    }
}
