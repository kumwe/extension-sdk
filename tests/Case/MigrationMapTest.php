<?php

/**
 * Proves the generated migration map covers the classification completely and mechanically.
 *
 * @since 0.1.1
 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use Kumwe\Extension\Tests\TestCase;

/**
 * Holds `docs/migration-map.json` to its contract: complete, disjoint, mechanical, and honest.
 *
 * Every classified public type appears in exactly one of the two sets; every moved type's canonical
 * name is a real declaration in this package whose kind matches the classification; every canonical
 * name is derivable from the recorded prefix rules, so no moved name can be invented by hand; and
 * every retained entry names the closure members that block it.
 *
 * @since  0.1.1
 */
final class MigrationMapTest extends TestCase
{
    /**
     * Every classified public type lands in exactly one of moved or retained.
     *
     * @return  void
     *
     * @since   0.1.1
     */
    public function testMigrationMapCoversTheClassificationExactly(): void
    {
        $map = $this->map();
        $classified = $this->classifiedTypes();
        $moved = array_keys($map['moved']);
        $retained = array_map(static fn (array $entry): string => $entry['type'], $map['retained']);

        $covered = [...$moved, ...$retained];
        sort($covered, SORT_STRING);
        $expected = array_keys($classified);
        sort($expected, SORT_STRING);

        $this->assertSame(
            count($moved) + count($retained),
            count(array_unique($covered)),
            'No classified type may appear in both the moved set and the retained set.',
        );
        $this->assertSame($expected, $covered, 'The migration map must cover every classified public type exactly once.');
    }

    /**
     * Every moved type's canonical name exists in this package as the kind the classification promises.
     *
     * @return  void
     *
     * @since   0.1.1
     */
    public function testEveryMovedTypeExistsWithItsClassifiedKind(): void
    {
        $map = $this->map();
        $classified = $this->classifiedTypes();
        foreach ($map['moved'] as $source => $target) {
            $this->assertTrue(
                str_starts_with($target, 'Kumwe\\Extension\\'),
                sprintf('Moved type %s must carry a canonical Kumwe\\Extension name.', $target),
            );
            $kind = $classified[$source]['kind'];
            $exists = match ($kind) {
                'interface' => interface_exists($target),
                'enum' => enum_exists($target),
                default => class_exists($target) && !enum_exists($target),
            };
            $this->assertTrue($exists, sprintf('Canonical name %s must be a declared %s.', $target, $kind));
        }
    }

    /**
     * Every canonical name is exactly what the recorded prefix rules derive from its historical name.
     *
     * @return  void
     *
     * @since   0.1.1
     */
    public function testEveryCanonicalNameIsDerivedFromTheRecordedRules(): void
    {
        $map = $this->map();
        foreach ($map['moved'] as $source => $target) {
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
                sprintf('Canonical name for %s must follow the recorded mechanical derivation.', $source),
            );
        }
    }

    /**
     * Every retained entry records the blockers that keep it in the App, and none is empty.
     *
     * @return  void
     *
     * @since   0.1.1
     */
    public function testEveryRetainedTypeNamesItsBlockers(): void
    {
        $map = $this->map();
        $this->assertTrue(count($map['retained']) > 0, 'The retained set documents the not-yet-portable surface.');
        foreach ($map['retained'] as $entry) {
            $this->assertTrue(
                is_array($entry['blocked_by']) && $entry['blocked_by'] !== [],
                sprintf('Retained type %s must name at least one blocking closure member.', $entry['type']),
            );
            foreach ($entry['blocked_by'] as $blocker) {
                $this->assertTrue(
                    str_starts_with((string) $blocker, 'Kumwe\\App\\'),
                    sprintf('Retained type %s names a non-App blocker.', $entry['type']),
                );
            }
        }
    }

    /**
     * Load the committed migration map.
     *
     * @return  array{moved: array<string, string>, retained: list<array{type: string,
     *          blocked_by: list<string>}>, rules: list<array{from: string, to: string}>}  Decoded map.
     *
     * @since   0.1.1
     */
    private function map(): array
    {
        $path = dirname(__DIR__, 2) . '/docs/migration-map.json';
        $map = json_decode((string) file_get_contents($path), true);
        $this->assertTrue(is_array($map), 'docs/migration-map.json must decode.');
        $this->assertSame(
            'kumwe-extension-sdk-migration-map-v1',
            $map['format'] ?? null,
            'The migration map must declare its format.',
        );

        /** @var array{moved: array<string, string>, retained: list<array{type: string,
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
