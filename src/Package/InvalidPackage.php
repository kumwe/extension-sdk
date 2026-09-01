<?php

declare(strict_types=1);

namespace Kumwe\Extension\Package;

use DomainException;

/**
 * Signals package data too malformed to describe as a complete inspected snapshot.
 *
 * This is not an admission decision. Author tooling converts it to a neutral finding, while a host can
 * apply its own policy. Transport and filesystem failures remain separate runtime exceptions.
 *
 * @since  0.2.0
 */
final class InvalidPackage extends DomainException
{
    /**
     * Retain the stable finding that explains why no snapshot could be produced.
     *
     * @param  PackageFinding  $finding  Policy-neutral malformed-package fact.
     *
     * @since  0.2.0
     */
    public function __construct(public readonly PackageFinding $finding)
    {
        parent::__construct($finding->message);
    }
}
