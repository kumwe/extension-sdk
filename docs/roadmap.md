# Extension SDK roadmap

Delivered work is recorded in [`CHANGELOG.md`](../CHANGELOG.md). Remaining work is intentionally small and
must preserve the canonical boundaries in [`CHARTER.md`](../CHARTER.md).

## 0.2 integration

- Validate canonical Studio composition documents through the exact schema corpus owned by
  `kumwe/producer`, while the SDK owns their manifest DTO and typed contribution getters.
- Prove all six canonical fixture generations and the rendered complete scaffold by loading each provider,
  reconciling exact manifest-bound IDs and invoking every executable surface.
- Qualify the first host against [`host-integration.md`](host-integration.md): direct canonical imports,
  one manifest parse, one inspection implementation and no compatibility layer.

## Future framework work

Framework extraction is a separate exercise. New host-neutral ports enter this SDK only when extension
authors need them and a host can implement them directly without an adapter. Host-domain implementations,
policy, storage and lifecycle orchestration remain outside this repository.
