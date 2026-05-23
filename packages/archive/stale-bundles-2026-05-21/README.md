# Stale Bundle Archive

These ZIP files were moved out of `packages/bundled` on 2026-05-21.

They are retained for diagnostics only. They are not manifest-selected release bundles and must not be packaged into operator-facing Kit Setup installers.

Current trusted release bundles must use the canonical filename format documented in `docs/data-prep-contract-template.md`:

```text
pbb-{app-code}-{semver}.zip
```

Kit Setup installer resources should include only `packages/packages.bundled.json` and the canonical bundles selected by that manifest.
