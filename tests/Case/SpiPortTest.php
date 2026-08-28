<?php

/**
 * Proves the ported SPI value types behave exactly as their App originals.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use InvalidArgumentException;
use Kumwe\Extension\Contract\NameBasedUuid;
use Kumwe\Extension\Manifest\ExtensionIdentifier;
use Kumwe\Extension\Spi\Contribution\AdministratorViewDefinition;
use Kumwe\Extension\Spi\Contribution\ContributionOwner;
use Kumwe\Extension\Spi\Contribution\TranslationSetItemAssociation;
use Kumwe\Extension\Spi\Portal\Contribution\PortalTemplateDefinition;
use Kumwe\Extension\Tests\TestCase;

/**
 * Behavioral checks for the moved value types, assertions carried over from the App's own suite.
 *
 * The port changes namespaces and nothing else, so what the App's tests proved about identifier
 * normalisation, ownership boundaries, and the frozen translation-group derivation must hold here
 * word for word — including the dependency-free UUIDv5 that replaced `ramsey/uuid`.
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
        $owner->assertOwns('acme.editor.view', 'view');
        $this->assertThrows(
            static fn () => $owner->assertOwns('rival.editor.view', 'view'),
            InvalidArgumentException::class,
            'An owner must not claim an identifier outside its namespace.',
        );
        $this->assertThrows(
            static fn () => $owner->assertOwns('acme.editor.', 'view'),
            InvalidArgumentException::class,
            'A graphical identifier must carry a non-empty suffix.',
        );
        ContributionOwner::core()->assertOwns('content.read', 'capability');
        $this->assertThrows(
            static fn () => ContributionOwner::core()->assertOwns('content.read', 'view'),
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
     * The derivation value itself is proven against the pinned example in `PinnedSurfaceTest`; this
     * covers the refusal paths the pin cannot exercise.
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
}
