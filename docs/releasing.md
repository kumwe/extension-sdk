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
