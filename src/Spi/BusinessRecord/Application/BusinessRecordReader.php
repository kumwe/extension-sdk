<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessRecord\Application;

/**
 * Host policy boundary through which extension code may browse business records.
 *
 * The host resolves the signed definition, applies capability, scope, row and field policy, and returns
 * only disclosure-safe views. Implementations must never expose persistence records through this port and
 * must verify that the request carries the concrete host-issued execution context for the active invocation;
 * implementing the public interface is not proof of authority.
 *
 * @since  0.2.0
 */
interface BusinessRecordReader
{
    /**
     * @param BusinessRecordReadRequest $query Host-issued context and closed canonical record query.
     *
     * @since 0.2.0
     */
    public function readPage(BusinessRecordReadRequest $query): BusinessRecordPage;
}
