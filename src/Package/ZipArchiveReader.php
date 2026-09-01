<?php

declare(strict_types=1);

namespace Kumwe\Extension\Package;

use InvalidArgumentException;
use ZipArchive;

/**
 * Reads a bounded ZIP central directory without expanding entry contents.
 *
 * Every path, size, encryption flag and Unix entry type is preserved in the resulting entry table.
 * Malformed directory records are package-data findings, while inaccessible files remain caller or I/O
 * failures. No archive content is written to disk.
 *
 * @since  0.2.0
 */
final readonly class ZipArchiveReader implements ArchiveReader
{
    /**
     * Read one ZIP central directory into a validated package description.
     *
     * @param   string         $archiveFile  Canonical staged archive path.
     * @param   PackageLimits  $limits       Exact staged-archive and entry-count limits.
     *
     * @return  ArchivePackage  Complete entry table in central-directory order.
     *
     * @throws  InvalidArgumentException  When the path is not a readable regular file.
     * @throws  InvalidPackage  When ZIP data is malformed, unsupported or exceeds metadata limits.
     * @since   0.2.0
     */
    public function inspect(string $archiveFile, PackageLimits $limits): ArchivePackage
    {
        clearstatcache(true, $archiveFile);
        $metadata = lstat($archiveFile);
        if (
            !is_array($metadata)
            || !is_int($metadata['mode'] ?? null)
            || (($metadata['mode'] & 0170000) !== 0100000)
            || !is_int($metadata['size'] ?? null)
            || $metadata['size'] < 0
            || !is_readable($archiveFile)
        ) {
            throw new InvalidArgumentException('The extension archive must be a readable regular file.');
        }
        if ($metadata['size'] > $limits->maximumArchiveBytes) {
            throw new InvalidPackage(new PackageFinding(
                'archive.file.limit',
                'The staged extension archive exceeds the configured file-size limit.',
            ));
        }

        $zip = new ZipArchive();
        if ($zip->open($archiveFile, ZipArchive::RDONLY) !== true) {
            throw new InvalidPackage(new PackageFinding(
                'archive.format.invalid',
                'The extension archive is not a readable ZIP file.',
            ));
        }

        try {
            if ($zip->numFiles < 1) {
                throw new InvalidPackage(new PackageFinding(
                    'archive.entries.empty',
                    'An extension archive cannot be empty.',
                ));
            }
            if ($zip->numFiles > $limits->maximumEntries) {
                throw new InvalidPackage(new PackageFinding(
                    'archive.entries.limit',
                    'The extension archive contains more entries than the configured limit.',
                ));
            }

            $entries = [];
            for ($index = 0; $index < $zip->numFiles; ++$index) {
                $entries[] = $this->entry($zip, $index);
            }

            return new ArchivePackage($entries);
        } finally {
            $zip->close();
        }
    }

    /**
     * Decode one central-directory row without discarding hostile metadata.
     *
     * @param   ZipArchive  $zip    Open read-only ZIP handle.
     * @param   int         $index  Central-directory position.
     *
     * @return  ArchiveEntry  Validated entry description.
     *
     * @throws  InvalidPackage  When path, sizes or directory metadata are malformed.
     *
     * @since   0.2.0
     */
    private function entry(ZipArchive $zip, int $index): ArchiveEntry
    {
        $stat = $zip->statIndex($index, ZipArchive::FL_UNCHANGED);
        if (
            !is_array($stat)
            || !is_string($stat['name'] ?? null)
            || !is_int($stat['size'] ?? null)
            || !is_int($stat['comp_size'] ?? null)
        ) {
            throw new InvalidPackage(new PackageFinding(
                'archive.entry.metadata',
                'The ZIP archive contains an unreadable central-directory entry.',
            ));
        }

        $name = $stat['name'];
        try {
            $path = PackagePath::fromString($name);
        } catch (InvalidArgumentException) {
            throw new InvalidPackage(new PackageFinding(
                'archive.path.invalid',
                'The ZIP archive contains a non-portable or unsafe entry path.',
                $this->findingPath($name),
            ));
        }

        $compressed = $stat['comp_size'];
        $expanded = $stat['size'];
        if ($compressed < 0 || $expanded < 0) {
            throw new InvalidPackage(new PackageFinding(
                'archive.entry.size',
                'The ZIP archive contains an entry with a negative size.',
                $path->value(),
            ));
        }

        $directoryName = str_ends_with($name, '/');
        $operatingSystem = 0;
        $attributes = 0;
        $hasAttributes = $zip->getExternalAttributesIndex($index, $operatingSystem, $attributes);
        if ($hasAttributes && (!is_int($operatingSystem) || !is_int($attributes))) {
            throw new InvalidPackage(new PackageFinding(
                'archive.entry.attributes',
                'The ZIP archive contains invalid external entry attributes.',
                $path->value(),
            ));
        }

        $type = $directoryName ? ArchiveEntryType::Directory : ArchiveEntryType::File;
        if ($hasAttributes && $operatingSystem === ZipArchive::OPSYS_UNIX) {
            $unixType = ($attributes >> 16) & 0xF000;
            $type = match ($unixType) {
                0x0000 => $type,
                0x4000 => $directoryName ? ArchiveEntryType::Directory : ArchiveEntryType::Special,
                0x8000 => $directoryName ? ArchiveEntryType::Special : ArchiveEntryType::File,
                0xA000 => ArchiveEntryType::SymbolicLink,
                default => ArchiveEntryType::Special,
            };
        } elseif ($hasAttributes) {
            $directoryAttribute = ($attributes & 0x10) !== 0;
            if ($directoryAttribute !== $directoryName) {
                $type = ArchiveEntryType::Special;
            }
        }
        if ($type === ArchiveEntryType::Directory && ($compressed !== 0 || $expanded !== 0)) {
            throw new InvalidPackage(new PackageFinding(
                'archive.directory.payload',
                'A ZIP directory entry claims payload bytes.',
                $path->value(),
            ));
        }

        $encryptionMethod = $stat['encryption_method'] ?? ZipArchive::EM_NONE;
        if (!is_int($encryptionMethod)) {
            throw new InvalidPackage(new PackageFinding(
                'archive.entry.encryption',
                'The ZIP archive contains unreadable encryption metadata.',
                $path->value(),
            ));
        }

        return new ArchiveEntry(
            $path,
            $type,
            $compressed,
            $expanded,
            $encryptionMethod !== ZipArchive::EM_NONE,
        );
    }

    /**
     * Bound an invalid raw path before placing it in a finding.
     *
     * @param   string  $path  Raw ZIP entry name.
     *
     * @return  string  Printable bounded path description.
     *
     * @since   0.2.0
     */
    private function findingPath(string $path): string
    {
        $printable = preg_replace('/[^\x20-\x7E]/', '?', $path);

        return substr(is_string($printable) ? $printable : '?', 0, 512);
    }
}
