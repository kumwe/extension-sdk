<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessIntegration\Application;

use Kumwe\Extension\Spi\BusinessIntegration\Domain\IntegrationEvent;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\WebhookContributionDefinition;

/** Outbound executable bound to one manifest-declared webhook adapter. @since 0.2.0 */
interface IntegrationEventTransport
{
    /**
     * Publish one durable event delivery, tolerating at-least-once redelivery.
     *
     * @param   WebhookContributionDefinition  $definition  Outbound-adapter contract this transport is bound to.
     * @param   IntegrationEvent               $event       Durable event being routed through the adapter.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function publish(WebhookContributionDefinition $definition, IntegrationEvent $event): void;
}
