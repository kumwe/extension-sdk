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
    /** @param string $name Logical table name from the manifest, resolved to its owner-prefixed physical name. @since 0.2.0 */
    public function raw(string $name): string;

    /** @param string $name Logical table name from the manifest, resolved and quoted for safe use in SQL statements. @since 0.2.0 */
    public function quoted(string $name): string;
}
