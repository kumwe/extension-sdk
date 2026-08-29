<?php

declare(strict_types=1);

namespace @@PHP_NAMESPACE@@\Tests;

use DateTimeImmutable;
use @@PHP_NAMESPACE@@\Integration\DigestJobHandler;
use @@PHP_NAMESPACE@@\Integration\IntegrationLedger;
use @@PHP_NAMESPACE@@\Integration\ItemDomainListener;
use @@PHP_NAMESPACE@@\Integration\ItemIntegrationConsumer;
use @@PHP_NAMESPACE@@\Integration\ItemProjectionBuilder;
use Kumwe\Extension\Spi\Application\ExecutionContext;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\JobContributionDefinition;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\DomainListenerDefinition;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\DomainEvent;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\EventConsumerDefinition;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\EventSensitivity;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\IntegrationEvent;
use Kumwe\Extension\Spi\BusinessReporting\Application\ProjectionEvent;
use Kumwe\Extension\Spi\BusinessReporting\Application\ProjectionWriter;
use Kumwe\Extension\Spi\BusinessReporting\Domain\ProjectionDefinition;
use Kumwe\Extension\Manifest\ExtensionManifest;
use PHPUnit\Framework\TestCase;

/** Proves every executable integration binding is runnable through canonical SDK ports. @since 2.0.0 */
final class IntegrationContributionTest extends TestCase
{
    /** @since 2.0.0 */
    public function testGeneratedHandlersAreIdempotentAndRunnable(): void
    {
        $ledger = new IntegrationLedger();
        $event = $this->event();
        $integration = $this->integration();
        $listenerDeclaration = DomainListenerDefinition::fromArray($integration['domain_listeners'][0]);
        $listener = new ItemDomainListener($ledger);
        $listener->handle($listenerDeclaration, $event);
        $listener->handle($listenerDeclaration, $event);
        $context = new class implements ExecutionContext {
            public function siteIdentifier(): string
            {
                return 'default';
            }

            public function actorId(): string { return 'system:test'; }
            public function organizationIdentifier(): ?string { return null; }
            public function workspaceIdentifier(): ?string { return null; }
            public function requestId(): string { return 'request-test'; }
            public function correlationId(): string { return 'correlation-test'; }
            public function deliverySurface(): string { return 'background'; }
        };
        $consumerDeclaration = EventConsumerDefinition::fromArray($integration['consumers'][0]);
        $consumer = new ItemIntegrationConsumer($ledger);
        $consumer->handle($consumerDeclaration, $event, $context);
        $consumer->handle($consumerDeclaration, $event, $context);
        $job = new DigestJobHandler($ledger);
        $jobDeclaration = JobContributionDefinition::fromArray($integration['jobs'][0]);
        $job->handle($jobDeclaration, ['message' => 'scheduled-health'], $context);
        $job->handle($jobDeclaration, ['message' => 'scheduled-health'], $context);

        self::assertSame([
            'domain_events' => 1,
            'integration_events' => 1,
            'latest_job_digest' => hash('sha256', 'scheduled-health'),
        ], $ledger->snapshot());
    }

    /** @since 2.0.0 */
    public function testGeneratedProjectionBuilderIsRunnable(): void
    {
        $writer = new class implements ProjectionWriter {
            /** @var list<array{key: array<string, bool|int|string>, values: array<string, bool|int|string|null>}> */
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
        $event = new class implements ProjectionEvent {
            public function sequence(): int { return 1; }
            public function id(): string { return '0f998d3d-cfd2-5362-b49e-916029a2a42f'; }
            public function type(): string { return '@@EXTENSION_DOTTED@@.item_observed'; }
            public function schemaVersion(): int { return 1; }
            public function occurredAt(): DateTimeImmutable { return new DateTimeImmutable('2026-08-10T00:00:00+00:00'); }
            public function payload(): array { return ['item_id' => 'item-1', 'title' => 'Example item']; }
            public function checksum(): string { return hash('sha256', 'fixture-event'); }
        };
        $declaration = ProjectionDefinition::fromArray($this->integration()['projections'][0]);
        (new ItemProjectionBuilder())->apply($declaration, $event, $writer);

        self::assertSame([[
            'key' => ['item_id' => 'item-1'],
            'values' => ['item_id' => 'item-1', 'title' => 'Example item'],
        ]], $writer->rows);
    }

    /** @return DomainEvent&IntegrationEvent @since 2.0.0 */
    private function event(): DomainEvent&IntegrationEvent
    {
        return new class implements DomainEvent, IntegrationEvent {
            public function eventType(): string { return '@@EXTENSION_DOTTED@@.item_observed'; }
            public function schemaVersion(): int { return 1; }
            public function eventId(): string { return '0f998d3d-cfd2-5362-b49e-916029a2a42f'; }
            public function occurredAt(): DateTimeImmutable { return new DateTimeImmutable('2026-08-10T00:00:00+00:00'); }
            public function actorId(): ?string { return 'system:test'; }
            public function systemIdentity(): ?string { return null; }
            public function siteIdentifier(): string { return 'default'; }
            public function organizationId(): ?string { return null; }
            public function aggregateType(): string { return '@@EXTENSION_DOTTED@@.item'; }
            public function aggregateId(): string { return 'item-1'; }
            public function aggregateVersion(): int { return 1; }
            public function correlationId(): string { return 'correlation-test'; }
            public function causationId(): string { return 'request-test'; }
            public function sensitivity(): EventSensitivity { return EventSensitivity::INTERNAL; }
            public function payload(): array { return ['item_id' => 'item-1', 'title' => 'Example item']; }
        };
    }

    /** @return array<string, mixed> @since 2.0.0 */
    private function integration(): array
    {
        $json = file_get_contents(dirname(__DIR__) . '/kumwe.json');
        self::assertIsString($json);
        $declarations = ExtensionManifest::fromJson($json)->contributions()->declarations();

        return $declarations['integration'];
    }
}
