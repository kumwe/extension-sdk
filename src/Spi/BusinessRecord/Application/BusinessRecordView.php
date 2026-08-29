<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessRecord\Application;

use DateTimeImmutable;

/** Read-only disclosure-safe projection of one business record. @since 0.2.0 */
interface BusinessRecordView
{
    /** @since 0.2.0 */
    public function definitionIdentifier(): string;

    /** @since 0.2.0 */
    public function definitionVersion(): int;

    /** @since 0.2.0 */
    public function recordIdentifier(): string;

    /** @since 0.2.0 */
    public function version(): int;

    /** @since 0.2.0 */
    public function siteIdentifier(): ?string;

    /** @since 0.2.0 */
    public function organizationIdentifier(): ?string;

    /** @since 0.2.0 */
    public function workflowState(): ?string;

    /** @return array<string, mixed> Policy-admitted field values only. @since 0.2.0 */
    public function values(): array;

    /** @since 0.2.0 */
    public function updatedAt(): DateTimeImmutable;
}
