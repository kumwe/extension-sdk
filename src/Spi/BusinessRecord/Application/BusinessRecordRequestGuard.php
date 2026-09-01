<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessRecord\Application;

use InvalidArgumentException;

/** Exact identity and concurrency grammar shared by canonical business-record request DTOs. @since 0.2.0 */
final class BusinessRecordRequestGuard
{
    /** @param string $value Definition identity to assert: a UUID or a multi-segment lowercase handle. @since 0.2.0 */
    public static function definition(string $value): void
    {
        if (!self::uuid($value) && preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)+$/D', $value) !== 1) {
            throw new InvalidArgumentException('A business-definition identifier is invalid.');
        }
    }

    /** @param string $value Record identity to assert: non-empty, at most 191 bytes, no control characters. @since 0.2.0 */
    public static function record(string $value): void
    {
        if ($value === '' || strlen($value) > 191 || preg_match('/[\x00-\x1F\x7F]/D', $value) === 1) {
            throw new InvalidArgumentException('A business-record ID must be a bounded identity without controls.');
        }
    }

    /**
     * @param  string  $value  Candidate handle: lowercase snake_case, at most 63 characters.
     * @param  string  $label  Role the handle plays (e.g. action, view, workflow state), named in the failure message.
     *
     * @since  0.2.0
     */
    public static function handle(string $value, string $label): void
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,62}$/D', $value) !== 1) {
            throw new InvalidArgumentException(sprintf('A business-record %s handle is invalid.', $label));
        }
    }

    /** @param ?string $value Organization scope to assert, or null when the request carries no scope. @since 0.2.0 */
    public static function organization(?string $value): void
    {
        if ($value !== null && preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,190}$/D', $value) !== 1) {
            throw new InvalidArgumentException('A business-record organization identifier is invalid.');
        }
    }

    /** @param int $value Expected optimistic-concurrency record version; must be one or greater. @since 0.2.0 */
    public static function version(int $value): void
    {
        if ($value < 1) {
            throw new InvalidArgumentException('A business-record expected version must be positive.');
        }
    }

    /** @param ?string $value Approval-request identity to assert as a UUID, or null when none is attached. @since 0.2.0 */
    public static function approval(?string $value): void
    {
        if ($value !== null && !self::uuid($value)) {
            throw new InvalidArgumentException('A custom action approval identity must be a valid UUID.');
        }
    }

    /** @param string $value Candidate checked against the canonical UUID shape once URN/brace wrappers are stripped. @since 0.2.0 */
    private static function uuid(string $value): bool
    {
        $normalized = str_replace(['urn:', 'uuid:', 'URN:', 'UUID:', '{', '}'], '', $value);

        return preg_match('/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/Di', $normalized) === 1;
    }

    /** Static grammar holder; never instantiated. @since 0.2.0 */
    private function __construct()
    {
    }
}
