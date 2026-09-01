<?php

declare(strict_types=1);

namespace Kumwe\Extension\Package;

/**
 * Produces deterministic archive-safety findings without deciding whether a host admits the package.
 *
 * Every rule is evaluated from the central-directory snapshot before entry contents are expanded. The
 * consuming host owns the decision; SDK author tooling can independently treat the same findings as
 * conformance failures.
 *
 * @since  0.2.0
 */
final readonly class PackageSafetyInspector
{
    /**
     * Inspect a central-directory description and return every objective discrepancy.
     *
     * @param   ArchivePackage  $package  Validated archive entry table.
     * @param   PackageLimits   $limits   Exact resource budget for this inspection.
     *
     * @return  list<PackageFinding>  Findings sorted by code, path and message.
     *
     * @since   0.2.0
     */
    public function findings(ArchivePackage $package, PackageLimits $limits): array
    {
        $findings = [];
        $entries = $package->entries();
        if (count($entries) > $limits->maximumEntries) {
            $findings[] = new PackageFinding(
                'archive.entries.limit',
                'The extension archive contains more entries than the configured limit.',
            );
        }

        $paths = [];
        $types = [];
        $expandedBytes = 0;
        $compressedBytes = 0;
        $expandedExceeded = false;
        $compressedExceeded = false;
        $hasManifest = false;

        foreach ($entries as $entry) {
            $path = $entry->path()->value();
            $pathKey = strtolower($path);
            if (isset($paths[$pathKey])) {
                $findings[] = new PackageFinding(
                    'archive.path.collision',
                    'Archive paths must be unique without relying on letter case.',
                    $path,
                );
            } else {
                $paths[$pathKey] = true;
                $types[$pathKey] = $entry->type();
            }

            if ($entry->type() === ArchiveEntryType::SymbolicLink) {
                $findings[] = new PackageFinding(
                    'archive.entry.symbolic_link',
                    'The extension archive contains a symbolic link.',
                    $path,
                );
            } elseif ($entry->type() === ArchiveEntryType::Special) {
                $findings[] = new PackageFinding(
                    'archive.entry.special',
                    'The extension archive contains a non-portable special filesystem entry.',
                    $path,
                );
            }
            if ($entry->encrypted()) {
                $findings[] = new PackageFinding(
                    'archive.entry.encrypted',
                    'The extension archive contains an encrypted entry.',
                    $path,
                );
            }

            $hasManifest = $hasManifest
                || ($path === 'kumwe.json' && $entry->type() === ArchiveEntryType::File && !$entry->encrypted());
            if ($entry->uncompressedBytes() > $limits->maximumEntryBytes) {
                $findings[] = new PackageFinding(
                    'archive.entry.expanded_limit',
                    'An extension archive entry exceeds the expanded-size limit.',
                    $path,
                );
            }
            if (
                $path === PackageBillOfMaterials::PATH
                && $entry->uncompressedBytes() > $limits->maximumBillOfMaterialsBytes
            ) {
                $findings[] = new PackageFinding(
                    'attestation.sbom.expanded_limit',
                    'The package bill of materials exceeds the configured expanded-size limit.',
                    $path,
                );
            }
            if (
                $path === PackageProvenance::PATH
                && $entry->uncompressedBytes() > $limits->maximumProvenanceBytes
            ) {
                $findings[] = new PackageFinding(
                    'attestation.provenance.expanded_limit',
                    'The package provenance statement exceeds the configured expanded-size limit.',
                    $path,
                );
            }
            if ($entry->uncompressedBytes() > 0 && $entry->compressedBytes() === 0) {
                $findings[] = new PackageFinding(
                    'archive.entry.compressed_size',
                    'A non-empty archive entry reports an impossible compressed size.',
                    $path,
                );
            } elseif (
                $entry->compressedBytes() > 0
                && $this->exceedsRatio(
                    $entry->uncompressedBytes(),
                    $entry->compressedBytes(),
                    $limits->maximumCompressionRatio,
                )
            ) {
                $findings[] = new PackageFinding(
                    'archive.entry.compression_ratio',
                    'An extension archive entry exceeds the compression-ratio limit.',
                    $path,
                );
            }

            if ($entry->uncompressedBytes() > $limits->maximumExpandedBytes - $expandedBytes) {
                $expandedExceeded = true;
            } else {
                $expandedBytes += $entry->uncompressedBytes();
            }
            if ($entry->compressedBytes() > $limits->maximumCompressedBytes - $compressedBytes) {
                $compressedExceeded = true;
            } else {
                $compressedBytes += $entry->compressedBytes();
            }
        }

        foreach ($types as $path => $type) {
            $segments = explode('/', $path);
            array_pop($segments);
            $ancestor = '';
            foreach ($segments as $segment) {
                $ancestor = $ancestor === '' ? $segment : $ancestor . '/' . $segment;
                if (($types[$ancestor] ?? null) === ArchiveEntryType::File) {
                    $findings[] = new PackageFinding(
                        'archive.path.file_ancestor',
                        'An archive file is also the parent of another entry.',
                        $path,
                    );
                    break;
                }
            }
        }

        if ($expandedExceeded) {
            $findings[] = new PackageFinding(
                'archive.expanded_limit',
                'The extension archive exceeds the total expanded-size limit.',
            );
        }
        if ($compressedExceeded) {
            $findings[] = new PackageFinding(
                'archive.compressed_limit',
                'The extension archive exceeds the total compressed-size limit.',
            );
        }
        if (!$hasManifest) {
            $findings[] = new PackageFinding(
                'archive.manifest.missing',
                'The extension archive has no readable kumwe.json regular file at its root.',
            );
        }

        usort($findings, static fn (PackageFinding $left, PackageFinding $right): int => [
            $left->code,
            $left->path ?? '',
            $left->message,
        ] <=> [
            $right->code,
            $right->path ?? '',
            $right->message,
        ]);

        return $findings;
    }

    /**
     * Compare an integer expansion ratio without floating-point rounding or multiplication overflow.
     *
     * @param   int  $expanded    Expanded byte count.
     * @param   int  $compressed  Compressed byte count, greater than zero.
     * @param   int  $maximum     Maximum permitted ratio.
     *
     * @return  bool  True when expanded divided by compressed is greater than the maximum.
     *
     * @since   0.2.0
     */
    private function exceedsRatio(int $expanded, int $compressed, int $maximum): bool
    {
        $whole = intdiv($expanded, $compressed);

        return $whole > $maximum || ($whole === $maximum && $expanded % $compressed !== 0);
    }
}
