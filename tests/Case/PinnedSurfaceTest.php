<?php

/**
 * Proves every moved pinned type still carries its byte-pinned member surface.
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
 * Compares each moved type against the vendored compatibility pin that froze its members.
 *
 * The pin fixtures record method signatures and enum cases under the historical `Kumwe\App` names;
 * this test translates every name through the migration map's moved set and holds the canonical
 * `Kumwe\Extension` type to exactly those members, using the same signature grammar the App's own
 * compatibility gate renders. A pinned type the migration map retains stays the App's to assert and
 * is passed over here; the test fails if that ever leaves nothing to prove.
 *
 * @since  0.1.0
 */
final class PinnedSurfaceTest extends TestCase
{
    /**
     * Every moved pinned interface keeps its exact method signatures under its canonical name.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testMovedPinnedInterfacesKeepTheirSignatures(): void
    {
        $moved = $this->movedNames();
        $checked = 0;
        foreach ($this->pinFixtures() as $file => $fixture) {
            $interfaces = $fixture['interfaces'] ?? [];
            if (is_string($fixture['interface'] ?? null) && is_array($fixture['methods'] ?? null)) {
                $interfaces[$fixture['interface']] = $fixture['methods'];
            }
            foreach ($interfaces as $appName => $expected) {
                $target = $moved[$appName] ?? null;
                if ($target === null) {
                    continue;
                }
                $this->assertTrue(
                    interface_exists($target),
                    sprintf('Moved pinned interface %s must exist (%s).', $target, $file),
                );
                $actual = [];
                foreach ((new ReflectionClass($target))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                    if ($method->getDeclaringClass()->getName() === $target) {
                        $actual[] = $this->signature($method);
                    }
                }
                $translated = array_map(fn (string $line): string => $this->translate($line, $moved), $expected);
                sort($actual, SORT_STRING);
                sort($translated, SORT_STRING);
                $this->assertSame(
                    $translated,
                    $actual,
                    sprintf('Moved interface %s must match its pin %s.', $target, $file),
                );
                $checked++;
            }
        }
        $this->assertSame(9, $checked, sprintf('Expected exactly 9 moved pinned interfaces, got %d.', $checked));
    }

    /**
     * Every moved pinned enum keeps its exact case names and backed values.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testMovedPinnedEnumsKeepTheirCases(): void
    {
        $moved = $this->movedNames();
        $checked = 0;
        foreach ($this->pinFixtures() as $file => $fixture) {
            foreach ($fixture['enums'] ?? [] as $appName => $expected) {
                $target = $moved[$appName] ?? null;
                if ($target === null) {
                    continue;
                }
                $this->assertTrue(
                    enum_exists($target),
                    sprintf('Moved pinned enum %s must exist (%s).', $target, $file),
                );
                $actual = [];
                foreach ((new ReflectionEnum($target))->getCases() as $case) {
                    $this->assertTrue(
                        $case instanceof ReflectionEnumBackedCase,
                        sprintf('Pinned enum %s must stay backed.', $target),
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
                        sprintf('Moved enum %s must match its pinned backed values in %s.', $target, $file),
                    );
                } else {
                    ksort($actual, SORT_STRING);
                    ksort($expected, SORT_STRING);
                    $this->assertSame(
                        $expected,
                        $actual,
                        sprintf('Moved enum %s must match its pin %s.', $target, $file),
                    );
                }
                $checked++;
            }
        }
        $this->assertSame(4, $checked, sprintf('Expected exactly 4 moved pinned enums, got %d.', $checked));
    }

    /**
     * The moved association keeps its pinned members and its frozen group derivation, value for value.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testMovedAssociationKeepsItsPinnedDerivation(): void
    {
        $fixtures = $this->pinFixtures();
        $fixture = $fixtures['content-translation-association-v1.json'];
        $moved = $this->movedNames();
        $target = $moved[$fixture['association_class']] ?? null;
        $this->assertSame(
            \Kumwe\Extension\Spi\Contribution\TranslationSetItemAssociation::class,
            $target,
            'The association class must be moved by the migration map.',
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
        $this->assertSame(10, count($fixtures), 'All ten pin fixtures must be vendored.');

        return $fixtures;
    }

    /**
     * Load the migration map's moved-name translations.
     *
     * @return  array<string, string>  Canonical name by historical `Kumwe\App` name.
     *
     * @since   0.1.1
     */
    private function movedNames(): array
    {
        $map = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/docs/migration-map.json'), true);
        $this->assertTrue(is_array($map) && is_array($map['moved'] ?? null), 'The migration map must decode.');

        return $map['moved'];
    }

    /**
     * Rewrite the `Kumwe\App` names inside one pinned signature line to their canonical names.
     *
     * @param   string                 $line   Signature line as the pin fixture records it.
     * @param   array<string, string>  $moved  Canonical name by historical name.
     *
     * @return  string  The line with every moved name translated.
     *
     * @since   0.1.0
     */
    private function translate(string $line, array $moved): string
    {
        return (string) preg_replace_callback(
            '/Kumwe\\\\App\\\\[A-Za-z0-9_\\\\]+/',
            static fn (array $match): string => $moved[$match[0]] ?? $match[0],
            $line,
        );
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
