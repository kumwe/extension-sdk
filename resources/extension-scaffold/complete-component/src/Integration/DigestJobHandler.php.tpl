<?php

declare(strict_types=1);

namespace @@PHP_NAMESPACE@@\Integration;

use InvalidArgumentException;
use Kumwe\Extension\Spi\Application\Automation\JobHandler;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\JobContributionDefinition;
use Kumwe\Extension\Spi\Application\ExecutionContext;

/**
 * Validates scheduled digest work and retains only a non-reversible diagnostic digest.
 *
 * @since  2.0.0
 */
final readonly class DigestJobHandler implements JobHandler
{
    /**
     * Bind digest evidence to the shared bounded ledger.
     *
     * @param  IntegrationLedger  $ledger  Bounded diagnostic job ledger.
     *
     * @since  2.0.0
     */
    public function __construct(private IntegrationLedger $ledger)
    {
    }

    /**
     * Validate the closed payload and record its digest without retaining message content.
     *
     * @param   array<string, mixed>  $payload  Decoded version-one digest payload.
     * @param   ExecutionContext      $context  Worker-owned execution context.
     *
     * @return  void
     *
     * @throws  InvalidArgumentException  When payload keys or message bounds do not match the signed schema.
     *
     * @since   2.0.0
     */
    public function handle(JobContributionDefinition $declaration, array $payload, ExecutionContext $context): void
    {
        $message = $payload['message'] ?? null;
        if (
            $declaration->type() !== '@@EXTENSION_DOTTED@@.digest'
            || $declaration->schemaVersion() !== 1
            || array_keys($payload) !== ['message']
            || !is_string($message)
            || $message === ''
            || mb_strlen($message) > 191
        ) {
            throw new InvalidArgumentException('The digest job payload is invalid.');
        }
        $this->ledger->recordJob($message);
    }
}
