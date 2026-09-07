<?php

/**
 * Proves the canonical SPI values enforce their published behavior.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use Kumwe\Extension\Manifest\ManifestIdentifierPolicies;

use InvalidArgumentException;
use Kumwe\Extension\Contract\NameBasedUuid;
use Kumwe\Extension\Manifest\ExtensionIdentifier;
use Kumwe\Administrator\Contract\AdministratorRouteDefinition;
use Kumwe\Administrator\Contract\AdministratorViewDefinition;
use Kumwe\Administrator\Contract\AdministratorWorkspaceDefinition;
use Kumwe\Contribution\ContributionOwner;
use Kumwe\Extension\Spi\Contribution\TranslationSetItemAssociation;
use Kumwe\Portal\Contract\PortalRouteDefinition;
use Kumwe\Portal\Contract\PortalTemplateDefinition;
use Kumwe\Portal\Contract\PortalWorkspaceDefinition;
use Kumwe\Extension\Tests\TestCase;

/**
 * Behavioral checks for identifier normalization, ownership boundaries and stable translation-group
 * derivation.
 *
 * @since  0.1.0
 */
final class SpiPortTest extends TestCase
{
    /**
     * Identifier parsing normalises case and whitespace, and compares by value.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testIdentifierNormalizesAndComparesVendorNames(): void
    {
        $identifier = ExtensionIdentifier::fromString(' Acme/Editor ');

        $this->assertSame('acme/editor', (string) $identifier, 'The identifier must normalise case and space.');
        $this->assertTrue(
            $identifier->equals(ExtensionIdentifier::fromString('acme/editor')),
            'Two spellings of the same identifier must compare equal.',
        );
    }

    /**
     * An identifier without a vendor boundary is refused rather than repaired.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testIdentifierRejectsNamesWithoutVendorBoundary(): void
    {
        $this->assertThrows(
            static fn (): ExtensionIdentifier => ExtensionIdentifier::fromString('editor'),
            InvalidArgumentException::class,
            'A single-segment name must be refused.',
        );
    }

    /**
     * An extension owner claims only its own dotted namespace, and core keeps its capability exemption.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testContributionOwnerBoundsItsNamespace(): void
    {
        $owner = ContributionOwner::extension('acme/editor');

        $this->assertSame('acme.editor', $owner->namespace(), 'The owner namespace is the dotted identifier.');
        $owner->assertOwns('acme.editor.view', ManifestIdentifierPolicies::forKind('view'));
        $this->assertThrows(
            static fn () => $owner->assertOwns('rival.editor.view', ManifestIdentifierPolicies::forKind('view')),
            InvalidArgumentException::class,
            'An owner must not claim an identifier outside its namespace.',
        );
        $this->assertThrows(
            static fn () => $owner->assertOwns('acme.editor.', ManifestIdentifierPolicies::forKind('view')),
            InvalidArgumentException::class,
            'A graphical identifier must carry a non-empty suffix.',
        );
        ContributionOwner::core()->assertOwns('content.read', ManifestIdentifierPolicies::forKind('capability'));
        $this->assertThrows(
            static fn () => ContributionOwner::core()->assertOwns('content.read', ManifestIdentifierPolicies::forKind('view')),
            InvalidArgumentException::class,
            'Core views must sit inside the core namespace.',
        );
    }

    /**
     * The ported definitions enforce the same construction contract as the originals.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testPortedDefinitionsKeepTheirConstructionContract(): void
    {
        $view = new AdministratorViewDefinition('acme.editor.index', 'index.twig');
        $this->assertSame('acme.editor.index', $view->identifier(), 'A view identifies by its name.');
        $this->assertSame(
            ['name' => 'acme.editor.index', 'template' => 'index.twig'],
            $view->toArray(),
            'The view export is its canonical manifest document.',
        );
        $template = new PortalTemplateDefinition('acme.editor.status', 'status.twig');
        $this->assertSame('acme.editor.status', $template->identifier(), 'A portal template identifies by name.');
    }

    /**
     * The name-based UUID matches the RFC 4122 reference vector and refuses a malformed namespace.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testNameBasedUuidMatchesTheRfcVector(): void
    {
        $this->assertSame(
            '886313e1-3b8a-5372-9b90-0c9aee199e5d',
            NameBasedUuid::v5('6ba7b810-9dad-11d1-80b4-00c04fd430c8', 'python.org'),
            'The RFC 4122 DNS-namespace reference vector must reproduce exactly.',
        );
        $this->assertSame(
            NameBasedUuid::v5('6BA7B810-9DAD-11D1-80B4-00C04FD430C8', 'python.org'),
            NameBasedUuid::v5('6ba7b810-9dad-11d1-80b4-00c04fd430c8', 'python.org'),
            'Namespace case must not change the derivation.',
        );
        $this->assertThrows(
            static fn (): string => NameBasedUuid::v5('not-a-uuid', 'python.org'),
            InvalidArgumentException::class,
            'A malformed namespace must be refused.',
        );
    }

    /**
     * The association keeps its ownership boundary and refuses an unsupported generation.
     *
     * It also covers malformed and unsupported input paths that a valid canonical example cannot
     * exercise.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testAssociationRefusalsMatchTheContract(): void
    {
        $this->assertThrows(
            static fn () => new TranslationSetItemAssociation('acme/blog', 'acme.blog.articles', 2),
            InvalidArgumentException::class,
            'Only the implemented association generation is accepted.',
        );
        $this->assertThrows(
            static fn () => new TranslationSetItemAssociation('acme/blog', 'rival.blog.articles'),
            InvalidArgumentException::class,
            'A package cannot claim another package\'s translation set.',
        );
        $this->assertThrows(
            static fn () => new TranslationSetItemAssociation('core', 'core.articles'),
            InvalidArgumentException::class,
            'Core content declares no set and is refused here.',
        );
        $association = new TranslationSetItemAssociation('acme/blog', 'acme.blog.articles');
        $this->assertThrows(
            static fn (): string => $association->groupIdForSite(''),
            InvalidArgumentException::class,
            'An empty site identifier must be refused.',
        );
        $this->assertSame(
            $association->groupIdForSite('default'),
            $association->groupIdForSite('default'),
            'The derivation is deterministic across calls.',
        );
    }

    /**
     * Neither graphical route definition admits a verb list that mixes safe and mutating methods.
     *
     * The registry decides once per route whether the CSRF guard sits in front of it, so a route
     * answering both GET and POST would drag that guard onto the safe verb as well.
     *
     * @return  void
     *
     * @since   0.2.4
     */
    public function testRouteDefinitionsRefuseToMixSafeAndMutatingMethods(): void
    {
        $administrator = $this->assertThrows(
            static fn (): AdministratorRouteDefinition => new AdministratorRouteDefinition(
                'acme.editor.index',
                '/',
                ['GET', 'POST'],
                'acme.editor.manage',
                'acme.editor.index',
            ),
            InvalidArgumentException::class,
            'An administrator route answering both GET and POST must be refused.',
        );
        $this->assertStringContains(
            'cannot mix',
            $administrator->getMessage(),
            'The administrator refusal names the rule.',
        );
        $portal = $this->assertThrows(
            static fn (): PortalRouteDefinition => new PortalRouteDefinition(
                'acme.editor.status',
                '/',
                ['GET', 'POST'],
                'acme.editor.manage',
                'acme.editor.status',
            ),
            InvalidArgumentException::class,
            'A portal route answering both GET and POST must be refused.',
        );
        $this->assertStringContains('cannot mix', $portal->getMessage(), 'The portal refusal names the rule.');

        $mutating = new AdministratorRouteDefinition(
            'acme.editor.save',
            '/',
            ['PUT', 'POST', 'PUT'],
            'acme.editor.manage',
            'acme.editor.index',
        );
        $this->assertSame(['POST', 'PUT'], $mutating->methods, 'Mutating verbs alone de-duplicate and byte-sort.');
        $portalMutating = new PortalRouteDefinition(
            'acme.editor.remove',
            '/',
            ['PATCH', 'DELETE'],
            'acme.editor.manage',
            'acme.editor.status',
        );
        $this->assertSame(['DELETE', 'PATCH'], $portalMutating->methods, 'Portal mutating verbs normalise the same way.');
    }

    /**
     * Repeated dots outside the exact owner prefix cannot become an ambiguous core contribution suffix.
     *
     * @return  void
     *
     * @since   0.2.4
     */
    public function testOwnerBoundaryRejectsRepeatedDotsInTheContributionSuffix(): void
    {
        $failure = $this->assertThrows(
            static fn () => ContributionOwner::core()->assertOwns('core..settings', ManifestIdentifierPolicies::forKind('interface surface')),
            InvalidArgumentException::class,
            'A repeated dot after the core boundary must be refused.',
        );
        $this->assertTrue($failure instanceof \Kumwe\Contribution\ContributionRejected, 'The canonical owner returns its typed refusal.');
        $this->assertSame('invalid_identifier', $failure->reason, 'The refusal identifies malformed suffix grammar.');
    }

    /**
     * Historical package dots remain representable only as part of the exact declaring owner prefix.
     *
     * @return  void
     *
     * @since   0.2.4
     */
    public function testLegacyOwnerDotSpellingsRemainRepresentable(): void
    {
        foreach (['a../b' => 'a...b', 'a./b' => 'a..b', 'a/b.' => 'a.b.'] as $ownerIdentifier => $namespace) {
            $owner = ContributionOwner::extension($ownerIdentifier);
            $identifier = $namespace . '.workspace';

            $owner->assertOwns($identifier, ManifestIdentifierPolicies::forKind('interface surface'));
            AdministratorWorkspaceDefinition::assertIdentifier($identifier, 'workspace');
            PortalWorkspaceDefinition::assertIdentifier($identifier, 'workspace');

            $this->assertSame(
                $namespace,
                $owner->namespace(),
                sprintf('The %s owner keeps its historical dotted namespace.', $ownerIdentifier),
            );
        }
    }

    /**
     * Every shared lexical parser rejects unsafe boundaries, path characters, casing drift and overlength values.
     *
     * @return  void
     *
     * @since   0.2.4
     */
    public function testSharedGrammarRejectsUnsafeOrAmbiguousIdentifiers(): void
    {
        foreach (
            [
                'leading separator' => '.acme.orders',
                'trailing separator' => 'acme.orders.',
                'uppercase' => 'Acme.orders',
                'path separator' => 'acme/orders.index',
                'overlength' => 'a.' . str_repeat('b', 190),
            ] as $case => $identifier
        ) {
            $this->assertThrows(
                static fn () => AdministratorWorkspaceDefinition::assertIdentifier($identifier, 'test'),
                InvalidArgumentException::class,
                sprintf('The administrator grammar must refuse a %s.', $case),
            );
            $this->assertThrows(
                static fn () => PortalWorkspaceDefinition::assertIdentifier($identifier, 'test'),
                InvalidArgumentException::class,
                sprintf('The portal grammar must refuse a %s.', $case),
            );
        }
    }

    /**
     * Both 63-character package segments remain representable inside the 191-character contribution bound.
     *
     * @return  void
     *
     * @since   0.2.4
     */
    public function testMaximumExtensionOwnerSegmentsRemainRepresentable(): void
    {
        $vendor = '9' . str_repeat('a', 62);
        $package = '2' . str_repeat('b', 62);
        $owner = ContributionOwner::extension($vendor . '/' . $package);
        $identifier = $vendor . '.' . $package . '.workspace';

        $owner->assertOwns($identifier, ManifestIdentifierPolicies::forKind('interface surface'));
        AdministratorWorkspaceDefinition::assertIdentifier($identifier, 'workspace');
        PortalWorkspaceDefinition::assertIdentifier($identifier, 'workspace');

        $this->assertSame(
            $vendor . '.' . $package,
            $owner->namespace(),
            'The maximum owner namespace is its dotted identifier.',
        );
        $this->assertTrue(strlen($identifier) <= 191, 'The identifier stays inside the shared contribution bound.');
    }
}
