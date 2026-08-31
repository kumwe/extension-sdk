<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\Application\Automation;

use InvalidArgumentException;

/** Immutable typed view of one validated manifest job declaration. @since 0.2.0 */
final readonly class JobDeclaration
{
    /** @param array<string, mixed> $data @since 0.2.0 */
    private function __construct(private string $typeValue, private int $version, private array $data)
    {
    }

    /** @param array<string, mixed> $data @since 0.2.0 */
    public static function fromManifest(array $data): self
    {
        $type = $data['job_type'] ?? null;
        $version = $data['schema_version'] ?? null;
        if (
            !is_string($type)
            || $type === ''
            || strlen($type) > 191
            || preg_match('/[\x00-\x20\x7F]/D', $type) === 1
            || !is_int($version)
            || $version < 1
        ) {
            throw new InvalidArgumentException('A job declaration is invalid.');
        }

        return new self($type, $version, $data);
    }

    /** @since 0.2.0 */
    public function type(): string
    {
        return $this->typeValue;
    }

    /** @since 0.2.0 */
    public function schemaVersion(): int
    {
        return $this->version;
    }

    /** @return array<string, mixed> @since 0.2.0 */
    public function toArray(): array
    {
        return $this->data;
    }
}
