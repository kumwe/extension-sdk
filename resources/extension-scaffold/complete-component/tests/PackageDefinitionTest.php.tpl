<?php

declare(strict_types=1);

namespace @@PHP_NAMESPACE@@\Tests;

use Kumwe\Extension\Manifest\ExtensionManifest;
use PHPUnit\Framework\TestCase;

/** Proves the signed manifest remains the component's sole complete declaration source. @since 2.0.0 */
final class PackageDefinitionTest extends TestCase
{
    /** @since 2.0.0 */
    public function testCanonicalManifestCarriesTheCompleteComponentContract(): void
    {
        $json = file_get_contents(dirname(__DIR__) . '/kumwe.json');
        self::assertIsString($json);
        $manifest = ExtensionManifest::fromJson($json);
        $declarations = $manifest->contributions()->declarations();

        self::assertSame('@@EXTENSION_IDENTIFIER@@', $manifest->identifier()->value());
        self::assertSame(2, $declarations['version']);
        self::assertSame('@@EXTENSION_DOTTED@@.item', $declarations['business']['definitions'][0]['handle']);
        self::assertSame(
            '@@EXTENSION_DOTTED@@.administrator.index',
            $declarations['administrator']['routes'][0]['name'],
        );
        self::assertSame('@@EXTENSION_DOTTED@@.portal.index', $declarations['portal']['routes'][0]['name']);
        self::assertSame(
            '@@EXTENSION_DOTTED@@.item_consumer',
            $declarations['integration']['consumers'][0]['consumer_id'],
        );
        self::assertSame(
            '@@EXTENSION_DOTTED@@.item_projection',
            $declarations['integration']['projections'][0]['identifier'],
        );
        self::assertSame(
            '@@EXTENSION_DOTTED@@.item_report',
            $declarations['integration']['reports'][0]['identifier'],
        );
    }
}
