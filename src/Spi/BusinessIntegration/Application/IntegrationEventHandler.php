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
     * @param EventConsumerDefinition $definition Signed durable-consumer definition selected by its binding.
     * @param IntegrationEvent $event Host-validated event matching the declared type and schema versions.
     * @param ExecutionContext $context Host-issued delivery identity and correlation context.
     *
     * @since 0.2.0
     */
    public function handle(
        EventConsumerDefinition $definition,
        IntegrationEvent $event,
        ExecutionContext $context,
    ): void;
}
