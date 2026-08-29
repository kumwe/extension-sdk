<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessRecord\Application;

use Kumwe\Extension\Spi\Application\ExecutionContext;
use Kumwe\Extension\Spi\BusinessRecord\Query\RecordQuerySpecification;

/** Immutable request for one host-authorized page of policy-admitted business records. @since 0.2.0 */
final readonly class BusinessRecordReadRequest
{
    /**
     * @param  ExecutionContext            $context                 Authenticated site context supplied by the host.
     * @param  string                      $definitionIdentifier    Published definition UUID or multi-segment handle.
     * @param  RecordQuerySpecification    $specification           Bounded query grammar shared by every delivery path.
     * @param ?string $organizationIdentifier Organization scope, when the definition requires one.
     * @param  BusinessRecordQueryPurpose  $purpose                 Security purpose of this disclosure.
     *
     * @since  0.2.0
     */
    public function __construct(
        public ExecutionContext $context,
        public string $definitionIdentifier,
        public RecordQuerySpecification $specification,
        public ?string $organizationIdentifier = null,
        public BusinessRecordQueryPurpose $purpose = BusinessRecordQueryPurpose::Browse,
    ) {
        BusinessRecordRequestGuard::definition($definitionIdentifier);
        BusinessRecordRequestGuard::organization($organizationIdentifier);
    }
}
