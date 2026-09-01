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
     * @param  ExecutionContext      $context                 Authenticated site context supplied by the host.
     * @param  string                $definitionIdentifier    Published definition UUID or multi-segment handle.
     * @param  string                $recordId                Identifier of the existing record the action targets.
     * @param  int                   $expectedVersion         Record version the caller last read, rejecting the
     *                                                        command on concurrent modification.
     * @param  string                $action                  Manifest-declared handle of the custom action to execute.
     * @param  IdempotencyKey        $idempotencyKey          Caller-supplied key deduplicating replayed submissions.
     * @param  array<string, mixed>  $input                   JSON-object payload handed to the action handler.
     * @param ?string $organizationIdentifier Organization scope, when the definition requires one.
     * @param ?string $approvalRequestId UUID of the approval request authorizing this action, when one is attached.
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
