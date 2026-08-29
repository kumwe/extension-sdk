<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\Contribution;

use InvalidArgumentException;
use Kumwe\Extension\Spi\Identity\Domain\Capability;

/**
 * Capability-gated entry in an administrator workspace.
 *
 * @since 0.2.0
 */
final readonly class AdministratorNavigationDefinition implements ContributionDefinition
{
    /** Normalized capability required to display this item. @since 0.2.0 */
    public string $capability;

    /**
     * @param string  $id           Owner-scoped item identifier.
     * @param string  $workspace    Declared workspace identifier.
     * @param string  $label        Visible label, at most 80 characters.
     * @param string  $description  Accessible description, at most 255 characters.
     * @param string  $path         Safe absolute path relative to the extension mount.
     * @param string  $icon         Portable lowercase icon token.
     * @param string  $capability   Required declared capability.
     * @param int     $priority     Sort weight from 0 through 100000.
     * @param string  $keywords     Optional search text, at most 500 characters.
     * @param ?string $surface      Optional owner-scoped KIS surface identifier.
     *
     * @throws InvalidArgumentException When any value is malformed or unbounded.
     *
     * @since 0.2.0
     */
    public function __construct(
        public string $id,
        public string $workspace,
        public string $label,
        public string $description,
        public string $path,
        public string $icon,
        string $capability,
        public int $priority,
        public string $keywords = '',
        public ?string $surface = null,
    ) {
        AdministratorWorkspaceDefinition::assertIdentifier($id, 'navigation');
        AdministratorWorkspaceDefinition::assertIdentifier($workspace, 'workspace');
        if ($surface !== null) {
            AdministratorWorkspaceDefinition::assertIdentifier($surface, 'surface');
        }
        $this->capability = Capability::fromString($capability)->value();
        if (trim($label) === '' || mb_strlen($label) > 80) {
            throw new InvalidArgumentException('An administrator navigation label must contain 1 to 80 characters.');
        }
        if (trim($description) === '' || mb_strlen($description) > 255) {
            throw new InvalidArgumentException(
                'An administrator navigation description must contain 1 to 255 characters.',
            );
        }
        if (preg_match('#^/(?:[a-z0-9][a-z0-9-]*(?:/|$))*$#D', $path) !== 1 || str_contains($path, '..')) {
            throw new InvalidArgumentException('A contributed administrator navigation path is unsafe.');
        }
        if (preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $icon) !== 1) {
            throw new InvalidArgumentException('A contributed administrator navigation icon is invalid.');
        }
        if ($priority < 0 || $priority > 100_000 || mb_strlen($keywords) > 500) {
            throw new InvalidArgumentException('Administrator navigation ordering or keywords are invalid.');
        }
    }

    /** @return string Stable owner-scoped item identifier. @since 0.2.0 */
    public function identifier(): string
    {
        return $this->id;
    }

    /** @return array<string, int|string> Canonical declaration. @since 0.2.0 */
    public function toArray(): array
    {
        $document = [
            'id' => $this->id,
            'workspace' => $this->workspace,
            'label' => $this->label,
            'description' => $this->description,
            'path' => $this->path,
            'icon' => $this->icon,
            'capability' => $this->capability,
            'priority' => $this->priority,
            'keywords' => $this->keywords,
        ];
        if ($this->surface !== null) {
            $document['surface'] = $this->surface;
        }

        return $document;
    }
}
