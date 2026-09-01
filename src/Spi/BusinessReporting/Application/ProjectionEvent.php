<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessReporting\Application;

use DateTimeImmutable;

/** Immutable, ordered input offered to a projection builder. @since 0.2.0 */
interface ProjectionEvent
{
    /** @since 0.2.0 */
    public function sequence(): int;

    /** @since 0.2.0 */
    public function id(): string;

    /** @since 0.2.0 */
    public function type(): string;

    /** @since 0.2.0 */
    public function schemaVersion(): int;

    /** @since 0.2.0 */
    public function occurredAt(): DateTimeImmutable;

    /** @return array<string, mixed> @since 0.2.0 */
    public function payload(): array;

    /** @since 0.2.0 */
    public function checksum(): string;
}
