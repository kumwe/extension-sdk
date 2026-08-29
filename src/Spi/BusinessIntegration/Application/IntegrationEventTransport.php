<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessIntegration\Application;

use Kumwe\Extension\Spi\BusinessIntegration\Domain\IntegrationEvent;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\WebhookContributionDefinition;

/** Outbound executable bound to one manifest-declared webhook adapter. @since 0.2.0 */
interface IntegrationEventTransport
{
    /**
     * @param WebhookContributionDefinition $definition Signed adapter definition selected by its binding.
     * @param IntegrationEvent $event Host-validated event admitted by the adapter's declared profile.
     *
     * @since 0.2.0
     */
    public function publish(WebhookContributionDefinition $definition, IntegrationEvent $event): void;
}
