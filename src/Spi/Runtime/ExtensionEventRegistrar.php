<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\Runtime;

/**
 * Subscription surface an extension is handed so it can react to Kumwe domain events.
 *
 * Extensions never see the dispatcher itself. This contract can only attach a listener, so an extension
 * cannot dispatch an event, detach somebody else's listener, or reorder the ones already attached. The
 * host's implementations narrow it further: the shipped registrar accepts only names in the `onKumwe*`
 * domain namespace, and the trust-enforcing wrapper the runtime loader places around it re-checks trust
 * on every dispatch so a quarantined extension's already-attached listeners stop running.
 *
 * @since  0.1.0
 */
interface ExtensionEventRegistrar
{
    /**
     * Attach a listener to a named domain event.
     *
     * @param   string                          $event     Name of the domain event to subscribe to.
     * @param   callable(ExtensionEvent): void  $listener  Invoked with each matching event once it is
     *          dispatched.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function listen(string $event, callable $listener): void;
}
