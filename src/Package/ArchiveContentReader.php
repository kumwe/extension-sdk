<?php

declare(strict_types=1);

namespace Kumwe\Extension\Package;

/**
 * Port that streams an extension archive's file contents without writing any of them to disk.
 *
 * `ArchiveReader` answers what an archive claims to contain; this answers what the immutable inspected
 * snapshot actually contains. Nothing an implementation yields is written to disk. Reading is separate
 * from listing so neutral safety findings and all expansion budgets are established before any entry is
 * expanded, and the snapshot checksum prevents a second set of bytes being substituted later.
 *
 * @since  0.1.0
 */
interface ArchiveContentReader
{
    /**
     * Yield every regular file entry as a path and its complete expanded bytes.
     *
     * Directory entries are skipped, since they carry no content to inspect. Implementations expand one
     * entry at a time so peak memory tracks the largest entry rather than the whole archive.
     *
     * @param   InspectedPackage  $package  Immutable package identity, entry table and shared limits.
     *
     * @return  iterable<string, string>  Complete entry bytes keyed by package path, in listing order.
     *
     * @since   0.1.0
     */
    public function contents(InspectedPackage $package): iterable;
}
