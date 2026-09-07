<?php

declare(strict_types=1);

namespace KumweContract\ManifestFour\Integration;

use Kumwe\Extension\Spi\Application\Automation\JobHandler;
use Kumwe\Automation\JobContributionDefinition;
use Kumwe\Extension\Spi\Application\ExecutionContext;

/**
 * Background job half of the manifest-4 compatibility package.
 *
 * @since  2.0.0
 */
final readonly class SummarizeJob implements JobHandler
{
    /**
     * Bind the executable job to the evidence sink the fixture reads back.
     *
     * @param  ObservationLedger  $ledger  Process-local evidence sink.
     *
     * @since  2.0.0
     */
    public function __construct(private ObservationLedger $ledger)
    {
    }

    /**
     * Record that the job ran, without any external effect.
     *
     * @param   array<string, mixed>  $payload  Decoded job arguments, in the shape the type's schema declares.
     * @param   ExecutionContext      $context  Authorization context the worker built for the job's owner.
     *
     * @return  void
     *
     * @since   2.0.0
     */
    public function handle(JobContributionDefinition $declaration, array $payload, ExecutionContext $context): void
    {
        if ($declaration->identifier() !== 'kumwe.contract-manifest-four.summarize') {
            throw new \InvalidArgumentException('The fixture job received the wrong declaration.');
        }
        $this->ledger->record('job');
    }
}
