<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessReporting\Application;

/** Owner-bound writer for deterministic derived rows. @since 0.2.0 */
interface ProjectionWriter
{
    /**
     * @param  array<string, bool|int|string>       $key
     * @param  array<string, bool|int|string|null>  $values
     *
     * @since  0.2.0
     */
    public function put(array $key, array $values): void;

    /** @param array<string, bool|int|string> $key @since 0.2.0 */
    public function remove(array $key): void;
}
