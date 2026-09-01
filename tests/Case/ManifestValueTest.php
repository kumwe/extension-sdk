<?php

/**
 * Proves the canonical manifest and package value types keep their published behaviour.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use InvalidArgumentException;
use Kumwe\Extension\Manifest\ExtensionDependency;
use Kumwe\Extension\Manifest\ExtensionIdentifier;
use Kumwe\Extension\Manifest\SemanticVersion;
use Kumwe\Extension\Manifest\TemplateKisCompatibility;
use Kumwe\Extension\Manifest\VersionConstraint;
use Kumwe\Extension\Package\PackageChecksum;
use Kumwe\Extension\Package\PackagePath;
use Kumwe\Extension\Package\PackageSignature;
use Kumwe\Extension\Tests\TestCase;

/**
 * Canonical value-type boundary and refusal assertions.
 *
 * @since  0.1.0
 */
final class ManifestValueTest extends TestCase
{
    /**
     * Versions order by Semantic Versioning precedence and ignore build metadata.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testSemanticVersionPrecedenceIgnoresBuildMetadata(): void
    {
        $alpha = SemanticVersion::fromString('2.0.0-alpha.1+build.7');
        $release = SemanticVersion::fromString('2.0.0+release.1');

        $this->assertTrue($alpha->isPreRelease(), 'An alpha version is a pre-release.');
        $this->assertTrue($alpha->compare($release) < 0, 'A pre-release ranks below the finished core.');
        $this->assertSame(
            0,
            $release->compare(SemanticVersion::fromString('2.0.0+other')),
            'Build metadata takes no part in precedence.',
        );
        $this->assertSame('2.0.0-alpha.1+build.7', (string) $alpha, 'The version renders back as written.');
    }

    /**
     * Leading zeroes and over-range components are refused rather than normalised.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testSemanticVersionRefusals(): void
    {
        $this->assertThrows(
            static fn (): SemanticVersion => SemanticVersion::fromString('02.0.0'),
            InvalidArgumentException::class,
            'A leading zero must be refused.',
        );
        $this->assertThrows(
            static fn (): SemanticVersion => SemanticVersion::fromString(PHP_INT_MAX . '0.0.0'),
            InvalidArgumentException::class,
            'A component past the platform integer range must be refused.',
        );
    }

    /**
     * Ranges and caret shorthands evaluate with their documented boundaries.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testVersionConstraintBoundaries(): void
    {
        $range = VersionConstraint::fromString('>=2.0.0 <3.0.0');

        $this->assertTrue($range->accepts(SemanticVersion::fromString('2.9.9')), 'Inside the range is accepted.');
        $this->assertTrue(!$range->accepts(SemanticVersion::fromString('3.0.0')), 'The upper bound is exclusive.');
        $this->assertTrue(
            VersionConstraint::fromString('^0.2.3')->accepts(SemanticVersion::fromString('0.2.9')),
            'A zero-major caret holds the minor fixed.',
        );
        $this->assertTrue(
            !VersionConstraint::fromString('^0.2.3')->accepts(SemanticVersion::fromString('0.3.0')),
            'A zero-major caret stops before the next minor.',
        );
        $this->assertTrue(
            VersionConstraint::fromString('*')->accepts(SemanticVersion::fromString('99.0.0')),
            'The wildcard accepts every version.',
        );
    }

    /**
     * A dependency evaluates its constraint and reports its optionality.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testDependencyEvaluatesCompatibilityAndOptionality(): void
    {
        $dependency = new ExtensionDependency(
            ExtensionIdentifier::fromString('acme/library'),
            VersionConstraint::fromString('^1.2.0'),
            true,
        );

        $this->assertTrue($dependency->isOptional(), 'The optional flag survives construction.');
        $this->assertTrue(
            $dependency->isSatisfiedBy(SemanticVersion::fromString('1.9.0')),
            'A version inside the caret satisfies the dependency.',
        );
        $this->assertTrue(
            !$dependency->isSatisfiedBy(SemanticVersion::fromString('2.0.0')),
            'The next major does not satisfy the dependency.',
        );
    }

    /**
     * A package path is stable when safe and refused for traversal or platform separators.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testPackagePathSafety(): void
    {
        $this->assertSame(
            'src/Provider.php',
            (string) PackagePath::fromString('src/Provider.php'),
            'A safe relative path passes through unchanged.',
        );
        foreach (['../secret', 'src/../secret', '/absolute', 'C:/windows', 'src\\file.php'] as $path) {
            $this->assertThrows(
                static fn (): PackagePath => PackagePath::fromString($path),
                InvalidArgumentException::class,
                sprintf('Path %s must be rejected.', $path),
            );
        }
    }

    /**
     * A checksum calculates, verifies in constant time, and refuses malformed digests.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testPackageChecksumCalculatesAndVerifies(): void
    {
        $checksum = PackageChecksum::calculate('package bytes');

        $this->assertTrue($checksum->matches('package bytes'), 'The digest matches its own bytes.');
        $this->assertTrue(!$checksum->matches('tampered'), 'Tampered bytes do not match.');
        $this->assertSame(hash('sha256', 'package bytes'), (string) $checksum, 'The rendering is the hex digest.');
        $this->assertThrows(
            static fn (): PackageChecksum => PackageChecksum::sha256('not-a-digest'),
            InvalidArgumentException::class,
            'A malformed digest must be refused.',
        );
    }

    /**
     * A signature decodes strictly and refuses a wrong-length value.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testPackageSignatureDecodesStrictly(): void
    {
        $encoded = base64_encode(str_repeat('s', SODIUM_CRYPTO_SIGN_BYTES));
        $signature = PackageSignature::ed25519('registry.primary', $encoded);

        $this->assertSame('registry.primary', $signature->keyId(), 'The key ID survives decoding.');
        $this->assertSame('ed25519', $signature->algorithm(), 'The scheme is always ed25519.');
        $this->assertSame($encoded, $signature->asBase64(), 'The re-encoding round-trips.');
        $this->assertThrows(
            static fn (): PackageSignature => PackageSignature::ed25519('registry.primary', base64_encode('short')),
            InvalidArgumentException::class,
            'A wrong-length signature must be refused.',
        );
    }

    /**
     * The schema-one KIS declaration is an exact compatibility point, not an open range.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testLegacyKisOneCompatibilityIsExact(): void
    {
        $compatibility = TemplateKisCompatibility::schemaOneKis();

        $this->assertSame(1, $compatibility->contract(), 'The schema-one declaration is contract version 1.');
        $this->assertSame('kis-1.0', $compatibility->standard(), 'The schema-one standard is KIS 1.0.');
        $this->assertTrue(
            $compatibility->supportsComponents(SemanticVersion::fromString('1.0.0')),
            'Exactly 1.0.0 components are supported.',
        );
        $this->assertTrue(
            !$compatibility->supportsComponents(SemanticVersion::fromString('1.0.1')),
            'The schema-one point does not widen upward.',
        );
        $this->assertTrue(
            !$compatibility->supportsTokens(SemanticVersion::fromString('0.9.9')),
            'The schema-one point does not widen downward.',
        );
    }

    /**
     * Malformed and open-ended KIS declarations fail closed with their stable messages.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testKisCompatibilityRefusesMalformedDeclarations(): void
    {
        $valid = [
            'contract' => 1,
            'standard' => 'kis-1.0',
            'components' => ['minimum' => '1.0.0', 'maximum' => '1.2.0'],
            'tokens' => ['minimum' => '1.0.0', 'maximum' => '1.0.0'],
        ];
        $compatibility = TemplateKisCompatibility::fromArray($valid);
        $this->assertTrue(
            $compatibility->supportsComponents(SemanticVersion::fromString('1.1.0'))
                && !$compatibility->supportsComponents(SemanticVersion::fromString('2.0.0')),
            'The component range binds independently of the token range.',
        );

        $cases = [
            'unknown key future' => [...$valid, 'future' => true],
            'contract must be version 1' => [...$valid, 'contract' => 2],
            'kis-major.minor' => [...$valid, 'standard' => '^1.0'],
            'components range must be a JSON object' => array_diff_key($valid, ['components' => null]),
            'requires string minimum and maximum' => [...$valid, 'tokens' => ['minimum' => '1.0.0']],
            'maximum cannot precede its minimum'
                => [...$valid, 'components' => ['minimum' => '2.0.0', 'maximum' => '1.0.0']],
        ];
        foreach ($cases as $fragment => $declaration) {
            $failure = $this->assertThrows(
                static fn (): TemplateKisCompatibility => TemplateKisCompatibility::fromArray($declaration),
                InvalidArgumentException::class,
                sprintf('A declaration violating "%s" must be refused.', $fragment),
            );
            $this->assertStringContains(
                (string) $fragment,
                $failure->getMessage(),
                'The refusal names the violated boundary.',
            );
        }
    }
}
