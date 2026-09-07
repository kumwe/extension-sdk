<?php

/** Portable SDK request construction and host attribute extraction. @since 0.2.5 */
declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use InvalidArgumentException;
use Kumwe\Extension\Spi\Application\ExecutionContext;
use Kumwe\Extension\Spi\BusinessRecord\Application\BusinessRecordReadRequest;
use Kumwe\Extension\Spi\BusinessRecord\Application\BusinessRecordQueryPurpose;
use Kumwe\Extension\Spi\BusinessRecord\Query\RecordQuerySpecification;
use Kumwe\Extension\Spi\Http\ExtensionRequest;
use Kumwe\Extension\Tests\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final class RequestBoundaryTest extends TestCase
{
    public function testReadRequestPreservesHostContextAndRefusesMalformedScopes(): void
    {
        $context = $this->context();
        $query = new RecordQuerySpecification();
        foreach (BusinessRecordQueryPurpose::cases() as $purpose) {
            $request = new BusinessRecordReadRequest($context, 'acme.invoice', $query, 'org-1', $purpose);
            $this->assertSame($context, $request->context, 'The exact host-issued context is retained.');
            $this->assertSame($query, $request->specification, 'The validated query is retained.');
            $this->assertSame($purpose, $request->purpose, 'Each disclosure purpose is explicit.');
        }
        foreach (['', 'Invoice', 'invoice', "acme.invoice\n"] as $definition) {
            $this->assertThrows(static fn () => new BusinessRecordReadRequest($context, $definition, $query),
                InvalidArgumentException::class, 'Definition identity uses exact canonical grammar.');
        }
        foreach (['', ' org', "org\n", str_repeat('a', 192)] as $organization) {
            $this->assertThrows(static fn () => new BusinessRecordReadRequest($context, 'acme.invoice', $query,
                $organization), InvalidArgumentException::class, 'Organization identity is bounded and exact.');
        }
    }

    public function testHttpHelpersRequireCanonicalContextWithoutInventingCsrfTokens(): void
    {
        $factory = new class ('fixture') extends \PHPUnit\Framework\TestCase {
            public function request(array $attributes): ServerRequestInterface
            {
                $request = $this->createStub(ServerRequestInterface::class);
                $request->method('getAttribute')->willReturnCallback(
                    static fn (string $key, mixed $default = null): mixed => $attributes[$key] ?? $default);
                return $request;
            }
        };
        $context = $this->context();
        $request = $factory->request([ExtensionRequest::CONTEXT => $context, ExtensionRequest::CSRF_TOKEN => 'token']);
        $this->assertSame($context, ExtensionRequest::context($request), 'Helper returns only canonical context.');
        $this->assertSame('token', ExtensionRequest::csrfToken($request), 'An existing token is returned unchanged.');
        foreach ([null, '', false, 0, new \stdClass()] as $value) {
            $request = $factory->request([ExtensionRequest::CONTEXT => $value, ExtensionRequest::CSRF_TOKEN => $value]);
            $this->assertThrows(static fn () => ExtensionRequest::context($request), RuntimeException::class,
                'Missing or foreign context refuses.');
            $this->assertSame(null, ExtensionRequest::csrfToken($request), 'Malformed token is absent.');
        }
    }

    private function context(): ExecutionContext
    {
        return new class implements ExecutionContext {
            public function siteIdentifier(): string { return 'site'; }
            public function actorId(): string { return 'actor'; }
            public function organizationIdentifier(): ?string { return 'org-1'; }
            public function workspaceIdentifier(): ?string { return null; }
            public function requestId(): string { return 'request'; }
            public function correlationId(): string { return 'trace'; }
            public function deliverySurface(): string { return 'api'; }
        };
    }
}
