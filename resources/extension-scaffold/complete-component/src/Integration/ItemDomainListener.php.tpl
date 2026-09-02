<?php

declare(strict_types=1);

namespace @@PHP_NAMESPACE@@\Integration;

use InvalidArgumentException;
use Kumwe\Extension\Spi\BusinessIntegration\Application\DomainEventHandler;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\DomainEvent;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\DomainListenerDefinition;

/**
 * Validates and records transaction-local item-observed facts.
 *
 * @since  2.0.0
 */
final readonly class ItemDomainListener implements DomainEventHandler
{
    /**
     * Bind listener observations to the shared bounded ledger.
     *
     * @param  IntegrationLedger  $ledger  Bounded diagnostic event ledger.
     *
     * @since  2.0.0
     */
    public function __construct(private IntegrationLedger $ledger)
    {
    }

    /**
     * Validate direct invocations and record the immutable event identity.
     *
     * @param   DomainEvent  $event  Transaction-local item-observed event.
     *
     * @return  void
     *
     * @throws  InvalidArgumentException  When called with an undeclared contract revision.
     *
     * @since   2.0.0
     */
    public function handle(DomainListenerDefinition $declaration, DomainEvent $event): void
    {
        if (!$declaration->accepts($event->eventType(), $event->schemaVersion(), $event->sensitivity())) {
            throw new InvalidArgumentException('The item listener received an unsupported event contract.');
        }
        $this->ledger->recordDomain($event);
    }
}
