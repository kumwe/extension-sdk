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
     * @param ExecutionContext $context Host-issued invocation identity and trace context.
     * @param string $definitionIdentifier Admitted business-definition identity.
     * @param string $recordId Target record identity.
     * @param int $expectedVersion Required optimistic-concurrency version.
     * @param string $action Signed action handle.
     * @param IdempotencyKey $idempotencyKey Caller-stable replay identity.
     * @param ?string $organizationIdentifier Optional active organization scope.
     * @param ?string $approvalRequestId Optional host-validated approval UUID.
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
