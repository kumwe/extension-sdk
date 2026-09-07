<?php

/**
 * Keeps the App's recorded adoption map honest against the SDK's canonical classification.
 *
 * @since 0.1.1
 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use Kumwe\Extension\Tests\TestCase;

/**
 * Holds `docs/migration-map.json` to its contract as the recorded adoption map for the App.
 *
 * The map documents the App's historical-to-canonical adoption change and is no longer release
 * authority: `resources/contract/classification.json` now records the SDK's own canonical public
 * API, so the map is judged against that classification instead of defining it. These tests keep
 * the record honest — the map declares its format; every moved value names a type the canonical
 * classification lists, declared in this package with the kind the classification records; no two
 * historical sources collapse onto one canonical name; every canonical name equals the mechanical
 * derivation of its historical name through the recorded prefix rules, so no moved name can be
 * invented by hand; every retained entry names the App-side closure members that block it; and
 * every replaced entry records why the canonical reset withdrew its historical surface.
 *
 * @since  0.1.1
 */
final class MigrationMapTest extends TestCase
{
    /**
     * Every moved value names a classified canonical type of the recorded kind, and only once.
     *
     * The moved set must be non-empty, each of its values must appear in the canonical
     * classification, each must be declared in this package as the kind the classification
     * records, and no two historical sources may map to the same canonical value.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function testEveryMovedValueNamesAClassifiedCanonicalTypeOnce(): void
    {
        $map = $this->map();
        $classified = $this->classifiedTypes();

        $this->assertTrue($map['moved'] !== [], 'The adoption map must record at least one moved type.');

        $sourcesByTarget = [];
        $successors = json_decode((string) file_get_contents(dirname(__DIR__, 2)
            . '/docs/canonical-package-migration.json'), true, 64, JSON_THROW_ON_ERROR);
        $byOld = array_column($successors['symbols'], null, 'old_fqcn');
        foreach ($map['moved'] as $source => $target) {
            // The historical host adoption record stays unchanged; this candidate records a second move.
            if (isset($byOld[$target])) {
                $target = $byOld[$target]['new_fqcn'];
                $type = new \ReflectionClass($target);
                $classified[$target] = ['kind' => $type->isInterface() ? 'interface' : ($type->isEnum() ? 'enum' : 'class')];
            }
            $this->assertTrue(
                isset($classified[$target]),
                sprintf('Moved value %s must name a type the canonical classification lists.', $target),
            );
            $kind = $classified[$target]['kind'];
            $exists = match ($kind) {
                'interface' => interface_exists($target),
                'enum' => enum_exists($target),
                default => class_exists($target) && !enum_exists($target),
            };
            $this->assertTrue($exists, sprintf('Canonical name %s must be a declared %s.', $target, $kind));
            $sourcesByTarget[$target][] = $source;
        }

        foreach ($sourcesByTarget as $target => $sources) {
            $this->assertSame(
                1,
                count($sources),
                sprintf(
                    'Historical sources %s must not collapse onto the same canonical value %s.',
                    implode(' and ', $sources),
                    $target,
                ),
            );
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
     * Every retained entry records the historical-App blockers that keep it there, and none is empty.
     *
     * @return  void
     *
     * @since   0.1.1
     */
    public function testEveryRetainedTypeNamesItsBlockers(): void
    {
        $map = $this->map();
        $historicalPrefix = 'Kumwe' . '\\' . 'App' . '\\';
        $this->assertTrue(count($map['retained']) > 0, 'The retained set documents the not-yet-portable surface.');
        foreach ($map['retained'] as $entry) {
            $this->assertTrue(
                is_array($entry['blocked_by']) && $entry['blocked_by'] !== [],
                sprintf('Retained type %s must name at least one blocking closure member.', $entry['type']),
            );
            foreach ($entry['blocked_by'] as $blocker) {
                $this->assertTrue(
                    str_starts_with((string) $blocker, $historicalPrefix),
                    sprintf('Retained type %s names a blocker outside the historical App namespace.', $entry['type']),
                );
            }
        }
    }

    /**
     * Every replaced entry names a withdrawn historical surface with a non-empty successor record.
     *
     * A replaced type is one the canonical reset withdrew instead of moving — its declarative
     * surface now lives in the signed manifest, an executable binding, or an already-extracted
     * canonical library — so it must never also appear in the moved set, and its record must say
     * what took its place.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function testEveryReplacedTypeRecordsItsSuccessor(): void
    {
        $map = $this->map();
        $historicalPrefix = 'Kumwe' . '\\' . 'App' . '\\';
        foreach ($map['replaced'] as $source => $successor) {
            $this->assertTrue(
                str_starts_with($source, $historicalPrefix),
                sprintf('Replaced entry %s must name a historical App type.', $source),
            );
            $this->assertTrue(
                is_string($successor) && $successor !== '',
                sprintf('Replaced entry %s must record its canonical successor.', $source),
            );
            $this->assertTrue(
                !isset($map['moved'][$source]),
                sprintf('Replaced entry %s must not also appear in the moved set.', $source),
            );
        }
    }

    /**
     * Load the committed adoption map and assert it declares its format.
     *
     * @return  array{moved: array<string, string>, retained: list<array{type: string,
     *          blocked_by: list<string>}>, replaced: array<string, string>,
     *          rules: list<array{from: string, to: string}>}  Decoded map.
     *
     * @since   0.1.1
     */
    private function map(): array
    {
        $path = dirname(__DIR__, 2) . '/docs/migration-map.json';
        $map = json_decode((string) file_get_contents($path), true);
        $this->assertTrue(is_array($map), 'docs/migration-map.json must decode.');
        $this->assertSame(
            'kumwe-extension-sdk-migration-map-v2',
            $map['format'] ?? null,
            'The migration map must declare its format.',
        );

        /** @var array{moved: array<string, string>, retained: list<array{type: string,
         *      blocked_by: list<string>}>, replaced: array<string, string>,
         *      rules: list<array{from: string, to: string}>} $map */
        return $map;
    }

    /**
     * Load the canonical public types from the vendored classification, keyed by canonical FQCN.
     *
     * @return  array<string, array{kind: string}>  Classified entries by canonical type name.
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
        $this->assertTrue($types !== [], 'The canonical classification must list the public surface.');

        return $types;
    }
}
