<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\Http;

use Kumwe\Extension\Spi\Application\ExecutionContext;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * Reads canonical extension context attributes from a PSR-7 request.
 *
 * @since  0.2.0
 */
final class ExtensionRequest
{
    /** @var string @since 0.2.0 */
    public const string CONTEXT = 'kumwe.extension.execution_context';

    /** @var string @since 0.2.0 */
    public const string CSRF_TOKEN = 'kumwe.extension.csrf_token';

    /**
     * Read the host-supplied execution context.
     *
     * @param   ServerRequestInterface  $request  Incoming PSR-7 request whose attributes the host populated.
     *
     * @return  ExecutionContext  Canonical extension context.
     *
     * @since   0.2.0
     */
    public static function context(ServerRequestInterface $request): ExecutionContext
    {
        $context = $request->getAttribute(self::CONTEXT);
        if (!$context instanceof ExecutionContext) {
            throw new RuntimeException('The request carries no canonical extension execution context.');
        }

        return $context;
    }

    /**
     * Read the host-supplied CSRF token, when the surface uses one.
     *
     * @param   ServerRequestInterface  $request  Incoming PSR-7 request that may carry the host-issued token attribute.
     *
     * @return  ?string  CSRF token or null.
     *
     * @since   0.2.0
     */
    public static function csrfToken(ServerRequestInterface $request): ?string
    {
        $token = $request->getAttribute(self::CSRF_TOKEN);

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * Prevent instantiation of this attribute reader.
     *
     * @since  0.2.0
     */
    private function __construct()
    {
    }
}
