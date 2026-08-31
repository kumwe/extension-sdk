<?php

/**
 * Proves canonical custom-handler DTOs preserve the former public validation boundary.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use InvalidArgumentException;
use Kumwe\Extension\Spi\Application\Automation\IdempotencyKey;
use Kumwe\Extension\Spi\Application\ExecutionContext;
use Kumwe\Extension\Spi\BusinessRecord\Query\RecordQuerySpecification;
use Kumwe\Extension\Spi\BusinessSurface\Application\Custom\CustomBusinessActionCommand;
use Kumwe\Extension\Spi\BusinessSurface\Application\Custom\CustomBusinessActionResult;
use Kumwe\Extension\Spi\BusinessSurface\Application\Custom\CustomBusinessViewQuery;
use Kumwe\Extension\Tests\TestCase;

/** Behavioral parity checks for typed query, replay, identity, and JSON bounds. @since 0.2.0 */
final class CustomBusinessPortTest extends TestCase
{
    /** @since 0.2.0 */
    public function testTypedViewAndActionInputsPreserveTheirGuards(): void
    {
        $query = new CustomBusinessViewQuery(
            $this->context(),
            'crm.contact',
            'detail',
            $this->query(),
            ['mode' => 'summary'],
            'org:north',
            'record-1',
        );
        $this->assertSame('crm.contact', $query->definitionIdentifier, 'A multi-segment definition is admitted.');
        $this->assertSame(64, strlen($query->records->digest()), 'The canonical query reaches the handler intact.');

        $key = IdempotencyKey::fromString('operation-123');
        $command = new CustomBusinessActionCommand(
            $this->context(),
            '018f22e2-7c8b-7ab0-8f3a-88e8026bc101',
            'record-1',
            3,
            'approve',
            $key,
            ['reason' => 'verified'],
            'org:north',
            '018f22e2-7c8b-7ab0-8f3a-88e8026bc102',
        );
        $result = new CustomBusinessActionResult(['status' => 'approved'], 4, $key);
        $this->assertSame(3, $command->expectedVersion, 'The concurrency guard is retained.');
        $this->assertSame('operation-123', $result->operationId->value(), 'The typed replay identity is retained.');
    }

    /** @since 0.2.0 */
    public function testInvalidIdentitiesAndExactJsonAreRefused(): void
    {
        $this->assertThrows(
            fn () => new CustomBusinessViewQuery($this->context(), 'contact', 'detail', $this->query()),
            InvalidArgumentException::class,
            'A single-segment definition handle must remain invalid.',
        );
        $this->assertThrows(
            fn () => new CustomBusinessViewQuery(
                $this->context(),
                'crm.contact',
                'detail',
                $this->query(),
                [],
                'bad organization!',
            ),
            InvalidArgumentException::class,
            'An unsafe organization identifier must remain invalid.',
        );
        $this->assertThrows(
            fn () => new CustomBusinessActionCommand(
                $this->context(),
                'crm.contact',
                "record\n1",
                1,
                'approve',
                IdempotencyKey::fromString('operation-123'),
            ),
            InvalidArgumentException::class,
            'A record identity containing a control character must remain invalid.',
        );
        $this->assertThrows(
            fn () => new CustomBusinessActionCommand(
                $this->context(),
                'crm.contact',
                'record-1',
                1,
                'approve',
                IdempotencyKey::fromString('short'),
            ),
            InvalidArgumentException::class,
            'An undersized idempotency identity must remain invalid even through an implementation.',
        );
        $this->assertThrows(
            fn () => new CustomBusinessActionResult(
                ['fraction' => 1.5],
                2,
                IdempotencyKey::fromString('operation-123'),
            ),
            InvalidArgumentException::class,
            'Custom results still reject inexact JSON numbers.',
        );
    }

    /** @since 0.2.0 */
    public function testReplayIdentityAndIdentifierBoundariesAreExact(): void
    {
        $minimum = IdempotencyKey::fromString(str_repeat('a', 8));
        $maximum = IdempotencyKey::fromString(str_repeat('z', 128));
        $this->assertSame(8, strlen($minimum->value()), 'The minimum replay identity is admitted.');
        $this->assertSame(128, strlen($maximum->value()), 'The maximum replay identity is admitted.');
        $this->assertTrue(
            $minimum->equals(IdempotencyKey::fromString(str_repeat('a', 8))),
            'Equal replay identities compare by their canonical bytes.',
        );
        $this->assertTrue(
            !$minimum->equals(IdempotencyKey::fromString(str_repeat('b', 8))),
            'Different replay identities do not compare equal.',
        );
        $this->assertThrows(
            fn () => IdempotencyKey::fromString(str_repeat('a', 7)),
            InvalidArgumentException::class,
            'A seven-byte replay identity is refused.',
        );
        $this->assertThrows(
            fn () => IdempotencyKey::fromString(str_repeat('a', 129)),
            InvalidArgumentException::class,
            'A 129-byte replay identity is refused.',
        );
        $urn = 'urn:uuid:018f22e2-7c8b-7ab0-8f3a-88e8026bc101';
        $command = new CustomBusinessActionCommand(
            $this->context(),
            $urn,
            str_repeat('r', 191),
            1,
            'approve',
            $minimum,
            approvalRequestId: '{018f22e2-7c8b-7ab0-8f3a-88e8026bc102}',
        );
        $this->assertSame($urn, $command->definitionIdentifier, 'The established generic UUID grammar is retained.');
        $this->assertThrows(
            fn () => new CustomBusinessActionCommand(
                $this->context(),
                'crm.contact',
                str_repeat('r', 192),
                1,
                'approve',
                $minimum,
            ),
            InvalidArgumentException::class,
            'A 192-byte record identity is refused.',
        );
    }

    /** @since 0.2.0 */
    private function context(): ExecutionContext
    {
        return new class implements ExecutionContext {
            public function siteIdentifier(): string
            {
                return 'default';
            }

            public function actorId(): string { return 'user:test'; }
            public function organizationIdentifier(): ?string { return 'org:north'; }
            public function workspaceIdentifier(): ?string { return null; }
            public function requestId(): string { return 'request-test'; }
            public function correlationId(): string { return 'correlation-test'; }
            public function deliverySurface(): string { return 'administrator'; }
        };
    }

    /** @since 0.2.0 */
    private function query(): RecordQuerySpecification
    {
        return new RecordQuerySpecification();
    }
}
