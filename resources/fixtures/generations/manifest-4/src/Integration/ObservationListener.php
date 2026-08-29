<?php

declare(strict_types=1);

namespace KumweContract\ManifestFour\Integration;

use Kumwe\Extension\Spi\BusinessIntegration\Application\DomainEventHandler;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\DomainEvent;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\DomainListenerDefinition;

/**
 * Synchronous listener half of the manifest-4 compatibility package.
 *
 * @since  2.0.0
 */
final readonly class ObservationListener implements DomainEventHandler
{
    /**
     * Bind the executable listener to the declaration the manifest signed.
     *
     * @param  ObservationLedger  $ledger  Process-local evidence sink.
     *
     * @since  2.0.0
     */
    public function __construct(private ObservationLedger $ledger)
    {
    }

    /**
     * Record that the transaction-local listener ran, without any external effect.
     *
     * @param   DomainEvent  $event  Mutation event executing inside the authoritative transaction.
     *
     * @return  void
     *
     * @since   2.0.0
     */
    public function handle(DomainListenerDefinition $declaration, DomainEvent $event): void
    {
        if (!$declaration->accepts(
            $event->eventType(),
            $event->schemaVersion(),
            $event->sensitivity(),
        )) {
            throw new \InvalidArgumentException('The fixture listener received an undeclared event.');
        }
        $this->ledger->record('domain-listener');
    }
}
