# Release Candidate v1-0.1.121

Status: release candidate rebuild for operator testing.

GitHub release tag: pending until the package is published.

Installer artifact:

```text
C:\wamp64\www\pbb\kit-setup\out\Project Bantay Bayan Setup 0.1.121.exe
```

Installer SHA-256:

```text
F7E234CF96F8B0519C796987801E93F8A50A5E0641AC963C5263ECA8EC94CAF5
```

## Purpose

This candidate keeps the v1-0.1.120 setup shell behavior and refreshes the embedded app bundles after the production-pruned packaging pass.

## Embedded Bundle Refresh

MapServer is unchanged because its size is map/data payload-driven.

Refreshed production-pruned app bundles:

| App | Bundle | SHA-256 | Size | Build |
| --- | --- | --- | ---: | --- |
| Maestro | `pbb-maestro-1.0.0.zip` | `576c18dadde34c8101a49f90d0ef81a10f40146eb26439a22b283d1db4e2193a` | 7,570,916 bytes | `pbb-maestro-1-1.0.0-20260527.080621` |
| Realtime | `pbb-realtime-1.0.0.zip` | `9ad22fb025bb782f33a644a350bde9ea28d5926fe859929d52e5402d860d2ceb` | 8,035,151 bytes | `pbb-realtime-20260527154611` |
| Relay | `pbb-relay-1.1.0.zip` | `314e452e3f9dfcfc2cac23b563ad69d0379f7e130ada332c6ce28775bb5f8f88` | 7,347,495 bytes | `pbb-relay-m1-1.1.0-20260527.161138` |
| Hotline | `pbb-hotline-5.6.1.zip` | `1531b3ccb7c22d71c8b588a2599e5eaf8ce182c0b9d711ebc3617128e636307f` | 46,438,266 bytes | `pbb-hotline-5.6.1-20260527-ffprobe-optional` |

Hotline remains larger than the other Laravel-style bundles because it still embeds `ffmpeg.exe` for media assembly. The unused bundled `ffprobe.exe` was removed and is now optional/external-only, saving about 36.6 MB compressed from the prior Hotline bundle.

## Carried-Forward Setup Behavior

- Setup mode shows the Startup Requirements gate before Admin Inputs.
- Setup mode keeps the gate result until the operator clicks `Check Again`; Start Installation does not force a gate rerun.
- `http://dns.pbb.ph:5380` may remain the Technitium URL, while the hidden Windows DNS client value is resolved/probed to an IPv4 address before validation.
- Data Prep mode hides the Startup Requirements gate and is gated only by completed Kit Setup state.

## Verification

Completed verification before handoff:

- `npm run check:desktop`
- WAMP PHP lint for `src\KitSetupRunner.php`
- source bundled package hash verification
- NSIS package build
- packaged version check
- packaged bundled package hash verification
- packaged app release metadata probes for refreshed bundles
