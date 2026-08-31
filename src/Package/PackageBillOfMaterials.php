<?php

declare(strict_types=1);

namespace Kumwe\Extension\Package;

use InvalidArgumentException;
use JsonException;
use Kumwe\Extension\Manifest\ExtensionManifest;
use Kumwe\Extension\Contract\NameBasedUuid;

/**
 * The CycloneDX bill of materials an extension package carries about its own contents.
 *
 * CycloneDX 1.6 was chosen over SPDX for three reasons that are specific to this repository rather
 * than to the formats in the abstract. The release pipeline already emits CycloneDX for the core
 * images and the source tree, so an operator correlating a package against the core it runs on reads
 * one format instead of two. Its JSON serialization is small enough to embed in every package and to
 * store on a release row, where an SPDX document of the same fidelity is several times the size. And
 * its component model has a first-class `file` type with a hash list, which is exactly the unit of
 * inventory an extension has: the deterministic builder refuses a packaged `vendor/` or
 * `node_modules/` tree, so bundled third-party code arrives as vendored source files, and a per-file
 * digest is the only honest way to describe it.
 *
 * The document lives inside the package as `kumwe.sbom.json`, not beside it. That is what makes it
 * evidence rather than an assertion: the package digest covers it, and the detached Ed25519 signature
 * covers the package digest, so the publisher's signature already vouches for the inventory without a
 * second signature format. The document necessarily excludes itself and its provenance sibling from
 * its own component list — a document cannot contain its own digest — and reconciliation verifies that
 * exclusion rather than assuming it, by requiring every other entry to be listed with a matching
 * digest and every listed component to exist.
 *
 * No timestamp is emitted. CycloneDX makes `metadata.timestamp` optional, and a build clock would
 * defeat the byte-reproducibility the package builder exists to provide.
 *
 * @since  0.1.0
 */
final readonly class PackageBillOfMaterials
{
    /**
     * Package path the bill of materials is carried at.
     *
     * @var    string
     * @since  0.1.0
     */
    public const string PATH = 'kumwe.sbom.json';

    /**
     * CycloneDX specification version emitted and accepted.
     *
     * @var    string
     * @since  0.1.0
     */
    public const string SPEC_VERSION = '1.6';

    /**
     * Largest bill of materials expanded during bounded evidence inspection.
     *
     * @var    int
     * @since  0.1.0
     */
    public const int MAXIMUM_BYTES = 4_194_304;

    /**
     * Build the document for one package from its manifest and its per-entry digests.
     *
     * @param   ExtensionManifest      $manifest      Strict parsed manifest naming the component this
     *          bill of materials describes.
     * @param   array<string, string>  $entryDigests  Lowercase SHA-256 by package path, covering every
     *          packaged file except the two attestation documents.
     *
     * @return  self  Document ready to encode into the package.
     *
     * @throws  InvalidArgumentException  When a digest is not a lowercase SHA-256 value.
     *
     * @since   0.1.0
     */
    public static function forPackage(ExtensionManifest $manifest, array $entryDigests): self
    {
        ksort($entryDigests, SORT_STRING);
        $components = [];
        $rolling = '';
        foreach ($entryDigests as $path => $digest) {
            if (!is_string($path)) {
                throw new InvalidArgumentException('A package bill-of-materials path must be a string.');
            }
            $path = PackagePath::fromString($path)->value();
            if (in_array($path, [self::PATH, PackageProvenance::PATH], true)) {
                throw new InvalidArgumentException('Package attestations cannot inventory themselves.');
            }
            if (preg_match('/^[a-f0-9]{64}$/D', $digest) !== 1) {
                throw new InvalidArgumentException('A package bill-of-materials digest must be a SHA-256 value.');
            }
            $rolling = hash('sha256', $rolling . $path . ':' . $digest);
            $components[] = [
                'type' => 'file',
                'bom-ref' => 'file:' . $path,
                'name' => $path,
                'hashes' => [['alg' => 'SHA-256', 'content' => $digest]],
            ];
        }
        $identifier = $manifest->identifier()->value();
        $version = (string) $manifest->version();
        $purl = sprintf('pkg:generic/%s@%s', $identifier, rawurlencode($version));
        $serial = NameBasedUuid::v5(
            NameBasedUuid::NAMESPACE_URL,
            'kumwe-extension-sbom:' . $identifier . ':' . $version . ':' . $rolling,
        );

        return new self([
            'bomFormat' => 'CycloneDX',
            'specVersion' => self::SPEC_VERSION,
            'serialNumber' => 'urn:uuid:' . $serial,
            'version' => 1,
            'metadata' => [
                'component' => [
                    'type' => 'application',
                    'bom-ref' => $purl,
                    'name' => $identifier,
                    'version' => $version,
                    'purl' => $purl,
                ],
                'tools' => [
                    'components' => [[
                        'type' => 'application',
                        'name' => PackageProvenance::BUILDER_NAME,
                        'version' => PackageProvenance::BUILDER_VERSION,
                    ]],
                ],
                'properties' => [
                    ['name' => 'kumwe:extension_type', 'value' => $manifest->type()->value],
                    ['name' => 'kumwe:manifest_schema', 'value' => (string) $manifest->schemaVersion()],
                    ['name' => 'kumwe:excluded_paths', 'value' => self::PATH . ',' . PackageProvenance::PATH],
                ],
            ],
            'components' => $components,
            'dependencies' => self::dependencyGraph($manifest, $purl),
        ]);
    }

    /**
     * Freeze an already-shaped CycloneDX document.
     *
     * @param  array<string, mixed>  $document  Complete document in canonical key order.
     *
     * @since  0.1.0
     */
    private function __construct(public array $document)
    {
    }

    /**
     * Decode the exact canonical CycloneDX profile emitted by this SDK.
     *
     * This package-level attestation is intentionally narrower than arbitrary CycloneDX. Unknown,
     * missing or reordered semantic fields would create a second representation of the same inventory,
     * so they are rejected and profile evolution requires an explicit format revision.
     *
     * @param   string  $json  Raw document bytes read from the package.
     *
     * @return  self  Validated document.
     *
     * @throws  InvalidArgumentException  When the document is oversized, is not a CycloneDX object of a
     *          supported specification version, or holds an unreadable component.
     * @throws  JsonException  When the JSON is malformed or too deeply nested.
     *
     * @since   0.1.0
     */
    public static function fromJson(string $json): self
    {
        if (strlen($json) > self::MAXIMUM_BYTES) {
            throw new InvalidArgumentException('The package bill of materials exceeds 4 MiB.');
        }
        $value = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($value) || array_is_list($value)) {
            throw new InvalidArgumentException('The package bill of materials must be a JSON object.');
        }
        if (($value['bomFormat'] ?? null) !== 'CycloneDX') {
            throw new InvalidArgumentException('The package bill of materials must declare bomFormat CycloneDX.');
        }
        if (($value['specVersion'] ?? null) !== self::SPEC_VERSION) {
            throw new InvalidArgumentException(sprintf(
                'The package bill of materials must declare CycloneDX specification version %s.',
                self::SPEC_VERSION,
            ));
        }
        $components = $value['components'] ?? null;
        if (!is_array($components) || !array_is_list($components)) {
            throw new InvalidArgumentException('The package bill of materials must carry a component list.');
        }
        self::assertCanonicalShape($value);

        /** @var array<string, mixed> $value */
        $document = new self($value);
        if (!hash_equals($document->toJson(), $json)) {
            throw new InvalidArgumentException('The package bill of materials is not canonical SDK JSON.');
        }

        return $document;
    }

    /**
     * Require every object in the SDK CycloneDX profile to use its one declared key sequence.
     *
     * @param   array<string, mixed>  $document  Decoded CycloneDX document.
     *
     * @return  void
     *
     * @throws  InvalidArgumentException  When a profile object, list, or key sequence is malformed.
     *
     * @since   0.2.0
     */
    private static function assertCanonicalShape(array $document): void
    {
        self::assertObjectKeys(
            $document,
            ['bomFormat', 'specVersion', 'serialNumber', 'version', 'metadata', 'components', 'dependencies'],
            'document',
        );
        $metadata = $document['metadata'] ?? null;
        if (!is_array($metadata) || array_is_list($metadata)) {
            throw new InvalidArgumentException('The package bill-of-materials metadata must be an object.');
        }
        self::assertObjectKeys($metadata, ['component', 'tools', 'properties'], 'metadata');

        $root = $metadata['component'] ?? null;
        if (!is_array($root) || array_is_list($root)) {
            throw new InvalidArgumentException('The package bill-of-materials root component must be an object.');
        }
        self::assertObjectKeys($root, ['type', 'bom-ref', 'name', 'version', 'purl'], 'root component');

        $tools = $metadata['tools'] ?? null;
        if (!is_array($tools) || array_is_list($tools)) {
            throw new InvalidArgumentException('The package bill-of-materials tools section must be an object.');
        }
        self::assertObjectKeys($tools, ['components'], 'tools');
        $toolComponents = $tools['components'] ?? null;
        if (!is_array($toolComponents) || !array_is_list($toolComponents) || count($toolComponents) !== 1) {
            throw new InvalidArgumentException('The package bill of materials must name exactly one builder tool.');
        }
        $tool = $toolComponents[0] ?? null;
        if (!is_array($tool) || array_is_list($tool)) {
            throw new InvalidArgumentException('The package bill-of-materials builder tool must be an object.');
        }
        self::assertObjectKeys($tool, ['type', 'name', 'version'], 'builder tool');

        $properties = $metadata['properties'] ?? null;
        if (!is_array($properties) || !array_is_list($properties)) {
            throw new InvalidArgumentException('The package bill-of-materials properties must be a list.');
        }
        foreach ($properties as $property) {
            if (!is_array($property) || array_is_list($property)) {
                throw new InvalidArgumentException('A package bill-of-materials property must be an object.');
            }
            self::assertObjectKeys($property, ['name', 'value'], 'property');
        }

        foreach ($document['components'] as $component) {
            if (!is_array($component) || array_is_list($component)) {
                throw new InvalidArgumentException('A package bill-of-materials component must be an object.');
            }
            self::assertObjectKeys($component, ['type', 'bom-ref', 'name', 'hashes'], 'file component');
            $hashes = $component['hashes'] ?? null;
            if (!is_array($hashes) || !array_is_list($hashes) || count($hashes) !== 1) {
                throw new InvalidArgumentException('A package bill-of-materials component must carry one hash.');
            }
            $hash = $hashes[0] ?? null;
            if (!is_array($hash) || array_is_list($hash)) {
                throw new InvalidArgumentException('A package bill-of-materials component hash must be an object.');
            }
            self::assertObjectKeys($hash, ['alg', 'content'], 'component hash');
        }

        $dependencies = $document['dependencies'] ?? null;
        if (!is_array($dependencies) || !array_is_list($dependencies)) {
            throw new InvalidArgumentException('The package bill-of-materials dependencies must be a list.');
        }
        foreach ($dependencies as $dependency) {
            if (!is_array($dependency) || array_is_list($dependency)) {
                throw new InvalidArgumentException('A package bill-of-materials dependency must be an object.');
            }
            self::assertObjectKeys($dependency, ['ref', 'dependsOn'], 'dependency');
            if (!is_array($dependency['dependsOn'] ?? null) || !array_is_list($dependency['dependsOn'])) {
                throw new InvalidArgumentException('A package bill-of-materials dependency target must be a list.');
            }
        }
    }

    /**
     * Require one decoded JSON object to expose exactly the canonical keys in canonical order.
     *
     * @param   array<string, mixed>  $object    Decoded JSON object.
     * @param   list<string>          $expected  Exact key order.
     * @param   string                $context   Object name used in the failure message.
     *
     * @return  void
     *
     * @throws  InvalidArgumentException  When the object key sequence differs.
     *
     * @since   0.2.0
     */
    private static function assertObjectKeys(array $object, array $expected, string $context): void
    {
        if (array_keys($object) !== $expected) {
            throw new InvalidArgumentException(sprintf(
                'The package bill-of-materials %s contains unknown, missing, or noncanonical keys.',
                $context,
            ));
        }
    }

    /**
     * Report the file components the document claims, as a digest keyed by package path.
     *
     * Only `file` components participate; anything else CycloneDX permits is another kind of claim and
     * is not something a package's own bytes can confirm.
     *
     * @return  array<string, string>  Lowercase SHA-256 by package path, sorted by path.
     *
     * @throws  InvalidArgumentException  When a file component names no path or no SHA-256 digest, or
     *          names the same path twice.
     *
     * @since   0.1.0
     */
    public function fileDigests(): array
    {
        $components = $this->document['components'] ?? [];
        $digests = [];
        if (!is_array($components)) {
            return $digests;
        }
        foreach ($components as $component) {
            if (!is_array($component) || ($component['type'] ?? null) !== 'file') {
                continue;
            }
            $name = $component['name'] ?? null;
            if (!is_string($name) || $name === '') {
                throw new InvalidArgumentException('A bill-of-materials file component names no path.');
            }
            $name = PackagePath::fromString($name)->value();
            if (in_array($name, [self::PATH, PackageProvenance::PATH], true)) {
                throw new InvalidArgumentException('A bill of materials cannot inventory an attestation document.');
            }
            if (isset($digests[$name])) {
                throw new InvalidArgumentException(sprintf(
                    'The bill of materials lists %s more than once.',
                    $name,
                ));
            }
            $digests[$name] = $this->sha256Of($component, $name);
        }
        ksort($digests, SORT_STRING);

        return $digests;
    }

    /**
     * Compare the document's inventory against the digests actually computed from the package.
     *
     * @param   ExtensionManifest      $manifest      Manifest carried by the same inspected package.
     * @param   array<string, string>  $entryDigests  Lowercase SHA-256 by package path for every packaged
     *          file except the two attestation documents.
     *
     * @return  list<string>  Sorted mismatch descriptions; empty when the inventory is exact.
     *
     * @throws  InvalidArgumentException  When a file component names no path or no SHA-256 digest.
     *
     * @since   0.1.0
     */
    public function reconcile(ExtensionManifest $manifest, array $entryDigests): array
    {
        $claimed = $this->fileDigests();
        ksort($entryDigests, SORT_STRING);
        $findings = [];
        foreach ($entryDigests as $path => $digest) {
            $listed = $claimed[$path] ?? null;
            if ($listed === null) {
                $findings[] = sprintf('The bill of materials does not list packaged file %s.', $path);
                continue;
            }
            if (!hash_equals($listed, $digest)) {
                $findings[] = sprintf('The bill of materials records a different digest for %s.', $path);
            }
        }
        foreach (array_keys($claimed) as $path) {
            if (!isset($entryDigests[$path])) {
                $findings[] = sprintf('The bill of materials lists %s, which the package does not carry.', $path);
            }
        }
        try {
            $expected = self::forPackage($manifest, $entryDigests)->document;
            if ($this->document !== $expected) {
                $findings[] = 'The bill of materials carries metadata or dependency claims outside the canonical package profile.';
            }
        } catch (InvalidArgumentException $failure) {
            $findings[] = 'The computed package inventory is invalid: ' . $failure->getMessage();
        }
        sort($findings, SORT_STRING);

        return $findings;
    }

    /**
     * Report how many file components the document inventories.
     *
     * @return  int  Component count, used by the operator surface as the inventory's size.
     *
     * @throws  InvalidArgumentException  When a file component names no path or no SHA-256 digest.
     *
     * @since   0.1.0
     */
    public function componentCount(): int
    {
        return count($this->fileDigests());
    }

    /**
     * Encode the document deterministically for embedding in a package.
     *
     * @return  string  Pretty-printed JSON ending with one newline.
     *
     * @throws  JsonException  When an internal field cannot be encoded.
     *
     * @since   0.1.0
     */
    public function toJson(): string
    {
        return json_encode(
            $this->document,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ) . "\n";
    }

    /**
     * Read the single SHA-256 hash a file component must carry.
     *
     * @param   array<mixed>  $component  Decoded CycloneDX component object.
     * @param   string        $name       Component name quoted in the failure message.
     *
     * @return  string  Lowercase hexadecimal SHA-256 digest.
     *
     * @throws  InvalidArgumentException  When no readable SHA-256 hash entry is present.
     *
     * @since   0.1.0
     */
    private function sha256Of(array $component, string $name): string
    {
        $hashes = $component['hashes'] ?? null;
        if (is_array($hashes)) {
            foreach ($hashes as $hash) {
                if (!is_array($hash) || ($hash['alg'] ?? null) !== 'SHA-256') {
                    continue;
                }
                $content = $hash['content'] ?? null;
                if (is_string($content) && preg_match('/^[a-f0-9]{64}$/D', $content) === 1) {
                    return $content;
                }
            }
        }

        throw new InvalidArgumentException(sprintf(
            'Bill-of-materials component %s carries no lowercase SHA-256 hash.',
            $name,
        ));
    }

    /**
     * Express the manifest's declared extension dependencies as a CycloneDX dependency graph.
     *
     * A Kumwe dependency names a version constraint rather than a resolved version, so the constraint is
     * carried in the reference instead of being invented as a version — an SBOM that claimed a resolved
     * version it never saw would be worse than one that admits it is describing a requirement.
     *
     * @param   ExtensionManifest  $manifest  Manifest whose declared dependencies are being described.
     * @param   string             $purl      Package URL of the component the graph is rooted at.
     *
     * @return  list<array{ref: string, dependsOn: list<string>}>  Root entry, plus one leaf per dependency.
     *
     * @since   0.1.0
     */
    private static function dependencyGraph(ExtensionManifest $manifest, string $purl): array
    {
        $references = [];
        foreach ($manifest->dependencies() as $dependency) {
            $references[] = sprintf(
                'requires:%s@%s',
                $dependency->extension()->value(),
                (string) $dependency->constraint(),
            );
        }
        sort($references, SORT_STRING);
        $graph = [['ref' => $purl, 'dependsOn' => $references]];
        foreach ($references as $reference) {
            $graph[] = ['ref' => $reference, 'dependsOn' => []];
        }

        return $graph;
    }
}
