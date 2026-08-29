<?php

declare(strict_types=1);

namespace @@PHP_NAMESPACE@@\Application;

use @@PHP_NAMESPACE@@\Integration\IntegrationLedger;
use Kumwe\Extension\Spi\Application\ExecutionContext;

/**
 * Supplies the transport-neutral overview model for both contributed surfaces.
 *
 * @since  2.0.0
 */
final readonly class OverviewService
{
    /**
     * Bind overview diagnostics to the shared bounded integration ledger.
     *
     * @param  IntegrationLedger  $ledger  Bounded process-local integration diagnostics.
     *
     * @since  2.0.0
     */
    public function __construct(private IntegrationLedger $ledger)
    {
    }

    /**
     * Build an overview after the host has admitted the manifest-declared route capability.
     *
     * @param   ExecutionContext  $context  Authenticated, surface-provenance execution context.
     *
     * @return  array{heading: string, message: string, activity: array<string, int|string|null>}
     *          Safe renderer data and bounded integration counts.
     *
     * @since   2.0.0
     */
    public function overview(ExecutionContext $context): array
    {
        return [
            'heading' => '@@LABEL_PHP@@',
            'message' => 'The component is installed, trusted, and contributing through the typed runtime SPI.',
            'site' => $context->siteIdentifier(),
            'activity' => $this->ledger->snapshot(),
        ];
    }
}
