<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessSurface\Application\Custom;

use InvalidArgumentException;
use JsonException;

/** Structural budget shared by custom business inputs and outputs. @since 0.2.0 */
final class CustomBusinessPayload
{
    /** @param array<string, mixed> $payload @since 0.2.0 */
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

    /** @since 0.2.0 */
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

    private function __construct()
    {
    }
}
