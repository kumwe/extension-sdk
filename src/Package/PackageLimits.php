<?php

declare(strict_types=1);

namespace Kumwe\Extension\Package;

use InvalidArgumentException;

/**
 * One immutable resource budget shared by archive listing, expansion, evidence inspection and building.
 *
 * A package must not be accepted under one set of limits and then read under another. Carrying the
 * complete budget in the inspected snapshot makes every later operation enforce the same ceilings.
 *
 * @since  0.2.0
 */
final readonly class PackageLimits
{
    /**
     * Configure every bounded package operation.
     *
     * @param   int  $maximumEntries           Most central-directory entries in one package.
     * @param   int  $maximumEntryBytes        Largest expanded regular-file entry.
     * @param   int  $maximumExpandedBytes     Largest total expanded package size.
     * @param   int  $maximumCompressedBytes   Largest total compressed entry size.
     * @param   int  $maximumArchiveBytes      Largest staged ZIP file, including directory metadata.
     * @param   int  $maximumCompressionRatio  Highest expanded-to-compressed ratio for one entry.
     * @param   int  $maximumManifestBytes     Largest root manifest document.
     * @param   int  $maximumBillOfMaterialsBytes  Largest embedded inventory document.
     * @param   int  $maximumProvenanceBytes   Largest embedded provenance document.
     * @param   int  $readChunkBytes           Bytes read from one entry stream at a time.
     *
     * @throws  InvalidArgumentException  When a limit is not positive, an individual entry can exceed
     *          the package total, a protocol-document cap escapes the shared expansion budget, or the
     *          read chunk exceeds the per-entry ceiling.
     *
     * @since   0.2.0
     */
    public function __construct(
        public int $maximumEntries = 4_096,
        public int $maximumEntryBytes = 67_108_864,
        public int $maximumExpandedBytes = 268_435_456,
        public int $maximumCompressedBytes = 268_435_456,
        public int $maximumArchiveBytes = 285_212_672,
        public int $maximumCompressionRatio = 100,
        public int $maximumManifestBytes = 1_048_576,
        public int $maximumBillOfMaterialsBytes = 4_194_304,
        public int $maximumProvenanceBytes = 16_384,
        public int $readChunkBytes = 262_144,
    ) {
        if (min(
            $maximumEntries,
            $maximumEntryBytes,
            $maximumExpandedBytes,
            $maximumCompressedBytes,
            $maximumArchiveBytes,
            $maximumCompressionRatio,
            $maximumManifestBytes,
            $maximumBillOfMaterialsBytes,
            $maximumProvenanceBytes,
            $readChunkBytes,
        ) < 1) {
            throw new InvalidArgumentException('Package limits must be positive integers.');
        }
        if ($maximumEntryBytes > $maximumExpandedBytes) {
            throw new InvalidArgumentException('The per-entry limit cannot exceed the total expanded limit.');
        }
        if ($readChunkBytes > $maximumEntryBytes) {
            throw new InvalidArgumentException('The package read chunk cannot exceed the per-entry limit.');
        }
        if (
            max($maximumManifestBytes, $maximumBillOfMaterialsBytes, $maximumProvenanceBytes)
            > min($maximumEntryBytes, $maximumExpandedBytes)
        ) {
            throw new InvalidArgumentException(
                'Protocol document limits cannot exceed the package entry or total expanded limit.',
            );
        }
        if (
            $maximumBillOfMaterialsBytes > PackageBillOfMaterials::MAXIMUM_BYTES
            || $maximumProvenanceBytes > PackageProvenance::MAXIMUM_BYTES
        ) {
            throw new InvalidArgumentException('Configured attestation limits cannot exceed the protocol maxima.');
        }
        if ($maximumExpandedBytes === PHP_INT_MAX || $maximumCompressedBytes === PHP_INT_MAX) {
            throw new InvalidArgumentException('Package total limits must leave room for an overflow sentinel.');
        }
    }
}
