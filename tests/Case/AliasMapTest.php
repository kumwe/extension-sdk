<?php

/**
 * Proves the generated alias map covers the classification completely and mechanically.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use Kumwe\Extension\Tests\TestCase;

/**
 * Holds `docs/alias-map.json` to its contract: complete, disjoint, mechanical, and honest.
 *
 * Every classified public type appears in exactly one of the two sets; every alias target is a real
 * declaration in this package whose kind matches the classification; every target is derivable from
 * the recorded prefix rules, so no alias can be invented by hand; and every skipped entry names the
 * closure members that block it.
 *
 * @since  0.1.0
 */
final class AliasMapTest extends TestCase
{
    /**
     * Every classified public type lands in exactly one of aliases or skipped.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testAliasMapCoversTheClassificationExactly(): void
    {
        $map = $this->map();
        $classified = $this->classifiedTypes();
        $aliased = array_keys($map['aliases']);
        $skipped = array_map(static fn (array $entry): string => $entry['type'], $map['skipped']);

        $covered = [...$aliased, ...$skipped];
        sort($covered, SORT_STRING);
        $expected = array_keys($classified);
        sort($expected, SORT_STRING);

        $this->assertSame(
            count($aliased) + count($skipped),
            count(array_unique($covered)),
            'No classified type may appear in both the alias set and the skipped set.',
        );
        $this->assertSame($expected, $covered, 'The alias map must cover every classified public type exactly once.');
    }

    /**
     * Every alias target exists in this package as the kind the classification promises.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testEveryAliasTargetExistsWithItsClassifiedKind(): void
    {
        $map = $this->map();
        $classified = $this->classifiedTypes();
        foreach ($map['aliases'] as $source => $target) {
            $this->assertTrue(
                str_starts_with($target, 'Kumwe\\Extension\\'),
                sprintf('Alias target %s must be a canonical Kumwe\\Extension name.', $target),
            );
            $kind = $classified[$source]['kind'];
            $exists = match ($kind) {
                'interface' => interface_exists($target),
                'enum' => enum_exists($target),
                default => class_exists($target) && !enum_exists($target),
            };
            $this->assertTrue($exists, sprintf('Alias target %s must be a declared %s.', $target, $kind));
        }
    }

    /**
     * Every alias target is exactly what the recorded prefix rules derive from its source.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testEveryAliasIsDerivedFromTheRecordedRules(): void
    {
        $map = $this->map();
        foreach ($map['aliases'] as $source => $target) {
            $derived = null;
            foreach ($map['rules'] as $rule) {
                if (str_starts_with($source, $rule['from'])) {
                    $derived = $rule['to'] . substr($source, strlen($rule['from']));
                    break;
                }
            }
            $this->assertSame(
                $derived,
                $target,
                sprintf('Alias for %s must follow the recorded mechanical derivation.', $source),
            );
        }
    }

    /**
     * Every skipped entry records the blockers that keep it in the App, and none is empty.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testEverySkippedTypeNamesItsBlockers(): void
    {
        $map = $this->map();
        $this->assertTrue(count($map['skipped']) > 0, 'The skipped set documents the not-yet-portable surface.');
        foreach ($map['skipped'] as $entry) {
            $this->assertTrue(
                is_array($entry['blocked_by']) && $entry['blocked_by'] !== [],
                sprintf('Skipped type %s must name at least one blocking closure member.', $entry['type']),
            );
            foreach ($entry['blocked_by'] as $blocker) {
                $this->assertTrue(
                    str_starts_with((string) $blocker, 'Kumwe\\App\\'),
                    sprintf('Skipped type %s names a non-App blocker.', $entry['type']),
                );
            }
        }
    }

    /**
     * Load the committed alias map.
     *
     * @return  array{aliases: array<string, string>, skipped: list<array{type: string,
     *          blocked_by: list<string>}>, rules: list<array{from: string, to: string}>}  Decoded map.
     *
     * @since   0.1.0
     */
    private function map(): array
    {
        $path = dirname(__DIR__, 2) . '/docs/alias-map.json';
        $map = json_decode((string) file_get_contents($path), true);
        $this->assertTrue(is_array($map), 'docs/alias-map.json must decode.');
        $this->assertSame(
            'kumwe-extension-sdk-alias-map-v1',
            $map['format'] ?? null,
            'The alias map must declare its format.',
        );

        /** @var array{aliases: array<string, string>, skipped: list<array{type: string,
         *      blocked_by: list<string>}>, rules: list<array{from: string, to: string}>} $map */
        return $map;
    }

    /**
     * Load the classified public types from the vendored classification, keyed by FQCN.
     *
     * @return  array<string, array{kind: string}>  Classified entries by type name.
     *
     * @since   0.1.0
     */
    private function classifiedTypes(): array
    {
        $path = dirname(__DIR__, 2) . '/resources/contract/classification.json';
        $classification = json_decode((string) file_get_contents($path), true);
        $this->assertTrue(is_array($classification), 'The vendored classification must decode.');
        $types = [];
        foreach ($classification['types'] as $entry) {
            $types[$entry['type']] = ['kind' => $entry['kind']];
        }
        $this->assertSame(122, count($types), 'The classification lists 122 public types.');

        return $types;
    }
}
