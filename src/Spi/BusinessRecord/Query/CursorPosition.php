<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessRecord\Query;

use InvalidArgumentException;

/**
 * Verified page position that a signed browse cursor carries from one request to the next.
 *
 * Both directions of a cursor build this value: a host mints it from the last returned row, and its
 * signed-cursor decoder rebuilds it from a client token. Construction is therefore where an untrusted payload is checked, which is why
 * nothing but a hex digest, a UUID record key, and at most five JSON-stable scalar sort values
 * survives it. Composite query values are deliberately refused because their JSON object form would
 * not be the same constructor input after a signed token is decoded. The digest names the exact query the position belongs to;
 * host query compiler compares it against the query actually being run, so a genuine cursor replayed
 * against a different filter or sort is refused rather than quietly paging over rows the original
 * query never matched.
 *
 * @since  0.2.0
 */
final readonly class CursorPosition
{
    /**
     * Values of the last returned row's sort columns, in the order the query's sorts declare them.
     *
     * Reindexed from zero on construction so the list round-trips through JSON as an array rather than
     * an object. The compiler requires one entry per declared sort, or a single default-ordering value
     * when the query declares none.
     *
     * @var    list<null|bool|int|string>
     * @since  0.2.0
     */
    public array $sortValues;

    /**
     * Build a page position, rejecting every part of it a client could have tampered with.
     *
     * @param   string       $specificationDigest  Lowercase 64-character hex checksum of the query this
     *          position belongs to, as the query compiler computes it.
     * @param   list<null|bool|int|string>  $sortValues  Sort-column values of the last row on the page
     *          just returned, in sort order; at most five JSON-stable bounded scalars.
     * @param   string       $recordKey            UUID of that last row, which breaks ties between rows
     *          whose sort values are equal.
     *
     * @throws  InvalidArgumentException  When the digest is not 64 hex characters, the record key is not a
     *          UUID, the values are not a list, more than five sort values are supplied, or a value is
     *          composite, inexact, or oversized.
     *
     * @since   0.2.0
     */
    public function __construct(
        public string $specificationDigest,
        array $sortValues,
        public string $recordKey,
    ) {
        if (
            preg_match('/^[a-f0-9]{64}$/D', $specificationDigest) !== 1
            || !\Ramsey\Uuid\Uuid::isValid($recordKey)
        ) {
            throw new InvalidArgumentException('A business-record cursor position identity is invalid.');
        }
        if (!array_is_list($sortValues) || count($sortValues) > 5) {
            throw new InvalidArgumentException('A business-record cursor requires at most five ordered sort values.');
        }
        foreach ($sortValues as $value) {
            if (
                !($value === null || is_bool($value) || is_int($value) || is_string($value))
                || (is_string($value) && (strlen($value) > 4096 || !mb_check_encoding($value, 'UTF-8')))
            ) {
                throw new InvalidArgumentException(
                    'A business-record cursor sort value must be a bounded UTF-8 JSON-stable scalar.',
                );
            }
        }
        $this->sortValues = $sortValues;
    }

    /**
     * Flatten the position into the payload `RecordCursorCodec` signs and encodes into a token.
     *
     * Sort values are already canonical JSON scalars, so the output can be decoded and passed back to
     * this constructor without type translation.
     *
     * @return  array{specification: string, values: list<null|bool|int|string>, record_key: string}  The
     *          query digest, JSON-stable sort values, and tie-breaking record key.
     *
     * @since   0.2.0
     */
    public function toArray(): array
    {
        return [
            'specification' => $this->specificationDigest,
            'values' => $this->sortValues,
            'record_key' => $this->recordKey,
        ];
    }
}
