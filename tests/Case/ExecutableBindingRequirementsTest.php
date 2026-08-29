<?php

/**
 * Proves executable bindings are derived only from validated signed declarations.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use InvalidArgumentException;
use Kumwe\Extension\Manifest\ExtensionManifest;
use Kumwe\Extension\Manifest\ManifestContributions;
use Kumwe\Extension\Spi\Binding\ExecutableBindingKind;
use Kumwe\Extension\Spi\Binding\ExecutableBindingRequirements;
use Kumwe\Extension\Tests\TestCase;

/** @since 0.2.0 */
final class ExecutableBindingRequirementsTest extends TestCase
{
    /** @since 0.2.0 */
    public function testEveryGenerationHasOneCanonicalExecutableInventory(): void
    {
        $expected = [
            1 => [],
            2 => [],
            3 => [
                'field_presenter' => ['kumwe.contract-manifest-three.grade'],
            ],
            4 => [
                'domain_listener' => ['kumwe.contract-manifest-four.observe-now'],
                'event_consumer' => ['kumwe.contract-manifest-four.observe-later'],
                'job_handler' => ['kumwe.contract-manifest-four.summarize'],
                'projection' => ['kumwe.contract-manifest-four.activity'],
                'webhook' => ['kumwe.contract-manifest-four.observed-webhook'],
            ],
            5 => [],
            6 => [
                'studio_preview_renderer' => ['kumwe.contract-manifest-six/grid-preview'],
            ],
        ];

        foreach ($expected as $generation => $inventory) {
            $requirements = $this->requirements($generation);
            $this->assertSame($inventory, $requirements->toArray(), 'The signed generation has an exact inventory.');
            $requirements->assertSatisfied($inventory);
        }
    }

    /** @since 0.2.0 */
    public function testUndeclaredWrongKindDuplicateAndMissingBindingsFailClosed(): void
    {
        $requirements = $this->requirements(4);
        $requirements->assertDeclared(
            ExecutableBindingKind::JobHandler,
            'kumwe.contract-manifest-four.summarize',
        );
        $this->assertThrows(
            fn (): null => $this->declared(
                $requirements,
                ExecutableBindingKind::Webhook,
                'kumwe.contract-manifest-four.summarize',
            ),
            InvalidArgumentException::class,
            'An identifier declared for another executable kind is refused.',
        );
        $this->assertThrows(
            fn (): null => $this->declared(
                $requirements,
                ExecutableBindingKind::JobHandler,
                'kumwe.contract-manifest-four.foreign',
            ),
            InvalidArgumentException::class,
            'An undeclared identifier is refused.',
        );

        $satisfied = $requirements->toArray();
        $duplicate = $satisfied;
        $duplicate['job_handler'][] = 'kumwe.contract-manifest-four.summarize';
        $missing = $satisfied;
        unset($missing['projection']);
        $extra = $satisfied;
        $extra['administrator_route'] = ['kumwe.contract-manifest-four.hidden'];
        foreach ([$duplicate, $missing, $extra] as $inventory) {
            $this->assertThrows(
                fn (): null => $this->satisfied($requirements, $inventory),
                InvalidArgumentException::class,
                'Duplicate, missing and extra executable implementations are refused.',
            );
        }
    }

    /** @since 0.2.0 */
    public function testCanonicalGraphExportReparsesWithoutNormalizationDrift(): void
    {
        for ($generation = 2; $generation <= 6; $generation++) {
            $manifest = $this->manifest($generation);
            $graph = $manifest->contributions()->declarations();
            $reparsed = ManifestContributions::fromManifest(
                $manifest->identifier(),
                $graph,
                $manifest->schemaVersion(),
            );
            $this->assertSame(
                $graph,
                $reparsed->declarations(),
                'Canonical graph export must be parse-idempotent.',
            );
            $this->assertSame(
                $manifest->contributions()->executableBindingRequirements()->toArray(),
                $reparsed->executableBindingRequirements()->toArray(),
                'Canonical graph export must preserve exact binding identities.',
            );
        }

        $decoded = json_decode($this->fixture(2), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('The generation-two manifest is malformed.');
        }
        $decoded['contributions']['capabilities'][0]['id'] .= ' ';
        $this->assertThrows(
            fn (): ExtensionManifest => ExtensionManifest::fromJson(json_encode($decoded, JSON_THROW_ON_ERROR)),
            InvalidArgumentException::class,
            'A binding identity with normalization-sensitive whitespace is refused.',
        );
    }

    /** @since 0.2.0 */
    public function testBlocksRequireRenderersWhileNonRenderedDocumentsDoNot(): void
    {
        $manifest = $this->manifest(6);
        $this->assertSame(
            ['kumwe.contract-manifest-six/grid-preview'],
            $manifest->contributions()->executableBindingRequirements()->identifiers(
                ExecutableBindingKind::StudioPreviewRenderer,
            ),
            'The block renderer is an executable requirement.',
        );

        $decoded = json_decode($this->fixture(6), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('The generation-six manifest is malformed.');
        }
        $decoded['contributions']['composition']['host_bindings'][0]['renderer'] = null;
        $this->assertThrows(
            fn (): ExtensionManifest => ExtensionManifest::fromJson(json_encode($decoded, JSON_THROW_ON_ERROR)),
            InvalidArgumentException::class,
            'A block without its exact preview renderer is refused.',
        );

        $inspector = $manifest->contributions()->declarations()['composition']['host_bindings'][1] ?? null;
        $this->assertTrue(
            is_array($inspector) && !array_key_exists('renderer', $inspector),
            'A non-rendered inspector binding needs no renderer executable.',
        );
    }

    /** @since 0.2.0 */
    public function testRawArraysCannotManufactureExecutableRequirements(): void
    {
        $this->assertTrue(
            !method_exists(ExecutableBindingRequirements::class, 'fromValidatedGraph'),
            'No raw-array executable-authority factory is public.',
        );
        $constructor = new \ReflectionMethod(ExecutableBindingRequirements::class, '__construct');
        $this->assertTrue($constructor->isPrivate(), 'Only the canonical contribution factory may construct requirements.');
    }

    /** @since 0.2.0 */
    private function requirements(int $generation): ExecutableBindingRequirements
    {
        return $this->manifest($generation)->contributions()->executableBindingRequirements();
    }

    /** @since 0.2.0 */
    private function manifest(int $generation): ExtensionManifest
    {
        return ExtensionManifest::fromJson($this->fixture($generation));
    }

    /** @since 0.2.0 */
    private function fixture(int $generation): string
    {
        $path = dirname(__DIR__, 2)
            . sprintf('/resources/fixtures/generations/manifest-%d/kumwe.json', $generation);
        $json = file_get_contents($path);
        if (!is_string($json)) {
            throw new InvalidArgumentException('A generation manifest could not be read.');
        }

        return $json;
    }

    /** @since 0.2.0 */
    private function declared(
        ExecutableBindingRequirements $requirements,
        ExecutableBindingKind $kind,
        string $identifier,
    ): null {
        $requirements->assertDeclared($kind, $identifier);

        return null;
    }

    /** @param array<string, list<string>> $inventory @since 0.2.0 */
    private function satisfied(ExecutableBindingRequirements $requirements, array $inventory): null
    {
        $requirements->assertSatisfied($inventory);

        return null;
    }
}
