<?php

declare(strict_types=1);

namespace @@PHP_NAMESPACE@@;

/**
 * Minimal extension-owned service proving registration and boot lifecycle.
 *
 * @since  1.0.0
 */
final class Greeting
{
    /**
     * Whether the host completed the extension boot phase.
     *
     * @var    bool
     * @since  1.0.0
     */
    private bool $booted = false;

    /**
     * Mark the extension service ready after composition is complete.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function boot(): void
    {
        $this->booted = true;
    }

    /**
     * Report whether the boot lifecycle completed.
     *
     * @return  bool  True after `boot()`.
     *
     * @since   1.0.0
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }
}
