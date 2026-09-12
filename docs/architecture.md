# SDK architecture and construction

The SDK owns strict manifest parsing, neutral package evidence, executable binding ports and
extension authoring tools. A signed manifest is the declaration authority. Binding implementations
can satisfy validated identifiers; they cannot declare new routes, jobs, policies or composition
relationships. The consuming host retains admission, authorization, activation, persistence and
execution policy. Canonical domain values retain their package namespaces and implementation owners.

`resources/contract/classification.json` identifies the 96 public SDK types and their source digests.
`resources/contract/generations.json` identifies the six manifest and four binding-SPI generations.
The standard `resources/public-api/v1.json` projects those exact types into the governed signature
schema. The accompanying signature details retain defaults and constant values; the API reference
includes source PHPDoc and every declared public member. CI rejects drift among these records.
Two PHPUnit bridge classes are optional development integrations; 94 public types are loadable
without PHPUnit. Internal `Support` helpers are implementation details.

## Explicit construction

The package has no ConfigProvider, factories registered in a container, aliases or configuration
defaults. Its reusable tools are constructed explicitly for an authoring operation. A provider
could not safely infer the expected native compatibility tuple, protected signing authority,
admission policy or tenant/request scope. The caller may supply host-owned factories without
changing SDK ownership. No empty provider or implicit service discovery is added.

Start with [the direct construction example](../examples/direct-construction.php), which accepts
the real `Kumwe\CanonicalJson\CanonicalEncoder` contract. It composes bounded inspection,
scaffolding, building and static conformance without choosing a concrete encoder or signing key.
`bin/kumwe-extension` is a separate per-invocation composition root: it requires the documented
Computation adapter and an explicitly supplied, verified native compatibility tuple. Failure to
load that tuple or the extension is a refusal; there is no PHP algorithm fallback.

Tools hold collaborator references and limits. The caller controls their lifetimes and must not
share mutable host collaborators across requests or processes without an independent guarantee.
Readonly wrapper properties do not certify collaborator concurrency. The SDK captures no active
transaction, actor authority or host container and starts no ambient background work.

## I/O and effect boundaries

Manifest/value operations validate bounded inputs and return immutable values or documented
refusals. Archive readers and inspectors read caller-selected files and enforce archive limits;
their findings remain facts for host policy. The scaffolder writes the explicitly selected output
directory. The deterministic builder reads source entries and writes the selected archive.
The signing-key reader checks and locks an explicitly selected protected local file, and signing
writes the selected signature document. These operations can throw the declared filesystem,
format and cryptographic refusals; they do not install or activate an extension.

Database migrations, HTTP rendering, record access and lifecycle contracts are extension/host
ports. Their callers own transaction boundaries, retries, authorization and resource scopes.
The SDK does not confer these powers through construction, a valid signature, an evidence report
or a successful authoring conformance test.

## Qualification and adoption

SDK CI uses exact candidate checkouts to exercise the native developer toolchain. A separate lane
installs the SDK archive against published stable dependencies without native runtime. Generated
projects run their own Composer install and PHPUnit suite, then reinstall production dependencies
with an authoritative classmap and load the actual generated delivery classes.

Independent release verification is a later observation over actual stable source, registry and
archive identities. The combined package-set consumer selects only independently checked archives,
installs the complete graph into a fresh project, audits it, deletes its vendor tree and repeats the
same lock with Composer network access disabled and a private warmed cache. It does not use SDK
vendor packages to qualify another artifact. Both final graphs require all 31 PHP packages: the 28 catalog owners, Conversion, Producer and SDK.
The portable mode refuses a loaded extension or any runtime extension requirement. Native mode
requires the qualified stable Computation successor, immutable native attestations and complete
expected runtime tuple, then performs a real installed-adapter call after its offline replay.
See [release qualification](release-qualification.md) for the distinct input and evidence boundaries.
Core owns namespace adoption and service composition; SDK checks do not establish host integration.
