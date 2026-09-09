<?php

/** Generate and verify SDK canonical API signatures and their source documentation. @since 0.3.0 */

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
$root = dirname(__DIR__);
$composer = json_decode(file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
$prefix = array_key_first($composer['autoload']['psr-4']);
$symbols = [];
$docs = "# SDK public API\n\nEvery public SDK type and declared member is generated from the canonical classification, source PHPDoc and reflection. The parent and interface names in the machine manifest identify inherited contracts; the SDK does not claim ownership of their declarations. Internal Support helpers are excluded.\n\nSee [architecture](architecture.md) and [host integration](host-integration.md) for authority boundaries, construction, I/O and collaborator lifetimes. Every example and signature below is a contract reference; publishing a package never grants host admission. The two PHPUnit bridge types require the optional PHPUnit development dependency and are absent from the production qualification count.\n\n";
$classification = json_decode(file_get_contents($root . '/resources/contract/classification.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ($classification['types'] as $type) {
    $name = $type['type'];
    $class = new ReflectionClass($name);
    if (realpath($class->getFileName()) !== realpath($root . '/' . $type['path'])
        || hash_file('sha256', $root . '/' . $type['path']) !== $type['source_sha256']) {
        throw new RuntimeException('Canonical classification/source identity drift: ' . $name);
    }
    $members = [];
    $docs .= '## ' . $name . "\n\n" . ($class->getDocComment() ?: '') . "\n\n";
    foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->getDeclaringClass()->getName() !== $name) {
            continue;
        }
        $params = [];
        $signatureParameters = [];
        foreach ($method->getParameters() as $param) {
            $signatureParameters[] = ((string) $param->getType() !== '' ? (string) $param->getType() . ' ' : '')
                . ($param->isPassedByReference() ? '&' : '') . ($param->isVariadic() ? '...' : '')
                . '$' . $param->getName()
                . ($param->isDefaultValueAvailable() ? ' = ' . var_export($param->getDefaultValue(), true) : '');
            $params[] = ['name' => $param->getName(),
                 'type' => (string) $param->getType(),
                 'optional' => $param->isOptional(),
                 'default' => $param->isDefaultValueAvailable() ? var_export($param->getDefaultValue(), true) : null,
                 'variadic' => $param->isVariadic(),
                 'reference' => $param->isPassedByReference()];
        }
        $members[] = ['name' => $method->getName(),
             'static' => $method->isStatic(),
             'parameters' => $params,
             'return' => (string) $method->getReturnType()];
        $docs .= '### ' . $method->getName() . "\n\n" . ($method->getDocComment() ?: 'Generated enum/runtime member.') . "\n\n";
        $docs .= "```php\npublic " . ($method->isStatic() ? 'static ' : '') . 'function '
            . $method->getName() . '(' . implode(', ', $signatureParameters) . ')'
            . ((string) $method->getReturnType() !== '' ? ': ' . (string) $method->getReturnType() : '')
            . ";\n```\n\n";
    }
    $properties = [];
    foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
        if ($property->getDeclaringClass()->getName() !== $name) { continue; }
        $properties[] = ['name' => $property->getName(),
             'type' => (string) $property->getType(),
             'readonly' => $property->isReadOnly()];
    }
    $constants = [];
    $constantValues = [];
    foreach ($class->getReflectionConstants() as $constant) {
        if ($constant->isPublic() && $constant->getDeclaringClass()->getName() === $name) {
            $value = $constant->getValue();
            $constants[] = $constant->getName();
            $constantValues[$constant->getName()] = var_export($value, true);
        }
    }
    if ($properties !== []) {
        $docs .= "### Public properties\n\n";
        foreach ($properties as $property) {
            $docs .= ($class->getProperty($property['name'])->getDocComment() ?: '') . "\n\n";
            $docs .= '- `' . ($property['readonly'] ? 'readonly ' : '') . $property['type'] . ' $'
                . $property['name'] . "`\n";
        }
        $docs .= "\n";
    }
    if ($constants !== []) {
        $docs .= "### Public constants\n\n";
        foreach ($constantValues as $constantName => $constantValue) {
            $docs .= ($class->getReflectionConstant($constantName)->getDocComment() ?: '') . "\n\n";
            $docs .= '- `' . $constantName . ' = ' . str_replace("\n", ' ', $constantValue) . "`\n";
        }
        $docs .= "\n";
    }
    $symbols[] = ['name' => $name,
         'kind' => $class->isEnum() ? 'enum' : ($class->isInterface() ? 'interface' : 'class'),
         'methods' => $members,
         'properties' => $properties,
         'constants' => $constants,
         'constant_values' => $constantValues];
}
usort($symbols, static fn ($a, $b) => strcmp($a['name'], $b['name']));
$record = [];
if (preg_match('/^## \[?([0-9]+\.[0-9]+\.[0-9]+)(?:\]|\s)/m', file_get_contents($root . '/CHANGELOG.md'), $record) !== 1) {
    throw new RuntimeException('A manifest requires a reviewed semantic-version release record.');
}
$release = $record[1];
$details = ['schema' => 'kumwe-public-api-details/v1', 'package' => $composer['name'],
    'release' => $release, 'namespace' => $prefix, 'symbols' => $symbols];
$detailBytes = json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
$governed = [];
$extensionPoints = [];
foreach ($symbols as $symbol) {
    $class = new ReflectionClass($symbol['name']);
    $methods = [];
    foreach ($symbol['methods'] as $method) {
        $parameters = [];
        foreach ($method['parameters'] as $parameter) {
            $parameters[] = ['name' => $parameter['name'], 'type' => $parameter['type'] === '' ? null : $parameter['type'],
                'optional' => $parameter['optional'], 'variadic' => $parameter['variadic'],
                'by_reference' => $parameter['reference']];
        }
        $methods[$method['name']] = ['visibility' => 'public', 'static' => $method['static'],
            'parameters' => $parameters, 'return' => $method['return'] === '' ? null : $method['return']];
    }
    $properties = [];
    foreach ($symbol['properties'] as $property) {
        $reflection = $class->getProperty($property['name']);
        $properties[$property['name']] = ['type' => $property['type'] === '' ? null : $property['type'],
            'static' => $reflection->isStatic(), 'readonly' => $property['readonly']];
    }
    $constants = [];
    foreach ($symbol['constants'] as $constant) {
        $reflection = $class->getReflectionConstant($constant);
        $constants[$constant] = ['type' => $reflection->hasType() ? (string) $reflection->getType() : null];
    }
    $parent = $class->getParentClass();
    $governed[$symbol['name']] = [
        'kind' => $symbol['kind'], 'stability' => 'stable',
        'file' => substr($class->getFileName(), strlen($root) + 1),
        'abstract' => $class->isAbstract(), 'final' => $class->isFinal(), 'readonly' => $class->isReadOnly(),
        'parent' => $parent === false ? null : $parent->getName(), 'interfaces' => $class->getInterfaceNames(),
        'constants' => (object) $constants, 'properties' => (object) $properties, 'methods' => (object) $methods,
        'deprecated' => null,
    ];
    if ($class->isInterface() || $class->isAbstract()) {
        $extensionPoints[] = $symbol['name'];
    }
}
$manifest = ['schema' => 'kumwe-package-public-api/v1', 'package' => $composer['name'],
    'release' => $release, 'namespace' => $prefix, 'symbols' => (object) $governed,
    'extension_points' => $extensionPoints, 'digest_of' => 'src/'];
$encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
$file = $root . '/resources/public-api/v1.json';
if (in_array('--write', $argv, true)) {
    if (!is_dir(dirname($file))) {
        mkdir(dirname($file), 0777, true);
    }
    file_put_contents($file, $encoded);
    file_put_contents($root . '/resources/public-api/signature-details-v1.json', $detailBytes);
    file_put_contents($root . '/docs/public-api.md', $docs);
} elseif (
    !is_file($file) || file_get_contents($file) !== $encoded
    || !is_file($root . '/docs/public-api.md')
    || file_get_contents($root . '/docs/public-api.md') !== $docs
    || !is_file($root . '/resources/public-api/signature-details-v1.json')
    || file_get_contents($root . '/resources/public-api/signature-details-v1.json') !== $detailBytes
) {
    throw new RuntimeException('Public API drift: review and regenerate with --write.');
}
echo count($symbols) . " public symbols verified.\n";
