<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessIntegration\Domain;

use InvalidArgumentException;

/** Immutable typed view of one validated manifest durable-consumer declaration. @since 0.2.0 */
final readonly class EventConsumerDeclaration
{
    /**
     * @param  string                $identifierValue  Validated durable-consumer identifier declared in the manifest.
     * @param  string                $eventTypeValue   Validated domain event type the consumer subscribes to.
     * @param  list<int>             $schemaVersions   Accepted event schema versions (positive, deduplicated).
     * @param  EventSensitivity      $ceiling          Highest event sensitivity the consumer may receive.
     * @param  array<string, mixed>  $data             Raw validated declaration payload kept for round-tripping.
     *
     * @since  0.2.0
     */
    private function __construct(
        private string $identifierValue,
        private string $eventTypeValue,
        private array $schemaVersions,
        private EventSensitivity $ceiling,
        private array $data,
    ) {
    }

    /** @param array<string, mixed> $data @since 0.2.0 */
    public static function fromManifest(array $data): self
    {
        $identifier = $data['consumer_id'] ?? null;
        $eventType = $data['event_type'] ?? null;
        $versions = $data['schema_versions'] ?? null;
        $sensitivity = $data['sensitivity_ceiling'] ?? null;
        if (
            !is_string($identifier)
            || !self::validIdentifier($identifier)
            || !is_string($eventType)
            || !self::validIdentifier($eventType)
            || !is_array($versions)
            || !array_is_list($versions)
            || $versions === []
            || count($versions) > 32
            || !is_string($sensitivity)
        ) {
            throw new InvalidArgumentException('An event-consumer declaration is invalid.');
        }
        $seen = [];
        foreach ($versions as $version) {
            if (!is_int($version) || $version < 1 || isset($seen[$version])) {
                throw new InvalidArgumentException('An event-consumer schema version is invalid.');
            }
            $seen[$version] = true;
        }
        $ceiling = EventSensitivity::tryFrom($sensitivity);
        if ($ceiling === null) {
            throw new InvalidArgumentException('An event-consumer sensitivity ceiling is invalid.');
        }

        return new self($identifier, $eventType, $versions, $ceiling, $data);
    }

    /** @since 0.2.0 */
    public function identifier(): string
    {
        return $this->identifierValue;
    }

    /** @since 0.2.0 */
    public function eventType(): string
    {
        return $this->eventTypeValue;
    }

    /** @return list<int> @since 0.2.0 */
    public function schemaVersions(): array
    {
        return $this->schemaVersions;
    }

    /** @since 0.2.0 */
    public function sensitivityCeiling(): EventSensitivity
    {
        return $this->ceiling;
    }

    /** @param EventEnvelope $event Delivered event checked against declared type, schema version, and sensitivity ceiling. @since 0.2.0 */
    public function accepts(EventEnvelope $event): bool
    {
        return $event->eventType() === $this->eventType()
            && in_array($event->schemaVersion(), $this->schemaVersions(), true)
            && $event->sensitivity()->allowedBy($this->sensitivityCeiling());
    }

    /** @return array<string, mixed> @since 0.2.0 */
    public function toArray(): array
    {
        return $this->data;
    }

    /** @param string $value Candidate identifier checked for emptiness, length, and control characters. @since 0.2.0 */
    private static function validIdentifier(string $value): bool
    {
        return $value !== ''
            && strlen($value) <= 191
            && preg_match('/[\x00-\x20\x7F]/D', $value) !== 1;
    }
}
