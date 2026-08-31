<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessIntegration\Application;

use Kumwe\Extension\Spi\BusinessIntegration\Domain\IntegrationEvent;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\WebhookContributionDefinition;

/** Outbound executable bound to one manifest-declared webhook adapter. @since 0.2.0 */
interface IntegrationEventTransport
{
    /** @since 0.2.0 */
    public function publish(WebhookContributionDefinition $definition, IntegrationEvent $event): void;
}
