# PBB Helper UI Bundle

This directory vendors the built browser assets from the official PBB Helper library.

- Upstream: https://github.com/jybanez/helpers.pbb.ph
- Local source used for this copy: `C:\wamp64\www\pbb\helpers.pbb.ph`
- Helper version: `0.21.83`
- Upstream commit: `5da75e1`
- Local source note: includes the current-step visible orbit refinement in `css/ui/ui.stepper.css` and removes the `prefers-reduced-motion` suppression from the active stepper marker animation.
- Included files: `dist/helpers.ui.bundle.min.js`, `dist/helpers.ui.bundle.min.css`

Kit Setup consumes only the `dist` bundle so the desktop installer does not carry the full helper source tree.
