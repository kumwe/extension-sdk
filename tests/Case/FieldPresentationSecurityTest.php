<?php

/**
 * Proves field presentations remain bounded, markup-free and provenance-complete.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Kumwe\Conversion\Decimal\ExactDecimal;
use Kumwe\Conversion\Decimal\ExactDecimalArithmetic;
use Kumwe\Conversion\Value\ConvertedMoneyValue;
use Kumwe\Conversion\Value\MoneyExchangeRate;
use Kumwe\Conversion\Value\MoneyRoundingMode;
use Kumwe\Conversion\Value\MoneyValue;
use Kumwe\Extension\Spi\BusinessSurface\Presentation\Field\FieldPresentationConfiguration;
use Kumwe\Extension\Spi\BusinessSurface\Presentation\Field\FieldPresentationContext;
use Kumwe\Extension\Spi\BusinessSurface\Presentation\Field\FieldPresentationInput;
use Kumwe\Extension\Spi\BusinessSurface\Presentation\Field\FieldPresentationModel;
use Kumwe\Extension\Spi\BusinessSurface\Presentation\Field\FieldWidget;
use Kumwe\Extension\Tests\TestCase;

/** @since 0.2.0 */
final class FieldPresentationSecurityTest extends TestCase
{
    /** @since 0.2.0 */
    public function testSignedFieldConstraintsAndConfigurationStayClosedAndBounded(): void
    {
        $configuration = FieldPresentationConfiguration::fromArray([
            'options' => ['draft', 'approved'],
            'widget' => 'compact',
        ]);
        $input = new FieldPresentationInput(
            'status',
            'Status',
            'core.enum',
            true,
            false,
            false,
            false,
            false,
            FieldPresentationContext::Create,
            editable: true,
            length: 80,
            precision: 12,
            scale: 2,
            configuration: $configuration,
        );
        $this->assertSame(80, $input->length, 'The signed maximum length is retained.');
        $this->assertSame(12, $input->precision, 'The signed precision is retained.');
        $this->assertSame(2, $input->scale, 'The signed scale is retained.');
        $this->assertSame(['draft', 'approved'], $input->configuration->get('options'), 'Options remain typed.');
        $this->assertSame(
            ['options' => ['draft', 'approved'], 'widget' => 'compact'],
            $input->configuration->toArray(),
            'Configuration keys are canonical and sorted.',
        );

        foreach (
            [
                ['Bad-Key' => true],
                array_fill(0, FieldPresentationConfiguration::MAXIMUM_KEYS + 1, true),
                ['value' => 1.5],
                ['value' => new \stdClass()],
                ['value' => ['nested' => true]],
                ['value' => array_fill(0, FieldPresentationConfiguration::MAXIMUM_LIST_ITEMS + 1, null)],
                ['value' => str_repeat('x', FieldPresentationConfiguration::MAXIMUM_STRING_BYTES + 1)],
            ] as $candidate
        ) {
            $this->assertThrows(
                fn (): FieldPresentationConfiguration => FieldPresentationConfiguration::fromArray($candidate),
                InvalidArgumentException::class,
                'A malformed, executable, nested or unbounded field configuration must be refused.',
            );
        }

        foreach (
            [
                ['length' => 0],
                ['precision' => 12],
                ['precision' => 4, 'scale' => 5],
            ] as $bounds
        ) {
            $this->assertThrows(
                fn (): FieldPresentationInput => new FieldPresentationInput(
                    'status',
                    'Status',
                    'core.enum',
                    true,
                    false,
                    false,
                    false,
                    false,
                    FieldPresentationContext::Create,
                    editable: true,
                    length: $bounds['length'] ?? null,
                    precision: $bounds['precision'] ?? null,
                    scale: $bounds['scale'] ?? null,
                ),
                InvalidArgumentException::class,
                'Malformed portable field bounds must be refused.',
            );
        }
    }

    /** @since 0.2.0 */
    public function testErrorsOptionsAndAttributesAreClosedAndBounded(): void
    {
        $this->assertThrows(
            fn (): FieldPresentationInput => new FieldPresentationInput(
                'name',
                'Name',
                'core.text',
                false,
                false,
                false,
                false,
                false,
                FieldPresentationContext::Detail,
                errors: [12],
            ),
            InvalidArgumentException::class,
            'A mistyped field error must be a controlled refusal.',
        );
        foreach ([[''], [str_repeat('e', 1001)]] as $errors) {
            $this->assertThrows(
                fn (): FieldPresentationModel => $this->model(errors: $errors),
                InvalidArgumentException::class,
                'An empty or oversized error must be refused.',
            );
        }
        foreach (
            [
                [['label' => 'One', 'value' => '1', 'extra' => true]],
                [['value' => 1, 'label' => 'One']],
                [['value' => str_repeat('v', 192), 'label' => 'One']],
                [['value' => '1', 'label' => '']],
            ] as $options
        ) {
            $this->assertThrows(
                fn (): FieldPresentationModel => $this->model(options: $options),
                InvalidArgumentException::class,
                'An option outside the exact bounded shape must be refused.',
            );
        }
        foreach (
            [
                ['onclick' => 'alert(1)'],
                ['autocomplete' => str_repeat('a', 192)],
                ['rows' => ['nested']],
            ] as $attributes
        ) {
            $this->assertThrows(
                fn (): FieldPresentationModel => $this->model(attributes: $attributes),
                InvalidArgumentException::class,
                'An unsafe or unbounded widget attribute must be refused.',
            );
        }
    }

    /** @since 0.2.0 */
    public function testRetainedInputIsExactJsonWithDepthWidthAndByteLimits(): void
    {
        foreach ([1.5, new \stdClass(), array_fill(0, 513, null), str_repeat('x', 1_048_577)] as $input) {
            $this->assertThrows(
                fn (): FieldPresentationModel => $this->model(
                    widget: FieldWidget::Text,
                    inputValue: $input,
                    editable: true,
                ),
                InvalidArgumentException::class,
                'An inexact, unsupported, over-wide or oversized retained value must be refused.',
            );
        }

        $nested = null;
        for ($depth = 0; $depth < 34; $depth++) {
            $nested = [$nested];
        }
        $this->assertThrows(
            fn (): FieldPresentationModel => $this->model(
                widget: FieldWidget::Text,
                inputValue: $nested,
                editable: true,
            ),
            InvalidArgumentException::class,
            'An over-deep retained value must be refused.',
        );
    }

    /** @since 0.2.0 */
    public function testConvertedMoneyCannotBeSeparatedFromItsEvidence(): void
    {
        $converted = self::convertedMoney();
        $model = $this->model(display: $converted->toPortableString(), provenance: $converted->toArray());
        $this->assertSame($converted->toArray(), $model->provenance, 'Complete conversion evidence is retained.');

        $broken = $converted->toArray();
        $broken['rate']['rate'] = '0.99999999';
        foreach (
            [
                ['display' => $converted->toPortableString(), 'provenance' => $broken],
                ['display' => 'EUR 1234.56', 'provenance' => $converted->toArray()],
                [
                    'display' => $converted->toPortableString(),
                    'provenance' => $converted->toArray(),
                    'widget' => FieldWidget::Text,
                    'inputValue' => '1234.56',
                    'editable' => true,
                ],
            ] as $candidate
        ) {
            $this->assertThrows(
                fn (): FieldPresentationModel => $this->model(...$candidate),
                InvalidArgumentException::class,
                'Incomplete, detached or editable converted money must be refused.',
            );
        }
    }

    /** @since 0.2.4 */
    public function testConvertedMoneyRefusalsFollowTheEditorStateInvariant(): void
    {
        $converted = self::convertedMoney();
        $display = $converted->toPortableString();
        $provenance = $converted->toArray();

        foreach (
            [
                'retained input' => ['inputValue' => ['amount' => '1.00']],
                'an enabled editor' => ['widget' => FieldWidget::Money, 'editable' => true],
            ] as $case => $candidate
        ) {
            $failure = $this->assertThrows(
                fn (): FieldPresentationModel => $this->model(
                    ...$candidate + ['display' => $display, 'provenance' => $provenance],
                ),
                InvalidArgumentException::class,
                sprintf('A converted amount presented with %s must be refused.', $case),
            );
            $this->assertStringContains('read-only', $failure->getMessage(), 'The refusal names the read-only rule.');
        }

        $inconsistent = $this->assertThrows(
            fn (): FieldPresentationModel => $this->model(
                display: $display,
                widget: FieldWidget::Money,
                provenance: $provenance,
            ),
            InvalidArgumentException::class,
            'A read-only input widget must be refused before provenance is consulted.',
        );
        $this->assertStringContains(
            'inconsistent editor state',
            $inconsistent->getMessage(),
            'The editor-state invariant runs first, so provenance never sees the inconsistent widget.',
        );

        $incomplete = $provenance;
        unset($incomplete['rate']);
        $missingRate = $this->assertThrows(
            fn (): FieldPresentationModel => $this->model(display: $display, provenance: $incomplete),
            InvalidArgumentException::class,
            'Provenance that cannot be read back as a whole converted amount must be refused.',
        );
        $this->assertStringContains(
            'incomplete conversion provenance',
            $missingRate->getMessage(),
            'The refusal names the missing evidence rather than trusting the figure.',
        );
    }

    /** @since 0.2.4 */
    public function testAnOrdinaryPresentationCarriesNoProvenance(): void
    {
        $model = $this->model(display: 'N$ 1,200.00');

        $this->assertSame(null, $model->provenance, 'An ordinary presentation retains no conversion evidence.');
        $this->assertSame(null, $model->toArray()['provenance'], 'The export carries the absent member as null.');
    }

    /**
     * @param list<string>                              $errors
     * @param list<array{value: string, label: string}> $options
     * @param array<string, mixed>                      $attributes
     * @param ?array<string, mixed>                     $provenance
     *
     * @since 0.2.0
     */
    private function model(
        string $display = 'Name',
        FieldWidget $widget = FieldWidget::Output,
        mixed $inputValue = null,
        bool $editable = false,
        array $errors = [],
        array $options = [],
        array $attributes = [],
        ?array $provenance = null,
    ): FieldPresentationModel {
        return new FieldPresentationModel(
            'name',
            'Name',
            FieldPresentationContext::Detail,
            $widget,
            $display,
            $inputValue,
            $editable,
            false,
            $errors,
            $options,
            $attributes,
            $provenance,
        );
    }

    /** @since 0.2.0 */
    private static function convertedMoney(): ConvertedMoneyValue
    {
        $source = new MoneyValue(ExactDecimal::fromString('25000.00', 12, 2), 'ZAR');
        $rate = new MoneyExchangeRate(
            'ZAR',
            'EUR',
            ExactDecimalArithmetic::fromLiteral('0.04938240'),
            new DateTimeImmutable('2026-08-14T00:00:00', new DateTimeZone('UTC')),
            'acme.rates.ecb',
        );
        $unrounded = ExactDecimalArithmetic::multiply($source->amount, $rate->rate);

        return new ConvertedMoneyValue(
            $source,
            new MoneyValue(
                ExactDecimalArithmetic::round($unrounded, 12, 2, MoneyRoundingMode::HalfUp),
                'EUR',
            ),
            $rate,
            MoneyRoundingMode::HalfUp,
            $unrounded,
        );
    }
}
