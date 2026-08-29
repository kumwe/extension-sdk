<?php

declare(strict_types=1);

namespace Kumwe\Extension\Toolchain;

use FilesystemIterator;
use InvalidArgumentException;
use JsonException;
use Kumwe\Extension\Package\PackageBillOfMaterials;
use Kumwe\Extension\Package\PackagePath;
use Kumwe\Extension\Package\PackageProvenance;
use Kumwe\Extension\Manifest\ExtensionManifest;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;
use ZipArchive;

/**
 * Builds byte-reproducible, bounded ZIP packages from complete extension source trees.
 *
 * Entries are sorted, stored without compressor variance, stamped with the ZIP epoch, and assigned a
 * fixed regular-file mode. The finished archive is re-read through `PackageInspector` before publication.
 *
 * Two documents are generated rather than copied from the source tree: a CycloneDX bill of materials at
 * `kumwe.sbom.json` inventorying every packaged file by SHA-256, and a provenance statement at
 * `kumwe.provenance.json` naming the builder and binding itself to that inventory. Both are ordinary
 * archive entries, so the package digest covers them and the detached signature therefore vouches for
 * them without a second signature format; both are excluded from the inventory they participate in,
 * because a document cannot carry its own digest, and evidence reconciliation verifies that exclusion rather than
 * trusting it. Neither carries a timestamp, so the reproducibility contract is unchanged: the same
 * source tree still builds to the same bytes.
 *
 * @since  0.1.0
 */
final readonly class DeterministicPackageBuilder
{
    /**
     * Fixed timestamp representable by every ZIP implementation.
     *
     * @var    int
     * @since  0.1.0
     */
    private const ZIP_EPOCH = 315532800;

    /**
     * Bind post-build verification to the production package inspector.
     *
     * @param  PackageInspector  $inspector  Safety and manifest boundary for the completed ZIP.
     *
     * @since  0.1.0
     */
    public function __construct(private PackageInspector $inspector)
    {
    }

    /**
     * Build one deterministic archive and publish it without replacing an existing path.
     *
     * @param   string  $sourceDirectory  Canonical absolute extension source root.
     * @param   string  $outputFile       Canonical absolute `.zip` path outside the source root.
     *
     * @return  PackageBuildResult  Published archive and its verified package inspection.
     *
     * @throws  InvalidArgumentException  When source or output paths are unsafe or conflict.
     * @throws  RuntimeException  When a source changes during reading or the ZIP cannot be built and verified.
     *
     * @since   0.1.0
     */
    public function build(string $sourceDirectory, string $outputFile): PackageBuildResult
    {
        $source = realpath($sourceDirectory);
        if (
            !is_string($source)
            || $source !== rtrim($sourceDirectory, '/')
            || !is_dir($source)
            || is_link($sourceDirectory)
        ) {
            throw new InvalidArgumentException('The extension source must be a canonical absolute directory.');
        }
        if (!str_starts_with($outputFile, '/') || strtolower(pathinfo($outputFile, PATHINFO_EXTENSION)) !== 'zip') {
            throw new InvalidArgumentException('The extension package output must be an absolute `.zip` path.');
        }
        if (file_exists($outputFile) || is_link($outputFile)) {
            throw new InvalidArgumentException('The extension package output already exists.');
        }
        $outputParent = realpath(dirname($outputFile));
        if (!is_string($outputParent) || is_link(dirname($outputFile)) || !is_writable($outputParent)) {
            throw new InvalidArgumentException('The extension package output parent is unavailable or unsafe.');
        }
        $output = $outputParent . '/' . basename($outputFile);
        if ($output !== $outputFile || str_starts_with($outputParent . '/', $source . '/')) {
            throw new InvalidArgumentException(
                'The extension package output must be canonical and outside its source.',
            );
        }

        $files = $this->sourceFiles($source);
        $manifestJson = $this->readStableFile($files['kumwe.json'] ?? throw new RuntimeException(
            'The extension source must contain kumwe.json at its root.',
        ));
        $manifest = ExtensionManifest::fromJson($manifestJson);
        $entries = [];
        $sourceBytes = 0;
        $reservedAttestationBytes = $this->inspector->limits()->maximumBillOfMaterialsBytes
            + $this->inspector->limits()->maximumProvenanceBytes;
        if ($reservedAttestationBytes > $this->inspector->limits()->maximumExpandedBytes) {
            throw new RuntimeException(
                'The package expanded-size limit cannot reserve both generated attestations.',
            );
        }
        foreach ($files as $relative => $path) {
            $contents = $this->readStableFile($path);
            $this->assertComplete($contents, $relative);
            if (
                strlen($contents)
                > $this->inspector->limits()->maximumExpandedBytes - $reservedAttestationBytes - $sourceBytes
            ) {
                throw new RuntimeException('The extension source exceeds the package expanded-size limit.');
            }
            $sourceBytes += strlen($contents);
            $entries[$relative] = $contents;
        }
        $entries = [...$entries, ...$this->attestations($manifest, $entries)];
        ksort($entries, SORT_STRING);
        $temporary = $outputParent . '/.' . basename($output) . '.kumwe-build-' . bin2hex(random_bytes(12));
        $zip = new ZipArchive();
        if ($zip->open($temporary, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new RuntimeException('The private extension package archive could not be created.');
        }
        $archiveOpen = true;
        $published = false;

        try {
            foreach ($entries as $relative => $contents) {
                if (!$zip->addFromString($relative, $contents)) {
                    throw new RuntimeException('An extension package entry could not be added.');
                }
                if (
                    !$zip->setCompressionName($relative, ZipArchive::CM_STORE)
                    || !$zip->setMtimeName($relative, self::ZIP_EPOCH)
                    || !$zip->setExternalAttributesName($relative, ZipArchive::OPSYS_UNIX, 0100644 << 16)
                ) {
                    throw new RuntimeException('An extension package entry could not be normalized.');
                }
            }
            if (!$zip->close()) {
                throw new RuntimeException('The deterministic extension package could not be finalized.');
            }
            $archiveOpen = false;
            if (!chmod($temporary, 0600)) {
                throw new RuntimeException('The deterministic extension package could not be protected.');
            }
            $inspection = $this->inspector->inspect($temporary);
            if (!$inspection->package->hasNoSafetyFindings()) {
                throw new RuntimeException('The deterministic package carries archive safety findings.');
            }
            if (!link($temporary, $output)) {
                throw new RuntimeException('The extension package output was claimed before publication.');
            }
            $published = true;
            $publishedInspection = $this->inspector->inspect($output);
            @unlink($temporary);

            return new PackageBuildResult($output, $publishedInspection);
        } catch (Throwable $failure) {
            if ($archiveOpen) {
                $zip->close();
            }
            if (is_file($temporary) && !is_link($temporary)) {
                @unlink($temporary);
            }
            if ($published && is_file($output) && !is_link($output)) {
                @unlink($output);
            }
            throw $failure;
        }
    }

    /**
     * Generate the bill of materials and the provenance statement that ship inside the package.
     *
     * Order matters and is the reason these are built together. The inventory covers the source entries
     * only, so it can be digested; the statement then binds itself to that digest, so the two cannot be
     * mixed between builds. Neither document lists the other, and neither lists itself — installation
     * evidence inspection re-derives both facts from the archive rather than accepting them.
     *
     * @param   ExtensionManifest      $manifest  Parsed manifest naming the component being described.
     * @param   array<string, string>  $entries   Source entry bytes keyed by package path.
     *
     * @return  array<string, string>  The two attestation documents keyed by their package paths.
     *
     * @throws  RuntimeException  When either document cannot be encoded.
     *
     * @since   0.1.0
     */
    private function attestations(ExtensionManifest $manifest, array $entries): array
    {
        $digests = [];
        $expandedBytes = 0;
        foreach ($entries as $relative => $contents) {
            $digests[$relative] = hash('sha256', $contents);
            $expandedBytes += strlen($contents);
        }

        try {
            $sbom = PackageBillOfMaterials::forPackage($manifest, $digests)->toJson();
            $provenance = PackageProvenance::forPackage(
                $manifest,
                hash('sha256', $sbom),
                count($digests),
                $expandedBytes,
            )->toJson();
        } catch (InvalidArgumentException | JsonException $failure) {
            throw new RuntimeException('The extension package attestations could not be built.', 0, $failure);
        }

        return [PackageBillOfMaterials::PATH => $sbom, PackageProvenance::PATH => $provenance];
    }

    /**
     * Enumerate and validate regular source files in archive order.
     *
     * @param   string  $root  Canonical source root.
     *
     * @return  array<string, string>  Absolute source paths keyed by package path.
     *
     * @throws  RuntimeException  When the source has a link, special file, forbidden build material, or is empty.
     *
     * @since   0.1.0
     */
    private function sourceFiles(string $root): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo) {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($root) + 1);
            try {
                $relative = PackagePath::fromString($relative)->value();
            } catch (InvalidArgumentException $failure) {
                throw new RuntimeException('The extension source contains a non-portable package path.', 0, $failure);
            }
            if ($this->developmentPath($relative)) {
                continue;
            }
            if ($file->isLink() || !$file->isFile()) {
                throw new RuntimeException('Extension source trees cannot contain links or special files.');
            }
            if ($this->sensitivePath($relative)) {
                throw new RuntimeException(sprintf('Extension source path %s contains sensitive material.', $relative));
            }
            if (in_array($relative, [PackageBillOfMaterials::PATH, PackageProvenance::PATH], true)) {
                throw new RuntimeException(sprintf(
                    'Extension source path %s is generated at build time and cannot be authored.',
                    $relative,
                ));
            }
            $files[$relative] = $file->getPathname();
            if (count($files) > $this->maximumSourceFiles()) {
                throw new RuntimeException('The extension source contains too many files.');
            }
        }
        if ($files === []) {
            throw new RuntimeException('The extension source tree is empty.');
        }
        ksort($files, SORT_STRING);

        return $files;
    }

    /**
     * Identify dependency and version-control material omitted from published archives.
     *
     * @param   string  $relative  Source path relative to the extension root.
     *
     * @return  bool  True when the path belongs only to the development environment.
     *
     * @since   0.1.0
     */
    private function developmentPath(string $relative): bool
    {
        $segments = explode('/', $relative);
        foreach ($segments as $segment) {
            if (in_array($segment, ['.git', '.phpunit.cache', 'node_modules', 'vendor'], true)) {
                return true;
            }
        }

        $name = basename($relative);

        return in_array($name, [
            '.gitattributes',
            '.gitignore',
            '.phpunit.result.cache',
        ], true) || str_ends_with($name, '.signature.json');
    }

    /**
     * Identify credentials and private key formats that must cause a package build to fail closed.
     *
     * @param   string  $relative  Source path relative to the extension root.
     *
     * @return  bool  True when the path resembles sensitive operational material.
     *
     * @since   0.1.0
     */
    private function sensitivePath(string $relative): bool
    {
        $name = strtolower(basename($relative));

        return $name === '.env'
            || str_starts_with($name, '.env.')
            || in_array($name, ['id_dsa', 'id_ecdsa', 'id_ed25519', 'id_rsa'], true)
            || str_ends_with($name, '.private-key')
            || str_ends_with($name, '.secret-key')
            || str_ends_with($name, '.key')
            || str_ends_with($name, '.p12')
            || str_ends_with($name, '.pem')
            || str_ends_with($name, '.pfx')
            || str_ends_with($name, '.seed');
    }

    /**
     * Read a regular file while proving the opened inode is the one originally inspected.
     *
     * @param   string  $path  Absolute source path.
     *
     * @return  string  Complete stable file bytes.
     *
     * @throws  RuntimeException  When the file is unsafe, oversized, changes, or cannot be read completely.
     *
     * @since   0.1.0
     */
    private function readStableFile(string $path): string
    {
        $before = lstat($path);
        $maximumEntryBytes = $this->inspector->limits()->maximumEntryBytes;
        if (!is_array($before) || !is_file($path) || is_link($path) || $before['size'] > $maximumEntryBytes) {
            throw new RuntimeException('An extension source file is unsafe or exceeds the package entry limit.');
        }
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('An extension source file could not be opened.');
        }
        try {
            $opened = fstat($handle);
            if (
                !is_array($opened)
                || $opened['dev'] !== $before['dev']
                || $opened['ino'] !== $before['ino']
                || $opened['size'] !== $before['size']
            ) {
                throw new RuntimeException('An extension source file changed while it was opened.');
            }
            $contents = stream_get_contents($handle, $maximumEntryBytes + 1);
            $after = fstat($handle);
            if (
                !is_string($contents)
                || strlen($contents) !== $before['size']
                || !is_array($after)
                || $after['size'] !== $before['size']
                || $after['mtime'] !== $before['mtime']
            ) {
                throw new RuntimeException('An extension source file changed while it was read.');
            }

            return $contents;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Reject unresolved generator and unfinished-work markers in packaged text.
     *
     * @param   string  $contents  Entry bytes.
     * @param   string  $path      Entry path used in the failure message.
     *
     * @return  void
     *
     * @throws  RuntimeException  When an unresolved marker is present.
     *
     * @since   0.1.0
     */
    private function assertComplete(string $contents, string $path): void
    {
        if (
            preg_match('/@@[A-Z0-9_]+@@|\{\{[A-Z0-9_]+\}\}/D', $contents) === 1
            || preg_match('/\b(?:TODO|FIXME)\b/', $contents) === 1
        ) {
            throw new RuntimeException(sprintf('Extension source path %s contains an unfinished marker.', $path));
        }
    }

    /**
     * Reserve the two generated attestation entries inside the shared package entry ceiling.
     *
     * @return  int  Maximum authored source-file count.
     *
     * @throws  RuntimeException  When configured package limits cannot hold manifest and attestations.
     *
     * @since   0.2.0
     */
    private function maximumSourceFiles(): int
    {
        $maximum = $this->inspector->limits()->maximumEntries - 2;
        if ($maximum < 1) {
            throw new RuntimeException('Package entry limits must reserve room for both attestations.');
        }

        return $maximum;
    }
}
