<?php

declare(strict_types=1);

namespace KumweContract\ManifestFour;

use Kumwe\Extension\Spi\Binding\ExtensionBindingProvider;
use Kumwe\Extension\Spi\Binding\ExtensionBindingRegistrar;
use Kumwe\Extension\Spi\Runtime\ExtensionContainer;
use KumweContract\ManifestFour\Integration\ObservationConsumer;
use KumweContract\ManifestFour\Integration\ObservationLedger;
use KumweContract\ManifestFour\Integration\ObservationListener;
use KumweContract\ManifestFour\Integration\ObservationProjectionBuilder;
use KumweContract\ManifestFour\Integration\ObservationWebhookTransport;
use KumweContract\ManifestFour\Integration\SummarizeJob;
use LogicException;

/** Schema-four fixture preserving every SPI-2 executable through canonical bindings. @since 2.0.0 */
final class Provider implements ExtensionBindingProvider
{
    /** @var string @since 2.0.0 */
    private const LEDGER = 'extension.kumwe.contract-manifest-four.ledger';

    /** @inheritDoc */
    public function register(ExtensionContainer $container): void
    {
        $container->share(
            self::LEDGER,
            static fn (ExtensionContainer $container): ObservationLedger => new ObservationLedger(),
        );
    }

    /** @inheritDoc */
    public function bind(ExtensionBindingRegistrar $bindings, ExtensionContainer $container): void
    {
        $ledger = $container->get(self::LEDGER);
        if (!$ledger instanceof ObservationLedger) {
            throw new LogicException('The manifest-four compatibility ledger is unavailable.');
        }

        $bindings->domainListener(
            'kumwe.contract-manifest-four.observe-now',
            new ObservationListener($ledger),
        );
        $bindings->eventConsumer(
            'kumwe.contract-manifest-four.observe-later',
            new ObservationConsumer($ledger),
        );
        $bindings->jobHandler('kumwe.contract-manifest-four.summarize', new SummarizeJob($ledger));
        $bindings->projection(
            'kumwe.contract-manifest-four.activity',
            new ObservationProjectionBuilder(),
        );
        $bindings->webhook(
            'kumwe.contract-manifest-four.observed-webhook',
            new ObservationWebhookTransport($ledger),
        );
    }
}
