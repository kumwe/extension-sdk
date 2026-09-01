<?php

declare(strict_types=1);

namespace Kumwe\Extension\Package;

/**
 * Port that lists an extension archive's contents without unpacking any of them.
 *
 * Hosts and author tooling begin package inspection here. Keeping central-directory access behind this
 * port lets neutral safety inspection remain archive-format independent; `ZipArchiveReader` is the
 * shipped binding.
 *
 * @since  0.1.0
 */
interface ArchiveReader
{
    /**
     * Read an archive's directory listing into a description neutral safety inspection can judge.
     *
     * Implementations inspect a non-public snapshot and never extract while producing the descriptor.
     *
     * @param   string         $archiveFile  Path of the staged archive file to inspect.
     * @param   PackageLimits  $limits       Exact resource budget for this inspection.
     *
     * @return  ArchivePackage  Every entry with its type and its compressed and expanded sizes.
     *
     * @since   0.1.0
     */
    public function inspect(string $archiveFile, PackageLimits $limits): ArchivePackage;
}
