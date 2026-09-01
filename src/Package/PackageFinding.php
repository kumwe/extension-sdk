<?php

declare(strict_types=1);

namespace Kumwe\Extension\Package;

use InvalidArgumentException;
use Stringable;

/**
 * One policy-neutral, machine-addressable fact found while inspecting a package.
 *
 * Codes identify facts rather than severities. A consuming host decides which codes it blocks, warns
 * about or ignores; the SDK supplies the same code and message to every consumer.
 *
 * @since  0.2.0
 */
final readonly class PackageFinding implements Stringable
{
    /**
     * Validate and retain one finding.
     *
     * @param   string   $code     Stable dotted lowercase finding code.
     * @param   string   $message  Bounded human-readable explanation.
     * @param   ?string  $path     Package path concerned, when one entry caused the finding.
     *
     * @throws  InvalidArgumentException  When the code, message or optional path is malformed.
     *
     * @since   0.2.0
     */
    public function __construct(
        public string $code,
        public string $message,
        public ?string $path = null,
    ) {
        if (preg_match('/^[a-z][a-z0-9]*(?:\.[a-z][a-z0-9_]*)+$/D', $code) !== 1) {
            throw new InvalidArgumentException('A package finding code must use dotted lowercase identifiers.');
        }
        if ($message === '' || strlen($message) > 4_096 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $message) === 1) {
            throw new InvalidArgumentException('A package finding message is empty, oversized or unsafe.');
        }
        if (
            $path !== null
            && ($path === '' || strlen($path) > 512 || preg_match('/[^\x20-\x7E]/', $path) === 1)
        ) {
            throw new InvalidArgumentException('A package finding path is empty, oversized or non-printable.');
        }
    }

    /**
     * Export the stable machine document.
     *
     * @return  array{code: string, message: string, path?: string}  Finding fields without policy metadata.
     *
     * @since   0.2.0
     */
    public function toArray(): array
    {
        $value = ['code' => $this->code, 'message' => $this->message];
        if ($this->path !== null) {
            $value['path'] = $this->path;
        }

        return $value;
    }

    /**
     * Render the stable human explanation for consoles and exception bridges.
     *
     * @return  string  The exact finding message.
     *
     * @since   0.2.0
     */
    public function __toString(): string
    {
        return $this->message;
    }
}
