<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\Migration;

/**
 * Owner-bound physical table-name allocator handed to extension migrations.
 *
 * @since  0.2.0
 */
interface ExtensionTableNames
{
    /** @since 0.2.0 */
    public function raw(string $name): string;

    /** @since 0.2.0 */
    public function quoted(string $name): string;
}
