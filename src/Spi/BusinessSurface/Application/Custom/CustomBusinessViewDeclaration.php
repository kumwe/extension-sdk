<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessSurface\Application\Custom;

use InvalidArgumentException;

/** Immutable signed declaration for one custom business view handler. @since 0.2.0 */
final readonly class CustomBusinessViewDeclaration
{
    /**
     * @param  string                $handler       Manifest-validated reference to the executable serving the view.
     * @param  string                $schema        Manifest-validated reference to the view schema definition.
     * @param  CustomBusinessSchema  $querySchema   Schema constraining the query the view accepts.
     * @param  CustomBusinessSchema  $resultSchema  Schema constraining the result the view produces.
     *
     * @since  0.2.0
     */
    private function __construct(
        public string $handler,
        public string $schema,
        public CustomBusinessSchema $querySchema,
        public CustomBusinessSchema $resultSchema,
    ) {
    }

    /**
     * @param  array<string, mixed>  $document  Validated manifest declaration.
     *
     * @since  0.2.0
     */
    public static function fromManifest(array $document): self
    {
        self::keys($document, ['handler', 'schema', 'query_schema', 'result_schema']);
        $handler = self::string($document, 'handler');
        $schema = self::string($document, 'schema');
        CustomBusinessReference::assert($handler, 'view handler');
        CustomBusinessReference::assert($schema, 'view schema');
        if ($handler === $schema) {
            throw new InvalidArgumentException('A custom business view handler and schema need distinct references.');
        }

        return new self(
            $handler,
            $schema,
            CustomBusinessSchema::fromArray($document['query_schema'] ?? null),
            CustomBusinessSchema::fromArray($document['result_schema'] ?? null),
        );
    }

    /** @return array<string, mixed> @since 0.2.0 */
    public function toArray(): array
    {
        return [
            'handler' => $this->handler,
            'schema' => $this->schema,
            'query_schema' => $this->querySchema->toArray(),
            'result_schema' => $this->resultSchema->toArray(),
        ];
    }

    /**
     * @param  array<string, mixed>  $document  Validated manifest declaration under inspection.
     * @param  list<string>          $expected  Exact member names the declaration must carry.
     *
     * @since  0.2.0
     */
    private static function keys(array $document, array $expected): void
    {
        if (array_diff($expected, array_keys($document)) !== [] || array_diff(array_keys($document), $expected) !== []) {
            throw new InvalidArgumentException('A custom business view declaration must carry exactly its members.');
        }
    }

    /**
     * @param  array<string, mixed>  $document  Validated manifest declaration under inspection.
     * @param  string                $key       Member name whose canonical string value is required.
     *
     * @since  0.2.0
     */
    private static function string(array $document, string $key): string
    {
        $value = $document[$key] ?? null;
        if (!is_string($value) || trim($value) === '' || $value !== trim($value)) {
            throw new InvalidArgumentException('A custom business view declaration requires canonical ' . $key . '.');
        }

        return $value;
    }
}
