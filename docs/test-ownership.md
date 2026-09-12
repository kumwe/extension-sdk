# Package test ownership

The package's `tests/ownership.json` maps every published type to the actual package runner's discovered behavior and boundary tests, and records the package-owned conformance corpus. The quality gate validates the complete API inventory, test names and evidence paths. Nine negative fixtures prove that stale, missing or unowned evidence fails. New exports cannot land without an ownership entry. The runner refuses empty suites and empty cases in both execution and discovery modes.

Evidence references identify responsibility; they are not a claim of 100% line, branch or input coverage. Ports with no runtime implementation own their signatures and vocabulary here; concrete host implementations retain their execution tests. Package tests use neutral fixtures and adapters, and never bootstrap Kumwe App.

Core and other hosts retain composition, trust, authorization, persistence, delivery, lifecycle
and recovery tests. A dependency update does not justify deleting those acceptance tests.

The SDK owns neutral signed-package parsing and inspection, executable bindings, manifest
conformance, generation compatibility and scaffolding. Values, grammar and algorithms owned
by canonical capability packages are tested in those packages. The retained symbol ownership
maps support consumer integration without creating namespace aliases or duplicate runtime code.
