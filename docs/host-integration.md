# Host integration

A host installs `kumwe/extension-sdk` and consumes canonical SDK objects directly.

## Manifest and binding

1. Parse the signed package manifest once through `ExtensionManifest` and `ManifestContributions`.
2. Apply host-owned semantic and admission rules to that validated canonical graph.
3. Create the restricted extension container and an `ExtensionBindingRegistrar` bound to the package owner.
4. Invoke the package's `ExtensionBindingProvider::bind()` exactly once.
5. Reconcile recorded bindings with `ManifestContributions::executableBindingRequirements()`.
6. Reject undeclared, foreign, wrong-kind, duplicate or missing required bindings.
7. Activate declarative contributions and executable bindings only after admission succeeds.

The host must not reconstruct SDK declaration DTOs from raw arrays. Use typed contribution getters and
lookups where the SDK publishes a type; interpret `declarations()` only for host-semantic surfaces that do
not have an SDK DTO.

## Policy boundary

Package inspection returns neutral findings and evidence. The host decides whether those facts permit
installation, activation or execution. It may enrich its decision record, but it must not rewrite the SDK
report or maintain a parallel scanner.

Execution contexts are host-issued provenance capabilities. Extensions may read neutral request and actor
identity fields, but they never receive raw grants, principals, sessions or an authorization gateway.
Authorization and resource-policy enforcement happen before invoking extension code and again at the
host-owned data/service boundary.

## Declarative authority

Routes, events, jobs, projections, policies, Studio documents and other contributions come only from the
validated manifest. Executable provider code supplies implementations for declared IDs; it cannot create a
second declarative relationship. Per-route renderer capabilities close over the validated owner and view or
template, so extension code cannot select another route surface at render time.
