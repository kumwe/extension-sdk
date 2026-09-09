# Kumwe Extension SDK 0.3 contract

This directory records the canonical author-facing contract owned by `kumwe/extension-sdk`. It is
generated and verified from this repository. No host repository, copied namespace, compatibility alias,
or remapping table is an authority for this API.

## One declaration authority

`kumwe.json` is the sole declaration source for an extension package. A strict manifest declares every
capability, route, presentation surface, business contribution, integration callback, and Studio
document. PHP code cannot add a second route, event, job, report, or composition declaration.

The SDK parses the manifest once into `Kumwe\Extension\Manifest\ExtensionManifest` and its canonical
`ManifestContributions` graph. The graph owns validated, immutable definition objects and exact
identifier lookups. Its exported declaration bytes are deterministic and parse-idempotent.

## Executable binding

An extension provider implements `Kumwe\Extension\Spi\Binding\ExtensionBindingProvider`. Registration
adds private services to the restricted extension container. Binding supplies executable implementations
for identifiers already present in the signed manifest.

`ManifestContributions::executableBindingRequirements()` is the exact executable inventory. A host must
refuse an undeclared identifier, a wrong-kind binding, a duplicate, an omitted required binding, or an
extra binding. Route renderers are per-route object capabilities under
`Kumwe\Extension\Spi\Binding\Http`; they close over the validated owner and view/template and expose no
selector that extension code can redirect.

Declarative contributions are activated from the parsed manifest, never reconstructed from provider
callbacks. Optional lifecycle boot behavior may run only after admission and exact binding
reconciliation; boot does not declare routes or events.

## Host authority

The SDK validates portable structure, ownership, bounds, cross-references, executable completeness,
archive evidence, and canonical protocol documents. The host remains solely responsible for admission
policy, trust decisions, authorization, lifecycle state, storage, transaction ownership, request/session
provenance, organization scope, capability enforcement, and operator-facing refusal decisions.

Extension code receives narrow SDK ports and already admitted values. `ExecutionContext` exposes stable
identity and correlation facts, not grants or authorization decisions. A host-issued context must be
verified at the boundary before any record read or executable callback.

## Manifest generations

- Schema 1 is an inert package-description generation. It has no typed contribution graph and cannot
  mount executable routes or subscriptions through historical root arrays.
- Schemas 2 and 3 use contribution SPI 1 for shell, portal, field, and business declarations.
- Schema 4 uses contribution SPI 2 and adds durable event, automation, conversion, projection, report,
  webhook, interface, and multilingual-content declarations.
- Schema 5 uses contribution SPI 3 and carries the frozen typed composition declaration vocabulary.
- Schema 6 uses contribution SPI 4 and replaces the schema-5 composition vocabulary with exact canonical
  Studio documents plus separate bounded host bindings.

Every retained fixture under `resources/fixtures/generations/` is an SDK-native package. Its provider,
tests, and templates import canonical dependency namespaces only. `generations.json` records the exact
fixture digest, contribution inventory, and executable binding set for each generation.

## Canonical Studio documents

Schema 6 carries exact canonical document bytes. `CanonicalCompositionDocument` preserves kind, decoded
value, identity, and the signed canonical bytes. `kumwe/producer` supplies the pinned Studio schema corpus
and validates each document against its exact kind; block property schemas also pass the Producer schema
property profile. Host bindings reference an admitted document by kind and identity. Every block
definition requires a non-empty owner-scoped preview renderer binding; non-rendered document kinds do
not create executable requirements.

## Package inspection

Archive inspection produces stable typed findings over one immutable inspected snapshot. Findings are
policy-neutral evidence; expected hostile package data does not become an SDK install decision. A host
maps the report to its own admission outcome.

The same snapshot binds entry metadata, actual content reads, manifest bytes, checksum, SBOM,
provenance, and signature evidence. Signatures use the versioned SDK package-message domain. Builder and
inspector share one limit object, including source files plus generated attestations.

## Published records

- `classification.json` is the SDK-owned public API profile. It lists every canonical public type and
  pins its source digest. `Kumwe\Extension\Support` remains internal.
- `generations.json` is the canonical manifest/SPI generation profile and fixture inventory.
- `resources/PIN.json` pins every shipped resource byte without naming another repository as authority.

Run `php tools/record-contract.php` only for a deliberate API/resource recording change. Run
`php tools/verify-contract.php` (or `composer contract`) to prove exact type membership, source digests,
generation digests, fixture manifests, executable inventories, resource pins, and namespace purity.
