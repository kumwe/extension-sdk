<?php

declare(strict_types=1);

namespace Kumwe\Extension\Package;

use Generator;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

/**
 * Expands regular ZIP entries from one immutable inspected package snapshot.
 *
 * The archive checksum and central-directory rows are checked again, each stream is bounded by the same
 * per-entry and total limits used during inspection, and no entry is written to disk. A changed archive,
 * under-reported expansion or incomplete read fails before inconsistent bytes are yielded further.
 *
 * @since  0.2.0
 */
final readonly class ZipArchiveContentReader implements ArchiveContentReader
{
    /**
     * Expand regular entries in central-directory order under the snapshot's exact limits.
     *
     * @param   InspectedPackage  $package  Stable package snapshot to read.
     *
     * @return  Generator<string, string>  Complete regular-file bytes keyed by portable package path.
     *
     * @throws  InvalidArgumentException  When safety findings remain or the staged path is unavailable.
     * @throws  RuntimeException  When the archive changes, an entry disagrees with its snapshot, or expansion
     *          exceeds a declared or configured bound.
     *
     * @since   0.2.0
     */
    public function contents(InspectedPackage $package): Generator
    {
        if (!$package->hasNoSafetyFindings()) {
            throw new InvalidArgumentException('Package contents cannot be expanded while archive safety findings remain.');
        }
        $package->assertCurrentArchiveIdentity();

        $zip = new ZipArchive();
        if ($zip->open($package->archive, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('The inspected extension archive could not be reopened.');
        }

        $expandedTotal = 0;
        try {
            $entries = $package->entries->entries();
            if ($zip->numFiles !== count($entries)) {
                throw new RuntimeException('The extension archive entry table changed before expansion.');
            }

            foreach ($entries as $index => $expected) {
                $stat = $this->matchingStat($zip, $index, $expected);
                if ($expected->type() === ArchiveEntryType::Directory) {
                    continue;
                }
                if ($expected->type() !== ArchiveEntryType::File || $expected->encrypted()) {
                    throw new RuntimeException('A non-regular package entry reached content expansion.');
                }

                $contents = $this->entry($zip, $index, $expected, $package->limits);
                $length = strlen($contents);
                if ($length !== $stat['size'] || $length !== $expected->uncompressedBytes()) {
                    throw new RuntimeException(sprintf(
                        'Package entry %s disagrees with its inspected expanded size.',
                        $expected->path()->value(),
                    ));
                }
                if ($length > $package->limits->maximumExpandedBytes - $expandedTotal) {
                    throw new RuntimeException('Package expansion exceeds the inspected total-size limit.');
                }
                $expandedTotal += $length;

                yield $expected->path()->value() => $contents;
            }
        } finally {
            $zip->close();
            $package->assertCurrentArchiveIdentity();
        }
    }

    /**
     * Require one live central-directory row to equal the inspected row.
     *
     * @param   ZipArchive    $zip       Open ZIP handle.
     * @param   int           $index     Central-directory position.
     * @param   ArchiveEntry  $expected  Entry recorded in the inspected snapshot.
     *
     * @return  array{name: string, size: int, comp_size: int}  Matching live metadata.
     *
     * @throws  RuntimeException  When the row is unreadable or has changed.
     *
     * @since   0.2.0
     */
    private function matchingStat(ZipArchive $zip, int $index, ArchiveEntry $expected): array
    {
        $stat = $zip->statIndex($index, ZipArchive::FL_UNCHANGED);
        if (
            !is_array($stat)
            || !is_string($stat['name'] ?? null)
            || !is_int($stat['size'] ?? null)
            || !is_int($stat['comp_size'] ?? null)
        ) {
            throw new RuntimeException('The extension archive contains an unreadable live entry.');
        }
        try {
            $livePath = PackagePath::fromString($stat['name'])->value();
        } catch (InvalidArgumentException) {
            throw new RuntimeException('The extension archive path changed after inspection.');
        }
        if (
            $livePath !== $expected->path()->value()
            || $stat['size'] !== $expected->uncompressedBytes()
            || $stat['comp_size'] !== $expected->compressedBytes()
        ) {
            throw new RuntimeException('The extension archive entry table changed after inspection.');
        }

        return [
            'name' => $stat['name'],
            'size' => $stat['size'],
            'comp_size' => $stat['comp_size'],
        ];
    }

    /**
     * Pull one regular entry in bounded chunks.
     *
     * @param   ZipArchive     $zip       Open ZIP handle.
     * @param   int            $index     Central-directory position.
     * @param   ArchiveEntry   $expected  Inspected entry description.
     * @param   PackageLimits  $limits    Exact resource budget carried by the snapshot.
     *
     * @return  string  Complete expanded entry bytes.
     *
     * @throws  RuntimeException  When the stream cannot be read or exceeds its limit.
     *
     * @since   0.2.0
     */
    private function entry(
        ZipArchive $zip,
        int $index,
        ArchiveEntry $expected,
        PackageLimits $limits,
    ): string {
        $stream = $zip->getStreamIndex($index, ZipArchive::FL_UNCHANGED);
        if (!is_resource($stream)) {
            throw new RuntimeException(sprintf(
                'Package entry %s could not be opened.',
                $expected->path()->value(),
            ));
        }

        try {
            $contents = '';
            while (!feof($stream)) {
                $chunk = fread($stream, $limits->readChunkBytes);
                if (!is_string($chunk)) {
                    throw new RuntimeException(sprintf(
                        'Package entry %s could not be read.',
                        $expected->path()->value(),
                    ));
                }
                if ($chunk === '') {
                    break;
                }
                $contents .= $chunk;
                if (
                    strlen($contents) > $limits->maximumEntryBytes
                    || strlen($contents) > $expected->uncompressedBytes()
                ) {
                    throw new RuntimeException(sprintf(
                        'Package entry %s exceeds its inspected expansion bound.',
                        $expected->path()->value(),
                    ));
                }
            }

            return $contents;
        } finally {
            fclose($stream);
        }
    }

}
