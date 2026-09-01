<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessIntegration\Application;

use Kumwe\Extension\Spi\Application\ExecutionContext;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\IntegrationEvent;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\EventConsumerDefinition;

/** Idempotent executable bound to one manifest-declared durable consumer. @since 0.2.0 */
interface IntegrationEventHandler
{
    /**
     * Consume one durable event delivery, tolerating at-least-once redelivery.
     *
     * @param   EventConsumerDefinition  $definition  Manifest-declared consumer contract this handler is bound to.
     * @param   IntegrationEvent         $event       Immutable versioned event being delivered.
     * @param   ExecutionContext         $context     Host-issued execution capabilities for this delivery.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function handle(
        EventConsumerDefinition $definition,
        IntegrationEvent $event,
        ExecutionContext $context,
    ): void;
}
