<?php

/** Freeze every published port and vocabulary, including those without a host implementation here. @since 0.2.5 */
declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use Kumwe\Extension\Tests\TestCase;
use ReflectionClass;
use ReflectionEnum;
use ReflectionMethod;
use UnitEnum;

final class PublicPortConformanceTest extends TestCase
{
    public function testEveryPublishedPortAndVocabularyKeepsItsReviewedContract(): void
    {
        $expected = json_decode((string) file_get_contents(dirname(__DIR__) . '/fixtures/public-ports-v1.json'),
            true, 64, JSON_THROW_ON_ERROR);
        $this->assertSame('d0484b8733eaa57d076f567ffa5e997b9564b5fa', $expected['source'],
            'The reviewed snapshot belongs to the legacy main contract.');
        $this->assertTrue(count($expected['types']) > 0, 'A port conformance corpus cannot be empty.');
        $this->assertSame($expected['types'], self::surface(),
            'Every public interface and enum retains its exact callable or vocabulary shape.');
    }

    public static function surface(): array
    {
        $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2)
            . '/resources/contract/classification.json'), true, 64, JSON_THROW_ON_ERROR);
        $surface = [];
        foreach ($manifest['types'] as $entry) {
            if (!in_array($entry['kind'], ['interface', 'enum'], true)) {
                continue;
            }
            $type = new ReflectionClass($entry['type']);
            $methods = [];
            foreach ($type->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $parameters = [];
                foreach ($method->getParameters() as $parameter) {
                    $default = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
                    if ($default instanceof UnitEnum) {
                        $default = $default::class . '::' . $default->name;
                    }
                    $parameters[] = ['name' => $parameter->getName(), 'type' => (string) $parameter->getType(),
                        'by_reference' => $parameter->isPassedByReference(), 'variadic' => $parameter->isVariadic(),
                        'optional' => $parameter->isOptional(), 'has_default' => $parameter->isDefaultValueAvailable(),
                        'default' => $default];
                }
                $methods[$method->getName()] = ['static' => $method->isStatic(), 'parameters' => $parameters,
                    'return' => (string) $method->getReturnType(), 'by_reference' => $method->returnsReference()];
            }
            ksort($methods);
            $interfaces = $type->getInterfaceNames();
            sort($interfaces);
            $record = ['kind' => $entry['kind'], 'interfaces' => $interfaces, 'methods' => $methods];
            if ($entry['kind'] === 'enum') {
                $cases = [];
                foreach ((new ReflectionEnum($entry['type']))->getCases() as $case) {
                    $cases[$case->getName()] = $case instanceof \ReflectionEnumBackedCase
                        ? $case->getBackingValue() : $case->getName();
                }
                $record['cases'] = $cases;
            }
            $surface[$entry['type']] = $record;
        }
        ksort($surface);
        return $surface;
    }
}
