<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessIntegration\Application;

use Kumwe\Extension\Spi\BusinessIntegration\Domain\DomainEvent;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\DomainListenerDefinition;

/** Executable bound to one manifest-declared synchronous listener. @since 0.2.0 */
interface DomainEventHandler
{
    /** @since 0.2.0 */
    public function handle(DomainListenerDefinition $definition, DomainEvent $event): void;
}
