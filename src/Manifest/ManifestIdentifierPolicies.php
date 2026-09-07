<?php

declare(strict_types=1);

namespace Kumwe\Extension\Manifest;

use Kumwe\Contribution\SurfaceIdentifierPolicy;
use Kumwe\Extension\Spi\Contribution\CanonicalCompositionKind;

/**
 * Manifest surface choices projected onto the canonical contribution policy contract.
 *
 * The SDK selects its manifest grammar; Contribution owns identifier validation and ownership.
 *
 * @since 0.3.0
 */
final class ManifestIdentifierPolicies
{
    /**
     * Select the documented grammar for one SDK manifest contribution kind.
     *
     * @param string $kind Manifest surface label.
     * @return SurfaceIdentifierPolicy Explicit canonical policy, without a registry or global default.
     * @since 0.3.0
     */
    public static function forKind(string $kind): SurfaceIdentifierPolicy
    {
        $surface = str_replace(' ', '-', $kind);

        return match ($kind) {
            'canonical composition document', 'canonical_composition_document' =>
                SurfaceIdentifierPolicy::slash($surface, ['core', 'studio.core']),
            'composition_host_binding' => SurfaceIdentifierPolicy::slash(
                $surface,
                ['core', 'studio.core'],
                array_map(static fn (CanonicalCompositionKind $kind): string => $kind->value, CanonicalCompositionKind::cases()),
            ),
            'preview renderer capability', 'studio preview renderer' =>
                SurfaceIdentifierPolicy::slash($surface, ['core', 'studio.core']),
            'interface surface', 'workspace', 'navigation', 'route', 'view', 'template',
            'portal workspace', 'portal navigation', 'portal route', 'portal template' =>
                SurfaceIdentifierPolicy::dotted($surface),
            default => SurfaceIdentifierPolicy::dotted($surface, true, $kind === 'capability'),
        };
    }

    /**
     * The projection has no instance state.
     *
     * @since 0.3.0
     */
    private function __construct()
    {
    }
}
