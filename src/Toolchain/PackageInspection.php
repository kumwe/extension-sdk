<?php

declare(strict_types=1);

namespace Kumwe\Extension\Toolchain;

use Kumwe\Extension\Package\InspectedPackage;
use Kumwe\Extension\Package\PackageFinding;

/**
 * Toolchain view over one immutable, host-neutral inspected package snapshot.
 *
 * @since  0.2.0
 */
final readonly class PackageInspection
{
    /**
     * Retain the canonical package snapshot without copying its fields into a second authority.
     *
     * @param  InspectedPackage  $package  Stable archive, manifest, limits and neutral safety findings.
     *
     * @since  0.2.0
     */
    public function __construct(public InspectedPackage $package)
    {
    }

    /**
     * Export the stable package description used by author tooling.
     *
     * Root routes and events are intentionally absent: they are not an executable or advisory package
     * channel. Executable contribution authority remains solely in the typed manifest contribution graph.
     *
     * @return  array<string, mixed>  JSON-compatible package and manifest inventory.
     *
     * @since   0.2.0
     */
    public function toArray(): array
    {
        return [
            'format' => 'kumwe-extension-inspection-v2',
            'archive' => $this->package->archive,
            'package_sha256' => (string) $this->package->checksum,
            'entry_count' => count($this->package->paths()),
            'expanded_bytes' => $this->package->expandedBytes(),
            'paths' => $this->package->paths(),
            'archive_findings' => array_map(
                static fn (PackageFinding $finding): array => $finding->toArray(),
                $this->package->safetyFindings,
            ),
            'manifest' => [
                'schema' => $this->package->manifest->schemaVersion(),
                'name' => $this->package->manifest->identifier()->value(),
                'type' => $this->package->manifest->type()->value,
                'version' => (string) $this->package->manifest->version(),
                'provider' => $this->package->manifest->serviceProvider(),
                'autoload' => ['psr-4' => $this->package->manifest->autoload()],
                'migrations' => $this->package->manifest->migrations(),
                'permissions' => $this->package->manifest->permissions(),
                'assets' => $this->package->manifest->assets(),
                'contributions' => $this->package->manifest->contributions()->toArray(),
            ],
        ];
    }
}
