<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessSurface\Application\Custom;

use Kumwe\Extension\Spi\Application\Automation\IdempotencyKey;
use Kumwe\Extension\Spi\BusinessRecord\Application\BusinessRecordRequestGuard;

/** Bounded, versioned result returned by a custom business action. @since 0.2.0 */
final readonly class CustomBusinessActionResult
{
    /**
     * @param array<string, mixed> $data Bounded result object.
     * @param int $recordVersion Resulting positive record version.
     * @param IdempotencyKey $operationId Exact command replay identity.
     * @param bool $replayed Whether an earlier durable result was returned.
     * @param ?string $workflowState Optional resulting workflow-state handle.
     * @param bool $deleted Whether the action deleted the target record.
     *
     * @since 0.2.0
     */
    public function __construct(
        public array $data,
        public int $recordVersion,
        public IdempotencyKey $operationId,
        public bool $replayed = false,
        public ?string $workflowState = null,
        public bool $deleted = false,
    ) {
        BusinessRecordRequestGuard::version($recordVersion);
        if ($workflowState !== null) {
            BusinessRecordRequestGuard::handle($workflowState, 'workflow state');
        }
        CustomBusinessPayload::assertObject($data, 'action result');
    }
}
