<?php

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use InvalidArgumentException;
use Kumwe\CanonicalJson\CanonicalEncoder;
use Kumwe\Contribution\ContributionOwner;
use Kumwe\Extension\Manifest\ExtensionManifest;
use Kumwe\Extension\Manifest\ManifestIdentifierPolicies;
use Kumwe\Extension\Tests\TestCase;
use ReflectionClass;
use RuntimeException;

/** SDK dependency ownership and explicit composition boundaries. @since 0.3.0 */
final class CanonicalPackageBoundaryTest extends TestCase
{
    /** @since 0.3.0 */
    public function testMovedDefinitionsResolveOnlyFromTheirCanonicalOwner(): void
    {
        $root = dirname(__DIR__, 2);
        $map = json_decode((string) file_get_contents($root . '/docs/canonical-package-migration.json'), true, 64, JSON_THROW_ON_ERROR);
        foreach ($map['symbols'] as $type) {
            $this->assertTrue(!is_file($root . '/' . $type['source_path']), 'The former SDK definition is removed.');
            $this->assertTrue(!class_exists($type['old_fqcn']) && !interface_exists($type['old_fqcn'])
                && !enum_exists($type['old_fqcn']), 'No old SDK alias remains autoloadable.');
            $reflection = new ReflectionClass($type['new_fqcn']);
            $path = $reflection->getFileName();
            $this->assertTrue(is_string($path) && !str_starts_with($path, $root . '/src/'),
                'Canonical definitions are loaded from their dependency.');
        }
        $this->assertTrue(interface_exists(\Kumwe\Extension\Spi\Application\ExecutionContext::class),
            'The SDK host-issued orchestration context remains owned by the SDK.');
        $this->assertTrue(!class_exists('Kumwe\\Extension\\Support\\CanonicalJson'),
            'The SDK no longer owns the generic PHP canonical implementation.');
    }

    /** @since 0.3.0 */
    public function testManifestDelegatesDeclarationEncodingAndPropagatesRefusal(): void
    {
        $encoder = new class implements CanonicalEncoder {
            public int $calls = 0;
            public bool $refuse = false;
            public function encode(mixed $value): string
            {
                ++$this->calls;
                if ($this->refuse) {
                    throw new RuntimeException('configured encoder refused');
                }
                // Fixed protocol test response: this double makes no canonicalization claim.
                return '{}';
            }
            public function digest(mixed $value): string
            {
                throw new RuntimeException('SDK must not invent a digest request for declaration admission.');
            }
        };
        $json = (string) file_get_contents(dirname(__DIR__, 2) . '/resources/fixtures/generations/manifest-4/kumwe.json');
        $manifest = ExtensionManifest::fromJson($encoder, $json);
        $this->assertSame('kumwe/contract-manifest-four', $manifest->identifier()->value(), 'SDK retains the signed package identity.');
        $this->assertTrue($encoder->calls > 0, 'SDK passes the exact configured encoder to canonical job admission.');
        $encoder->refuse = true;
        $failure = $this->assertThrows(static fn () => ExtensionManifest::fromJson($encoder, $json), RuntimeException::class,
            'Encoder refusal propagates without a local fallback.');
        $this->assertSame('configured encoder refused', $failure->getMessage(), 'The original dependency refusal is retained.');
    }

    /** @since 0.3.0 */
    public function testManifestSelectsExplicitSurfacePolicies(): void
    {
        $owner = ContributionOwner::extension('acme/editor');
        $owner->assertOwns('acme.editor.view', ManifestIdentifierPolicies::forKind('view'));
        $owner->assertOwns('acme.editor.job@1', ManifestIdentifierPolicies::forKind('job'));
        $owner->assertOwns('acme.editor/block', ManifestIdentifierPolicies::forKind('canonical composition document'));
        ContributionOwner::core()->assertOwns('content.read', ManifestIdentifierPolicies::forKind('capability'));
        $this->assertSame('view', ManifestIdentifierPolicies::forKind('view')->surface(), 'Manifest selects a diagnostic surface explicitly.');
        foreach (['rival.editor.view', 'acme.editor..view', 'acme.editor.view@1'] as $identifier) {
            $this->assertThrows(static fn () => $owner->assertOwns($identifier, ManifestIdentifierPolicies::forKind('view')),
                InvalidArgumentException::class, 'Graphical manifests retain exact owner and suffix boundaries.');
        }
    }
}
