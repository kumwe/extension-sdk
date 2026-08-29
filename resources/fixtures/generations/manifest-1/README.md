# Manifest generation 1 compatibility package

A schema-1 plugin, kept as small as the generation allows. It registers one service of its own, resolves
it during the behavior-only boot phase, and declares no executable surface — which is the whole of what
this generation fixture proves. No code-side declaration registrar exists.

The SDK generation gate builds this tree reproducibly and drives its provider through registration and
boot. Change it only when the canonical schema-one profile changes.
