<?php

/**
 * Proves the generated and fixture integration handlers execute against real SDK declarations.
 *
 * @since 0.2.4
 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use DateTimeImmutable;
use InvalidArgumentException;
use Kumwe\Extension\Manifest\ExtensionManifest;
use Kumwe\Extension\Spi\Application\ExecutionContext;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\DomainEvent;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\DomainListenerDefinition;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\EventConsumerDefinition;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\EventSensitivity;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\IntegrationEvent;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\JobContributionDefinition;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\WebhookContributionDefinition;
use Kumwe\Extension\Spi\BusinessReporting\Application\ProjectionEvent;
use Kumwe\Extension\Spi\BusinessReporting\Application\ProjectionWriter;
use Kumwe\Extension\Spi\BusinessReporting\Domain\ProjectionDefinition;
use Kumwe\Extension\Tests\TestCase;
use Kumwe\Extension\Toolchain\ComponentScaffolder;
use Kumwe\Extension\Toolchain\ScaffoldRequest;

/**
 * Executes what the autoload proof cannot: the calls generated code makes into SDK declarations.
 *
 * Autoloading a generated handler proves its imports and declared types exist; it does not prove the
 * method calls inside `handle()` name real SDK members. 0.2.3 shipped a listener passing the event
 * object to `DomainListenerDefinition::accepts()`, a job handler calling a non-existent
 * `JobContributionDefinition::type()`, and a consumer calling a non-existent
 * `EventConsumerDefinition::accepts()` — defects only visible once a host dispatched to them. These
 * tests instantiate the generated classes, build every declaration from the generated manifest, and
 * drive each handler with real event, context and writer values, asserting the observable effect and
 * each refusal. The schema-4 generation fixture and the scaffold's own PHPUnit suite are held to the
 * same standard.
 *
 * @since  0.2.4
 */
final class ScaffoldExecutionTest extends TestCase
{
    /**
     * The generated listener, consumer, job handler and projection builder run and refuse correctly.
     *
     * @return  void
     *
     * @since   0.2.4
     */
    public function testGeneratedIntegrationHandlersExecuteAgainstSdkDeclarations(): void
    {
        $work = $this->workspace();
        $source = $work . '/component';
        $namespace = 'Acme\\ExecutedComponent';
        (new ComponentScaffolder())->scaffold(new ScaffoldRequest(
            'acme/executed-component',
            $namespace,
            $source,
            'Executed Component',
        ));
        $loader = $this->register([$namespace . '\\' => $source . '/src']);
        $integration = $this->integration($source);
        $owned = 'acme.executed-component.item_observed';
        $context = $this->context();

        $ledgerClass = $namespace . '\\Integration\\IntegrationLedger';
        $ledger = new $ledgerClass();
        $listenerClass = $namespace . '\\Integration\\ItemDomainListener';
        $listener = new $listenerClass($ledger);
        $listenerDeclaration = DomainListenerDefinition::fromArray($integration['domain_listeners'][0]);
        $event = $this->event($owned, 1, EventSensitivity::INTERNAL, 'event-1');
        $listener->handle($listenerDeclaration, $event);
        $listener->handle($listenerDeclaration, $event);
        $listener->handle($listenerDeclaration, $this->event($owned, 1, EventSensitivity::PUBLIC, 'event-2'));
        foreach ([
            'foreign type' => $this->event('acme.executed-component.other', 1, EventSensitivity::INTERNAL, 'x'),
            'undeclared version' => $this->event($owned, 2, EventSensitivity::INTERNAL, 'x'),
            'above ceiling' => $this->event($owned, 1, EventSensitivity::RESTRICTED, 'x'),
        ] as $case => $refused) {
            $this->assertThrows(
                static fn () => $listener->handle($listenerDeclaration, $refused),
                InvalidArgumentException::class,
                sprintf('The generated listener refuses a %s event.', $case),
            );
        }

        $consumerClass = $namespace . '\\Integration\\ItemIntegrationConsumer';
        $consumer = new $consumerClass($ledger);
        $consumerDeclaration = EventConsumerDefinition::fromArray($integration['consumers'][0]);
        $consumer->handle($consumerDeclaration, $event, $context);
        $consumer->handle($consumerDeclaration, $event, $context);
        foreach ([
            'foreign type' => $this->event('acme.executed-component.other', 1, EventSensitivity::INTERNAL, 'y'),
            'undeclared version' => $this->event($owned, 3, EventSensitivity::INTERNAL, 'y'),
        ] as $case => $refused) {
            $this->assertThrows(
                static fn () => $consumer->handle($consumerDeclaration, $refused, $context),
                InvalidArgumentException::class,
                sprintf('The generated consumer refuses a %s event.', $case),
            );
        }

        $jobClass = $namespace . '\\Integration\\DigestJobHandler';
        $job = new $jobClass($ledger);
        $jobDeclaration = JobContributionDefinition::fromArray($integration['jobs'][0]);
        $job->handle($jobDeclaration, ['message' => 'scheduled-health'], $context);
        $foreignJob = JobContributionDefinition::fromArray(
            ['job_type' => 'acme.executed-component.other'] + $integration['jobs'][0],
        );
        foreach ([
            'foreign declaration' => [$foreignJob, ['message' => 'scheduled-health']],
            'empty payload' => [$jobDeclaration, []],
            'empty message' => [$jobDeclaration, ['message' => '']],
            'extra member' => [$jobDeclaration, ['message' => 'ok', 'extra' => true]],
            'overlong message' => [$jobDeclaration, ['message' => str_repeat('m', 192)]],
        ] as $case => [$declaration, $payload]) {
            $this->assertThrows(
                static fn () => $job->handle($declaration, $payload, $context),
                InvalidArgumentException::class,
                sprintf('The generated job handler refuses a %s.', $case),
            );
        }

        $this->assertSame(
            ['domain_events' => 2, 'integration_events' => 1, 'latest_job_digest' => hash('sha256', 'scheduled-health')],
            $ledger->snapshot(),
            'The ledger records each accepted identity once and the latest job digest.',
        );

        $builderClass = $namespace . '\\Integration\\ItemProjectionBuilder';
        $builder = new $builderClass();
        $projection = ProjectionDefinition::fromArray($integration['projections'][0]);
        $writer = $this->writer();
        $builder->apply($projection, $this->projectionEvent($owned, 1, ['item_id' => 'item-1', 'title' => 'One']), $writer);
        $this->assertSame(
            [['key' => ['item_id' => 'item-1'], 'values' => ['item_id' => 'item-1', 'title' => 'One']]],
            $writer->rows,
            'The generated projection derives one row per accepted event.',
        );
        foreach ([
            'foreign type' => $this->projectionEvent('acme.executed-component.other', 1, ['item_id' => 'i', 'title' => 't']),
            'undeclared version' => $this->projectionEvent($owned, 2, ['item_id' => 'i', 'title' => 't']),
            'missing title' => $this->projectionEvent($owned, 1, ['item_id' => 'i']),
        ] as $case => $refused) {
            $this->assertThrows(
                static fn () => $builder->apply($projection, $refused, $writer),
                InvalidArgumentException::class,
                sprintf('The generated projection builder refuses a %s event.', $case),
            );
        }

        spl_autoload_unregister($loader);
        $this->removeTree($work);
    }

    /**
     * The schema-4 fixture's executable halves run against the declarations their manifest carries.
     *
     * @return  void
     *
     * @since   0.2.4
     */
    public function testSchemaFourFixtureHandlersExecuteAgainstSdkDeclarations(): void
    {
        $fixture = dirname(__DIR__, 2) . '/resources/fixtures/generations/manifest-4';
        $namespace = 'KumweContract\\ManifestFour';
        $loader = $this->register([$namespace . '\\' => $fixture . '/src']);
        $integration = $this->integration($fixture);
        $owned = 'kumwe.contract-manifest-four.observed';
        $context = $this->context();
        $event = $this->event($owned, 1, EventSensitivity::PUBLIC, 'observed-1');
        $foreign = $this->event('kumwe.contract-manifest-four.other', 1, EventSensitivity::PUBLIC, 'observed-2');

        $ledgerClass = $namespace . '\\Integration\\ObservationLedger';
        $ledger = new $ledgerClass();
        $listenerClass = $namespace . '\\Integration\\ObservationListener';
        $listener = new $listenerClass($ledger);
        $listenerDeclaration = DomainListenerDefinition::fromArray($integration['domain_listeners'][0]);
        $listener->handle($listenerDeclaration, $event);
        $this->assertThrows(
            static fn () => $listener->handle($listenerDeclaration, $foreign),
            InvalidArgumentException::class,
            'The fixture listener refuses a foreign event.',
        );

        $consumerClass = $namespace . '\\Integration\\ObservationConsumer';
        $consumer = new $consumerClass($ledger);
        $consumerDeclaration = EventConsumerDefinition::fromArray($integration['consumers'][0]);
        $consumer->handle($consumerDeclaration, $event, $context);
        $this->assertThrows(
            static fn () => $consumer->handle($consumerDeclaration, $foreign, $context),
            InvalidArgumentException::class,
            'The fixture consumer refuses a foreign event.',
        );

        $transportClass = $namespace . '\\Integration\\ObservationWebhookTransport';
        $transport = new $transportClass($ledger);
        $webhook = WebhookContributionDefinition::fromArray($integration['webhooks'][0]);
        $transport->publish($webhook, $event);
        $this->assertThrows(
            static fn () => $transport->publish($webhook, $foreign),
            InvalidArgumentException::class,
            'The fixture webhook transport refuses a foreign event.',
        );

        $jobClass = $namespace . '\\Integration\\SummarizeJob';
        $job = new $jobClass($ledger);
        $jobDeclaration = JobContributionDefinition::fromArray($integration['jobs'][0]);
        $job->handle($jobDeclaration, ['site_identifier' => 'default', 'limit' => 25], $context);
        $this->assertThrows(
            static fn () => $job->handle(
                JobContributionDefinition::fromArray(['job_type' => 'kumwe.contract-manifest-four.other'] + $integration['jobs'][0]),
                ['site_identifier' => 'default', 'limit' => 25],
                $context,
            ),
            InvalidArgumentException::class,
            'The fixture job refuses a foreign declaration.',
        );

        $this->assertSame(
            ['domain-listener', 'consumer', 'webhook', 'job'],
            $ledger->entries(),
            'Every fixture executable recorded exactly one accepted call in order.',
        );

        $builderClass = $namespace . '\\Integration\\ObservationProjectionBuilder';
        $builder = new $builderClass();
        $projection = ProjectionDefinition::fromArray($integration['projections'][0]);
        $writer = $this->writer();
        $builder->apply($projection, $this->projectionEvent($owned, 1, ['message' => 'observed']), $writer);
        $this->assertSame(
            [['key' => ['aggregate_id' => 'event-id'], 'values' => ['aggregate_id' => 'event-id', 'message' => 'observed']]],
            $writer->rows,
            'The fixture projection derives its row from the accepted event.',
        );
        $foreignProjected = $this->projectionEvent('kumwe.contract-manifest-four.other', 1, ['message' => 'observed']);
        $this->assertThrows(
            static fn () => $builder->apply($projection, $foreignProjected, $writer),
            InvalidArgumentException::class,
            'The fixture projection builder refuses a foreign source event.',
        );

        spl_autoload_unregister($loader);
    }

    /**
     * The scaffold's generated PHPUnit suite passes against this SDK.
     *
     * @return  void
     *
     * @since   0.2.4
     */
    public function testGeneratedPhpUnitSuitePasses(): void
    {
        $root = dirname(__DIR__, 2);
        $phpunit = $root . '/vendor/bin/phpunit';
        $this->assertTrue(is_file($phpunit), 'The development PHPUnit binary is installed.');
        $work = $this->workspace();
        $source = $work . '/component';
        (new ComponentScaffolder())->scaffold(new ScaffoldRequest(
            'acme/tested-component',
            'Acme\\TestedComponent',
            $source,
            'Tested Component',
        ));
        $bootstrap = $work . '/bootstrap.php';
        file_put_contents($bootstrap, sprintf(
            "<?php\nrequire %s;\nspl_autoload_register(static function (string \$class): void {\n"
            . "    foreach (%s as \$prefix => \$directory) {\n"
            . "        if (str_starts_with(\$class, \$prefix)) {\n"
            . "            \$path = \$directory . '/' . str_replace('\\\\', '/', substr(\$class, strlen(\$prefix))) . '.php';\n"
            . "            if (is_file(\$path)) {\n                require \$path;\n            }\n            return;\n"
            . "        }\n    }\n});\n",
            var_export($root . '/vendor/autoload.php', true),
            var_export([
                'Acme\\TestedComponent\\Tests\\' => $source . '/tests',
                'Acme\\TestedComponent\\' => $source . '/src',
            ], true),
        ), LOCK_EX);

        $process = proc_open(
            [
                PHP_BINARY,
                $phpunit,
                '--no-configuration',
                '--do-not-cache-result',
                '--bootstrap=' . $bootstrap,
                $source . '/tests',
            ],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $work,
        );
        $this->assertTrue(is_resource($process), 'PHPUnit starts.');
        fclose($pipes[0]);
        $output = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);

        $this->assertSame(0, $status, 'The generated suite passes: ' . $output);
        $this->assertStringContains('OK (', $output, 'PHPUnit reports success.');
        $this->assertTrue(
            preg_match('/OK \((\d+) tests?, \d+ assertions?\)/', $output, $counts) === 1 && (int) $counts[1] >= 3,
            'Every generated test ran: ' . $output,
        );
        $this->removeTree($work);
    }

    /**
     * Register a PSR-4 autoloader for generated or fixture code.
     *
     * @param   array<string, string>  $map  Namespace prefix to absolute directory.
     *
     * @return  callable  The registered loader, for later removal.
     *
     * @since   0.2.4
     */
    private function register(array $map): callable
    {
        $loader = static function (string $class) use ($map): void {
            foreach ($map as $prefix => $directory) {
                if (str_starts_with($class, $prefix)) {
                    $path = $directory . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                    if (is_file($path)) {
                        require_once $path;
                    }

                    return;
                }
            }
        };
        spl_autoload_register($loader);

        return $loader;
    }

    /**
     * Read the integration declarations of one package manifest.
     *
     * @param   string  $root  Package root holding kumwe.json.
     *
     * @return  array<string, list<array<string, mixed>>>  Integration declaration lists.
     *
     * @since   0.2.4
     */
    private function integration(string $root): array
    {
        $declarations = ExtensionManifest::fromJson((string) file_get_contents($root . '/kumwe.json'))
            ->contributions()
            ->declarations();
        $this->assertTrue(is_array($declarations['integration'] ?? null), 'The manifest declares integration.');

        /** @var array<string, list<array<string, mixed>>> $integration */
        $integration = $declarations['integration'];

        return $integration;
    }

    /**
     * Build one event carrying both the transaction-local and durable contracts.
     *
     * @param   string            $type         Event type.
     * @param   int               $version      Schema version.
     * @param   EventSensitivity  $sensitivity  Declared sensitivity.
     * @param   string            $id           Immutable event identity.
     *
     * @return  DomainEvent&IntegrationEvent  Minimal owned event.
     *
     * @since   0.2.4
     */
    private function event(string $type, int $version, EventSensitivity $sensitivity, string $id): DomainEvent&IntegrationEvent
    {
        return new class ($type, $version, $sensitivity, $id) implements DomainEvent, IntegrationEvent {
            public function __construct(
                private string $type,
                private int $version,
                private EventSensitivity $sensitivity,
                private string $id,
            ) {
            }

            public function eventType(): string { return $this->type; }
            public function schemaVersion(): int { return $this->version; }
            public function eventId(): string { return $this->id; }
            public function occurredAt(): DateTimeImmutable { return new DateTimeImmutable('2026-09-02T00:00:00+00:00'); }
            public function actorId(): ?string { return 'system:test'; }
            public function systemIdentity(): ?string { return null; }
            public function siteIdentifier(): string { return 'default'; }
            public function organizationId(): ?string { return null; }
            public function aggregateType(): string { return 'item'; }
            public function aggregateId(): string { return 'item-1'; }
            public function aggregateVersion(): int { return 1; }
            public function correlationId(): string { return 'correlation-test'; }
            public function causationId(): string { return 'request-test'; }
            public function sensitivity(): EventSensitivity { return $this->sensitivity; }
            public function payload(): array { return ['item_id' => 'item-1', 'title' => 'One', 'message' => 'observed']; }
        };
    }

    /**
     * Build one reporting event for a projection builder.
     *
     * @param   string                $type     Event type.
     * @param   int                   $version  Schema version.
     * @param   array<string, mixed>  $payload  Event payload.
     *
     * @return  ProjectionEvent  Minimal projected event.
     *
     * @since   0.2.4
     */
    private function projectionEvent(string $type, int $version, array $payload): ProjectionEvent
    {
        return new class ($type, $version, $payload) implements ProjectionEvent {
            /** @param array<string, mixed> $payload Event payload. */
            public function __construct(private string $type, private int $version, private array $payload)
            {
            }

            public function sequence(): int { return 1; }
            public function id(): string { return 'event-id'; }
            public function type(): string { return $this->type; }
            public function schemaVersion(): int { return $this->version; }
            public function occurredAt(): DateTimeImmutable { return new DateTimeImmutable('2026-09-02T00:00:00+00:00'); }
            public function payload(): array { return $this->payload; }
            public function checksum(): string { return hash('sha256', 'event'); }
        };
    }

    /**
     * Build a recording projection writer.
     *
     * @return  ProjectionWriter&object{rows: list<array{key: array<string, mixed>, values: array<string, mixed>}>}
     *          Writer exposing its rows.
     *
     * @since   0.2.4
     */
    private function writer(): ProjectionWriter
    {
        return new class implements ProjectionWriter {
            /** @var list<array{key: array<string, mixed>, values: array<string, mixed>}> */
            public array $rows = [];

            public function put(array $key, array $values): void
            {
                $this->rows[] = ['key' => $key, 'values' => $values];
            }

            public function remove(array $key): void
            {
                $this->rows = array_values(array_filter(
                    $this->rows,
                    static fn (array $row): bool => $row['key'] !== $key,
                ));
            }
        };
    }

    /**
     * Build a host-issued execution context for worker-side handlers.
     *
     * @return  ExecutionContext  Minimal background context.
     *
     * @since   0.2.4
     */
    private function context(): ExecutionContext
    {
        return new class implements ExecutionContext {
            public function siteIdentifier(): string { return 'default'; }
            public function actorId(): string { return 'system:test'; }
            public function organizationIdentifier(): ?string { return null; }
            public function workspaceIdentifier(): ?string { return null; }
            public function requestId(): string { return 'request-test'; }
            public function correlationId(): string { return 'correlation-test'; }
            public function deliverySurface(): string { return 'background'; }
        };
    }

    /**
     * Allocate a private working directory for one test.
     *
     * @return  string  Absolute path of the writable directory.
     *
     * @since   0.2.4
     */
    private function workspace(): string
    {
        $work = sys_get_temp_dir() . '/kumwe-sdk-execution-' . bin2hex(random_bytes(8));
        mkdir($work, 0700);

        return $work;
    }

    /**
     * Remove one private working directory created by this test.
     *
     * @param   string  $root  Absolute path of the tree to remove.
     *
     * @return  void
     *
     * @since   0.2.4
     */
    private function removeTree(string $root): void
    {
        if (!str_starts_with($root, sys_get_temp_dir() . '/kumwe-sdk-execution-')) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            if (!$entry instanceof \SplFileInfo) {
                continue;
            }
            $entry->isDir() && !$entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($root);
    }
}
