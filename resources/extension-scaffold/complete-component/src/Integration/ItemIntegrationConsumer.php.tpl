<?php

declare(strict_types=1);

namespace @@PHP_NAMESPACE@@\Integration;

use InvalidArgumentException;
use Kumwe\Extension\Spi\Application\ExecutionContext;
use Kumwe\Extension\Spi\BusinessIntegration\Application\IntegrationEventHandler;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\EventConsumerDefinition;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\IntegrationEvent;

/**
 * Performs an idempotent process-local observation of durable item events.
 *
 * @since  2.0.0
 */
final readonly class ItemIntegrationConsumer implements IntegrationEventHandler
{
    /**
     * Bind the executable consumer to its signed declaration and diagnostic ledger.
     *
     * @param  IntegrationLedger  $ledger  Bounded diagnostic event ledger.
     *
     * @since  2.0.0
     */
    public function __construct(private IntegrationLedger $ledger)
    {
    }

    /**
     * Validate direct invocations and record the inbox-deduplicated event identity.
     *
     * @param   IntegrationEvent  $event    Durable item-observed event.
     * @param   ExecutionContext  $context  Worker-owned execution context.
     *
     * @return  void
     *
     * @throws  InvalidArgumentException  When the event falls outside the signed consumer contract.
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
        ) {
            throw new InvalidArgumentException('The item consumer received an unsupported event contract.');
        }
        $this->ledger->recordIntegration($event);
    }
}
