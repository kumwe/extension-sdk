<?php

/** Proves callback definitions retain the complete bounded signed manifest contract. @since 0.2.0 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use InvalidArgumentException;
use Kumwe\Extension\Manifest\ExtensionManifest;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\DomainListenerDefinition;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\EventConsumerDefinition;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\JobContributionDefinition;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\WebhookContributionDefinition;
use Kumwe\Extension\Spi\BusinessReporting\Domain\ProjectionDefinition;
use Kumwe\Extension\Spi\Contribution\CanonicalCompositionDocument;
use Kumwe\Extension\Tests\TestCase;

/** Complete canonical definition and refusal vectors. @since 0.2.0 */
final class TypedDefinitionTest extends TestCase
{
    /** @since 0.2.0 */
    public function testManifestConstructsCompleteExecutableDefinitionsOnce(): void
    {
        $manifest = ExtensionManifest::fromJson($this->fixture());
        $graph = $manifest->contributions();

        $listener = $graph->domainListener('kumwe.contract-manifest-four.observe-now');
        $consumer = $graph->eventConsumer('kumwe.contract-manifest-four.observe-later');
        $job = $graph->job('kumwe.contract-manifest-four.summarize');
        $projection = $graph->projection('kumwe.contract-manifest-four.activity');
        $webhook = $graph->webhook('kumwe.contract-manifest-four.observed-webhook');

        $this->assertTrue($listener instanceof DomainListenerDefinition, 'The listener is canonical and typed.');
        $this->assertSame('1.0.0', $listener->handlerVersion(), 'Listener handler version is retained.');
        $this->assertSame(20, $listener->priority(), 'Listener priority is retained.');
        $this->assertTrue($consumer instanceof EventConsumerDefinition, 'The consumer is canonical and typed.');
        $this->assertSame('kumwe.contract-manifest-four.integration', $consumer->queue(), 'Consumer queue is retained.');
        $this->assertSame(7, $consumer->maximumAttempts(), 'Consumer retry budget is retained.');
        $this->assertTrue($job instanceof JobContributionDefinition, 'The job is canonical and typed.');
        $this->assertSame('1.0.0', $job->handlerVersion(), 'Job handler version is retained.');
        $this->assertTrue(!$job->installationWide(), 'Job scope is retained.');
        $this->assertTrue($projection instanceof ProjectionDefinition, 'The projection is canonical and typed.');
        $this->assertSame(250, $projection->rebuildBatchSize, 'Projection rebuild budget is retained.');
        $this->assertSame(64, strlen($projection->checksum()), 'Projection checksum is canonical.');
        $this->assertTrue($webhook instanceof WebhookContributionDefinition, 'The webhook is canonical and typed.');
        $this->assertSame(6, $webhook->maximumAttempts(), 'Webhook retry budget is retained.');
    }

    /** @since 0.2.0 */
    public function testManifestPublishesCanonicalTypedContributionLookups(): void
    {
        $administrator = ExtensionManifest::fromJson($this->fixture(2))->contributions();
        $workspace = $administrator->administratorWorkspaces()[0];
        $navigation = $administrator->administratorNavigation()[0];
        $view = $administrator->administratorViews()[0];
        $this->assertSame($workspace, $administrator->administratorWorkspace($workspace->identifier()), 'Workspace lookup retains the parsed value.');
        $this->assertSame($navigation, $administrator->administratorNavigationItem($navigation->identifier()), 'Navigation lookup retains the parsed value.');
        $this->assertSame($view, $administrator->administratorView($view->identifier()), 'View lookup retains the parsed value.');
        $this->assertSame([], $administrator->administratorRoutes(), 'An undeclared route surface stays empty.');

        $portal = ExtensionManifest::fromJson($this->fixture(3))->contributions();
        $portalWorkspace = $portal->portalWorkspaces()[0];
        $portalNavigation = $portal->portalNavigation()[0];
        $portalTemplate = $portal->portalTemplates()[0];
        $fieldPresentation = $portal->fieldPresentations()[0];
        $this->assertSame($portalWorkspace, $portal->portalWorkspace($portalWorkspace->identifier()), 'Portal workspace lookup retains the parsed value.');
        $this->assertSame($portalNavigation, $portal->portalNavigationItem($portalNavigation->identifier()), 'Portal navigation lookup retains the parsed value.');
        $this->assertSame($portalTemplate, $portal->portalTemplate($portalTemplate->identifier()), 'Portal template lookup retains the parsed value.');
        $this->assertSame($fieldPresentation, $portal->fieldPresentation($fieldPresentation->identifier()), 'Field-presenter lookup retains the parsed value.');

        $composition = ExtensionManifest::fromJson($this->fixture(5))->contributions();
        $block = $composition->compositionBlocks()[0];
        $pattern = $composition->compositionPatterns()[0];
        $control = $composition->compositionFieldControls()[0];
        $inspector = $composition->compositionInspectors()[0];
        $vocabulary = $composition->compositionDesignVocabularies()[0];
        $migration = $composition->compositionMigrations()[0];
        $this->assertSame($block, $composition->compositionBlock($block->identifier()), 'Block lookup retains the parsed value.');
        $this->assertSame($pattern, $composition->compositionPattern($pattern->identifier()), 'Pattern lookup retains the parsed value.');
        $this->assertSame($control, $composition->compositionFieldControl($control->identifier()), 'Control lookup retains the parsed value.');
        $this->assertSame($inspector, $composition->compositionInspector($inspector->identifier()), 'Inspector lookup retains the parsed value.');
        $this->assertSame($vocabulary, $composition->compositionDesignVocabulary($vocabulary->identifier()), 'Vocabulary lookup retains the parsed value.');
        $this->assertSame($migration, $composition->compositionMigration($migration->identifier()), 'Migration lookup retains the parsed value.');

        $canonical = ExtensionManifest::fromJson($this->fixture(6))->contributions();
        $documents = $canonical->canonicalCompositionDocuments();
        $this->assertSame(6, count($documents), 'Every Producer-validated Studio document is typed once.');
        foreach ($documents as $document) {
            $this->assertTrue(
                $document instanceof CanonicalCompositionDocument,
                'The canonical document uses the package-owned immutable type.',
            );
            $this->assertSame(
                $document,
                $canonical->canonicalCompositionDocument($document->identifier()),
                'Canonical document lookup retains the validated object.',
            );
        }
        foreach ($canonical->compositionHostBindings() as $binding) {
            $this->assertSame($binding, $canonical->compositionHostBinding($binding->identifier()), 'Host-binding lookup retains the parsed value.');
        }
    }

    /** @since 0.2.0 */
    public function testMalformedCompleteDefinitionsFailClosed(): void
    {
        $integration = $this->integration();
        $job = $integration['jobs'][0];
        $job['schema_version'] = '1';
        $this->assertThrows(
            static fn (): JobContributionDefinition => JobContributionDefinition::fromArray($job),
            InvalidArgumentException::class,
            'A numeric-string job schema version is refused.',
        );

        $listener = $integration['domain_listeners'][0];
        $listener['schema_versions'] = [1, 1];
        $this->assertThrows(
            static fn (): DomainListenerDefinition => DomainListenerDefinition::fromArray($listener),
            InvalidArgumentException::class,
            'A non-canonical listener version list is refused by the manifest graph.',
        );

        $projection = $integration['projections'][0];
        $projection['fields'][0]['type'] = 'unknown';
        $this->assertThrows(
            static fn (): ProjectionDefinition => ProjectionDefinition::fromArray($projection),
            InvalidArgumentException::class,
            'An unknown projection value type is refused.',
        );
    }

    /** @since 0.2.0 */
    public function testCanonicalStudioDocumentsMustPassThePinnedProducerSchema(): void
    {
        $manifest = json_decode($this->fixture(6), true, 64, JSON_THROW_ON_ERROR);
        $canonical = $manifest['contributions']['composition']['documents'][0]['canonical'] ?? null;
        if (!is_string($canonical)) {
            throw new InvalidArgumentException('The canonical fixture document is malformed.');
        }
        $manifest['contributions']['composition']['documents'][0]['canonical'] = str_replace(
            '"contractVersion":"0.1-draft"',
            '"contractVersion":"invalid"',
            $canonical,
        );

        $failure = $this->assertThrows(
            static fn (): ExtensionManifest => ExtensionManifest::fromJson(json_encode(
                $manifest,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            )),
            InvalidArgumentException::class,
            'A canonical but schema-invalid Studio document is refused by the SDK parser.',
        );
        $this->assertStringContains(
            'fails',
            $failure->getMessage(),
            'The refusal identifies the pinned schema verdict.',
        );
    }

    /** @since 0.2.0 */
    public function testCanonicalStudioDocumentViewsCannotMutateSignedState(): void
    {
        $document = ExtensionManifest::fromJson($this->fixture(6))
            ->contributions()
            ->canonicalCompositionDocuments()[0];
        $first = $document->document();
        $identityMember = $document->kind->identityMember();
        $first->{$identityMember} = 'attacker/changed';

        $this->assertSame(
            $document->identity(),
            $document->document()->{$identityMember},
            'Each decoded view is isolated from prior consumer mutation.',
        );
        $this->assertStringContains(
            $document->identity(),
            $document->canonical,
            'The exact signed bytes remain authoritative after a view is mutated.',
        );
    }

    /** @return array<string, mixed> Canonical integration section. @since 0.2.0 */
    private function integration(): array
    {
        $manifest = ExtensionManifest::fromJson($this->fixture());
        $integration = $manifest->contributions()->declarations()['integration'] ?? null;
        if (!is_array($integration)) {
            throw new InvalidArgumentException('The integration fixture is malformed.');
        }

        return $integration;
    }

    /** @since 0.2.1 */
    public function testPlatformEventBindingsCrossTheOwnerBoundaryOnlyTowardCore(): void
    {
        $manifest = json_decode($this->fixture(), true, 64, JSON_THROW_ON_ERROR);
        $integration = $manifest['contributions']['integration'];
        $integration['domain_listeners'][0]['event_type'] = 'core.business_record.mutated';
        $integration['consumers'][0]['event_type'] = 'core.business_record.mutated';
        $integration['projections'][0]['sources'][0]['event_type'] = 'core.business_record.mutated';
        $integration['webhooks'][0]['event_types'] = ['core.business_record.mutated'];
        $manifest['contributions']['integration'] = $integration;
        $accepted = ExtensionManifest::fromJson(json_encode($manifest, JSON_THROW_ON_ERROR));
        $declared = $accepted->contributions()->declarations()['integration'] ?? null;
        if (!is_array($declared)) {
            throw new InvalidArgumentException('The platform-event fixture lost its integration section.');
        }
        $this->assertSame(
            'core.business_record.mutated',
            $declared['domain_listeners'][0]['event_type'] ?? null,
            'A signed extension observes a platform core event without declaring its schema.',
        );
        $this->assertSame(
            'core.business_record.mutated',
            $declared['consumers'][0]['event_type'] ?? null,
            'A signed extension consumes a platform core event through its own queue.',
        );

        $integration['domain_listeners'][0]['event_type'] = 'acme.rival.observed';
        $manifest['contributions']['integration'] = $integration;
        $failure = $this->assertThrows(
            static fn (): ExtensionManifest => ExtensionManifest::fromJson(
                json_encode($manifest, JSON_THROW_ON_ERROR),
            ),
            InvalidArgumentException::class,
            'A foreign vendor event binding is refused at the manifest boundary.',
        );
        $this->assertStringContains(
            'cannot claim',
            $failure->getMessage(),
            'The refusal names the foreign claim, not a missing schema.',
        );
    }

    /** @since 0.2.1 */
    public function testContentTranslationGroupsStayInsideTheSignedNamespace(): void
    {
        $manifest = json_decode($this->fixture(), true, 64, JSON_THROW_ON_ERROR);
        $group = [
            'group_id' => 'kumwe.contract-manifest-four.articles',
            'locales' => ['de', 'en-GB'],
            'fallback_locale' => 'en-GB',
        ];
        $manifest['contributions']['content'] = ['translation_groups' => [$group]];
        $accepted = ExtensionManifest::fromJson(json_encode($manifest, JSON_THROW_ON_ERROR));
        $declared = $accepted->contributions()->declarations()['content'] ?? null;
        $this->assertSame(
            'kumwe.contract-manifest-four.articles',
            is_array($declared) ? ($declared['translation_groups'][0]['group_id'] ?? null) : null,
            'An owned content set with a declared fallback locale is admitted.',
        );

        $group['group_id'] = 'zeta.shop.products';
        $manifest['contributions']['content'] = ['translation_groups' => [$group]];
        $foreign = $this->assertThrows(
            static fn (): ExtensionManifest => ExtensionManifest::fromJson(
                json_encode($manifest, JSON_THROW_ON_ERROR),
            ),
            InvalidArgumentException::class,
            'A foreign content set claim is refused at the manifest boundary.',
        );
        $this->assertStringContains('cannot claim', $foreign->getMessage(), 'The refusal names the claim.');

        $group['group_id'] = 'kumwe.contract-manifest-four.articles';
        $group['fallback_locale'] = 'fr';
        $manifest['contributions']['content'] = ['translation_groups' => [$group]];
        $this->assertThrows(
            static fn (): ExtensionManifest => ExtensionManifest::fromJson(
                json_encode($manifest, JSON_THROW_ON_ERROR),
            ),
            InvalidArgumentException::class,
            'A fallback outside the declared locales is refused.',
        );
    }

    /** @since 0.2.2 */
    public function testGraphicalPackageCannotOmitItsInterfaceDeclaration(): void
    {
        $manifest = json_decode($this->fixture(), true, 64, JSON_THROW_ON_ERROR);
        $prefix = 'kumwe.contract-manifest-four';
        $manifest['contributions']['administrator']['views'] = [
            ['name' => $prefix . '.administrator.index', 'template' => 'index.twig'],
        ];
        $manifest['contributions']['administrator']['routes'] = [[
            'name' => $prefix . '.administrator.index',
            'path' => '/',
            'methods' => ['GET'],
            'capability' => $prefix . '.view',
            'view' => $prefix . '.administrator.index',
        ]];
        $manifest['contributions']['interface'] = ['surfaces' => [[
            'surface' => $prefix . '.administrator.index',
            'standard' => 'kis-1.0',
            'area' => 'administrator',
            'actor' => 'administrator',
            'intent' => 'diagnostics',
            'resource' => 'contract-manifest-four',
            'purpose' => 'Inspect the manifest-four contract fixture.',
            'pattern' => 'diagnostics-workspace',
            'capabilities' => [$prefix . '.view'],
            'states' => ['default', 'empty', 'error', 'permission-reduced'],
        ]]];
        $accepted = ExtensionManifest::fromJson(json_encode($manifest, JSON_THROW_ON_ERROR));
        $declared = $accepted->contributions()->declarations()['interface'] ?? null;
        $this->assertSame(
            $prefix . '.administrator.index',
            is_array($declared) ? ($declared['surfaces'][0]['surface'] ?? null) : null,
            'A graphical GET route covered by an area-matched surface is admitted.',
        );

        $undeclared = $manifest;
        unset($undeclared['contributions']['interface']);
        $failure = $this->assertThrows(
            static fn (): ExtensionManifest => ExtensionManifest::fromJson(
                json_encode($undeclared, JSON_THROW_ON_ERROR),
            ),
            InvalidArgumentException::class,
            'A graphical package cannot omit its interface declaration.',
        );
        $this->assertStringContains(
            'declare every administrator graphical GET route as a surface',
            $failure->getMessage(),
            'The refusal names the missing surface coverage.',
        );

        $foreignArea = $manifest;
        $foreignArea['contributions']['interface']['surfaces'][0]['area'] = 'portal';
        $this->assertThrows(
            static fn (): ExtensionManifest => ExtensionManifest::fromJson(
                json_encode($foreignArea, JSON_THROW_ON_ERROR),
            ),
            InvalidArgumentException::class,
            'An area-mismatched surface never covers a graphical route.',
        );
    }

    /** @return string Manifest-four JSON. @since 0.2.0 */
    private function fixture(int $generation = 4): string
    {
        $json = file_get_contents(
            dirname(__DIR__, 2) . sprintf('/resources/fixtures/generations/manifest-%d/kumwe.json', $generation),
        );
        if (!is_string($json)) {
            throw new InvalidArgumentException('The manifest-four fixture cannot be read.');
        }

        return $json;
    }
}
