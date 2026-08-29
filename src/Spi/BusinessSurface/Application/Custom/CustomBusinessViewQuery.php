<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessSurface\Application\Custom;

use Kumwe\Extension\Spi\Application\ExecutionContext;
use Kumwe\Extension\Spi\BusinessRecord\Application\BusinessRecordRequestGuard;
use Kumwe\Extension\Spi\BusinessRecord\Query\RecordQuerySpecification;

/** Validated, delivery-neutral query for one signed custom business view. @since 0.2.0 */
final readonly class CustomBusinessViewQuery
{
    /**
     * @param  array<string, mixed>  $parameters
     *
     * @param ExecutionContext $context Host-issued invocation identity and trace context.
     * @param string $definitionIdentifier Admitted business-definition identity.
     * @param string $view Signed custom-view handle.
     * @param RecordQuerySpecification $records Closed host-constrained record query.
     * @param ?string $organizationIdentifier Optional active organization scope.
     * @param ?string $recordId Optional target record identity for detail views.
     * @since  0.2.0
     */
    public function __construct(
        public ExecutionContext $context,
        public string $definitionIdentifier,
        public string $view,
        public RecordQuerySpecification $records,
        public array $parameters = [],
        public ?string $organizationIdentifier = null,
        public ?string $recordId = null,
    ) {
        BusinessRecordRequestGuard::definition($definitionIdentifier);
        BusinessRecordRequestGuard::handle($view, 'view');
        BusinessRecordRequestGuard::organization($organizationIdentifier);
        if ($recordId !== null) {
            BusinessRecordRequestGuard::record($recordId);
        }
        CustomBusinessPayload::assertObject($parameters, 'view query');
    }
}
