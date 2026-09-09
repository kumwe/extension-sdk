# Complete released package graph qualification

`php tools/verify-package-set-consumer.php INPUT_JSON OUTPUT_JSON` accepts the archive set produced
by the independent published release verifier. It requires all 28 extraction catalog owners plus
`kumwe/conversion`, `kumwe/producer` and `kumwe/extension-sdk`. Every Kumwe runtime dependency must
resolve exactly within the supplied stable graph. An incomplete graph is a refusal.

The input schema is `kumwe-verified-php-package-set/v1`. Each `packages` entry contains its canonical
`name`, exact stable `version`, full `source_commit`, local canonical `archive_path`, verified
`archive_sha256`, extracted `package_root` and unchanged original `composer` object. The upstream
verifier owns tag/source/registry/archive and handoff validation. This consumer checks those local
bytes again, installs only their ZIP distributions, and never substitutes a path or source checkout.

Both modes install into a fresh project with a private Composer cache, no development packages or
plugins/scripts, and an authoritative classmap. They verify platform requirements and audit the lock,
remove the entire vendor tree, then reinstall the same lock with `COMPOSER_DISABLE_NETWORK=1` and
`COMPOSER_CACHE_READ_ONLY=1`. The final installed metadata and runtime assertions use this second
installation. This is Composer's network-disabled mode, not a claim of OS network namespace isolation.
Exactly the two documented SDK PHPUnit bridges are omitted from no-dev loading; every other mapped
type must load from its owning installed archive.

## Portable graph

Set `graph_mode` to `portable`; omitted mode retains this behavior for earlier verifier inputs.
No `native` field is allowed. The process must have no loaded `kumwe_engine` extension, and no
selected package may require it. Select the independently verified portable Computation baseline.
This graph demonstrates extension-free extraction compatibility.

## Native graph

Set `graph_mode` to `native`, replace the Computation selection with its independently qualified
stable native successor, and preserve the remainder of the released PHP graph. Only Computation
may require `ext-kumwe_engine`, and its requirement must exactly equal the supplied stable version.
No development version, floating extension constraint, candidate ABI or unverified semantic freeze
is accepted. The actual extension must already be provisioned for PHP and its Composer subprocesses.

The additional `native` object contains:

- `extension_version`: the selected exact stable extension version.
- `compatibility_tuple`: the complete direct result expected from
  `(new Kumwe\Engine\Runtime())->capabilities()`. This is the `tuple` member of the native diagnostic
  record, not its outer PHP/platform wrapper or the smaller Computation constructor tuple.
- `engine` and `extension`: each carries `package`, `version`, `tag`, full `commit`, published
  `archive_sha256`, and its independently validated `attestation` reference.
- Each `attestation` carries its immutable GitHub `uri`, uploaded ZIP `sha256`, local ZIP
  `archive_path`, `member` equal to `RELEASE-ATTESTATION.yaml`, local `member_path`, and separate
  `member_sha256`. The ZIP must contain exactly one matching member with exactly those YAML bytes.
- `engine.embedding_archive_path` and `engine.embedding_archive_sha256` identify the raw,
  prefix-free `git archive --format=tar ENGINE_COMMIT` bytes that the binding embeds. This digest
  must equal the runtime's `embedded_source_sha256`. It is distinct from the published prefixed,
  compressed Engine source archive digest above.

The consumer requires the real internal Runtime class and exact extension version, then compares
every typed tuple value, preserving list order and ignoring only JSON object key order. It repeats
the comparison after the offline installation. Finally, the actual installed Computation adapter
performs a canonical encoding and digest call through the native module. There is no replacement
encoder, userland fallback or synthetic success tuple.

The native release verifier remains responsible for publisher signatures, source/archive semantics
and supported-platform evidence. This consumer binds its passed input to unchanged ZIP/member and
embedding bytes and verifies the actual runtime; hashing is never reported as signature validation.
Synthetic fixtures in `tools/test-package-set-consumer.php` test refusal paths only and never produce
release attestations or a successful native runtime report.

## Result

The output schema is `kumwe-php-package-set-consumer/v1`, written only after complete success.
It identifies the mode, all package versions/source/digests, runtime type counts, clean no-dev and
authoritative state, unchanged lock hash and actual offline replay. Native output additionally
records its supplied immutable evidence and observed adapter-call success. Neither output performs
App integration or grants host admission policy.
