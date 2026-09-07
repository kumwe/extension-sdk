# Releasing the extension SDK

The SDK follows semantic versioning. A release is recorded by the newest `CHANGELOG.md` heading in the form
`## [X.Y.Z] - YYYY-MM-DD`.

1. Land the reviewed change on `main` with its release record.
2. The release workflow installs Composer dependencies and runs the same `composer check` gate as pull
   requests, then proves a production-only install with `composer smoke`.
3. The release job creates `vX.Y.Z` only when absent and verifies that the tag resolves to the exact checked
   commit. A mismatched existing tag fails the workflow.
4. The workflow publishes the GitHub release with job-scoped `contents: write`; all check jobs remain
   read-only. Packagist follows the verified tag.

Patch releases preserve public behavior. Minor releases add backward-compatible canonical capability.
Breaking contract or SPI changes require a major release; while the package is `0.x`, consumers should pin
the exact qualified version.

## Release integrity prerequisites

Before merging the recorded patch release, a maintainer must protect `main` and enable immutable releases in the repository or applicable organization policy. The release job refuses an unprotected ref before tag/publication mutations and requires the exact stable version to be published with `immutable: true`. A pre-existing mutable release fails verification. Changing settings now does not make past mutable releases independently verified.

The shared release-heading parser is tested against malformed records and is used for both the pushed changelog and an existing tag. Source/tag checks, all package tests, true archive checks and dependency audit remain required. A fresh independent release verifier and exact artifact evidence are still required before dependent publication or App adoption. Agents open reviewable PRs; maintainers merge and publication follows the recorded version.
