<?php

declare(strict_types=1);

namespace @@PHP_NAMESPACE@@;

use @@PHP_NAMESPACE@@\Application\OverviewService;
use @@PHP_NAMESPACE@@\Delivery\Administrator\OverviewHandlerFactory as AdministratorOverviewFactory;
use @@PHP_NAMESPACE@@\Delivery\Portal\OverviewHandlerFactory as PortalOverviewFactory;
use @@PHP_NAMESPACE@@\Integration\DigestJobHandler;
use @@PHP_NAMESPACE@@\Integration\IntegrationLedger;
use @@PHP_NAMESPACE@@\Integration\ItemDomainListener;
use @@PHP_NAMESPACE@@\Integration\ItemIntegrationConsumer;
use @@PHP_NAMESPACE@@\Integration\ItemProjectionBuilder;
use Kumwe\Extension\Spi\Binding\ExtensionBindingProvider;
use Kumwe\Extension\Spi\Binding\ExtensionBindingRegistrar;
use Kumwe\Extension\Spi\Binding\Http\AdministratorRouteHandlerFactory;
use Kumwe\Extension\Spi\Binding\Http\PortalRouteHandlerFactory;
use Kumwe\Extension\Spi\Runtime\ExtensionContainer;
use LogicException;

/**
 * Composes extension-owned services and binds executable implementations to signed declarations.
 *
 * All declarative metadata lives exclusively in `kumwe.json`. The host parses that manifest once,
 * applies its semantic and admission rules, then admits only bindings whose identifiers and kinds
 * match declarations owned by this extension.
 *
 * @since  2.0.0
 */
final class Provider implements ExtensionBindingProvider
{
    /** @var string @since 2.0.0 */
    private const LEDGER = 'extension.@@EXTENSION_DOTTED@@.integration-ledger';

    /** @var string @since 2.0.0 */
    private const SERVICE = 'extension.@@EXTENSION_DOTTED@@.overview';

    /** @var string @since 2.0.0 */
    private const ADMINISTRATOR_FACTORY = 'extension.@@EXTENSION_DOTTED@@.administrator-factory';

    /** @var string @since 2.0.0 */
    private const PORTAL_FACTORY = 'extension.@@EXTENSION_DOTTED@@.portal-factory';

    /** @inheritDoc */
    public function register(ExtensionContainer $container): void
    {
        $container->share(
            self::LEDGER,
            static fn (ExtensionContainer $container): IntegrationLedger => new IntegrationLedger(),
        );
        $container->share(
            self::SERVICE,
            static function (ExtensionContainer $container): OverviewService {
                $ledger = $container->get(self::LEDGER);
                if (!$ledger instanceof IntegrationLedger) {
                    throw new LogicException('The component integration ledger is unavailable.');
                }

                return new OverviewService($ledger);
            },
        );
        $container->share(
            self::ADMINISTRATOR_FACTORY,
            static function (ExtensionContainer $container): AdministratorRouteHandlerFactory {
                $service = $container->get(self::SERVICE);
                if (!$service instanceof OverviewService) {
                    throw new LogicException('The component overview service is unavailable.');
                }

                return new AdministratorOverviewFactory($service);
            },
        );
        $container->share(
            self::PORTAL_FACTORY,
            static function (ExtensionContainer $container): PortalRouteHandlerFactory {
                $service = $container->get(self::SERVICE);
                if (!$service instanceof OverviewService) {
                    throw new LogicException('The component overview service is unavailable.');
                }

                return new PortalOverviewFactory($service);
            },
        );
    }

    /** @inheritDoc */
    public function bind(ExtensionBindingRegistrar $bindings, ExtensionContainer $container): void
    {
        $ledger = $container->get(self::LEDGER);
        $administrator = $container->get(self::ADMINISTRATOR_FACTORY);
        $portal = $container->get(self::PORTAL_FACTORY);
        if (
            !$ledger instanceof IntegrationLedger
            || !$administrator instanceof AdministratorRouteHandlerFactory
            || !$portal instanceof PortalRouteHandlerFactory
        ) {
            throw new LogicException('The component executable graph is incomplete.');
        }

        $bindings->administratorRoute('@@EXTENSION_DOTTED@@.administrator.index', $administrator);
        $bindings->portalRoute('@@EXTENSION_DOTTED@@.portal.index', $portal);
        $bindings->domainListener('@@EXTENSION_DOTTED@@.item_listener', new ItemDomainListener($ledger));
        $bindings->eventConsumer('@@EXTENSION_DOTTED@@.item_consumer', new ItemIntegrationConsumer($ledger));
        $bindings->jobHandler('@@EXTENSION_DOTTED@@.digest', new DigestJobHandler($ledger));
        $bindings->projection('@@EXTENSION_DOTTED@@.item_projection', new ItemProjectionBuilder());
    }
}
