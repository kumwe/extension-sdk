<?php

/** Proves typed callback declarations accept only bounded manifest member shapes. @since 0.2.0 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use InvalidArgumentException;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\JobContributionDefinition;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\DomainListenerDefinition;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\EventConsumerDefinition;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\WebhookContributionDefinition;
use Kumwe\Extension\Spi\BusinessReporting\Domain\ProjectionDefinition;
use Kumwe\Extension\Tests\TestCase;

/** Strict shape checks for host-supplied declaration views. @since 0.2.0 */
final class TypedDeclarationTest extends TestCase
{
    /** @since 0.2.0 */
    public function testCanonicalDeclarationsExposeTheirSignedIdentifiers(): void
    {
        $listener = DomainListenerDefinition::fromArray([
            'listener_id' => 'acme.sample.listener',
            'event_type' => 'acme.sample.changed',
            'schema_versions' => [1, 2],
            'sensitivity_ceiling' => 'internal',
        ]);
        $consumer = EventConsumerDefinition::fromArray([
            'consumer_id' => 'acme.sample.consumer',
            'event_type' => 'acme.sample.changed',
            'schema_versions' => [1],
            'sensitivity_ceiling' => 'restricted',
        ]);
        $job = JobContributionDefinition::fromArray(['job_type' => 'acme.sample.digest', 'schema_version' => 1]);
        $webhook = WebhookContributionDefinition::fromArray([
            'adapter_id' => 'acme.sample.webhook',
            'event_types' => ['acme.sample.changed'],
            'schema_versions' => [1],
            'sensitivity_ceiling' => 'public',
        ]);
        $projection = ProjectionDefinition::fromArray([
            'identifier' => 'acme.sample.activity',
            'version' => 1,
            'sources' => [['event_type' => 'acme.sample.changed', 'schema_versions' => [1]]],
        ]);

        $this->assertSame('acme.sample.listener', $listener->identifier(), 'Listener identity is retained.');
        $this->assertSame('acme.sample.consumer', $consumer->identifier(), 'Consumer identity is retained.');
        $this->assertSame('acme.sample.digest', $job->type(), 'Job identity is retained.');
        $this->assertSame('acme.sample.webhook', $webhook->identifier(), 'Webhook identity is retained.');
        $this->assertSame('acme.sample.activity', $projection->identifier(), 'Projection identity is retained.');
    }

    /** @since 0.2.0 */
    public function testMalformedDeclarationMembersFailClosed(): void
    {
        $this->assertThrows(
            fn () => JobContributionDefinition::fromArray(['job_type' => 'acme.sample.job', 'schema_version' => '1']),
            InvalidArgumentException::class,
            'A numeric-string job schema version is refused.',
        );
        $this->assertThrows(
            fn () => DomainListenerDefinition::fromArray([
                'listener_id' => 'acme.sample.listener',
                'event_type' => 'acme.sample.changed',
                'schema_versions' => [1, 1],
                'sensitivity_ceiling' => 'public',
            ]),
            InvalidArgumentException::class,
            'Duplicate listener schema versions are refused.',
        );
        $this->assertThrows(
            fn () => EventConsumerDefinition::fromArray([
                'consumer_id' => 'acme.sample.consumer',
                'event_type' => 'acme.sample.changed',
                'schema_versions' => [],
                'sensitivity_ceiling' => 'public',
            ]),
            InvalidArgumentException::class,
            'An empty consumer schema-version set is refused.',
        );
        $this->assertThrows(
            fn () => WebhookContributionDefinition::fromArray([
                'adapter_id' => 'acme.sample.webhook',
                'event_types' => ['acme.sample.changed', 'acme.sample.changed'],
                'schema_versions' => [1],
                'sensitivity_ceiling' => 'public',
            ]),
            InvalidArgumentException::class,
            'Duplicate webhook event types are refused.',
        );
        $this->assertThrows(
            fn () => ProjectionDefinition::fromArray([
                'identifier' => 'acme.sample.activity',
                'version' => 1,
                'sources' => [['event_type' => 'acme.sample.changed', 'schema_versions' => [0]]],
            ]),
            InvalidArgumentException::class,
            'A non-positive projection source version is refused.',
        );
    }
}
