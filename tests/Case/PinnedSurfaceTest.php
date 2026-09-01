<?php

/**
 * Proves every canonical pinned type carries its byte-pinned member surface.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use Kumwe\Extension\Tests\TestCase;
use ReflectionClass;
use ReflectionEnum;
use ReflectionEnumBackedCase;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

/**
 * Holds each canonical type directly to the vendored compatibility pin that froze its members.
 *
 * The pin fixtures record method signatures and enum cases under the canonical `Kumwe\Extension`
 * names, freezing the member surface the App adopted; every fixture key is asserted as-is, with no
 * translation step, using the same signature grammar the App's own compatibility gate renders. Pins
 * whose surfaces the canonical reset withdrew, or ceded to an already-extracted canonical library
 * such as `kumwe/conversion`, are no longer vendored — the adoption map's replaced records document
 * where each went. The migration record in docs/ carries no authority over these pins; the test
 * fails if the fixtures ever leave nothing to prove.
 *
 * @since  0.1.0
 */
final class PinnedSurfaceTest extends TestCase
{
    /**
     * Every canonical pinned interface keeps its exact method signatures.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testPinnedInterfacesKeepTheirSignatures(): void
    {
        $checked = 0;
        foreach ($this->pinFixtures() as $file => $fixture) {
            $interfaces = $fixture['interfaces'] ?? [];
            if (is_string($fixture['interface'] ?? null) && is_array($fixture['methods'] ?? null)) {
                $interfaces[$fixture['interface']] = $fixture['methods'];
            }
            foreach ($interfaces as $name => $expected) {
                $this->assertTrue(
                    is_string($name) && is_array($expected),
                    sprintf('Pin fixture %s must map interface names to signature lists.', $file),
                );
                $this->assertTrue(
                    interface_exists($name),
                    sprintf('Pinned interface %s must exist (%s).', $name, $file),
                );
                $actual = [];
                foreach ((new ReflectionClass($name))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                    if ($method->getDeclaringClass()->getName() === $name) {
                        $actual[] = $this->signature($method);
                    }
                }
                sort($actual, SORT_STRING);
                sort($expected, SORT_STRING);
                $this->assertSame(
                    $expected,
                    $actual,
                    sprintf('Interface %s must match its pin %s.', $name, $file),
                );
                $checked++;
            }
        }
        $this->assertSame(2, $checked, sprintf('Expected exactly 2 pinned interfaces, got %d.', $checked));
    }

    /**
     * Every canonical pinned enum keeps its exact case names and backed values.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testPinnedEnumsKeepTheirCases(): void
    {
        $checked = 0;
        foreach ($this->pinFixtures() as $file => $fixture) {
            foreach ($fixture['enums'] ?? [] as $name => $expected) {
                $this->assertTrue(
                    is_string($name) && is_array($expected),
                    sprintf('Pin fixture %s must map enum names to case records.', $file),
                );
                $this->assertTrue(
                    enum_exists($name),
                    sprintf('Pinned enum %s must exist (%s).', $name, $file),
                );
                $actual = [];
                foreach ((new ReflectionEnum($name))->getCases() as $case) {
                    $this->assertTrue(
                        $case instanceof ReflectionEnumBackedCase,
                        sprintf('Pinned enum %s must stay backed.', $name),
                    );
                    $actual[$case->getName()] = $case->getBackingValue();
                }
                // A pin records either the full name-to-value map or, for wire-format enums, the
                // ordered backed values alone; each shape is compared as recorded.
                if (array_is_list($expected)) {
                    $values = array_values($actual);
                    sort($values, SORT_STRING);
                    sort($expected, SORT_STRING);
                    $this->assertSame(
                        $expected,
                        $values,
                        sprintf('Enum %s must match its pinned backed values in %s.', $name, $file),
                    );
                } else {
                    ksort($actual, SORT_STRING);
                    ksort($expected, SORT_STRING);
                    $this->assertSame(
                        $expected,
                        $actual,
                        sprintf('Enum %s must match its pin %s.', $name, $file),
                    );
                }
                $checked++;
            }
        }
        $this->assertSame(2, $checked, sprintf('Expected exactly 2 pinned enums, got %d.', $checked));
    }

    /**
     * The canonical association keeps its pinned members and its frozen group derivation, value for value.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testAssociationKeepsItsPinnedDerivation(): void
    {
        $fixtures = $this->pinFixtures();
        $fixture = $fixtures['content-translation-association-v1.json'];
        $this->assertSame(
            \Kumwe\Extension\Spi\Contribution\TranslationSetItemAssociation::class,
            $fixture['association_class'],
            'The pin must name the canonical association class.',
        );

        $example = $fixture['group_derivation']['example'];
        $association = new \Kumwe\Extension\Spi\Contribution\TranslationSetItemAssociation(
            $example['owner'],
            $example['translation_set'],
            $example['generation'],
        );
        $this->assertSame(
            ['generation', 'owner', 'translation_set'],
            array_keys($association->toArray()),
            'The association export must keep its pinned members in order.',
        );
        $this->assertSame(
            $example['group_id'],
            $association->groupIdForSite($example['site']),
            'The dependency-free UUID derivation must reproduce the pinned example value exactly.',
        );
        $this->assertSame(
            $fixture['group_derivation']['namespace'],
            \Kumwe\Extension\Spi\Contribution\TranslationSetItemAssociation::GROUP_NAMESPACE,
            'The frozen derivation namespace must not change.',
        );
    }

    /**
     * Load every vendored pin fixture document, keyed by file name.
     *
     * @return  array<string, array<string, mixed>>  Decoded pin fixtures.
     *
     * @since   0.1.0
     */
    private function pinFixtures(): array
    {
        $directory = dirname(__DIR__, 2) . '/resources/fixtures/pins';
        $fixtures = [];
        foreach (glob($directory . '/*.json') ?: [] as $path) {
            $decoded = json_decode((string) file_get_contents($path), true);
            $this->assertTrue(is_array($decoded), sprintf('Pin fixture %s must decode.', basename($path)));
            $fixtures[basename($path)] = $decoded;
        }
        $this->assertSame(5, count($fixtures), 'All five pin fixtures must be vendored.');

        return $fixtures;
    }

    /**
     * Render one declared method into the pin fixtures' canonical signature grammar.
     *
     * @param   ReflectionMethod  $method  Declared public method.
     *
     * @return  string  Fully qualified parameter and return signature.
     *
     * @since   0.1.0
     */
    private function signature(ReflectionMethod $method): string
    {
        $parameters = array_map(
            fn (ReflectionParameter $parameter): string => $this->parameter($parameter),
            $method->getParameters(),
        );
        $returnType = $method->getReturnType();
        $this->assertTrue(
            $returnType !== null,
            sprintf('Public method %s has no return type.', $method->getName()),
        );

        /** @var ReflectionType $returnType */
        return sprintf(
            '%s(%s): %s',
            $method->getName(),
            implode(', ', $parameters),
            $this->type($returnType),
        );
    }

    /**
     * Render one method parameter, including reference, variadic, and optional-value compatibility.
     *
     * @param   ReflectionParameter  $parameter  Parameter to encode.
     *
     * @return  string  Canonical parameter fragment.
     *
     * @since   0.1.0
     */
    private function parameter(ReflectionParameter $parameter): string
    {
        $type = $parameter->getType();
        $this->assertTrue(
            $type !== null,
            sprintf('Public parameter $%s has no type.', $parameter->getName()),
        );
        /** @var ReflectionType $type */
        $rendered = $this->type($type) . ' ';
        if ($parameter->isPassedByReference()) {
            $rendered .= '&';
        }
        if ($parameter->isVariadic()) {
            $rendered .= '...';
        }
        $rendered .= '$' . $parameter->getName();
        if ($parameter->isDefaultValueAvailable()) {
            $rendered .= ' = ' . ($parameter->isDefaultValueConstant()
                ? (string) $parameter->getDefaultValueConstantName()
                : var_export($parameter->getDefaultValue(), true));
        }

        return $rendered;
    }

    /**
     * Render named, union, and intersection reflection types without source-context import abbreviations.
     *
     * @param   ReflectionType  $type  Reflected type declaration.
     *
     * @return  string  Canonical fully qualified type expression.
     *
     * @since   0.1.0
     */
    private function type(ReflectionType $type): string
    {
        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();

            return $type->allowsNull() && !in_array($name, ['mixed', 'null'], true) ? '?' . $name : $name;
        }
        if ($type instanceof ReflectionUnionType) {
            return implode('|', array_map(fn (ReflectionType $part): string => $this->type($part), $type->getTypes()));
        }
        $this->assertTrue($type instanceof ReflectionIntersectionType, 'Unknown reflection type kind.');

        /** @var ReflectionIntersectionType $type */
        return implode('&', array_map(fn (ReflectionType $part): string => $this->type($part), $type->getTypes()));
    }
}
