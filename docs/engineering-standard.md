# Engineering standard

This document says out loud what "good" means in this repository, so quality is a stated,
checkable expectation rather than a hope. It binds every contribution, human or agent. The
[charter](../CHARTER.md) says what the SDK is; this says how it is built. These agreements are
carried over from the Kumwe App programme deliberately, so quality does not diverge between the
App and the SDK it consumes.

## Architecture

The SDK is a layered library with one dependency direction and two lanes — the contract and the
toolchain. A layer may use the layers above it in this table and never the ones below:

| Layer | Namespace | Owns | Must never know about |
| --- | --- | --- | --- |
| Contract | `Kumwe\Extension\Contract` | Generation records, classification, digests | archives, signing, hosts |
| Manifest | `Kumwe\Extension\Manifest` | Manifest grammar, frozen generations, parsing | archives, signing, hosts |
| SPI | `Kumwe\Extension\Spi` | Interfaces an extension implements; value types | archives, signing, hosts |
| Package | `Kumwe\Extension\Package` | Archive reading, safety limits, shared findings | scaffolding, hosts |
| Toolchain | `Kumwe\Extension\Toolchain` | Scaffold, build, sign, inspect, conformance | trust state, activation, hosts |

Exact type placement inside these namespaces is settled by the roadmap phase that moves each
surface; the direction rule is law from day one. Rules that keep the lanes honest:

1. **The contract is the API.** Public shapes follow the frozen generation records and the pinned
   fixture bytes; nothing invents a manifest key, an SPI member, or a finding category the
   contract does not define. A frozen generation is changed by publishing a successor beside it,
   never by widening it in place.
2. **The toolchain reports; it never decides.** Every tool produces findings — a report, a digest,
   a signature document, a refusal with a stable reason. No tool answers an admission, trust, or
   activation question. Those are the consumer's, per the charter's never-clauses.
3. **One implementation of findings.** The per-file package checks live in exactly one place and
   both the author-facing runner and the consumer's admission path call it. Copying a check "for
   convenience" is a security defect, not a style issue.
4. **Determinism.** Same input, same SDK release: identical output bytes — built archives,
   canonical JSON, signature documents, reports. No clocks, no randomness, no locale-dependent
   formatting anywhere in the library. Anything nondeterministic belongs to the consumer, behind
   an interface. Where a stable identifier is needed, it is derived from its inputs (name-based,
   not random).
5. **Bounded before expensive.** Size, entry-count, and depth limits are enforced before parsing,
   hashing, or walking attacker-influenced archive contents, so a hostile package cannot amplify
   work. The archive reader never executes, autoloads, or includes anything from a package.
6. **All effects are injected.** No service locator, no global state, no static mutable anything.
   Dependencies arrive through constructors.

## Code

- The SDK targets PHP 8.5 (`"php": "^8.5"`, matching Kumwe App). Every file starts with `<?php`,
  a blank line, the file-level documentation block where one applies, and
  `declare(strict_types=1);`.
- `final` classes by default, `readonly` when the instance carries no mutable state; constructor
  property promotion for wiring; native parameter, return, and property types on everything. A
  missing native type is a defect, not a style preference.
- One class-like declaration per file, named after the file, autoloaded PSR-4 from
  `Kumwe\Extension\`.
- Layout follows PSR-12: four-space indentation, LF endings, one trailing newline, no trailing
  whitespace. Code lines, documentation blocks included, stay at or below 120 characters.
- **No runtime Composer dependencies, ever.** The SDK runs on PHP alone; PHP extensions such as
  `ext-json`, `ext-zip`, and `ext-sodium` are permitted and are declared in `composer.json` when
  the code that needs them arrives. An extension author and the App adopt a contract
  implementation, not a dependency tree.
- Escaping, hashing, and canonicalization are centralized: exactly one path produces canonical
  JSON, exactly one path computes a package digest, exactly one implementation produces findings.

## Documentation blocks

Every class-like declaration, method, function, non-promoted property, class constant, and enum
case carries a documentation block, in the aligned, tag-ordered house format shared with Kumwe App
(its coding standard, section 3). The gate is `php tools/check-docblocks.php`, inside
`composer check` and CI; an undocumented member fails the build.

### Canonical shape

```php
/**
 * Summary sentence that says what the member does, ending with a period.
 *
 * Optional paragraph that says when to reach for it: the problem it solves, the guarantee it
 * makes, and which collaborator owns the parts it does not.
 *
 * @param   string        $reference  What the caller identifies with this value.
 * @param   list<string>  $entries    Which archive entries the check restricts itself to.
 *
 * @return  PackageInspection  The findings, never a decision.
 *
 * @throws  NonConformingPackage  When the archive violates a bounded safety limit.
 *
 * @since   0.1.0
 */
```

### Rules

1. **Tag order**: `@template`/`@extends`/`@implements`, then `@param`, `@return`, `@throws`, then
   the trailing group (`@var`, `@deprecated`, `@see`, `@link`, `@internal`, `@since`), each group
   separated by a `*`-only line. `@since` is always last, and always present.
2. **Alignment**: within one block, every tag value starts two spaces after the longest tag name
   in the block; inside a `@param` group the type column and the variable column are each padded
   to the widest entry. Tags longer than eight characters take a single space and do not join the
   alignment calculation.
3. **`@since` records introduction**, carrying the release the member first appears in, and is
   never rewritten when a member is edited.
4. **Types are precise and never widen.** `list<string>`, `array<string, mixed>`,
   `array{name: string, schema: int}` — a bare `array` in a block is a defect, and an existing
   narrow type is never widened or dropped.
5. **Prose earns its lines.** A block states the member's contract — what it accepts, what it
   guarantees, what it throws and when — not a restatement of its name. "Gets the name." is
   noise; write what the caller needs.
6. **Enum cases carry no `@var`**; a case block is its description and `@since`. Interfaces
   document the guarantee every implementation owes; each implementation carries its own full
   block. `{@inheritDoc}` is never the whole block.
7. **File-level blocks** on scripts that are not class files (`tools/`, `tests/run.php`) describe
   the file's job, its invocation, and `@since`.

## Errors

- Throw domain-named exceptions; refusals carry stable, typed reasons a caller can act on.
  Free-text matching on messages is forbidden; message strings are for humans, are complete
  sentences addressed to an operator, and never contain secrets, key material, or raw archive
  contents.
- Catch narrowly. A bare `catch (Throwable $e)` needs a comment explaining the boundary it
  guards.

## Testing

The suite exists to prove intended outcomes, and only that. The standard for every test:

1. **A test asserts an observable contract, not an implementation detail.** It proves what the
   class promises — the archive bytes, the digest, the finding, the refusal reason, the report —
   never that a private method was called or an internal array has a shape.
2. **The compatibility fixtures are the spine.** Anything the signed generation fixtures can
   prove is proven by replaying them, because those assertions are shared with the App's own
   gate. Local tests cover what the fixtures cannot: hostile archives, boundary values, refusal
   paths, and determinism (two runs, identical bytes).
3. **Negative paths are first-class.** Every typed refusal a class can produce has a test that
   provokes it and asserts its stable reason. A class whose failure modes are untested is
   untested. Security-sensitive surfaces — archive handling, signing, verification, manifest
   parsing — carry adversarial tests alongside the happy path.
4. **No frivolous tests.** A test that cannot fail for a reason a user would care about — a
   getter returning what the constructor took, mock expectations restating the code, asserting a
   class exists — must not be written. Coverage is a consequence of testing outcomes, never a
   goal pursued for its own number.
5. **Dependency-free and deterministic.** Tests run with `php tests/run.php` on a clean clone
   with no composer install, touch no network and no clock, and pass in any order.

## Documentation

- Every document states behaviour that exists; plans live in [`docs/roadmap.md`](roadmap.md) and
  nowhere else. A claim the check lane cannot back is a defect in the document.
- Wrap prose at roughly 100 columns; sentence-case headings; link with relative paths; write for
  the reader who arrives with no context, because the next implementer may be an agent with
  nothing but this repository.

## The check lane

`composer check` is the whole standard, executable: `lint` (syntax over every PHP file), `docs`
(member documentation completeness), `test` (the dependency-free suite). Contract digest
verification joins the lane in the roadmap phase that vendors the artifacts. Every commit passes
the lane; a release re-proves it on the tagged commit. There is no path to publication that skips
it.
