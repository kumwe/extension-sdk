<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessRecord\Application;

use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

/** Exact identity and concurrency grammar shared by canonical business-record request DTOs. @since 0.2.0 */
final class BusinessRecordRequestGuard
{
    /**
     * @param string $value UUID or multi-segment namespaced definition identifier.
     *
     * @since 0.2.0
     */
    public static function definition(string $value): void
    {
        if (!self::uuid($value) && preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)+$/D', $value) !== 1) {
            throw new InvalidArgumentException('A business-definition identifier is invalid.');
        }
    }

    /**
     * @param string $value Bounded record identity with no control characters.
     *
     * @since 0.2.0
     */
    public static function record(string $value): void
    {
        if ($value === '' || strlen($value) > 191 || preg_match('/[\x00-\x1F\x7F]/D', $value) === 1) {
            throw new InvalidArgumentException('A business-record ID must be a bounded identity without controls.');
        }
    }

    /**
     * @param string $value Lowercase snake-case handle to validate.
     * @param string $label Handle kind named in a refusal.
     *
     * @since 0.2.0
     */
    public static function handle(string $value, string $label): void
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,62}$/D', $value) !== 1) {
            throw new InvalidArgumentException(sprintf('A business-record %s handle is invalid.', $label));
        }
    }

    /**
     * @param ?string $value Optional bounded organization identity.
     *
     * @since 0.2.0
     */
    public static function organization(?string $value): void
    {
        if ($value !== null && preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,190}$/D', $value) !== 1) {
            throw new InvalidArgumentException('A business-record organization identifier is invalid.');
        }
    }

    /**
     * @param int $value Positive optimistic-concurrency version.
     *
     * @since 0.2.0
     */
    public static function version(int $value): void
    {
        if ($value < 1) {
            throw new InvalidArgumentException('A business-record expected version must be positive.');
        }
    }

    /**
     * @param ?string $value Optional UUID approval-request identity.
     *
     * @since 0.2.0
     */
    public static function approval(?string $value): void
    {
        if ($value !== null && !self::uuid($value)) {
            throw new InvalidArgumentException('A custom action approval identity must be a valid UUID.');
        }
    }

    /**
     * @param string $value Candidate generic UUID representation.
     *
     * @since 0.2.0
     */
    private static function uuid(string $value): bool
    {
        return Uuid::isValid($value);
    }

    /**
     * @since 0.2.0
     */
    private function __construct()
    {
    }
}
