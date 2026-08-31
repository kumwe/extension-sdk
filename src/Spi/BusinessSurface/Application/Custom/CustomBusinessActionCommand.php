<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessSurface\Application\Custom;

use Kumwe\Extension\Spi\Application\Automation\IdempotencyKey;
use Kumwe\Extension\Spi\Application\ExecutionContext;
use Kumwe\Extension\Spi\BusinessRecord\Application\BusinessRecordRequestGuard;

/** Concurrency- and replay-aware command for one signed custom business action. @since 0.2.0 */
final readonly class CustomBusinessActionCommand
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @since  0.2.0
     */
    public function __construct(
        public ExecutionContext $context,
        public string $definitionIdentifier,
        public string $recordId,
        public int $expectedVersion,
        public string $action,
        public IdempotencyKey $idempotencyKey,
        public array $input = [],
        public ?string $organizationIdentifier = null,
        public ?string $approvalRequestId = null,
    ) {
        BusinessRecordRequestGuard::definition($definitionIdentifier);
        BusinessRecordRequestGuard::record($recordId);
        BusinessRecordRequestGuard::version($expectedVersion);
        BusinessRecordRequestGuard::handle($action, 'action');
        BusinessRecordRequestGuard::organization($organizationIdentifier);
        BusinessRecordRequestGuard::approval($approvalRequestId);
        CustomBusinessPayload::assertObject($input, 'action command');
    }
}
