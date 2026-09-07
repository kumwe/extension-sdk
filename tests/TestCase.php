<?php

/**
 * Minimal assertion base for the dependency-free suite.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests;

abstract class TestCase
{
    /**
     * Native integration fixture; no PHP encoder is substituted when the native lane is unavailable.
     *
     * @return \Kumwe\CanonicalJson\CanonicalEncoder Explicitly configured native encoder.
     * @since 0.3.0
     */
    final protected static function encoder(): \Kumwe\CanonicalJson\CanonicalEncoder
    {
        $path = getenv('KUMWE_NATIVE_EXPECTED_TUPLE');
        if (!is_string($path) || !is_file($path) || !extension_loaded('kumwe_engine')) {
            throw new \RuntimeException('SDK integration tests require native Engine and KUMWE_NATIVE_EXPECTED_TUPLE.');
        }
        $tuple = json_decode((string) file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
        return new \Kumwe\Computation\NativeCanonicalEncoder(new \Kumwe\Engine\Runtime(), new \Kumwe\Computation\NativeCompatibility(
            \Kumwe\Computation\CapabilitySet::fromArray($tuple['capabilities']),
            $tuple['extension_version'],
            $tuple['embedded_engine_commit'],
            $tuple['embedded_source_sha256'],
            $tuple['binding_build_digest'],
        ));
    }

    private int $assertions = 0;

    final public function assertionCount(): int
    {
        return $this->assertions;
    }

    final protected function assertTrue(bool $condition, string $message): void
    {
        $this->assertions++;
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    final protected function assertSame(mixed $expected, mixed $actual, string $message): void
    {
        $this->assertions++;
        if ($expected !== $actual) {
            throw new \RuntimeException(
                $message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . '.'
            );
        }
    }

    final protected function assertStringContains(string $needle, string $haystack, string $message): void
    {
        $this->assertions++;
        if (!str_contains($haystack, $needle)) {
            throw new \RuntimeException($message . ' Missing: ' . $needle);
        }
    }

    final protected function assertStringExcludes(string $needle, string $haystack, string $message): void
    {
        $this->assertions++;
        if (str_contains($haystack, $needle)) {
            throw new \RuntimeException($message . ' Forbidden substring present: ' . $needle);
        }
    }

    final protected function assertThrows(callable $operation, string $exceptionClass, string $message): \Throwable
    {
        $this->assertions++;
        try {
            $operation();
        } catch (\Throwable $error) {
            if (!($error instanceof $exceptionClass)) {
                throw new \RuntimeException(
                    $message . ' Threw ' . get_class($error) . ' instead of ' . $exceptionClass . ': '
                        . $error->getMessage()
                );
            }
            return $error;
        }
        throw new \RuntimeException($message . ' Nothing was thrown; expected ' . $exceptionClass . '.');
    }
}
