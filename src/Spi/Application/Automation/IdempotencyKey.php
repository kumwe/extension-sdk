<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\Application\Automation;

use InvalidArgumentException;
use Stringable;

/** Validated caller-supplied identity of one replay-protected operation. @since 0.2.0 */
final readonly class IdempotencyKey implements Stringable
{
    /**
     * @param string $key Already validated transport-safe replay identity.
     *
     * @since 0.2.0
     */
    private function __construct(private string $key)
    {
    }

    /**
     * @throws  InvalidArgumentException  When the value is not 8 to 128 transport-safe ASCII characters.
     *
     * @param string $value Candidate caller-supplied replay identity.
     * @since   0.2.0
     */
    public static function fromString(string $value): self
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/D', $value) !== 1) {
            throw new InvalidArgumentException(
                'An idempotency key must contain 8 to 128 transport-safe ASCII characters.',
            );
        }

        return new self($value);
    }

    /** @return string 8 to 128 transport-safe ASCII characters. @since 0.2.0 */
    public function value(): string
    {
        return $this->key;
    }

    /**
     * @param self $other Replay identity to compare in constant time.
     *
     * @since 0.2.0
     */
    public function equals(self $other): bool
    {
        return hash_equals($this->key, $other->key);
    }

    /** @since 0.2.0 */
    public function __toString(): string
    {
        return $this->key;
    }
}
