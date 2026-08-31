<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessSurface\Application\Custom;

use InvalidArgumentException;
use JsonException;

/** Structural budget shared by custom business inputs and outputs. @since 0.2.0 */
final class CustomBusinessPayload
{
    /**
     * Assert that one decoded payload is a JSON object inside the shared structural budget.
     *
     * @param   array<string, mixed>  $payload  Decoded custom business object whose shape, size and keys are checked.
     * @param   string                $kind     Payload role (for example "view query" or "action input") named in failure messages.
     *
     * @return  void
     *
     * @throws  InvalidArgumentException  When the payload is not an object or breaches the depth, node, string or byte budget.
     *
     * @since   0.2.0
     */
    public static function assertObject(array $payload, string $kind): void
    {
        if ($payload !== [] && array_is_list($payload)) {
            throw new InvalidArgumentException(sprintf('A custom business %s must be an object.', $kind));
        }
        $nodes = 0;
        self::assertValue($payload, $kind, 0, $nodes);
        try {
            $bytes = json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                sprintf('A custom business %s must contain valid UTF-8 JSON values.', $kind),
                0,
                $exception,
            );
        }
        if (strlen($bytes) > 262_144) {
            throw new InvalidArgumentException(sprintf('A custom business %s exceeds 262144 bytes.', $kind));
        }
    }

    /**
     * Recursively enforce the depth, node, string and property-name budget on one JSON value.
     *
     * @param   mixed   $value  Candidate JSON value found at the current position of the payload tree.
     * @param   string  $kind   Payload role named in failure messages.
     * @param   int     $depth  Nesting level of the current value, zero for the root object.
     * @param   int     $nodes  Running count of visited nodes, shared by reference across the whole traversal.
     *
     * @return  void
     *
     * @throws  InvalidArgumentException  When the value is not an exact JSON value or exceeds a structural bound.
     *
     * @since   0.2.0
     */
    private static function assertValue(mixed $value, string $kind, int $depth, int &$nodes): void
    {
        ++$nodes;
        if ($depth > 8 || $nodes > 4096) {
            throw new InvalidArgumentException(sprintf(
                'A custom business %s exceeds its depth or node budget.',
                $kind,
            ));
        }
        if (is_string($value)) {
            if (strlen($value) > 65_535) {
                throw new InvalidArgumentException(sprintf(
                    'A custom business %s contains an oversized string.',
                    $kind,
                ));
            }
            return;
        }
        if ($value === null || is_bool($value) || is_int($value)) {
            return;
        }
        if (!is_array($value)) {
            throw new InvalidArgumentException(sprintf(
                'A custom business %s may contain only exact JSON values.',
                $kind,
            ));
        }
        $list = array_is_list($value);
        if (count($value) > ($list ? 200 : 128)) {
            throw new InvalidArgumentException(sprintf(
                'A custom business %s contains an unbounded collection.',
                $kind,
            ));
        }
        foreach ($value as $key => $item) {
            if (!$list && (!is_string($key) || preg_match('/^[a-z][a-z0-9_]{0,62}$/D', $key) !== 1)) {
                throw new InvalidArgumentException(sprintf(
                    'A custom business %s contains an unsafe property name.',
                    $kind,
                ));
            }
            self::assertValue($item, $kind, $depth + 1, $nodes);
        }
    }

    /** Static validation utility; never instantiated. @since 0.2.0 */
    private function __construct()
    {
    }
}
