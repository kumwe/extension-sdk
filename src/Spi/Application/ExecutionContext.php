<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\Application;

/**
 * Host-issued identity and trace context handed to extension code.
 *
 * This interface carries no grant, capability or authorization decision. A host constructs the concrete
 * context only after authenticating the invocation and must not accept an extension-created implementation
 * back across an authorization or record-disclosure boundary.
 *
 * @since  0.2.0
 */
interface ExecutionContext
{
    /**
     * Return the site under which the extension callback executes.
     *
     * @return  string  Stable site identifier.
     *
     * @since   0.2.0
     */
    public function siteIdentifier(): string;

    /** @return string Stable human or system actor identifier. @since 0.2.0 */
    public function actorId(): string;

    /** @return ?string Active organization identifier, or null outside an organization scope. @since 0.2.0 */
    public function organizationIdentifier(): ?string;

    /** @return ?string Active workspace identifier, or null outside a workspace scope. @since 0.2.0 */
    public function workspaceIdentifier(): ?string;

    /** @return string Identifier of this unit of work. @since 0.2.0 */
    public function requestId(): string;

    /** @return string Identifier shared across the active trace. @since 0.2.0 */
    public function correlationId(): string;

    /**
     * @return  string  Delivery surface such as administrator, portal, api, mcp, cli or background.
     *
     * @since   0.2.0
     */
    public function deliverySurface(): string;
}
