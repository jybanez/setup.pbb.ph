# Release Candidate v1-0.1.124

Build artifacts:

```text
C:\wamp64\www\pbb\kit-setup\out\Project Bantay Bayan Setup 0.1.124.exe
C:\wamp64\www\pbb\kit-setup\out\pbb-kit-setup-v1-0.1.124-ph-0722-cebu.exe
```

SHA-256:

```text
EBC99B3DE1DBADA8180D8A67131FF32054030F4468FCBB3490ED6EFF7697CFA5
```

Size:

```text
180,578,983 bytes
```

## Purpose

This release candidate consumes Hotline's baseline-schema seed-skip bundle so fresh production-pruned installs no longer call the absent `SettingsSeeder`. It keeps the Cebu province-specific MapServer package layout introduced in v1-0.1.122 and the Maestro seed-skip fix consumed in v1-0.1.123.

## Bundled Packages

| App | Bundle | SHA-256 | Size | Build |
| --- | --- | --- | ---: | --- |
| MapServer | `pbb-mapserver-1.0.0.zip` | `b651e77701861c07ab50420d1b102d7da0a6af73856e8b93e23063fad9459642` | 1,202,247 bytes | `pbb-mapserver-20260527-180531` |
| MapServer Cebu boundaries | `pbb-mapserver-boundaries-province-0722.zip` | `1e314649821540bfa68abf3294d23615eaf91dd64e6c2e66bbac21d24a0797b6` | 5,962,459 bytes | supplemental |
| Maestro | `pbb-maestro-1.0.0.zip` | `77cfa366d2ac3a6ca8e96e8f64000306f08f2c41ee334a549a073544f851e790` | 7,571,004 bytes | `pbb-maestro-1-1.0.0-20260527.104007` |
| Realtime | `pbb-realtime-1.0.0.zip` | `9ad22fb025bb782f33a644a350bde9ea28d5926fe859929d52e5402d860d2ceb` | 8,035,151 bytes | `pbb-realtime-20260527154611` |
| Relay | `pbb-relay-1.1.0.zip` | `314e452e3f9dfcfc2cac23b563ad69d0379f7e130ada332c6ce28775bb5f8f88` | 7,347,495 bytes | `pbb-relay-m1-1.1.0-20260527.161138` |
| Hotline | `pbb-hotline-5.6.1.zip` | `3d75dbb5e0a96a342b35969f6e98ad9a9add494aa885b816d3cc92084afaacef` | 46,438,583 bytes | `pbb-hotline-5.6.1-20260527-baseline-seed-skip` |

## Verification

- pre-build manifest integrity and internal checksum scan for all bundled packages
- seed contract audit for Maestro, Realtime, Relay, and Hotline
- PHP lint for `src\KitSetupRunner.php`
- `npm run check:desktop`
- source bundled package hash verification, including supplemental packages
- package deployment harness for all bundled apps into an isolated workspace
- MapServer harness check proving the national boundary ZIP is absent and the Cebu province pack installs under `resources\boundaries\provinces\0722`
- Hotline fresh-install dry-run proving `apply_baseline_schema` is planned and `artisan_seed_settings` is skipped for `baseline_schema`
- NSIS desktop installer build
- packaged version check
- packaged bundled package hash verification, including supplemental packages
- packaged MapServer ZIP probe confirming no national boundary ZIP
- packaged Cebu province pack probe confirming `BgySubMuns.shp.zip` and `pack.json`
- packaged Hotline ZIP probe confirming `SettingsSeeder.php` and `ffprobe.exe` are absent, `ffmpeg.exe` is present, and seed-skip branches exist
