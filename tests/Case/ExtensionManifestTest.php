<?php

/**
 * Proves the canonical SDK manifest parser's accepted and refused surface.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use InvalidArgumentException;
use Kumwe\Extension\Manifest\ExtensionManifest;
use Kumwe\Extension\Manifest\ExtensionType;
use Kumwe\Extension\Manifest\SemanticVersion;
use Kumwe\Extension\Tests\TestCase;

/**
 * Canonical manifest grammar, bounded values and contribution graph assertions.
 *
 * @since  0.1.0
 */
final class ExtensionManifestTest extends TestCase
{
    /**
     * Historical schema-one templates receive the conservative exact KIS 1.0 default.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testSchemaOneTemplateReceivesExactLegacyKisCompatibility(): void
    {
        $manifest = ExtensionManifest::fromJson(str_replace(
            '"type": "plugin"',
            '"type": "template"',
            $this->manifestJson(),
        ));
        $compatibility = $manifest->templateCompatibility();

        $this->assertTrue($compatibility !== null, 'A schema-one template receives the frozen declaration.');
        $this->assertSame('kis-1.0', $compatibility->standard(), 'The schema-one standard is KIS 1.0.');
        $this->assertTrue(
            $compatibility->supportsComponents(SemanticVersion::fromString('1.0.0'))
                && !$compatibility->supportsComponents(SemanticVersion::fromString('1.0.1')),
            'The schema-one component point is exact.',
        );
        $this->assertTrue(
            $compatibility->supportsTokens(SemanticVersion::fromString('1.0.0'))
                && !$compatibility->supportsTokens(SemanticVersion::fromString('0.9.9')),
            'The schema-one token point is exact.',
        );
    }

    /**
     * Strict manifest revisions cannot rely on the schema-one compatibility default.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testStrictTemplateManifestRequiresExplicitKisCompatibility(): void
    {
        $data = json_decode($this->manifestJson(), true, 16, JSON_THROW_ON_ERROR);
        $data['schema'] = 2;
        $data['type'] = 'template';
        $data['contributions'] = ['version' => 1, 'capabilities' => [], 'administrator' => []];

        $failure = $this->assertThrows(
            static fn (): ExtensionManifest
                => ExtensionManifest::fromJson(json_encode($data, JSON_THROW_ON_ERROR)),
            InvalidArgumentException::class,
            'A strict template without a declaration must be refused.',
        );
        $this->assertStringContains(
            'versioned template compatibility object',
            $failure->getMessage(),
            'The refusal names the missing declaration.',
        );
    }

    /**
     * The signed manifest exposes its validated KIS compatibility declaration.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testTemplateManifestParsesClosedKisCompatibility(): void
    {
        $data = json_decode($this->manifestJson(), true, 16, JSON_THROW_ON_ERROR);
        $data['type'] = 'template';
        $data['template'] = [
            'contract' => 1,
            'standard' => 'kis-1.0',
            'components' => ['minimum' => '1.0.0', 'maximum' => '1.1.0'],
            'tokens' => ['minimum' => '1.0.0', 'maximum' => '1.0.0'],
        ];

        $manifest = ExtensionManifest::fromJson(json_encode($data, JSON_THROW_ON_ERROR));
        $compatibility = $manifest->templateCompatibility();

        $this->assertTrue($compatibility !== null, 'The declaration is parsed.');
        $this->assertSame('kis-1.0', $compatibility->standard(), 'The declared standard survives parsing.');
    }

    /**
     * A compatible schema-one manifest parses with its typed dependencies.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testParsesACompatibleManifestWithTypedDependencies(): void
    {
        $manifest = ExtensionManifest::fromJson($this->manifestJson());

        $this->assertSame('acme/editor', $manifest->identifier()->value(), 'The identifier normalises.');
        $this->assertSame(ExtensionType::Plugin, $manifest->type(), 'The type parses to its enum case.');
        $this->assertSame('Acme\\Editor\\Provider', $manifest->serviceProvider(), 'The provider is kept.');
        $this->assertSame(['Acme\\Editor\\' => 'src/'], $manifest->autoload(), 'The autoload map is kept.');
        $this->assertSame(1, count($manifest->dependencies()), 'The dependency list parses.');
        $this->assertTrue(
            $manifest->supports(SemanticVersion::fromString('2.4.0'), SemanticVersion::fromString('8.4.1')),
            'Both declared constraints accept their versions.',
        );
    }

    /**
     * A package cannot depend on itself.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testRejectsSelfDependencies(): void
    {
        $this->assertThrows(
            fn (): ExtensionManifest => ExtensionManifest::fromJson(
                str_replace('acme/library', 'acme/editor', $this->manifestJson()),
            ),
            InvalidArgumentException::class,
            'A self-dependency must be refused.',
        );
    }

    /**
     * A strict schema-2 manifest parses its typed shell contributions.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testParsesStrictSchemaTwoContributionContracts(): void
    {
        $manifest = ExtensionManifest::fromJson(<<<'JSON'
{
  "schema": 2,
  "name": "acme/editor",
  "type": "component",
  "version": "2.0.0",
  "provider": "Acme\\Editor\\Provider",
  "autoload": {"psr-4": {"Acme\\Editor\\": "src/"}},
  "requires": {"kumwe": "^2.0.0", "php": "^8.5.0"},
  "contributions": {
    "version": 1,
    "capabilities": [{
      "id": "acme.editor.manage",
      "label": "Manage editor",
      "description": "Open and manage the editor workspace."
    }],
    "administrator": {
      "workspaces": [{
        "id": "acme.editor.workspace",
        "label": "Editor",
        "description": "Editor operations",
        "priority": 300
      }],
      "navigation": [{
        "id": "acme.editor.navigation",
        "workspace": "acme.editor.workspace",
        "label": "Editor",
        "description": "Open editor",
        "path": "/",
        "icon": "content",
        "capability": "acme.editor.manage",
        "priority": 10,
        "keywords": "editor"
      }],
      "routes": [{
        "name": "acme.editor.index",
        "path": "/",
        "methods": ["GET"],
        "capability": "acme.editor.manage",
        "view": "acme.editor.index"
      }],
      "views": [{"name": "acme.editor.index", "template": "index.twig"}]
    }
  }
}
JSON);

        $this->assertSame(2, $manifest->schemaVersion(), 'The schema survives parsing.');
        $this->assertSame(
            ['acme.editor.manage'],
            $manifest->permissions(),
            'Absent permissions are filled from the contributed capabilities.',
        );
        $this->assertSame(
            'index.twig',
            $manifest->contributions()->administratorViews()[0]->template,
            'The declared view template is exposed for reference checks.',
        );
        $this->assertSame(
            [
                'capabilities' => 1,
                'administrator.workspaces' => 1,
                'administrator.navigation' => 1,
                'administrator.routes' => 1,
                'administrator.views' => 1,
            ],
            $manifest->contributions()->surfaceCounts(),
            'Every declared surface is counted under its contract key.',
        );
    }

    /**
     * A strict manifest is closed to unknown root keys.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testSchemaTwoRejectsUnknownRootKeys(): void
    {
        $json = str_replace('"schema": 1,', '"schema": 2, "unknown": true,', $this->manifestJson());

        $failure = $this->assertThrows(
            static fn (): ExtensionManifest => ExtensionManifest::fromJson($json),
            InvalidArgumentException::class,
            'An unknown strict root key must be refused.',
        );
        $this->assertStringContains('unknown key unknown', $failure->getMessage(), 'The key is named.');
    }

    /**
     * Schema one remains permissive and has no typed shell contributions.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testSchemaOneRemainsPermissiveAndHasNoTypedShellContributions(): void
    {
        $manifest = ExtensionManifest::fromJson(str_replace(
            '"schema": 1,',
            '"schema": 1, "unknown_package_metadata": true,',
            $this->manifestJson(),
        ));

        $this->assertSame(1, $manifest->schemaVersion(), 'Schema one tolerates unknown root keys.');
        $this->assertSame(
            [],
            $manifest->contributions()->capabilityIdentifiers(),
            'The schema-one set declares no capabilities.',
        );
        $this->assertSame([], $manifest->contributions()->surfaceCounts(), 'The schema-one set declares nothing.');
    }

    /**
     * A capability outside the declaring package's namespace is refused.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testSchemaTwoRejectsForeignContributionOwnership(): void
    {
        $failure = $this->assertThrows(
            static fn (): ExtensionManifest => ExtensionManifest::fromJson(<<<'JSON'
{
  "schema": 2,
  "name": "acme/editor",
  "type": "component",
  "version": "2.0.0",
  "provider": "Acme\\Editor\\Provider",
  "autoload": {"psr-4": {"Acme\\Editor\\": "src/"}},
  "requires": {"kumwe": "^2.0.0", "php": "^8.5.0"},
  "contributions": {
    "version": 1,
    "capabilities": [{
      "id": "foreign.manage",
      "label": "Foreign",
      "description": "Invalid foreign ownership."
    }],
    "administrator": {}
  }
}
JSON),
            InvalidArgumentException::class,
            'A foreign-owned capability must be refused.',
        );
        $this->assertStringContains(
            'cannot claim capability identifier foreign.manage',
            $failure->getMessage(),
            'The refusal names the claimed identifier.',
        );
    }

    /**
     * Nested strict objects are closed to unknown keys with the same message.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testSchemaTwoRejectsUnknownNestedKeys(): void
    {
        $failure = $this->assertThrows(
            static fn (): ExtensionManifest => ExtensionManifest::fromJson(<<<'JSON'
{
  "schema": 2,
  "name": "acme/editor",
  "type": "component",
  "version": "2.0.0",
  "provider": "Acme\\Editor\\Provider",
  "autoload": {"psr-4": {"Acme\\Editor\\": "src/"}},
  "requires": {"kumwe": "^2.0.0", "php": "^8.5.0", "platform": "unknown"},
  "contributions": {"version": 1, "capabilities": [], "administrator": {}}
}
JSON),
            InvalidArgumentException::class,
            'An unknown requirements key must be refused.',
        );
        $this->assertStringContains(
            'requirements object contains unknown key platform',
            $failure->getMessage(),
            'The refusal names the nested key.',
        );
    }

    /**
     * Presentation and handler declarations require schema 3 without changing schema-2 keys.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testSchemaTwoRejectsSchemaThreeBusinessKeys(): void
    {
        $failure = $this->assertThrows(
            fn (): ExtensionManifest => ExtensionManifest::fromJson($this->schemaThreeBusinessManifest(2)),
            InvalidArgumentException::class,
            'A schema-2 manifest naming schema-3 business keys must be refused.',
        );
        $this->assertStringContains(
            'business contributions contains unknown key action_handlers',
            $failure->getMessage(),
            'The refusal names the too-new key.',
        );
    }

    /**
     * Schema 3 retains strict parsing while admitting presentation and handler-contract keys.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testSchemaThreeAdmitsNewBusinessContributionKeys(): void
    {
        $manifest = ExtensionManifest::fromJson($this->schemaThreeBusinessManifest(3));

        $this->assertSame(3, $manifest->schemaVersion(), 'The schema-3 manifest parses.');
        $this->assertSame(
            [],
            $manifest->contributions()->surfaceCounts(),
            'Empty schema-3 business collections declare nothing.',
        );
    }

    /**
     * A strict manifest's declared permissions must exactly match its sorted capability set.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testStrictPermissionsMustMatchContributedCapabilities(): void
    {
        $data = [
            'schema' => 2,
            'name' => 'acme/editor',
            'type' => 'component',
            'version' => '2.0.0',
            'provider' => 'Acme\\Editor\\Provider',
            'autoload' => ['psr-4' => ['Acme\\Editor\\' => 'src/']],
            'requires' => ['kumwe' => '^2.0.0', 'php' => '^8.5.0'],
            'permissions' => ['acme.editor.other'],
            'contributions' => [
                'version' => 1,
                'capabilities' => [[
                    'id' => 'acme.editor.manage',
                    'label' => 'Manage editor',
                    'description' => 'Open and manage the editor workspace.',
                ]],
            ],
        ];

        $failure = $this->assertThrows(
            static fn (): ExtensionManifest => ExtensionManifest::fromJson(
                json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            ),
            InvalidArgumentException::class,
            'Permissions disagreeing with the capabilities must be refused.',
        );
        $this->assertStringContains(
            'must exactly match the ordered contributed capability identifiers',
            $failure->getMessage(),
            'The refusal names the reconciliation rule.',
        );
    }

    /**
     * Executables may subscribe directly to a host-published event without claiming its declaration.
     *
     * Package-owned references still resolve inside the same signed graph; the host event registry is
     * responsible for refusing an unknown external type/version during activation.
     *
     * @since 0.2.0
     */
    public function testExternalEventReferencesDoNotClaimPackageOwnership(): void
    {
        $manifest = $this->schemaFourManifest();
        $manifest['contributions']['integration']['domain_listeners'][0]['event_type'] =
            'core.business_record.mutated';

        $parsed = ExtensionManifest::fromJson(json_encode(
            $manifest,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ));
        $listener = $parsed->contributions()->domainListeners()[0];
        $this->assertSame(
            'core.business_record.mutated',
            $listener->eventType(),
            'The external canonical event reference survives without namespace rewriting.',
        );

        $manifest['contributions']['integration']['domain_listeners'][0]['event_type'] =
            'kumwe.contract-manifest-four.undeclared';
        $this->assertThrows(
            static fn (): ExtensionManifest => ExtensionManifest::fromJson(json_encode(
                $manifest,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            )),
            InvalidArgumentException::class,
            'An undeclared package-owned event reference must still fail closed.',
        );
    }

    /**
     * Schedule and report identifiers name one immutable declaration each.
     *
     * @since 0.2.0
     */
    public function testRejectsDuplicateScheduleAndReportIdentities(): void
    {
        $manifest = $this->schemaFourManifest();
        $schedule = $manifest['contributions']['integration']['schedules'][0];
        $schedule['cron_expression'] = '0 4 * * *';
        $schedule['payload'] = ['site_identifier' => 'default', 'limit' => 50];
        $manifest['contributions']['integration']['schedules'][] = $schedule;
        $this->assertThrows(
            fn (): ExtensionManifest => ExtensionManifest::fromJson($this->encodeManifest($manifest)),
            InvalidArgumentException::class,
            'A duplicate schedule identity must be refused even when its body differs.',
        );

        $manifest = $this->schemaFourManifest();
        $report = $manifest['contributions']['integration']['reports'][0];
        $report['version'] = 2;
        $report['title'] = 'Conflicting report body';
        $manifest['contributions']['integration']['reports'][] = $report;
        $this->assertThrows(
            fn (): ExtensionManifest => ExtensionManifest::fromJson($this->encodeManifest($manifest)),
            InvalidArgumentException::class,
            'A duplicate report identity must be refused even when its version and body differ.',
        );
    }

    /**
     * A schema-four package may declare a closed, owned multilingual content set.
     *
     * @since 0.2.0
     */
    public function testValidatesContentTranslationGroups(): void
    {
        $manifest = $this->schemaFourManifest();
        $manifest['contributions']['content'] = ['translation_groups' => [[
            'group_id' => 'kumwe.contract-manifest-four.articles',
            'locales' => ['en', 'pt-BR'],
            'fallback_locale' => 'en',
        ]]];

        $parsed = ExtensionManifest::fromJson($this->encodeManifest($manifest));
        $this->assertSame(
            1,
            $parsed->contributions()->surfaceCounts()['content.translation_groups'] ?? null,
            'The validated content set is retained and counted.',
        );
    }

    /**
     * Translation-group declarations fail closed on shape, ownership, locale and identity attacks.
     *
     * @since 0.2.0
     */
    public function testRejectsHostileContentTranslationGroups(): void
    {
        $valid = [
            'group_id' => 'kumwe.contract-manifest-four.articles',
            'locales' => ['en', 'pt-BR'],
            'fallback_locale' => 'en',
        ];
        $unknown = $valid;
        $unknown['handler'] = 'Injected\\Handler';
        $missing = $valid;
        unset($missing['fallback_locale']);
        $wrongCollection = $valid;
        $wrongCollection['locales'] = 'en';
        $wrongLocale = $valid;
        $wrongLocale['locales'] = ['en', 42];
        $malformedLocale = $valid;
        $malformedLocale['locales'] = ['en-US-private'];
        $unreachableFallback = $valid;
        $unreachableFallback['fallback_locale'] = 'fr';
        $foreign = $valid;
        $foreign['group_id'] = 'foreign.package.articles';
        $overBound = $valid;
        $overBound['locales'] = array_fill(0, 65, 'en');
        $cases = [
            'unknown member' => [$unknown],
            'missing member' => [$missing],
            'wrong collection type' => [$wrongCollection],
            'wrong locale type' => [$wrongLocale],
            'malformed locale' => [$malformedLocale],
            'unreachable fallback' => [$unreachableFallback],
            'foreign identity' => [$foreign],
            'over-bound locales' => [$overBound],
            'duplicate identity' => [$valid, $valid],
        ];

        foreach ($cases as $name => $groups) {
            $manifest = $this->schemaFourManifest();
            $manifest['contributions']['content'] = ['translation_groups' => $groups];
            $this->assertThrows(
                fn (): ExtensionManifest => ExtensionManifest::fromJson($this->encodeManifest($manifest)),
                InvalidArgumentException::class,
                sprintf('A translation group with %s must be refused.', $name),
            );
        }
    }

    /**
     * Reports reject open grammar and mistyped scalar or collection members before host interpretation.
     *
     * @since 0.2.0
     */
    public function testRejectsHostileReportDocuments(): void
    {
        $manifest = $this->schemaFourManifest();
        $valid = $manifest['contributions']['integration']['reports'][0];
        $unknown = $valid;
        $unknown['sql'] = 'SELECT * FROM secrets';
        $missing = $valid;
        unset($missing['title']);
        $wrongVisibility = $valid;
        $wrongVisibility['portal_visible'] = 'true';
        $wrongCap = $valid;
        $wrongCap['synchronous_row_cap'] = '100';
        $wrongCollection = $valid;
        $wrongCollection['parameters'] = ['name' => 'not-a-list'];
        $emptyColumns = $valid;
        $emptyColumns['columns'] = [];
        $unknownColumn = $valid;
        $unknownColumn['columns'][0]['callback'] = 'Injected\\Column';
        $wrongParameter = $valid;
        $wrongParameter['parameters'][0]['required'] = 1;
        $wrongFilter = $valid;
        $wrongFilter['filters'][0]['parameter'] = 42;
        $wrongType = $valid;
        $wrongType['columns'][0]['type'] = 'serialized_object';
        $wrongExpression = $valid;
        $wrongExpression['formulas'] = [[
            'alias' => 'derived',
            'label' => 'Derived',
            'type' => 'integer',
            'expression' => ['op' => 'field', 'type' => 'integer', 'field' => 'count', 'eval' => true],
        ]];
        $cases = [
            'unknown top-level member' => $unknown,
            'missing required member' => $missing,
            'mistyped visibility' => $wrongVisibility,
            'mistyped row cap' => $wrongCap,
            'non-list collection' => $wrongCollection,
            'empty columns' => $emptyColumns,
            'unknown nested member' => $unknownColumn,
            'mistyped parameter flag' => $wrongParameter,
            'mistyped nullable string' => $wrongFilter,
            'unknown value type' => $wrongType,
            'open expression node' => $wrongExpression,
        ];

        foreach ($cases as $name => $report) {
            $manifest = $this->schemaFourManifest();
            $manifest['contributions']['integration']['reports'] = [$report];
            $this->assertThrows(
                fn (): ExtensionManifest => ExtensionManifest::fromJson($this->encodeManifest($manifest)),
                InvalidArgumentException::class,
                sprintf('A report with %s must be refused.', $name),
            );
        }
    }

    /**
     * Build the canonical schema-one fixture manifest this package owns.
     *
     * @return  string  JSON manifest document.
     *
     * @since   0.1.0
     */
    private function manifestJson(): string
    {
        return <<<'JSON'
{
  "schema": 1,
  "name": "acme/editor",
  "type": "plugin",
  "version": "1.2.3",
  "provider": "Acme\\Editor\\Provider",
  "autoload": {"psr-4": {"Acme\\Editor\\": "src/"}},
  "requires": {"kumwe": "^2.0.0", "php": "^8.4.0"},
  "dependencies": [{"name": "acme/library", "constraint": "^1.0.0", "optional": false}]
}
JSON;
    }

    /**
     * Build a strict manifest naming the schema-3-only presentation and custom contract collections.
     *
     * @param   int  $schema  Manifest schema to exercise.
     *
     * @return  string  JSON manifest using empty but explicitly present schema-3 collections.
     *
     * @since   0.1.0
     */
    private function schemaThreeBusinessManifest(int $schema): string
    {
        return json_encode([
            'schema' => $schema,
            'name' => 'acme/editor',
            'type' => 'component',
            'version' => '2.0.0',
            'provider' => 'Acme\\Editor\\Provider',
            'autoload' => ['psr-4' => ['Acme\\Editor\\' => 'src/']],
            'requires' => ['kumwe' => '^2.0.0', 'php' => '^8.5.0'],
            'contributions' => [
                'version' => 1,
                'business' => [
                    'field_types' => [],
                    'definitions' => [],
                    'field_presentations' => [],
                    'view_handlers' => [],
                    'action_handlers' => [],
                ],
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Load the reviewed schema-four compatibility manifest for hostile mutation tests.
     *
     * @return array<string, mixed> Decoded manifest.
     *
     * @since 0.2.0
     */
    private function schemaFourManifest(): array
    {
        $path = dirname(__DIR__, 2) . '/resources/fixtures/generations/manifest-4/kumwe.json';
        $bytes = file_get_contents($path);
        $this->assertTrue(is_string($bytes), 'The canonical schema-four fixture is readable.');
        $manifest = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
        $this->assertTrue(is_array($manifest), 'The canonical schema-four fixture decodes.');

        return $manifest;
    }

    /**
     * Encode one mutated manifest without changing canonical fixture bytes.
     *
     * @param array<string, mixed> $manifest Decoded manifest.
     *
     * @return string JSON accepted by the parser.
     *
     * @since 0.2.0
     */
    private function encodeManifest(array $manifest): string
    {
        return json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
