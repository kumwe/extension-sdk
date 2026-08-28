# The App agreement

This document records the contract between the extension SDK and Kumwe App, its source and its
first consumer. Every clause is written so a second consumer — another platform admitting Kumwe
extension packages — needs no new agreement. The [charter](../CHARTER.md) states what the SDK is;
this states what a consumer may rely on and what it owes.

## The pin protocol

1. The App pins this package **exactly** — no version ranges while the SDK is `0.x`, and an exact
   pin remains the recommendation after — and records the SDK version it qualified in its own
   release evidence.
2. A change reaches the App only as a **deliberate re-pin**: SDK release first, then one App
   change that bumps the pin, with its own review and its own evidence (the compatibility-fixture
   replay). No stage may be skipped; nothing floats.
3. The contract moves only by **succession**: a published generation is frozen forever, and new
   capability arrives as a new generation beside the old ones. The App never carries a local
   divergence of the contract — a need the pinned contract cannot express is raised as an issue
   in this repository and lands as an SDK release, never as an App-side patch, paraphrase, or
   widened generation.
4. The digests are shared: the generation `surface_digest` values this package verifies are the
   same values the App's `extension:contract` gate verifies. A byte of drift between the two is a
   release-blocking defect on whichever side moved.

## The alias contract

1. Every pinned `Kumwe\App\...` FQCN in the classification resolves, forever. The App carries a
   `class_alias` shim per pinned name, mapping it to the canonical `Kumwe\Extension\...` type.
   Because `class_alias` creates one class under two names, `instanceof`, type declarations,
   reflection, and serialized references behave identically under either name.
2. The shim file is **generated from the classification**, never hand-maintained, so a public
   type cannot be missed; a completeness check that every classified public type resolves under
   both names runs in the App's gate.
3. The aliases are extension API. Removing one, or changing what it points to, is a breaking
   change the contract forbids — the withdrawn list exists for surface that was never
   load-bearing, and an alias is load-bearing by definition.
4. New public types are born canonical: they appear under `Kumwe\Extension\...` only and receive
   no `Kumwe\App\...` alias. The alias set is closed at extraction and only ever shrinks by the
   ordinary generation-withdrawal rules, which for aliases means never.

## The shared-inspector invariant

The invariant, stated as the testable obligation it is:

> For any package bytes, the findings produced by the SDK's author-facing inspection entry point
> and the findings the App's admission path acts on are **identical** — same findings, same
> stable reasons, same order.

What makes it hold, and how it is proven:

1. **One implementation.** The App's admission constructs the SDK's findings implementation; the
   App carries no second implementation, no fork, and no "kept in sync" copy. The SDK carries no
   App-specific variant. Structural, not procedural.
2. **Findings are not edited.** The App's policy layer interprets findings — it decides what an
   admission finding means for this site, this trust store, this operator — but it never
   suppresses, rewrites, or reorders them before deciding. A finding an author's CI reported is a
   finding admission saw.
3. **The equality test.** The App's suite carries a test that replays every signed generation
   fixture and the hostile-archive corpus through both entry points — the SDK inspector as an
   author runs it, and the admission path as the App runs it — and asserts identical findings.
   That test is part of the App's standing gate from the phase that wires admission to the SDK
   (E-5 in [`roadmap.md`](roadmap.md)) onward, and removing or weakening it is a security defect.

## What the consumer keeps

The mirror of the charter's never-clauses, as the consumer's obligations to itself: admission
policy (whether an inspected package may enter), trust registries, trust state and revocation,
lifecycle activation, and capability gating are the App's to decide and enforce. The SDK hands
the App findings, digests, signatures, and reports; the App answers every "may this?" question
with its own authority and its own storage, and does not push those answers back down into this
package.
