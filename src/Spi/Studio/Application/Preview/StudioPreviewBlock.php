<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\Studio\Application\Preview;

/** Read-only view of a validated contributed block. @since 0.2.0 */
interface StudioPreviewBlock
{
    /** @since 0.2.0 */
    public function id(): string;

    /** @since 0.2.0 */
    public function type(): string;

    /** @since 0.2.0 */
    public function version(): string;

    /** @param string $name Manifest-declared property name whose configured value is read. @since 0.2.0 */
    public function property(string $name): mixed;
}
