<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessIntegration\Application;

use Kumwe\Extension\Spi\BusinessIntegration\Domain\DomainEvent;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\DomainListenerDefinition;

/** Executable bound to one manifest-declared synchronous listener. @since 0.2.0 */
interface DomainEventHandler
{
    /**
     * Observe one synchronous event inside the authoritative transaction; throwing aborts the mutation.
     *
     * @param   DomainListenerDefinition  $definition  Manifest-declared listener contract this handler is bound to.
     * @param   DomainEvent               $event       Transaction-local event being delivered synchronously.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function handle(DomainListenerDefinition $definition, DomainEvent $event): void;
}
