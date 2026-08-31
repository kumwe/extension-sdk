<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\Contribution;

use InvalidArgumentException;
use JsonException;
use Kumwe\Producer\Canonical\CanonicalEncodingException;
use Kumwe\Producer\Canonical\CanonicalJson;
use Kumwe\Producer\Schema\StudioDocumentSchemaRegistry;
use LogicException;
use stdClass;

/**
 * One exact canonical Studio contribution document from a schema-six manifest.
 *
 * Producer owns canonical JSON and the pinned Studio schema corpus. This SDK value owns the package
 * declaration: it preserves the signed bytes, decoded document and kind-scoped identity consumed by a
 * host, after requiring the exact Producer schema to accept them.
 *
 * @since 0.2.0
 */
final readonly class CanonicalCompositionDocument implements ContributionDefinition
{
    /** Largest canonical document admitted by the published contribution profile. @since 0.2.0 */
    public const int MAXIMUM_CANONICAL_BYTES = 262144;

    /** Document identity stored under the kind's published identity member. @since 0.2.0 */
    private string $identity;

    /**
     * @param CanonicalCompositionKind $kind Declared Studio contribution kind.
     * @param string $canonical Exact canonical JSON bytes carried by the signed manifest.
     *
     * @throws InvalidArgumentException When bytes are invalid, non-canonical, over budget, have the
     *         wrong root or kind, lack an identity, or fail Producer's exact Studio document schema.
     *
     * @since 0.2.0
     */
    public function __construct(
        public CanonicalCompositionKind $kind,
        public string $canonical,
    ) {
        if ($canonical === '' || strlen($canonical) > self::MAXIMUM_CANONICAL_BYTES) {
            throw new InvalidArgumentException(sprintf(
                'A canonical composition document must contain at most %d bytes.',
                self::MAXIMUM_CANONICAL_BYTES,
            ));
        }
        try {
            $decoded = CanonicalJson::decode($canonical);
            $expected = CanonicalJson::stringify($decoded);
        } catch (JsonException|CanonicalEncodingException $exception) {
            throw new InvalidArgumentException(
                'A canonical composition document must use valid canonical JSON.',
                0,
                $exception,
            );
        }
        if (!$decoded instanceof stdClass) {
            throw new InvalidArgumentException('A canonical composition document must be a JSON object.');
        }
        if (!hash_equals($expected, $canonical)) {
            throw new InvalidArgumentException(
                'A composition document must be declared in its exact canonical serialization.',
            );
        }
        if (($decoded->kind ?? null) !== $kind->value) {
            throw new InvalidArgumentException('A canonical composition document must match its declared kind.');
        }

        $validation = StudioDocumentSchemaRegistry::fromVendoredCorpus()->validate($kind->value, $decoded);
        if (!$validation->valid()) {
            $first = $validation->diagnostics()[0] ?? null;
            throw new InvalidArgumentException($first === null
                ? 'A canonical composition document fails its pinned Studio schema.'
                : sprintf(
                    'A canonical composition document fails %s at "%s": %s',
                    $first->keyword,
                    $first->instancePath,
                    $first->message,
                ));
        }

        $identity = $decoded->{$kind->identityMember()} ?? null;
        if (!is_string($identity) || $identity === '') {
            throw new InvalidArgumentException(sprintf(
                'A %s document must carry its "%s" identity.',
                $kind->value,
                $kind->identityMember(),
            ));
        }
        $this->identity = $identity;
    }

    /** Kind-scoped identity used by manifest lookups and host bindings. @since 0.2.0 */
    public function identifier(): string
    {
        return $this->kind->value . ' ' . $this->identity;
    }

    /** Identity value stored inside the canonical document. @since 0.2.0 */
    public function identity(): string
    {
        return $this->identity;
    }

    /**
     * Decode an isolated view of the signed canonical bytes.
     *
     * A fresh object graph prevents one consumer from mutating the value observed by another or
     * making decoded state disagree with `canonical` and `identity()`.
     *
     * @return stdClass Decoded canonical document with JSON objects preserved as objects.
     *
     * @throws LogicException When previously validated canonical bytes cannot be decoded.
     *
     * @since 0.2.0
     */
    public function document(): stdClass
    {
        try {
            $document = CanonicalJson::decode($this->canonical);
        } catch (JsonException $exception) {
            throw new LogicException('Validated canonical document bytes can no longer be decoded.', 0, $exception);
        }
        if (!$document instanceof stdClass) {
            throw new LogicException('Validated canonical document bytes no longer describe an object.');
        }

        return $document;
    }

    /**
     * @return array{kind: string, canonical: string} Exact manifest declaration.
     *
     * @since 0.2.0
     */
    public function toArray(): array
    {
        return ['kind' => $this->kind->value, 'canonical' => $this->canonical];
    }
}
