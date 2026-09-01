<?php

declare(strict_types=1);

namespace Kumwe\Extension\Package;

use InvalidArgumentException;
use Stringable;

/**
 * Relative, traversal-free path naming one entry inside an extension package.
 *
 * `ZipArchiveReader` puts every archive entry name through `fromString` before it becomes an
 * `ArchiveEntry`, which is what lets the inspection and extraction code downstream treat a path as
 * plain data. A hostile archive therefore fails while its index is being read — before any byte is
 * written to disk — rather than at extraction time.
 *
 * @since  0.1.0
 */
final readonly class PackagePath implements Stringable
{
    /**
     * Hold a path whose segments have already been checked.
     *
     * @param  string  $value  Normalised relative path, `/`-separated and without a trailing slash.
     *
     * @since  0.1.0
     */
    private function __construct(private string $value)
    {
    }

    /**
     * Validate an archive entry name and normalise it into a safe relative path.
     *
     * Normalisation is deliberately minimal: only trailing slashes are dropped, so a directory entry
     * and a reference to that same directory settle on one value and nothing else is rewritten.
     * Everything unsafe is refused instead of sanitised. Package paths use a portable ASCII profile so
     * case folding and filesystem normalization have the same result on every supported host. Empty,
     * absolute, drive-qualified, control-bearing and over-long paths are refused, as are dot segments,
     * trailing dots, Windows device names and characters whose filesystem meaning varies by platform.
     *
     * @param   string  $path  Entry name exactly as the archive records it.
     *
     * @return  self  The normalised relative path.
     *
     * @throws  InvalidArgumentException  When the path is empty, absolute, over-long, or unsafe.
     *
     * @since   0.1.0
     */
    public static function fromString(string $path): self
    {
        if ($path === '' || strlen($path) > 512 || str_contains($path, "\0") || str_contains($path, '\\')) {
            throw new InvalidArgumentException('The package path is empty, too long, or contains an unsafe character.');
        }

        if ($path[0] === '/' || preg_match('/^[A-Za-z]:/D', $path) === 1) {
            throw new InvalidArgumentException('A package path must be relative.');
        }

        $segments = explode('/', rtrim($path, '/'));

        foreach ($segments as $segment) {
            if (
                $segment === ''
                || $segment === '.'
                || $segment === '..'
                || strlen($segment) > 191
                || preg_match('/^[A-Za-z0-9._@+\-]+$/D', $segment) !== 1
                || str_ends_with($segment, '.')
            ) {
                throw new InvalidArgumentException('A package path contains an unsafe segment.');
            }

            $stem = strtoupper(explode('.', $segment, 2)[0]);
            if (
                in_array($stem, ['CON', 'PRN', 'AUX', 'NUL'], true)
                || preg_match('/^(?:COM|LPT)[1-9]$/D', $stem) === 1
            ) {
                throw new InvalidArgumentException('A package path cannot use a reserved device name.');
            }
        }

        return new self(implode('/', $segments));
    }

    /**
     * Expose the path for joining against an extraction root or comparing against another entry.
     *
     * @return  string  Relative path, `/`-separated, with no trailing slash and no `.` or `..` segment.
     *
     * @since   0.1.0
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Render the path where it is interpolated into a message or a filesystem join.
     *
     * @return  string  The same value `value()` returns.
     *
     * @since   0.1.0
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
