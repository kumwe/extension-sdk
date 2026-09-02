<?php

/**
 * Proves the capability code normalises at the domain boundary and refuses every other shape.
 *
 * @since 0.2.4
 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use InvalidArgumentException;
use Kumwe\Extension\Spi\Identity\Domain\Capability;
use Kumwe\Extension\Tests\TestCase;

/**
 * Behavioral checks for `Capability::fromString()`, the value's only constructor.
 *
 * The grammar is a leading letter, then alphanumeric groups joined by single `.`, `_`, `:` or `-`
 * separators, at most 191 characters after trimming and lowercasing. Casing and surrounding space
 * are corrected, never refused; everything else fails closed with the documented message.
 *
 * @since  0.2.4
 */
final class CapabilityTest extends TestCase
{
    /**
     * Every accepted shape of the grammar round-trips as its own value.
     *
     * @return  void
     *
     * @since   0.2.4
     */
    public function testAcceptedGrammarRoundTrips(): void
    {
        $accepted = [
            'a',
            'x9',
            'content.publish',
            'acme.orders_v1',
            'acme.orders:read-all',
            'a.b_c:d-e',
            'core.business_record.mutated',
            'z' . str_repeat('9', 190),
        ];
        foreach ($accepted as $code) {
            $capability = Capability::fromString($code);
            $this->assertSame($code, $capability->value(), sprintf('%s is accepted unchanged.', $code));
            $this->assertSame($code, (string) $capability, sprintf('%s renders as its code.', $code));
        }
    }

    /**
     * Surrounding whitespace and casing are corrected before the grammar is judged.
     *
     * @return  void
     *
     * @since   0.2.4
     */
    public function testNormalisationTrimsAndLowercases(): void
    {
        $normalised = [
            ' content.publish ' => 'content.publish',
            'Content.Publish' => 'content.publish',
            "\tCONTENT_PUBLISH\n" => 'content_publish',
            "\r\n Acme.Orders:Read-All \0" => 'acme.orders:read-all',
            '  ' . str_repeat('A', 191) . '  ' => str_repeat('a', 191),
        ];
        foreach ($normalised as $written => $expected) {
            $this->assertSame(
                $expected,
                Capability::fromString($written)->value(),
                sprintf('%s normalises to %s.', var_export($written, true), $expected),
            );
        }

        $canonical = Capability::fromString('content.publish');
        $this->assertTrue(
            $canonical->equals(Capability::fromString(' Content.PUBLISH ')),
            'Two spellings of one permission compare equal after normalisation.',
        );
        $this->assertTrue(
            !$canonical->equals(Capability::fromString('content.publish_all')),
            'Distinct codes never compare equal.',
        );
    }

    /**
     * An empty, blank or overlong value is refused with the bounds message.
     *
     * @return  void
     *
     * @since   0.2.4
     */
    public function testEmptyAndOverlongValuesAreRefused(): void
    {
        foreach (['', ' ', "\t\n", 'a' . str_repeat('b', 191), ' ' . str_repeat('c', 192) . ' '] as $code) {
            $failure = $this->assertThrows(
                static fn (): Capability => Capability::fromString($code),
                InvalidArgumentException::class,
                sprintf('%s must be refused on its bounds.', var_export($code, true)),
            );
            $this->assertStringContains(
                'between 1 and 191 characters',
                $failure->getMessage(),
                'The bounds refusal names the bounds.',
            );
        }
    }

    /**
     * Every value outside the delimiter-separated identifier grammar is refused, never repaired.
     *
     * @return  void
     *
     * @since   0.2.4
     */
    public function testMalformedIdentifiersAreRefused(): void
    {
        $malformed = [
            '1content',
            '_content',
            '.publish',
            '-publish',
            'content.',
            'content-',
            'content..publish',
            'content._publish',
            'content publish',
            'content/publish',
            'content\\publish',
            'content.publish!',
            'contént',
            "content\0publish",
            'content.pub lish',
        ];
        foreach ($malformed as $code) {
            $failure = $this->assertThrows(
                static fn (): Capability => Capability::fromString($code),
                InvalidArgumentException::class,
                sprintf('%s must be refused by the grammar.', var_export($code, true)),
            );
            $this->assertStringContains(
                'lowercase, delimiter-separated identifier',
                $failure->getMessage(),
                'The grammar refusal names the grammar.',
            );
        }
    }
}
