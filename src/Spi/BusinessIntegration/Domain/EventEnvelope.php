<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessIntegration\Domain;

use DateTimeImmutable;

/**
 * Read-only, host-neutral view of a validated business event.
 *
 * @since  0.2.0
 */
interface EventEnvelope
{
    /** @since 0.2.0 */
    public function eventType(): string;

    /** @since 0.2.0 */
    public function schemaVersion(): int;

    /** @since 0.2.0 */
    public function eventId(): string;

    /** @since 0.2.0 */
    public function occurredAt(): DateTimeImmutable;

    /** @since 0.2.0 */
    public function actorId(): ?string;

    /** @since 0.2.0 */
    public function systemIdentity(): ?string;

    /** @since 0.2.0 */
    public function siteIdentifier(): string;

    /** @since 0.2.0 */
    public function organizationId(): ?string;

    /** @since 0.2.0 */
    public function aggregateType(): string;

    /** @since 0.2.0 */
    public function aggregateId(): string;

    /** @since 0.2.0 */
    public function aggregateVersion(): int;

    /** @since 0.2.0 */
    public function correlationId(): string;

    /** @since 0.2.0 */
    public function causationId(): string;

    /** @since 0.2.0 */
    public function sensitivity(): EventSensitivity;

    /** @return array<string, mixed> @since 0.2.0 */
    public function payload(): array;
}
