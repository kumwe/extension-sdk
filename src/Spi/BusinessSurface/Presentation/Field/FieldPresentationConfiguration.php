<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessSurface\Presentation\Field;

use InvalidArgumentException;
use JsonException;

/**
 * Closed portable configuration handed to one field presenter.
 *
 * Configuration is copied from an already admitted signed field definition. It deliberately accepts
 * only a shallow object of exact scalars or scalar lists: presenters can read type-specific options,
 * while executable values, nested policy documents and unbounded structures cannot cross the SPI.
 *
 * @since 0.2.0
 */
final readonly class FieldPresentationConfiguration
{
    /** Maximum number of type-specific settings one field may expose. @since 0.2.0 */
    public const int MAXIMUM_KEYS = 64;

    /** Maximum members in one configuration list. @since 0.2.0 */
    public const int MAXIMUM_LIST_ITEMS = 256;

    /** Maximum UTF-8 bytes in one string value. @since 0.2.0 */
    public const int MAXIMUM_STRING_BYTES = 4096;

    /** Maximum canonical encoded bytes for the complete configuration object. @since 0.2.0 */
    public const int MAXIMUM_BYTES = 32768;

    /**
     * @param array<string, string|int|bool|list<string|int|bool|null>|null> $values Sorted settings.
     *
     * @since 0.2.0
     */
    private function __construct(private array $values)
    {
    }

    /**
     * Admit the portable field-definition configuration profile.
     *
     * @param array<string, mixed> $values Candidate type-specific settings.
     *
     * @return self Validated, key-sorted configuration.
     *
     * @throws InvalidArgumentException When the object is too wide, a key is malformed, a value is
     *         nested or executable, a string/list exceeds its bound, or canonical JSON exceeds 32 KiB.
     *
     * @since 0.2.0
     */
    public static function fromArray(array $values): self
    {
        if (($values !== [] && array_is_list($values)) || count($values) > self::MAXIMUM_KEYS) {
            throw new InvalidArgumentException('Field presentation configuration must be a bounded object.');
        }

        foreach ($values as $key => $value) {
            if (!is_string($key) || preg_match('/^[a-z][a-z0-9_]{0,62}$/D', $key) !== 1) {
                throw new InvalidArgumentException('A field presentation configuration key is invalid.');
            }
            self::assertValue($value);
        }
        ksort($values, SORT_STRING);

        try {
            $encoded = json_encode(
                $values,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                'Field presentation configuration must contain valid UTF-8 JSON values.',
                0,
                $exception,
            );
        }
        if (strlen($encoded) > self::MAXIMUM_BYTES) {
            throw new InvalidArgumentException('Field presentation configuration exceeds its byte budget.');
        }

        /** @var array<string, string|int|bool|list<string|int|bool|null>|null> $values */
        return new self($values);
    }

    /** @return self Empty configuration. @since 0.2.0 */
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Read one declared setting without exposing a mutable array.
     *
     * @param string $key Valid configuration key.
     *
     * @return string|int|bool|list<string|int|bool|null>|null Declared value, or null when absent.
     *
     * @since 0.2.0
     */
    public function get(string $key): string|int|bool|array|null
    {
        return $this->values[$key] ?? null;
    }

    /** @return array<string, string|int|bool|list<string|int|bool|null>|null> Canonical settings. @since 0.2.0 */
    public function toArray(): array
    {
        return $this->values;
    }

    /**
     * @param mixed $value Candidate setting.
     *
     * @throws InvalidArgumentException When the setting falls outside the closed portable profile.
     *
     * @since 0.2.0
     */
    private static function assertValue(mixed $value): void
    {
        if (is_string($value)) {
            if (strlen($value) > self::MAXIMUM_STRING_BYTES) {
                throw new InvalidArgumentException('A field presentation configuration string is too large.');
            }

            return;
        }
        if (is_int($value) || is_bool($value) || $value === null) {
            return;
        }
        if (!is_array($value) || !array_is_list($value) || count($value) > self::MAXIMUM_LIST_ITEMS) {
            throw new InvalidArgumentException('A field presentation configuration value is invalid.');
        }
        foreach ($value as $item) {
            if (is_string($item)) {
                if (strlen($item) > self::MAXIMUM_STRING_BYTES) {
                    throw new InvalidArgumentException('A field presentation configuration string is too large.');
                }
                continue;
            }
            if (!is_int($item) && !is_bool($item) && $item !== null) {
                throw new InvalidArgumentException('A field presentation configuration list value is invalid.');
            }
        }
    }
}
