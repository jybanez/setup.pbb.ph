# Release Candidate v1-0.1.122

Build artifact:

```text
C:\wamp64\www\pbb\kit-setup\out\Project Bantay Bayan Setup 0.1.122.exe
```

SHA-256:

```text
A0AA4ED643631227513DCBE05308B787180C7C224FE05B0242BDFF3091D78D65
```

Size:

```text
180,578,285 bytes
```

## Purpose

This release candidate adds Kit-side support for trusted supplemental packages and uses it for the MapServer Cebu province boundary pack. It replaces the prior MapServer national boundary payload with a small MapServer runtime bundle plus a deployment-scoped province pack.

## Bundled Packages

| App | Bundle | SHA-256 | Size | Build |
| --- | --- | --- | ---: | --- |
| MapServer | `pbb-mapserver-1.0.0.zip` | `b651e77701861c07ab50420d1b102d7da0a6af73856e8b93e23063fad9459642` | 1,202,247 bytes | `pbb-mapserver-20260527-180531` |
| MapServer Cebu boundaries | `pbb-mapserver-boundaries-province-0722.zip` | `1e314649821540bfa68abf3294d23615eaf91dd64e6c2e66bbac21d24a0797b6` | 5,962,459 bytes | supplemental |
| Maestro | `pbb-maestro-1.0.0.zip` | `576c18dadde34c8101a49f90d0ef81a10f40146eb26439a22b283d1db4e2193a` | 7,570,916 bytes | `pbb-maestro-1-1.0.0-20260527.080621` |
| Realtime | `pbb-realtime-1.0.0.zip` | `9ad22fb025bb782f33a644a350bde9ea28d5926fe859929d52e5402d860d2ceb` | 8,035,151 bytes | `pbb-realtime-20260527154611` |
| Relay | `pbb-relay-1.1.0.zip` | `314e452e3f9dfcfc2cac23b563ad69d0379f7e130ada332c6ce28775bb5f8f88` | 7,347,495 bytes | `pbb-relay-m1-1.1.0-20260527.161138` |
| Hotline | `pbb-hotline-5.6.1.zip` | `1531b3ccb7c22d71c8b588a2599e5eaf8ce182c0b9d711ebc3617128e636307f` | 46,438,266 bytes | `pbb-hotline-5.6.1-20260527-ffprobe-optional` |

## Verification

- PHP lint for `src\KitSetupRunner.php`
- `npm run check:desktop`
- source bundled package hash verification, including supplemental packages
- MapServer package harness proving the national ZIP is absent and the Cebu province pack installs under `resources\boundaries\provinces\0722`
- NSIS desktop installer build
- packaged version check
- packaged bundled package hash verification
- packaged MapServer ZIP probe confirming no national boundary ZIP
- packaged Cebu province pack probe confirming `BgySubMuns.shp.zip` and `pack.json`
