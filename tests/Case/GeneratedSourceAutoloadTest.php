<?php

/**
 * Proves every PHP class the SDK generates or ships as a fixture resolves against the SDK it targets.
 *
 * @since 0.2.4
 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use Kumwe\Extension\Manifest\ExtensionManifest;
use Kumwe\Extension\Package\PackageLimits;
use Kumwe\Extension\Tests\TestCase;
use Kumwe\Extension\Toolchain\ComponentScaffolder;
use Kumwe\Extension\Toolchain\DeterministicPackageBuilder;
use Kumwe\Extension\Toolchain\PackageInspector;
use Kumwe\Extension\Toolchain\ScaffoldRequest;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

/**
 * Autoloads, import-resolves and reflection-checks every generated and fixture class.
 *
 * Static conformance deliberately never executes package code, so a generated file importing an SDK
 * type that does not exist passes `build` and `conformance` and fails only when a host autoloads it.
 * 0.2.3 shipped two scaffold templates and one generation fixture in exactly that state. These tests
 * scaffold the complete component, package it, and load every PHP entry of the package — and every
 * class of every shipped generation fixture — through the PSR-4 map the manifest declares, then hold
 * each `use` import and each declared parameter, return and property type to an existing type.
 *
 * Only names this package can answer for are held to existence: the tree's own namespaces, global
 * PHP types, `Kumwe\Extension` and the namespaces of the SDK's declared Composer dependencies. A
 * scaffold dependency the SDK does not require (the generated delivery handlers respond through
 * `laminas/laminas-diactoros`) is resolved by the author's own Composer install, not here.
 *
 * @since  0.2.4
 */
final class GeneratedSourceAutoloadTest extends TestCase
{
    /**
     * Namespace roots the SDK's own Composer dependencies provide.
     *
     * @var    list<string>
     * @since  0.2.4
     */
    private const array CHECKED_ROOTS = [
        'Kumwe\\Extension\\',
        'Kumwe\\Conversion\\',
        'Kumwe\\Producer\\',
        'Psr\\',
        'Doctrine\\DBAL\\',
        'Ramsey\\Uuid\\',
        'PHPUnit\\',
    ];

    /**
     * Every PHP entry of a packaged scaffold is a loadable class whose imports and declared types exist.
     *
     * @return  void
     *
     * @since   0.2.4
     */
    public function testEveryPackagedScaffoldClassAutoloadsAgainstTheSdk(): void
    {
        $work = $this->workspace();
        $source = $work . '/component';
        (new ComponentScaffolder())->scaffold(new ScaffoldRequest(
            'acme/autoload-component',
            'Acme\\AutoloadComponent',
            $source,
            'Autoload Component',
        ));
        $inspector = new PackageInspector(new PackageLimits());
        $package = (new DeterministicPackageBuilder($inspector))
            ->build($source, $work . '/autoload-component.zip')
            ->inspection
            ->package;

        $map = $this->manifestMap($source, $package->manifest);
        $composer = json_decode((string) file_get_contents($source . '/composer.json'), true);
        $this->assertTrue(is_array($composer), 'The generated composer.json decodes.');
        foreach (['autoload', 'autoload-dev'] as $section) {
            foreach ($composer[$section]['psr-4'] ?? [] as $prefix => $directory) {
                $map[$prefix] = $source . '/' . rtrim($directory, '/');
            }
        }

        $paths = array_values(array_filter(
            $package->paths(),
            static fn (string $path): bool => str_ends_with($path, '.php'),
        ));
        $this->assertTrue(count($paths) >= 15, 'The packaged scaffold carries the complete component code.');
        $checked = $this->assertTreeLoads($map, array_map(
            static fn (string $path): string => $source . '/' . $path,
            $paths,
        ), 'scaffold');
        $this->assertSame(count($paths), $checked, 'Every packaged PHP entry was resolved to a class and loaded.');
        $this->removeTree($work);
    }

    /**
     * Every class of every shipped generation fixture loads against the SDK that records it.
     *
     * @return  void
     *
     * @since   0.2.4
     */
    public function testEveryGenerationFixtureClassAutoloadsAgainstTheSdk(): void
    {
        $fixtures = glob(dirname(__DIR__, 2) . '/resources/fixtures/generations/manifest-*') ?: [];
        $this->assertSame(6, count($fixtures), 'All six generation fixtures are shipped.');
        $checked = 0;
        foreach ($fixtures as $fixture) {
            $manifest = ExtensionManifest::fromJson((string) file_get_contents($fixture . '/kumwe.json'));
            $files = [];
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($fixture, \FilesystemIterator::SKIP_DOTS),
            );
            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
            sort($files, SORT_STRING);
            $this->assertTrue($files !== [], sprintf('%s ships PHP source.', basename($fixture)));
            $checked += $this->assertTreeLoads($this->manifestMap($fixture, $manifest), $files, basename($fixture));
        }
        $this->assertTrue($checked > 10, 'The generation fixtures were checked as a whole.');
    }

    /**
     * Resolve the manifest's PSR-4 map to absolute directories under one package root.
     *
     * @param   string             $root      Absolute package root.
     * @param   ExtensionManifest  $manifest  Parsed manifest declaring the autoload map.
     *
     * @return  array<string, string>  Namespace prefix to absolute directory.
     *
     * @since   0.2.4
     */
    private function manifestMap(string $root, ExtensionManifest $manifest): array
    {
        $map = [];
        foreach ($manifest->autoload() as $prefix => $directory) {
            $map[$prefix] = $root . '/' . rtrim($directory, '/');
        }
        $this->assertTrue($map !== [], sprintf('%s declares a PSR-4 map.', basename($root)));

        return $map;
    }

    /**
     * Load every file through the PSR-4 map and hold its imports and declared types to existing types.
     *
     * @param   array<string, string>  $map    Namespace prefix to absolute directory.
     * @param   list<string>           $files  Absolute PHP file paths that must all be mapped classes.
     * @param   string                 $label  Tree name for diagnostics.
     *
     * @return  int  Number of classes loaded and checked.
     *
     * @since   0.2.4
     */
    private function assertTreeLoads(array $map, array $files, string $label): int
    {
        uksort($map, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));
        $loader = static function (string $class) use ($map): void {
            foreach ($map as $prefix => $directory) {
                if (str_starts_with($class, $prefix)) {
                    $path = $directory . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                    if (is_file($path)) {
                        require_once $path;
                    }

                    return;
                }
            }
        };
        spl_autoload_register($loader);
        $checked = 0;
        try {
            foreach ($files as $file) {
                $class = null;
                foreach ($map as $prefix => $directory) {
                    if (str_starts_with($file, $directory . '/')) {
                        $relative = substr($file, strlen($directory) + 1, -4);
                        $class = $prefix . str_replace('/', '\\', $relative);
                        break;
                    }
                }
                $this->assertTrue(
                    is_string($class),
                    sprintf('%s: %s must sit inside a declared PSR-4 directory.', $label, $file),
                );
                /** @var string $class */
                $this->assertTrue(
                    $this->typeExists($class),
                    sprintf('%s: %s must declare %s.', $label, $file, $class),
                );
                foreach ($this->imports((string) file_get_contents($file)) as $import) {
                    if (!$this->answerable($import, $map)) {
                        continue;
                    }
                    $this->assertTrue(
                        $this->typeExists($import),
                        sprintf('%s: %s imports %s, which does not exist.', $label, $class, $import),
                    );
                }
                foreach ($this->declaredTypes($class) as $member => $type) {
                    if (!$this->answerable($type, $map)) {
                        continue;
                    }
                    $this->assertTrue(
                        $this->typeExists($type),
                        sprintf('%s: %s declares %s against %s, which does not exist.', $label, $class, $member, $type),
                    );
                }
                $checked++;
            }
        } finally {
            spl_autoload_unregister($loader);
        }

        return $checked;
    }

    /**
     * Read the class-like `use` imports of one PHP file.
     *
     * @param   string  $source  Complete PHP source.
     *
     * @return  list<string>  Imported fully qualified names, functions and constants excluded.
     *
     * @since   0.2.4
     */
    private function imports(string $source): array
    {
        preg_match_all('/^use\s+(?!function\s|const\s)([A-Za-z0-9_\\\\]+)(?:\s+as\s+\w+)?;/m', $source, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * Collect every non-builtin type a class declares on its own methods and properties.
     *
     * @param   string  $class  Loaded class-like name.
     *
     * @return  array<string, string>  Type name keyed by the member that declares it.
     *
     * @since   0.2.4
     */
    private function declaredTypes(string $class): array
    {
        $reflection = new ReflectionClass($class);
        $types = [];
        foreach ($reflection->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $class) {
                continue;
            }
            foreach ($method->getParameters() as $parameter) {
                foreach ($this->namedTypes($parameter->getType()) as $type) {
                    $types[$method->getName() . '($' . $parameter->getName() . ')'] = $type;
                }
            }
            foreach ($this->namedTypes($method->getReturnType()) as $type) {
                $types[$method->getName() . '()'] = $type;
            }
        }
        foreach ($reflection->getProperties() as $property) {
            if ($property->getDeclaringClass()->getName() !== $class) {
                continue;
            }
            foreach ($this->namedTypes($property->getType()) as $type) {
                $types['$' . $property->getName()] = $type;
            }
        }

        return $types;
    }

    /**
     * Flatten one declared type into the non-builtin class-like names it references.
     *
     * @param   ?ReflectionType  $type  Declared type, or null when untyped.
     *
     * @return  list<string>  Class-like names, `self`, `static` and `parent` excluded.
     *
     * @since   0.2.4
     */
    private function namedTypes(?ReflectionType $type): array
    {
        if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
            $names = [];
            foreach ($type->getTypes() as $member) {
                $names = [...$names, ...$this->namedTypes($member)];
            }

            return $names;
        }
        if (
            !$type instanceof ReflectionNamedType
            || $type->isBuiltin()
            || in_array(strtolower($type->getName()), ['self', 'static', 'parent'], true)
        ) {
            return [];
        }

        return [$type->getName()];
    }

    /**
     * Whether this package's install can answer for a name: its own trees, global PHP types and the
     * namespaces its declared Composer dependencies provide.
     *
     * @param   string                 $name  Fully qualified class-like name.
     * @param   array<string, string>  $map   Namespace prefix to absolute directory of the tree under test.
     *
     * @return  bool  True when the name must resolve here.
     *
     * @since   0.2.4
     */
    private function answerable(string $name, array $map): bool
    {
        if (!str_contains($name, '\\')) {
            return true;
        }
        foreach ([...self::CHECKED_ROOTS, ...array_keys($map)] as $root) {
            if (str_starts_with($name, $root)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a class-like name resolves through the registered autoloaders.
     *
     * @param   string  $name  Fully qualified class, interface, enum or trait name.
     *
     * @return  bool  True when any class-like declaration with that name exists.
     *
     * @since   0.2.4
     */
    private function typeExists(string $name): bool
    {
        return class_exists($name) || interface_exists($name) || enum_exists($name) || trait_exists($name);
    }

    /**
     * Allocate a private working directory for one test.
     *
     * @return  string  Absolute path of the writable directory.
     *
     * @since   0.2.4
     */
    private function workspace(): string
    {
        $work = sys_get_temp_dir() . '/kumwe-sdk-autoload-' . bin2hex(random_bytes(8));
        mkdir($work, 0700);

        return $work;
    }

    /**
     * Remove one private working directory created by this test.
     *
     * @param   string  $root  Absolute path of the tree to remove.
     *
     * @return  void
     *
     * @since   0.2.4
     */
    private function removeTree(string $root): void
    {
        if (!str_starts_with($root, sys_get_temp_dir() . '/kumwe-sdk-autoload-')) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            if (!$entry instanceof \SplFileInfo) {
                continue;
            }
            $entry->isDir() && !$entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($root);
    }
}
