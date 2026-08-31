<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessSurface\Application\Custom;

use Kumwe\Extension\Spi\Application\Automation\IdempotencyKey;
use Kumwe\Extension\Spi\BusinessRecord\Application\BusinessRecordRequestGuard;

/** Bounded, versioned result returned by a custom business action. @since 0.2.0 */
final readonly class CustomBusinessActionResult
{
    /** @param array<string, mixed> $data @since 0.2.0 */
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
