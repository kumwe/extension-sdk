<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessIntegration\Domain;

use InvalidArgumentException;

/** Immutable typed view of one validated manifest webhook declaration. @since 0.2.0 */
final readonly class WebhookDeclaration
{
    /**
     * @param  list<string>          $eventTypes
     * @param  list<int>             $schemaVersions
     * @param  array<string, mixed>  $data
     *
     * @since  0.2.0
     */
    private function __construct(
        private string $identifierValue,
        private array $eventTypes,
        private array $schemaVersions,
        private EventSensitivity $ceiling,
        private array $data,
    ) {
    }

    /** @param array<string, mixed> $data @since 0.2.0 */
    public static function fromManifest(array $data): self
    {
        $identifier = $data['adapter_id'] ?? null;
        $types = $data['event_types'] ?? null;
        $versions = $data['schema_versions'] ?? null;
        $sensitivity = $data['sensitivity_ceiling'] ?? null;
        if (
            !is_string($identifier)
            || !self::validIdentifier($identifier)
            || !is_array($types)
            || !array_is_list($types)
            || $types === []
            || count($types) > 128
            || !is_array($versions)
            || !array_is_list($versions)
            || $versions === []
            || count($versions) > 32
            || !is_string($sensitivity)
        ) {
            throw new InvalidArgumentException('A webhook declaration is invalid.');
        }
        $seenTypes = [];
        foreach ($types as $type) {
            if (!is_string($type) || !self::validIdentifier($type) || isset($seenTypes[$type])) {
                throw new InvalidArgumentException('A webhook event type is invalid.');
            }
            $seenTypes[$type] = true;
        }
        $seenVersions = [];
        foreach ($versions as $version) {
            if (!is_int($version) || $version < 1 || isset($seenVersions[$version])) {
                throw new InvalidArgumentException('A webhook schema version is invalid.');
            }
            $seenVersions[$version] = true;
        }
        /** @var list<string> $types */
        /** @var list<int> $versions */
        $ceiling = EventSensitivity::tryFrom($sensitivity);
        if ($ceiling === null) {
            throw new InvalidArgumentException('A webhook sensitivity ceiling is invalid.');
        }

        return new self($identifier, $types, $versions, $ceiling, $data);
    }

    /** @since 0.2.0 */
    public function identifier(): string
    {
        return $this->identifierValue;
    }

    /** @return list<string> @since 0.2.0 */
    public function eventTypes(): array
    {
        return $this->eventTypes;
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

    /** @since 0.2.0 */
    public function accepts(IntegrationEvent $event): bool
    {
        return in_array($event->eventType(), $this->eventTypes(), true)
            && in_array($event->schemaVersion(), $this->schemaVersions(), true)
            && $event->sensitivity()->allowedBy($this->sensitivityCeiling());
    }

    /** @return array<string, mixed> @since 0.2.0 */
    public function toArray(): array
    {
        return $this->data;
    }

    /** @since 0.2.0 */
    private static function validIdentifier(string $value): bool
    {
        return $value !== ''
            && strlen($value) <= 191
            && preg_match('/[\x00-\x20\x7F]/D', $value) !== 1;
    }
}
