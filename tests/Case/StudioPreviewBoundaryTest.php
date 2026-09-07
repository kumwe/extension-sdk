<?php

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use InvalidArgumentException;
use Kumwe\Extension\Spi\Studio\Application\Preview\StudioPreviewBindingResult;
use Kumwe\Extension\Spi\Studio\Application\Preview\StudioPreviewBlockFragment;
use Kumwe\Extension\Tests\TestCase;

/** Remaining SDK preview DTOs retain their own behavioral ownership. @since 0.3.0 */
final class StudioPreviewBoundaryTest extends TestCase
{
    /** @since 0.3.0 */
    public function testBindingStatesRetainTheRequestedDisclosure(): void
    {
        $visible = new StudioPreviewBindingResult(true, false, 'text');
        $this->assertSame('text', $visible->value, 'The visible value is retained.');
        $this->assertSame(false, StudioPreviewBindingResult::unavailable()->available, 'Absence is explicit.');
        $this->assertSame(null, StudioPreviewBindingResult::hidden()->value, 'Hidden results expose no value.');
        $this->assertThrows(static fn () => new StudioPreviewBindingResult(true, true, 'secret'),
            InvalidArgumentException::class, 'Conflicting disclosure state is refused.');
    }

    /** @since 0.3.0 */
    public function testFragmentKeepsTextAndRejectsUnsafeMarkupChoices(): void
    {
        $fragment = new StudioPreviewBlockFragment('section', 'preview-block', '<b>plain text</b>');
        $this->assertSame('<b>plain text</b>', $fragment->text, 'The DTO preserves plain text without rendering markup.');
        foreach ([['script', 'preview-block', []], ['section', 'onclick=bad', []],
            ['section', 'preview-block', ['onclick' => 'bad()']]] as [$element, $class, $attributes]) {
            $this->assertThrows(static fn () => new StudioPreviewBlockFragment($element, $class, 'text', false, $attributes),
                InvalidArgumentException::class, 'Unsafe element, class or arbitrary attributes are refused.');
        }
    }
}
