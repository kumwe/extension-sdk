<?php

declare(strict_types=1);

namespace KumweContract\ManifestFour\Integration;

use Kumwe\Extension\Spi\BusinessIntegration\Application\IntegrationEventTransport;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\IntegrationEvent;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\WebhookContributionDefinition;

/**
 * Outbound adapter half of the manifest-4 compatibility package.
 *
 * The transport publishes nowhere. It exists so the webhook surface is exercised by a real
 * implementation of the public transport contract, including the sensitivity ceiling that decides what
 * an adapter is ever offered.
 *
 * @since  2.0.0
 */
final readonly class ObservationWebhookTransport implements IntegrationEventTransport
{
    /**
     * Bind the transport to the evidence sink the fixture reads back.
     *
     * @param  ObservationLedger  $ledger  Process-local evidence sink.
     *
     * @since  2.0.0
     */
    public function __construct(private ObservationLedger $ledger)
    {
    }

    /**
     * Record that delivery was attempted, without leaving the process.
     *
     * @param   IntegrationEvent  $event  Event offered for outbound delivery.
     *
     * @return  void
     *
     * @since   2.0.0
     */
    public function publish(WebhookContributionDefinition $declaration, IntegrationEvent $event): void
    {
        if (!$declaration->accepts($event->eventType(), $event->schemaVersion())) {
            throw new \InvalidArgumentException('The fixture webhook received an undeclared event.');
        }
        $this->ledger->record('webhook');
    }

}
