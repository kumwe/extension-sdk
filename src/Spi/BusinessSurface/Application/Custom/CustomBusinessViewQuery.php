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
     * @param  ExecutionContext          $context                 Authenticated host-issued context for the active invocation.
     * @param  string                    $definitionIdentifier    Published business definition UUID or multi-segment handle the view belongs to.
     * @param  string                    $view                    Manifest-declared handle of the custom view being rendered.
     * @param  RecordQuerySpecification  $records                 Bounded browse specification selecting the records the view draws on.
     * @param  array<string, mixed>      $parameters              Caller-supplied view parameters, budget-checked as a custom payload.
     * @param  ?string                   $organizationIdentifier  Organization scope, when the definition requires one.
     * @param  ?string                   $recordId                Single record the view is anchored to, or null for a collection view.
     *
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
