<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessSurface\Application\Custom;

use Kumwe\Extension\Spi\Application\Automation\IdempotencyKey;
use Kumwe\Extension\Spi\BusinessRecord\Application\BusinessRecordRequestGuard;

/** Bounded, versioned result returned by a custom business action. @since 0.2.0 */
final readonly class CustomBusinessActionResult
{
    /**
     * @param  array<string, mixed>  $data           Bounded JSON-object payload the action hands back to the caller.
     * @param  int                   $recordVersion  Guarded version the business record holds after the action ran.
     * @param  IdempotencyKey        $operationId    Idempotency key identifying the operation for replay deduplication.
     * @param  bool                  $replayed       Whether this result replays a previously completed submission.
     * @param ?string $workflowState Workflow state handle the record moved to, when the action changed it.
     * @param  bool                  $deleted        Whether the action deleted the record it targeted.
     *
     * @since  0.2.0
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
