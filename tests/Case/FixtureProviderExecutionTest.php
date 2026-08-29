<?php

/**
 * Executes every canonical generation provider and the rendered complete-component provider.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use Closure;
use DateTimeImmutable;
use InvalidArgumentException;
use Kumwe\Conversion\Provider\MoneyRateProvider;
use Kumwe\Conversion\Provider\UnitConversionProvider;
use Kumwe\Extension\Manifest\ExtensionManifest;
use Kumwe\Extension\Spi\Application\Automation\JobHandler;
use Kumwe\Extension\Spi\Application\ExecutionContext;
use Kumwe\Extension\Spi\Application\ExtensionServiceProvider;
use Kumwe\Extension\Spi\Binding\ExecutableBindingKind;
use Kumwe\Extension\Spi\Binding\ExecutableBindingRequirements;
use Kumwe\Extension\Spi\Binding\ExtensionBindingProvider;
use Kumwe\Extension\Spi\Binding\ExtensionBindingRegistrar;
use Kumwe\Extension\Spi\Binding\Http\AdministratorRouteHandlerFactory;
use Kumwe\Extension\Spi\Binding\Http\AdministratorRouteRenderer;
use Kumwe\Extension\Spi\Binding\Http\PortalRouteHandlerFactory;
use Kumwe\Extension\Spi\Binding\Http\PortalRouteRenderer;
use Kumwe\Extension\Spi\BusinessIntegration\Application\DomainEventHandler;
use Kumwe\Extension\Spi\BusinessIntegration\Application\IntegrationEventHandler;
use Kumwe\Extension\Spi\BusinessIntegration\Application\IntegrationEventTransport;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\DomainEvent;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\EventSensitivity;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\IntegrationEvent;
use Kumwe\Extension\Spi\BusinessReporting\Application\ProjectionBuilder;
use Kumwe\Extension\Spi\BusinessReporting\Application\ProjectionEvent;
use Kumwe\Extension\Spi\BusinessReporting\Application\ProjectionWriter;
use Kumwe\Extension\Spi\BusinessSurface\Application\Custom\CustomBusinessActionHandler;
use Kumwe\Extension\Spi\BusinessSurface\Application\Custom\CustomBusinessViewHandler;
use Kumwe\Extension\Spi\BusinessSurface\Presentation\Field\FieldPresentationContext;
use Kumwe\Extension\Spi\BusinessSurface\Presentation\Field\FieldPresentationInput;
use Kumwe\Extension\Spi\BusinessSurface\Presentation\Field\FieldPresenter;
use Kumwe\Extension\Spi\Runtime\BootableExtension;
use Kumwe\Extension\Spi\Runtime\ExtensionContainer;
use Kumwe\Extension\Tests\TestCase;
use Kumwe\Extension\Toolchain\ComponentScaffolder;
use Kumwe\Extension\Toolchain\ScaffoldRequest;
use Kumwe\Producer\Render\BlockRenderer;
use Kumwe\Producer\Render\CompositionRenderer;
use Kumwe\Producer\Render\RenderContext;
use Kumwe\Producer\Render\RenderState;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use SplFileInfo;

/**
 * Proves signed executable identifiers, provider code, canonical definitions and callbacks stay aligned.
 *
 * @since 0.2.0
 */
final class FixtureProviderExecutionTest extends TestCase
{
    /**
     * Register, bind and exercise all six SDK-owned manifest generations.
     *
     * @return void
     *
     * @since 0.2.0
     */
    public function testEveryGenerationProviderSatisfiesAndExecutesItsManifest(): void
    {
        $prefixes = [];
        $loader = self::fixtureLoader($prefixes);
        spl_autoload_register($loader);
        try {
            for ($generation = 1; $generation <= 6; ++$generation) {
                $root = dirname(__DIR__, 2) . '/resources/fixtures/generations/manifest-' . $generation;
                [$manifest, $container, $registrar] = $this->activate($root, $prefixes);
                $this->invokeGeneration($generation, $manifest, $container, $registrar);
            }
        } finally {
            spl_autoload_unregister($loader);
        }
    }

    /**
     * Render the complete scaffold, strict-autoload its provider and invoke every generated binding.
     *
     * @return void
     *
     * @since 0.2.0
     */
    public function testRenderedCompleteScaffoldSatisfiesAndExecutesItsManifest(): void
    {
        $workspace = self::workspace();
        $target = $workspace . '/component';
        $prefixes = [];
        $loader = self::fixtureLoader($prefixes);
        spl_autoload_register($loader);
        try {
            (new ComponentScaffolder())->scaffold(new ScaffoldRequest(
                'smoke/complete-component',
                'Smoke\\CompleteComponent',
                $target,
                'Complete Component',
                '1.0.0',
            ));
            [$manifest, $container, $registrar] = $this->activate($target, $prefixes);

            $administrator = $registrar->implementation(
                ExecutableBindingKind::AdministratorRoute,
                'smoke.complete-component.administrator.index',
            );
            $portal = $registrar->implementation(
                ExecutableBindingKind::PortalRoute,
                'smoke.complete-component.portal.index',
            );
            $this->assertTrue($administrator instanceof AdministratorRouteHandlerFactory, 'Administrator route is executable.');
            $this->assertTrue($portal instanceof PortalRouteHandlerFactory, 'Portal route is executable.');
            $this->assertTrue(
                $administrator->create(new FixtureAdministratorRenderer()) instanceof RequestHandlerInterface,
                'Administrator factory accepts only a host-bound route renderer.',
            );
            $this->assertTrue(
                $portal->create(new FixturePortalRenderer()) instanceof RequestHandlerInterface,
                'Portal factory accepts only a host-bound route renderer.',
            );

            $event = new FixtureEvent(
                'smoke.complete-component.item_observed',
                EventSensitivity::INTERNAL,
                ['item_id' => 'item-1', 'title' => 'First item'],
            );
            $context = new FixtureExecutionContext();
            $listener = $registrar->implementation(
                ExecutableBindingKind::DomainListener,
                'smoke.complete-component.item_listener',
            );
            $consumer = $registrar->implementation(
                ExecutableBindingKind::EventConsumer,
                'smoke.complete-component.item_consumer',
            );
            $job = $registrar->implementation(ExecutableBindingKind::JobHandler, 'smoke.complete-component.digest');
            $projection = $registrar->implementation(
                ExecutableBindingKind::Projection,
                'smoke.complete-component.item_projection',
            );
            $this->assertTrue($listener instanceof DomainEventHandler, 'Generated listener is typed.');
            $this->assertTrue($consumer instanceof IntegrationEventHandler, 'Generated consumer is typed.');
            $this->assertTrue($job instanceof JobHandler, 'Generated job is typed.');
            $this->assertTrue($projection instanceof ProjectionBuilder, 'Generated projection is typed.');

            $contributions = $manifest->contributions();
            $listener->handle(
                $contributions->domainListener('smoke.complete-component.item_listener')
                    ?? throw new LogicException('Generated listener definition is absent.'),
                $event,
            );
            $consumer->handle(
                $contributions->eventConsumer('smoke.complete-component.item_consumer')
                    ?? throw new LogicException('Generated consumer definition is absent.'),
                $event,
                $context,
            );
            $job->handle(
                $contributions->job('smoke.complete-component.digest')
                    ?? throw new LogicException('Generated job definition is absent.'),
                ['message' => 'daily summary'],
                $context,
            );
            $writer = new FixtureProjectionWriter();
            $projection->apply(
                $contributions->projection('smoke.complete-component.item_projection')
                    ?? throw new LogicException('Generated projection definition is absent.'),
                new FixtureProjectionEvent(
                    'smoke.complete-component.item_observed',
                    ['item_id' => 'item-1', 'title' => 'First item'],
                ),
                $writer,
            );
            $this->assertSame(
                [['key' => ['item_id' => 'item-1'], 'values' => ['item_id' => 'item-1', 'title' => 'First item']]],
                $writer->puts,
                'Generated projection writes the canonical declared row.',
            );
            $ledger = $container->get('extension.smoke.complete-component.integration-ledger');
            $this->assertTrue(method_exists($ledger, 'snapshot'), 'Generated diagnostic ledger is available.');
            $this->assertSame(
                ['domain_events' => 1, 'integration_events' => 1, 'latest_job_digest' => hash('sha256', 'daily summary')],
                $ledger->snapshot(),
                'Every generated integration callback ran through its provider binding.',
            );
        } finally {
            spl_autoload_unregister($loader);
            self::removeTree($workspace);
        }
    }

    /**
     * Activate one package using only its canonical manifest, provider and owner-scoped ports.
     *
     * @param string $root Absolute package source root.
     * @param array<string, string> $prefixes Mutable namespace-prefix map for the fixture loader.
     *
     * @return array{ExtensionManifest, FixtureContainer, FixtureBindingRegistrar} Activated fixture state.
     *
     * @since 0.2.0
     */
    private function activate(string $root, array &$prefixes): array
    {
        $bytes = file_get_contents($root . '/kumwe.json');
        $this->assertTrue(is_string($bytes), 'A canonical fixture manifest is readable.');
        $manifest = ExtensionManifest::fromJson($bytes);
        foreach ($manifest->autoload() as $prefix => $directory) {
            $prefixes[$prefix] = $root . '/' . rtrim($directory, '/');
        }
        $providerClass = $manifest->serviceProvider();
        $this->assertTrue(class_exists($providerClass), 'The manifest provider strict-autoloads from its own map.');
        $provider = new $providerClass();
        $this->assertTrue($provider instanceof ExtensionServiceProvider, 'The provider implements the canonical entry point.');

        $container = new FixtureContainer();
        $provider->register($container);
        $requirements = $manifest->contributions()->executableBindingRequirements();
        $registrar = new FixtureBindingRegistrar($requirements);
        if ($provider instanceof ExtensionBindingProvider) {
            $provider->bind($registrar, $container);
        }
        $requirements->assertSatisfied($registrar->inventory());
        if ($provider instanceof BootableExtension) {
            $provider->boot($container);
        }

        return [$manifest, $container, $registrar];
    }

    /**
     * Invoke the executable behavior unique to one canonical generation.
     *
     * @param int $generation Manifest generation number.
     * @param ExtensionManifest $manifest Parsed canonical manifest.
     * @param FixtureContainer $container Activated owner-scoped container.
     * @param FixtureBindingRegistrar $registrar Exact binding recorder.
     *
     * @return void
     *
     * @since 0.2.0
     */
    private function invokeGeneration(
        int $generation,
        ExtensionManifest $manifest,
        FixtureContainer $container,
        FixtureBindingRegistrar $registrar,
    ): void {
        if ($generation === 1) {
            $greeting = $container->get('extension.kumwe.contract-manifest-one.greeting');
            $this->assertTrue(method_exists($greeting, 'booted') && $greeting->booted(), 'Schema one boot executes.');
            return;
        }
        if (in_array($generation, [2, 5], true)) {
            $this->assertSame([], $registrar->inventory(), 'Declarative-only generations require no executable binding.');
            return;
        }
        if ($generation === 3) {
            $presenter = $registrar->implementation(
                ExecutableBindingKind::FieldPresenter,
                'kumwe.contract-manifest-three.grade',
            );
            $this->assertTrue($presenter instanceof FieldPresenter, 'Schema three binds its presenter.');
            $model = $presenter->present(new FieldPresentationInput(
                'grade',
                'Grade',
                'kumwe.contract-manifest-three.grade',
                true,
                false,
                false,
                false,
                false,
                FieldPresentationContext::Detail,
                'A',
            ));
            $this->assertSame('A', $model->display, 'Schema three presenter executes with the canonical input.');
            return;
        }
        if ($generation === 4) {
            $this->invokeManifestFour($manifest, $container, $registrar);
            return;
        }
        if ($generation === 6) {
            $renderer = $registrar->implementation(
                ExecutableBindingKind::StudioPreviewRenderer,
                'kumwe.contract-manifest-six/grid-preview',
            );
            $this->assertTrue($renderer instanceof BlockRenderer, 'Schema six binds Producer directly.');
            $node = (object) ['properties' => (object) ['columns' => 4]];
            $state = new RenderState(new RenderContext(), new CompositionRenderer());
            $this->assertStringContains(
                'Contributed grid: 4 columns',
                $renderer->render($node, 'scope-fixture', $state),
                'Schema six Producer renderer executes.',
            );
            return;
        }
        throw new InvalidArgumentException('An unknown fixture generation was selected.');
    }

    /**
     * Invoke every schema-four integration callback against its canonical definition.
     *
     * @param ExtensionManifest $manifest Parsed schema-four manifest.
     * @param FixtureContainer $container Activated owner-scoped container.
     * @param FixtureBindingRegistrar $registrar Exact binding recorder.
     *
     * @return void
     *
     * @since 0.2.0
     */
    private function invokeManifestFour(
        ExtensionManifest $manifest,
        FixtureContainer $container,
        FixtureBindingRegistrar $registrar,
    ): void {
        $event = new FixtureEvent(
            'kumwe.contract-manifest-four.observed',
            EventSensitivity::PUBLIC,
            ['message' => 'observed'],
        );
        $context = new FixtureExecutionContext();
        $contributions = $manifest->contributions();
        $listener = $registrar->implementation(
            ExecutableBindingKind::DomainListener,
            'kumwe.contract-manifest-four.observe-now',
        );
        $consumer = $registrar->implementation(
            ExecutableBindingKind::EventConsumer,
            'kumwe.contract-manifest-four.observe-later',
        );
        $job = $registrar->implementation(ExecutableBindingKind::JobHandler, 'kumwe.contract-manifest-four.summarize');
        $projection = $registrar->implementation(
            ExecutableBindingKind::Projection,
            'kumwe.contract-manifest-four.activity',
        );
        $webhook = $registrar->implementation(
            ExecutableBindingKind::Webhook,
            'kumwe.contract-manifest-four.observed-webhook',
        );
        $this->assertTrue($listener instanceof DomainEventHandler, 'Schema four listener is typed.');
        $this->assertTrue($consumer instanceof IntegrationEventHandler, 'Schema four consumer is typed.');
        $this->assertTrue($job instanceof JobHandler, 'Schema four job is typed.');
        $this->assertTrue($projection instanceof ProjectionBuilder, 'Schema four projection is typed.');
        $this->assertTrue($webhook instanceof IntegrationEventTransport, 'Schema four webhook is typed.');

        $listener->handle(
            $contributions->domainListener('kumwe.contract-manifest-four.observe-now')
                ?? throw new LogicException('Schema-four listener definition is absent.'),
            $event,
        );
        $consumer->handle(
            $contributions->eventConsumer('kumwe.contract-manifest-four.observe-later')
                ?? throw new LogicException('Schema-four consumer definition is absent.'),
            $event,
            $context,
        );
        $job->handle(
            $contributions->job('kumwe.contract-manifest-four.summarize')
                ?? throw new LogicException('Schema-four job definition is absent.'),
            ['site_identifier' => 'default', 'limit' => 10],
            $context,
        );
        $writer = new FixtureProjectionWriter();
        $projection->apply(
            $contributions->projection('kumwe.contract-manifest-four.activity')
                ?? throw new LogicException('Schema-four projection definition is absent.'),
            new FixtureProjectionEvent('kumwe.contract-manifest-four.observed', ['message' => 'observed']),
            $writer,
        );
        $webhook->publish(
            $contributions->webhook('kumwe.contract-manifest-four.observed-webhook')
                ?? throw new LogicException('Schema-four webhook definition is absent.'),
            $event,
        );
        $this->assertSame(
            [['key' => ['aggregate_id' => 'aggregate-1'], 'values' => ['aggregate_id' => 'aggregate-1', 'message' => 'observed']]],
            $writer->puts,
            'Schema-four projection executes its declared source contract.',
        );
        $ledger = $container->get('extension.kumwe.contract-manifest-four.ledger');
        $this->assertTrue(method_exists($ledger, 'entries'), 'Schema-four ledger is available.');
        $this->assertSame(
            ['domain-listener', 'consumer', 'job', 'webhook'],
            $ledger->entries(),
            'Every schema-four integration binding executed.',
        );
    }

    /**
     * Build a PSR-4 loader for source fixtures without adding them to the SDK production autoloader.
     *
     * @param array<string, string> $prefixes Mutable namespace-to-directory map.
     *
     * @return Closure(string): void Fixture loader.
     *
     * @since 0.2.0
     */
    private static function fixtureLoader(array &$prefixes): Closure
    {
        return static function (string $class) use (&$prefixes): void {
            foreach ($prefixes as $prefix => $root) {
                if (!str_starts_with($class, $prefix)) {
                    continue;
                }
                $path = $root . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($path)) {
                    require $path;
                }
                return;
            }
        };
    }

    /**
     * Allocate one private test directory.
     *
     * @return string Absolute workspace path.
     *
     * @since 0.2.0
     */
    private static function workspace(): string
    {
        $path = sys_get_temp_dir() . '/kumwe-extension-provider-test-' . bin2hex(random_bytes(12));
        if (!mkdir($path, 0700)) {
            throw new LogicException('The provider test workspace could not be created.');
        }
        return $path;
    }

    /**
     * Remove only the private workspace allocated by this test.
     *
     * @param string $root Absolute private workspace.
     *
     * @return void
     *
     * @since 0.2.0
     */
    private static function removeTree(string $root): void
    {
        if (!is_dir($root) || is_link($root) || !str_starts_with(basename($root), 'kumwe-extension-provider-test-')) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            if (!$item instanceof SplFileInfo) {
                continue;
            }
            $path = $item->getPathname();
            if ($item->isDir() && !$item->isLink()) {
                rmdir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($root);
    }
}

/** Owner-scoped in-memory service surface for provider execution. @since 0.2.0 */
final class FixtureContainer implements ExtensionContainer
{
    /** @var array<string, Closure(ExtensionContainer): object> @since 0.2.0 */
    private array $factories = [];

    /** @var array<string, object> @since 0.2.0 */
    private array $services = [];

    /** @inheritDoc */
    public function get(string $id): object
    {
        if (isset($this->services[$id])) {
            return $this->services[$id];
        }
        $factory = $this->factories[$id] ?? null;
        if (!$factory instanceof Closure) {
            throw new LogicException('A fixture provider resolved an undeclared service.');
        }
        return $this->services[$id] = $factory($this);
    }

    /** @inheritDoc */
    public function share(string $id, callable $factory): void
    {
        if (isset($this->factories[$id]) || isset($this->services[$id])) {
            throw new LogicException('A fixture provider shared a service twice.');
        }
        $this->factories[$id] = $factory(...);
    }
}

/** Manifest-aware recorder that refuses undeclared, wrong-kind and duplicate bindings. @since 0.2.0 */
final class FixtureBindingRegistrar implements ExtensionBindingRegistrar
{
    /** @var array<string, array<string, object>> @since 0.2.0 */
    private array $implementations = [];

    /** @since 0.2.0 */
    public function __construct(private readonly ExecutableBindingRequirements $requirements)
    {
    }

    /** @inheritDoc */
    public function fieldPresenter(string $fieldType, FieldPresenter $presenter): void
    {
        $this->record(ExecutableBindingKind::FieldPresenter, $fieldType, $presenter);
    }

    /** @inheritDoc */
    public function moneyRateProvider(string $identifier, MoneyRateProvider $provider): void
    {
        $this->record(ExecutableBindingKind::MoneyRateProvider, $identifier, $provider);
    }

    /** @inheritDoc */
    public function unitConversionProvider(string $identifier, UnitConversionProvider $provider): void
    {
        $this->record(ExecutableBindingKind::UnitConversionProvider, $identifier, $provider);
    }

    /** @inheritDoc */
    public function customBusinessViewHandler(string $identifier, CustomBusinessViewHandler $handler): void
    {
        $this->record(ExecutableBindingKind::CustomBusinessViewHandler, $identifier, $handler);
    }

    /** @inheritDoc */
    public function customBusinessActionHandler(string $identifier, CustomBusinessActionHandler $handler): void
    {
        $this->record(ExecutableBindingKind::CustomBusinessActionHandler, $identifier, $handler);
    }

    /** @inheritDoc */
    public function administratorRoute(string $name, AdministratorRouteHandlerFactory $factory): void
    {
        $this->record(ExecutableBindingKind::AdministratorRoute, $name, $factory);
    }

    /** @inheritDoc */
    public function portalRoute(string $name, PortalRouteHandlerFactory $factory): void
    {
        $this->record(ExecutableBindingKind::PortalRoute, $name, $factory);
    }

    /** @inheritDoc */
    public function domainListener(string $identifier, DomainEventHandler $handler): void
    {
        $this->record(ExecutableBindingKind::DomainListener, $identifier, $handler);
    }

    /** @inheritDoc */
    public function eventConsumer(string $identifier, IntegrationEventHandler $handler): void
    {
        $this->record(ExecutableBindingKind::EventConsumer, $identifier, $handler);
    }

    /** @inheritDoc */
    public function jobHandler(string $identifier, JobHandler $handler): void
    {
        $this->record(ExecutableBindingKind::JobHandler, $identifier, $handler);
    }

    /** @inheritDoc */
    public function projection(string $identifier, ProjectionBuilder $builder): void
    {
        $this->record(ExecutableBindingKind::Projection, $identifier, $builder);
    }

    /** @inheritDoc */
    public function webhook(string $identifier, IntegrationEventTransport $transport): void
    {
        $this->record(ExecutableBindingKind::Webhook, $identifier, $transport);
    }

    /** @inheritDoc */
    public function studioPreviewRenderer(string $identifier, BlockRenderer $renderer): void
    {
        $this->record(ExecutableBindingKind::StudioPreviewRenderer, $identifier, $renderer);
    }

    /**
     * Return canonical binding identifiers for completeness reconciliation.
     *
     * @return array<string, list<string>> Bound identifiers by kind.
     *
     * @since 0.2.0
     */
    public function inventory(): array
    {
        $inventory = [];
        foreach ($this->implementations as $kind => $implementations) {
            $identifiers = array_keys($implementations);
            sort($identifiers, SORT_STRING);
            $inventory[$kind] = $identifiers;
        }
        return $inventory;
    }

    /**
     * Resolve one exactly recorded implementation for execution.
     *
     * @param ExecutableBindingKind $kind Signed binding kind.
     * @param string $identifier Exact signed identifier.
     *
     * @return object Bound implementation.
     *
     * @since 0.2.0
     */
    public function implementation(ExecutableBindingKind $kind, string $identifier): object
    {
        return $this->implementations[$kind->value][$identifier]
            ?? throw new LogicException('A required fixture implementation was not bound.');
    }

    /**
     * Record one binding only after manifest kind and identity validation.
     *
     * @param ExecutableBindingKind $kind Signed binding kind.
     * @param string $identifier Exact signed identifier.
     * @param object $implementation Typed executable implementation.
     *
     * @return void
     *
     * @since 0.2.0
     */
    private function record(ExecutableBindingKind $kind, string $identifier, object $implementation): void
    {
        $this->requirements->assertDeclared($kind, $identifier);
        if (isset($this->implementations[$kind->value][$identifier])) {
            throw new InvalidArgumentException('A fixture executable binding was duplicated.');
        }
        $this->implementations[$kind->value][$identifier] = $implementation;
    }
}

/** Host-issued event used to exercise both synchronous and durable handlers. @since 0.2.0 */
final readonly class FixtureEvent implements DomainEvent, IntegrationEvent
{
    /**
     * @param string $type Canonical event type.
     * @param EventSensitivity $eventSensitivity Disclosure classification.
     * @param array<string, mixed> $eventPayload Validated payload.
     * @since 0.2.0
     */
    public function __construct(
        private string $type,
        private EventSensitivity $eventSensitivity,
        private array $eventPayload,
    ) {
    }

    /** @inheritDoc */ public function eventType(): string
    {
        return $this->type;
    }
    /** @inheritDoc */ public function schemaVersion(): int
    {
        return 1;
    }
    /** @inheritDoc */ public function eventId(): string
    {
        return 'event-1';
    }
    /** @inheritDoc */ public function occurredAt(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-01-01T00:00:00Z');
    }
    /** @inheritDoc */ public function actorId(): ?string
    {
        return 'actor-1';
    }
    /** @inheritDoc */ public function systemIdentity(): ?string
    {
        return null;
    }
    /** @inheritDoc */ public function siteIdentifier(): string
    {
        return 'default';
    }
    /** @inheritDoc */ public function organizationId(): ?string
    {
        return null;
    }
    /** @inheritDoc */ public function aggregateType(): string
    {
        return 'fixture.aggregate';
    }
    /** @inheritDoc */ public function aggregateId(): string
    {
        return 'aggregate-1';
    }
    /** @inheritDoc */ public function aggregateVersion(): int
    {
        return 1;
    }
    /** @inheritDoc */ public function correlationId(): string
    {
        return 'correlation-1';
    }
    /** @inheritDoc */ public function causationId(): string
    {
        return 'causation-1';
    }
    /** @inheritDoc */ public function sensitivity(): EventSensitivity
    {
        return $this->eventSensitivity;
    }
    /** @inheritDoc */ public function payload(): array
    {
        return $this->eventPayload;
    }
}

/** Host-issued trace context used by fixture workers. @since 0.2.0 */
final readonly class FixtureExecutionContext implements ExecutionContext
{
    /** @inheritDoc */ public function siteIdentifier(): string
    {
        return 'default';
    }
    /** @inheritDoc */ public function actorId(): string
    {
        return 'actor-1';
    }
    /** @inheritDoc */ public function organizationIdentifier(): ?string
    {
        return null;
    }
    /** @inheritDoc */ public function workspaceIdentifier(): ?string
    {
        return null;
    }
    /** @inheritDoc */ public function requestId(): string
    {
        return 'request-1';
    }
    /** @inheritDoc */ public function correlationId(): string
    {
        return 'correlation-1';
    }
    /** @inheritDoc */ public function deliverySurface(): string
    {
        return 'background';
    }
}

/** Ordered projection input for fixture builders. @since 0.2.0 */
final readonly class FixtureProjectionEvent implements ProjectionEvent
{
    /** @param string $eventType Canonical event type. @param array<string, mixed> $eventPayload Validated payload. @since 0.2.0 */
    public function __construct(private string $eventType, private array $eventPayload)
    {
    }

    /** @inheritDoc */ public function sequence(): int
    {
        return 1;
    }
    /** @inheritDoc */ public function id(): string
    {
        return 'aggregate-1';
    }
    /** @inheritDoc */ public function type(): string
    {
        return $this->eventType;
    }
    /** @inheritDoc */ public function schemaVersion(): int
    {
        return 1;
    }
    /** @inheritDoc */ public function occurredAt(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-01-01T00:00:00Z');
    }
    /** @inheritDoc */ public function payload(): array
    {
        return $this->eventPayload;
    }
    /** @inheritDoc */ public function checksum(): string
    {
        return hash('sha256', json_encode($this->eventPayload, JSON_THROW_ON_ERROR));
    }
}

/** Captures deterministic projection writes for invocation assertions. @since 0.2.0 */
final class FixtureProjectionWriter implements ProjectionWriter
{
    /** @var list<array{key: array<string, bool|int|string>, values: array<string, bool|int|string|null>}> @since 0.2.0 */
    public array $puts = [];

    /** @inheritDoc */
    public function put(array $key, array $values): void
    {
        $this->puts[] = ['key' => $key, 'values' => $values];
    }

    /** @inheritDoc */
    public function remove(array $key): void
    {
    }
}

/** Host-bound administrator renderer capability for factory invocation. @since 0.2.0 */
final readonly class FixtureAdministratorRenderer implements AdministratorRouteRenderer
{
    /** @inheritDoc */
    public function render(array $model, ServerRequestInterface $request): string
    {
        return 'administrator';
    }
}

/** Host-bound portal renderer capability for factory invocation. @since 0.2.0 */
final readonly class FixturePortalRenderer implements PortalRouteRenderer
{
    /** @inheritDoc */
    public function render(array $model, ServerRequestInterface $request): string
    {
        return 'portal';
    }
}
