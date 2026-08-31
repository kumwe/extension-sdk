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

    /** @since 0.2.0 */
    public function up(Connection $database, ExtensionTableNames $tables): void;

    /** @since 0.2.0 */
    public function down(Connection $database, ExtensionTableNames $tables): void;
}
