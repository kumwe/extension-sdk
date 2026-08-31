<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\Migration;

use Doctrine\DBAL\Connection;

/**
 * Reversible extension-owned schema migration.
 *
 * @since  0.2.0
 */
interface ExtensionMigration
{
    /** @since 0.2.0 */
    public function id(): string;

    /**
     * @param  Connection           $database  Host database connection the schema change executes on.
     * @param  ExtensionTableNames  $tables    Allocator resolving logical names to this extension's physical tables.
     *
     * @since  0.2.0
     */
    public function up(Connection $database, ExtensionTableNames $tables): void;

    /**
     * @param  Connection           $database  Host database connection the schema rollback executes on.
     * @param  ExtensionTableNames  $tables    Allocator resolving logical names to this extension's physical tables.
     *
     * @since  0.2.0
     */
    public function down(Connection $database, ExtensionTableNames $tables): void;
}
