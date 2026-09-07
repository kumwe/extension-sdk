<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\Application\Automation;

use Kumwe\Extension\Spi\Application\ExecutionContext;
use Kumwe\Automation\JobContributionDefinition;

/**
 * Idempotent executable bound to one manifest-declared job type.
 *
 * The binding registrar owns the identifier. Implementations therefore expose behavior only and never
 * repeat the signed declaration in executable code.
 *
 * @since  0.2.0
 */
interface JobHandler
{
    /**
     * @param  JobContributionDefinition  $definition  Signed job contribution declaration this handler is bound to.
     * @param  array<string, mixed>       $payload     Payload validated against the signed job schema.
     * @param  ExecutionContext           $context     Host-provided execution services scoped to this job run.
     *
     * @since  0.2.0
     */
    public function handle(JobContributionDefinition $definition, array $payload, ExecutionContext $context): void;
}
