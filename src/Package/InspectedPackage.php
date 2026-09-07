<?php

declare(strict_types=1);

namespace Kumwe\Extension\Package;

use Kumwe\CanonicalJson\CanonicalEncoder;

use InvalidArgumentException;
use Kumwe\Extension\Manifest\ExtensionManifest;
use LogicException;
use RuntimeException;
use ZipArchive;

/**
 * Immutable identity and metadata snapshot produced only by inspecting one staged ZIP.
 *
 * The constructor is private: callers cannot combine a checksum, entry table, manifest or finding list
 * from different sources. `inspect()` computes every field from the same stable archive bytes. Content
 * readers verify the checksum again before and after expansion. The caller must keep the canonical
 * archive path in a privately owned, non-shared staging directory for the lifetime of the snapshot;
 * current-file checks fail closed, but no pathname API can make a shared writable directory race-free.
 *
 * @since  0.2.0
 */
final readonly class InspectedPackage
{
    /**
     * Retain fields established by the closed inspection path.
     *
     * @param   string                $archive         Canonical absolute staged archive path.
     * @param   PackageChecksum       $checksum        Digest of the exact inspected bytes.
     * @param   ArchivePackage        $entries         Central-directory description of those bytes.
     * @param   ExtensionManifest     $manifest        Manifest parsed from those bytes.
     * @param   string                $manifestJson    Exact manifest bytes read from the archive.
     * @param   PackageLimits         $limits          Limits used during inspection and later expansion.
     * @param   list<PackageFinding>  $safetyFindings  Neutral central-directory findings.
     *
     * @since   0.2.0
     */
    private function __construct(
        public string $archive,
        public PackageChecksum $checksum,
        public ArchivePackage $entries,
        public ExtensionManifest $manifest,
        public string $manifestJson,
        public PackageLimits $limits,
        public array $safetyFindings,
    ) {
    }

    /**
     * Capture one same-bytes snapshot through the canonical ZIP inspection implementation.
     *
     * @param   string         $archiveFile  Canonical absolute path in caller-controlled private staging.
     * @param   PackageLimits  $limits       Exact limits carried into the resulting snapshot.
     *
     * @return  self  Closed snapshot whose public fields all derive from the same stable bytes.
     *
     * @throws  InvalidArgumentException  When the caller supplies a non-canonical or unavailable path.
     * @throws  InvalidPackage  When package bytes are too malformed to produce a complete snapshot.
     * @throws  RuntimeException  When stable filesystem reads or hashing fail.
     *
     * @since   0.2.0
     * @param CanonicalEncoder $canonicalEncoder Canonical encoding port supplied by the composition root.
     */
    public static function inspect(CanonicalEncoder $canonicalEncoder, string $archiveFile, PackageLimits $limits = new PackageLimits()): self
    {
        if (!str_starts_with($archiveFile, '/')) {
            throw new InvalidArgumentException('The extension package path must be absolute.');
        }
        $canonical = realpath($archiveFile);
        if (!is_string($canonical) || $canonical !== $archiveFile) {
            throw new InvalidArgumentException('The extension package must be a canonical readable regular file.');
        }
        clearstatcache(true, $canonical);
        $before = lstat($canonical);
        if (!is_array($before) || !self::regularFile($before) || !is_readable($canonical)) {
            throw new InvalidArgumentException('The extension package must be a canonical readable regular file.');
        }
        if ($before['size'] > $limits->maximumArchiveBytes) {
            throw new InvalidPackage(new PackageFinding(
                'archive.file.limit',
                'The staged extension archive exceeds the configured file-size limit.',
            ));
        }
        $digest = hash_file('sha256', $canonical);
        if (!is_string($digest)) {
            throw new RuntimeException('The extension package digest could not be read.');
        }

        $entries = (new ZipArchiveReader())->inspect($canonical, $limits);
        $findings = (new PackageSafetyInspector())->findings($entries, $limits);
        $manifestIndex = null;
        foreach ($entries->entries() as $index => $entry) {
            if ($entry->path()->value() !== 'kumwe.json') {
                continue;
            }
            if ($entry->type() === ArchiveEntryType::File && !$entry->encrypted()) {
                $manifestIndex = $index;
            }
            break;
        }
        if ($manifestIndex === null) {
            throw new InvalidPackage(self::finding(
                $findings,
                'archive.manifest.missing',
                'The extension archive has no readable kumwe.json regular file at its root.',
            ));
        }
        $manifestEntry = $entries->entries()[$manifestIndex];
        if ($manifestEntry->uncompressedBytes() > $limits->maximumManifestBytes) {
            throw new InvalidPackage(new PackageFinding(
                'archive.manifest.limit',
                'The extension package manifest exceeds the configured size limit.',
                'kumwe.json',
            ));
        }

        $manifestJson = self::manifest($canonical, $manifestIndex, $manifestEntry->uncompressedBytes(), $limits);
        try {
            $manifest = ExtensionManifest::fromJson($canonicalEncoder, $manifestJson);
        } catch (InvalidArgumentException $failure) {
            throw new InvalidPackage(new PackageFinding(
                'manifest.document.invalid',
                'The extension package manifest is invalid: ' . $failure->getMessage(),
                'kumwe.json',
            ));
        }

        clearstatcache(true, $canonical);
        $after = lstat($canonical);
        if (
            !is_array($after)
            || !self::regularFile($after)
            || !is_readable($canonical)
            || $after['size'] > $limits->maximumArchiveBytes
        ) {
            throw new RuntimeException('The extension package changed beyond its inspected archive-size limit.');
        }
        $confirmedDigest = hash_file('sha256', $canonical);
        if (
            $after['dev'] !== $before['dev']
            || $after['ino'] !== $before['ino']
            || $after['size'] !== $before['size']
            || $after['mtime'] !== $before['mtime']
            || $after['ctime'] !== $before['ctime']
            || !is_string($confirmedDigest)
            || !hash_equals($digest, $confirmedDigest)
        ) {
            throw new RuntimeException('The extension package changed while it was inspected.');
        }

        return new self(
            $canonical,
            PackageChecksum::sha256($digest),
            $entries,
            $manifest,
            $manifestJson,
            $limits,
            $findings,
        );
    }

    /**
     * Return package paths in central-directory order.
     *
     * @return  list<string>  Validated portable package paths.
     *
     * @since   0.2.0
     */
    public function paths(): array
    {
        return array_map(
            static fn (ArchiveEntry $entry): string => $entry->path()->value(),
            $this->entries->entries(),
        );
    }

    /**
     * Sum declared expanded bytes without exceeding the configured bound.
     *
     * @return  int  Declared expanded bytes, capped one byte above the configured maximum.
     *
     * @since   0.2.0
     */
    public function expandedBytes(): int
    {
        $total = 0;
        foreach ($this->entries->entries() as $entry) {
            if ($entry->uncompressedBytes() > $this->limits->maximumExpandedBytes - $total) {
                return $this->limits->maximumExpandedBytes + 1;
            }
            $total += $entry->uncompressedBytes();
        }

        return $total;
    }

    /**
     * Report whether central-directory inspection produced no safety discrepancy.
     *
     * This is a fact about the report, not permission to install or extract the package.
     *
     * @return  bool  True when the neutral safety finding list is empty.
     *
     * @since   0.2.0
     */
    public function hasNoSafetyFindings(): bool
    {
        return $this->safetyFindings === [];
    }

    /**
     * Require the live staged path to remain the same bounded regular-file bytes as this snapshot.
     *
     * Hosts may call this immediately before a pathname-based operation. They must still retain the
     * package in private staging so another process cannot swap the path between this check and that
     * operation.
     *
     * @return  void
     *
     * @throws  RuntimeException  When the path is unavailable, non-regular, oversized or changed.
     *
     * @since   0.2.0
     */
    public function assertCurrentArchiveIdentity(): void
    {
        clearstatcache(true, $this->archive);
        $metadata = lstat($this->archive);
        if (
            !is_array($metadata)
            || !self::regularFile($metadata)
            || !is_readable($this->archive)
            || $metadata['size'] > $this->limits->maximumArchiveBytes
        ) {
            throw new RuntimeException('The inspected extension archive is no longer a bounded readable regular file.');
        }
        $digest = hash_file('sha256', $this->archive);
        if (!is_string($digest) || !hash_equals((string) $this->checksum, $digest)) {
            throw new RuntimeException('The inspected extension archive bytes changed.');
        }
    }

    /**
     * Prevent persistence from turning a stale path and caller-authored fields into a new snapshot.
     *
     * @return  array<never, never>  Never returned; snapshots must be inspected again.
     *
     * @throws  LogicException  Always, because serialized snapshots cannot retain the same-bytes proof.
     *
     * @since   0.2.0
     */
    public function __serialize(): array
    {
        throw new LogicException('Inspected package snapshots cannot be serialized; inspect the archive again.');
    }

    /**
     * Report whether fresh lstat metadata describes a regular file with a valid byte count.
     *
     * @param   array<mixed>  $metadata  Fresh, non-following filesystem metadata.
     *
     * @return  bool  True only for a non-negative regular-file record.
     *
     * @since   0.2.0
     */
    private static function regularFile(array $metadata): bool
    {
        return is_int($metadata['mode'] ?? null)
            && (($metadata['mode'] & 0170000) === 0100000)
            && is_int($metadata['size'] ?? null)
            && $metadata['size'] >= 0;
    }

    /**
     * Prevent native deserialization from bypassing the closed constructor.
     *
     * @param   array<mixed>  $data  Untrusted serialized fields, never adopted.
     *
     * @return  void
     *
     * @throws  LogicException  Always, because snapshots can only come from `inspect()`.
     *
     * @since   0.2.0
     */
    public function __unserialize(array $data): void
    {
        throw new LogicException('Inspected package snapshots can only be created by inspecting archive bytes.');
    }

    /**
     * Read the already bounded manifest entry without extracting it.
     *
     * @param   string         $archiveFile  Canonical archive path.
     * @param   int            $index        Central-directory position of kumwe.json.
     * @param   int            $expected     Declared manifest byte length.
     * @param   PackageLimits  $limits       Exact snapshot resource budget.
     *
     * @return  string  Exact manifest bytes.
     *
     * @throws  RuntimeException  When the ZIP or manifest cannot be read exactly.
     *
     * @since   0.2.0
     */
    private static function manifest(
        string $archiveFile,
        int $index,
        int $expected,
        PackageLimits $limits,
    ): string {
        $zip = new ZipArchive();
        if ($zip->open($archiveFile, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('The inspected extension package could not be reopened.');
        }
        try {
            $json = $zip->getFromIndex($index, $limits->maximumManifestBytes + 1, ZipArchive::FL_UNCHANGED);
            if (!is_string($json) || strlen($json) !== $expected) {
                throw new RuntimeException('The extension package manifest could not be read completely.');
            }

            return $json;
        } finally {
            $zip->close();
        }
    }

    /**
     * Select an existing stable finding code or build the fallback fact.
     *
     * @param   list<PackageFinding>  $findings  Neutral inspection findings.
     * @param   string                $code      Code to select.
     * @param   string                $fallback  Message used when the code is absent.
     *
     * @return  PackageFinding  Existing or fallback finding.
     *
     * @since   0.2.0
     */
    private static function finding(array $findings, string $code, string $fallback): PackageFinding
    {
        foreach ($findings as $finding) {
            if ($finding->code === $code) {
                return $finding;
            }
        }

        return new PackageFinding($code, $fallback);
    }
}
