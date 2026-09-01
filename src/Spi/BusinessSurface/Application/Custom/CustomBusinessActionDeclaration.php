<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessSurface\Application\Custom;

use InvalidArgumentException;

/** Immutable signed declaration for one custom business action handler. @since 0.2.0 */
final readonly class CustomBusinessActionDeclaration
{
    /**
     * @param  string                $handler        Manifest-validated reference naming the action handler routine.
     * @param  string                $schema         Manifest-validated reference naming the schema backing the action.
     * @param  CustomBusinessSchema  $commandSchema  Closed object contract validating the inbound command payload.
     * @param  CustomBusinessSchema  $resultSchema   Closed object contract validating the produced result payload.
     *
     * @since  0.2.0
     */
    private function __construct(
        public string $handler,
        public string $schema,
        public CustomBusinessSchema $commandSchema,
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
        self::keys($document, ['handler', 'schema', 'command_schema', 'result_schema']);
        $handler = self::string($document, 'handler');
        $schema = self::string($document, 'schema');
        CustomBusinessReference::assert($handler, 'action handler');
        CustomBusinessReference::assert($schema, 'action schema');
        if ($handler === $schema) {
            throw new InvalidArgumentException('A custom business action handler and schema need distinct references.');
        }

        return new self(
            $handler,
            $schema,
            CustomBusinessSchema::fromArray($document['command_schema'] ?? null),
            CustomBusinessSchema::fromArray($document['result_schema'] ?? null),
        );
    }

    /** @return array<string, mixed> @since 0.2.0 */
    public function toArray(): array
    {
        return [
            'handler' => $this->handler,
            'schema' => $this->schema,
            'command_schema' => $this->commandSchema->toArray(),
            'result_schema' => $this->resultSchema->toArray(),
        ];
    }

    /**
     * @param  array<string, mixed>  $document  Validated manifest declaration under inspection.
     * @param  list<string>          $expected  Exact member keys the declaration must carry.
     *
     * @since  0.2.0
     */
    private static function keys(array $document, array $expected): void
    {
        if (array_diff($expected, array_keys($document)) !== [] || array_diff(array_keys($document), $expected) !== []) {
            throw new InvalidArgumentException('A custom business action declaration must carry exactly its members.');
        }
    }

    /**
     * @param  array<string, mixed>  $document  Validated manifest declaration.
     * @param  string                $key       Declaration member expected to hold a canonical string.
     *
     * @since  0.2.0
     */
    private static function string(array $document, string $key): string
    {
        $value = $document[$key] ?? null;
        if (!is_string($value) || trim($value) === '' || $value !== trim($value)) {
            throw new InvalidArgumentException('A custom business action declaration requires canonical ' . $key . '.');
        }

        return $value;
    }
}
