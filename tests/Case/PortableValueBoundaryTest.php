<?php

/** SDK-owned declaration, disclosure and preview value boundaries. @since 0.2.5 */
declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use InvalidArgumentException;
use Kumwe\Extension\Spi\Application\Automation\JobDeclaration;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\DomainListenerDeclaration;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\EventConsumerDeclaration;
use Kumwe\Extension\Spi\BusinessIntegration\Domain\WebhookDeclaration;
use Kumwe\Extension\Spi\BusinessSecurity\Application\FieldAccessUsage;
use Kumwe\Extension\Spi\BusinessSecurity\Application\FieldDisclosurePlan;
use Kumwe\Extension\Spi\BusinessSurface\Application\Custom\CustomBusinessActionDeclaration;
use Kumwe\Extension\Spi\BusinessSurface\Application\Custom\CustomBusinessViewDeclaration;
use Kumwe\Extension\Spi\BusinessSurface\Application\Custom\CustomBusinessSchema;
use Kumwe\Extension\Spi\Studio\Application\Preview\StudioPreviewBindingResult;
use Kumwe\Extension\Spi\Studio\Application\Preview\StudioPreviewBlockFragment;
use Kumwe\Extension\Tests\TestCase;

final class PortableValueBoundaryTest extends TestCase
{
    public function testJobAndEventDeclarationViewsRetainTheirCompleteCanonicalData(): void
    {
        $job = ['job_type' => 'acme.sync', 'schema_version' => 1];
        $value = JobDeclaration::fromManifest($job);
        $this->assertSame('acme.sync', $value->type(), 'Job type is retained.');
        $this->assertSame(1, $value->schemaVersion(), 'Job version is retained.');
        $this->assertSame($job, $value->toArray(), 'Complete job declaration survives.');
        foreach ([[DomainListenerDeclaration::class, 'listener_id'], [EventConsumerDeclaration::class, 'consumer_id'],
            [WebhookDeclaration::class, 'adapter_id']] as [$class, $key]) {
            $document = [$key => 'acme.audit', 'schema_versions' => [1, 3], 'sensitivity_ceiling' => 'public'];
            $document[$key === 'adapter_id' ? 'event_types' : 'event_type'] = $key === 'adapter_id'
                ? ['acme.changed'] : 'acme.changed';
            $declaration = $class::fromManifest($document);
            $this->assertSame('acme.audit', $declaration->identifier(), 'Declaration owner is retained.');
            $this->assertSame([1, 3], $declaration->schemaVersions(), 'Versions preserve signed order.');
            $this->assertSame($document, $declaration->toArray(), 'Complete declaration round trips.');
            foreach ([[], [0], [1, 1], [1 => 1], range(1, 33)] as $versions) {
                $invalid = $document;
                $invalid['schema_versions'] = $versions;
                $this->assertThrows(static fn () => $class::fromManifest($invalid), InvalidArgumentException::class,
                    'Malformed, duplicate or over-limit versions are refused.');
            }
            $invalid = $document;
            $invalid['sensitivity_ceiling'] = 'unknown';
            $this->assertThrows(static fn () => $class::fromManifest($invalid), InvalidArgumentException::class,
                'Unknown sensitivity cannot widen delivery.');
        }
        foreach ([['job_type' => 'acme.sync', 'schema_version' => 0],
            ['job_type' => "acme.sync\n", 'schema_version' => 1],
            ['job_type' => str_repeat('a', 192), 'schema_version' => 1]] as $invalid) {
            $this->assertThrows(static fn () => JobDeclaration::fromManifest($invalid), InvalidArgumentException::class,
                'Job grammar and version boundaries are enforced in the SDK.');
        }
    }

    public function testDisclosureIsAnExplicitSetForEveryUsage(): void
    {
        $empty = new FieldDisclosurePlan();
        $plan = new FieldDisclosurePlan(['detail' => ['name', 'code', 'name']]);
        foreach (FieldAccessUsage::cases() as $usage) {
            $this->assertSame([], $empty->fields($usage), 'Missing usage discloses nothing.');
            $this->assertSame(false, $empty->allows($usage, 'name'), 'Missing grant never means all fields.');
            $this->assertSame($usage === FieldAccessUsage::Detail, $plan->allows($usage, 'name'),
                'Detail disclosure cannot grant filter/search/export uses.');
        }
        $this->assertSame(['code', 'name'], $plan->fields(FieldAccessUsage::Detail), 'Fields sort and deduplicate.');
        $this->assertSame($plan->toArray(), (new FieldDisclosurePlan($plan->toArray()))->toArray(),
            'Disclosure export is a canonical fixed point.');
        foreach ([['unknown' => ['name']], ['detail' => array_fill(0, 257, 'name')],
            ['detail' => ['Name']], ['detail' => ['name' => 'name']], [['name']]] as $invalid) {
            $this->assertThrows(static fn () => new FieldDisclosurePlan($invalid), InvalidArgumentException::class,
                'Invalid usage, count, handle and list shapes cannot become grants.');
        }
    }

    public function testPreviewValuesKeepHiddenUnavailableAndNullDistinct(): void
    {
        $null = new StudioPreviewBindingResult(true, false, null);
        $this->assertSame(true, $null->available, 'A resolved null is an available value.');
        $this->assertSame(false, StudioPreviewBindingResult::unavailable()->hidden, 'Unavailable is not hidden.');
        $this->assertSame(true, StudioPreviewBindingResult::hidden()->hidden, 'Hidden state is explicit.');
        $this->assertSame(null, StudioPreviewBindingResult::hidden()->value, 'Factory hides source data.');
        $this->assertThrows(static fn () => new StudioPreviewBindingResult(true, true, 'secret'),
            InvalidArgumentException::class, 'Contradictory hidden and available state refuses.');
        $fragment = new StudioPreviewBlockFragment('section', 'acme-card', '<plain text>', false,
            ['data-studio-layout-spacing' => 'compact', 'data-studio-layout-columns' => '2']);
        $this->assertSame('<plain text>', $fragment->text, 'Text remains text for the host escaper.');
        $this->assertSame(['data-studio-layout-columns', 'data-studio-layout-spacing'],
            array_keys($fragment->layoutAttributes), 'Layout attributes have deterministic order.');
        foreach ([static fn () => new StudioPreviewBlockFragment('script', 'safe', ''),
            static fn () => new StudioPreviewBlockFragment('div', 'safe onclick', ''),
            static fn () => new StudioPreviewBlockFragment('div', 'safe', str_repeat('x', 100001)),
            static fn () => new StudioPreviewBlockFragment('div', 'safe', '', false, ['onclick' => 'run()']),
            static fn () => new StudioPreviewBlockFragment('div', 'safe', '', false,
                ['data-studio-layout-columns' => '13'])] as $invalid) {
            $this->assertThrows($invalid, InvalidArgumentException::class, 'Unsafe fragment surfaces refuse.');
        }
    }

    public function testCustomDeclarationsValidatePayloadsAgainstTheirClosedSchema(): void
    {
        $schema = ['type' => 'object', 'additionalProperties' => false, 'properties' => [
            'count' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10]], 'required' => ['count']];
        $contract = CustomBusinessSchema::fromArray($schema);
        $contract->assertValid(['count' => 1], 'command');
        $contract->assertValid(['count' => 10], 'result');
        $this->assertSame($contract->toArray(), CustomBusinessSchema::fromArray($contract->toArray())->toArray(),
            'Custom schema canonical export round trips.');
        foreach ([[], ['count' => 0], ['count' => 11], ['count' => 1.0], ['count' => 1, 'extra' => true]] as $payload) {
            $this->assertThrows(static fn () => $contract->assertValid($payload, 'command'),
                InvalidArgumentException::class, 'Closed exact payload constraints are package-owned.');
        }
        foreach ([[CustomBusinessActionDeclaration::class, 'command_schema'],
            [CustomBusinessViewDeclaration::class, 'query_schema']] as [$class, $input]) {
            $document = ['handler' => 'acme.handler', 'schema' => 'acme.schema', $input => $schema,
                'result_schema' => $schema];
            $declared = $class::fromManifest($document);
            $this->assertSame($declared->toArray(), $class::fromManifest($declared->toArray())->toArray(),
                'Action/view declarations retain canonical schema data.');
            foreach ([array_replace($document, ['schema' => 'acme.handler']),
                array_replace($document, ['handler' => 'foreign']), $document + ['unexpected' => true]] as $invalid) {
                $this->assertThrows(static fn () => $class::fromManifest($invalid), InvalidArgumentException::class,
                    'Ambiguous references and extra members refuse.');
            }
        }
    }
}
