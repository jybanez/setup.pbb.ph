# PBB Installer Readiness Checklist

Last updated: 2026-05-15 14:22 Asia/Manila

This checklist tracks where each project is against the Kit Setup app-installer contract in [App Installer Template](app-installer-template.md).

Sources used:

- Recent entries in `C:\wamp64\www\pbb\chat_log.md`
- Local file checks in each project workspace
- Existing project installer docs and release metadata

## Status Legend

- `Ready for harness`: Kit-facing contract exists and can be tried from Kit Setup.
- `Partial`: project has installer work, but the Kit-facing contract is incomplete.
- `Planned`: project has planning docs or intent, but no Kit-facing installer files yet.
- `Not started`: no app-installer work found.
- `N/A`: not an app installer target.

## Summary

| Project | Current Status | Local Contract Files | Recent Chat Signal | Next Gate |
|---|---:|---:|---|---|
| Kit Setup | Ready for harness | Yes | Population-tool additive broadcast at 2026-05-15 13:00:08 | Add real app sample configs and orchestrate app population tools |
| PBB MapServer | Ready for harness | Yes | Tile populator hardened at 2026-05-15 14:03:04 | Add to Kit sample config, include optional populate config, and run from Kit Setup |
| PBB Realtime | Ready for harness | Yes | First Admin Contract adopted at 2026-05-15 14:03:00 | Add to Kit sample config and run install plus population dry-run from Kit Setup |
| PBB Relay | Ready for harness | Yes | First Admin Contract adopted at 2026-05-15 13:48:17 | Add to Kit sample config and run from Kit Setup |
| PBB Maestro | Ready for harness | Yes | First Admin Contract adopted at 2026-05-15 13:52:02 | Add to Kit sample config and run preflight/population dry-run from Kit Setup |
| PBB Hotline | Ready for harness | Yes | First Admin Contract and Kit installer files confirmed at 2026-05-15 14:20:13 | Add to Kit sample config and run preflight/fresh dry-run from Kit Setup |
| PBB Helper | N/A | N/A | Shared UI library, not a machine app installer target | Keep vendoring/version metadata clear for apps |
| PBB Workspace | Future/Out of current kit scope | No | Workspace has app discovery work, not Kit installer work | Decide whether Workspace joins this kit bundle |
| PBB HQ / Hub | Future/Out of current kit scope | No local check in this pass | HQ context exists through Relay integration | Decide whether HQ is a Kit Setup install target |

## Contract Checklist By Project

### Kit Setup

Path: `C:\wamp64\www\pbb\kit-setup`

Current status: `Ready for harness`

Implemented:

- [x] App installer contract guide
- [x] Kit config schema
- [x] Stub kit config
- [x] Executable runner: `bin/kit-setup.php`
- [x] Release discovery from `release.json`
- [x] Dependency ordering through `depends_on`
- [x] Per-app unattended config generation
- [x] App installer execution for `plan`, `preflight`, and `install`
- [x] Aggregated `kit-report.json`
- [x] Stub app fixture for contract testing

Pending:

- [ ] Shared host preflight aggregation
- [ ] Real app sample configs
- [ ] Release ZIP extraction support
- [ ] Checksum verification
- [ ] Service artifact collection/registration
- [ ] Population tool discovery from `release.json`
- [ ] Population tool config/report orchestration
- [ ] Cross-app final smoke checks
- [ ] Secret generation and protected persistence

Recent note:

- 2026-05-15 12:18:40 chat update broadcasted the executable harness and stub fixture.
- 2026-05-15 13:00:08 chat update broadcasted the initial data population tool additive.

### PBB MapServer

Path: `C:\wamp64\www\mapserver`

Current status: `Ready for harness`

Local contract files:

- [x] `release.json`
- [x] `checksums.sha256`
- [x] `installer\install-run.php`
- [x] `installer\status.php`
- [x] `installer\schema\install.schema.json`
- [x] Installer sample config/docs
- [x] `tools\populate-tiles.php`
- [x] `docs\tile-populator.md`

Reported capabilities:

- [x] `preflight`
- [x] `fresh`
- [x] `repair`
- [x] `upgrade`
- [x] install manifest
- [x] install report
- [x] install log
- [x] status output
- [x] web-only service model
- [x] `.env` preservation on repair/upgrade unless `options.overwrite_env=true`
- [x] optional `mapserver.populate` install config
- [x] tile population from GeoJSON PSGC matches
- [x] tile population from `bbox`
- [x] tile population from `center/radius`
- [x] regenerated `checksums.sha256`

Pending Kit Setup validation:

- [ ] Add MapServer to a Kit Setup example config
- [ ] Add optional MapServer tile population settings to a Kit Setup example config
- [ ] Run Kit `preflight` against MapServer
- [ ] Run Kit `install` into a temporary target
- [ ] Run tile population dry-run or tiny live fetch through Kit orchestration
- [ ] Verify `/tiles/health`
- [ ] Verify `/api/status`
- [ ] Verify repeated sample tile `MISS` then `HIT` where upstream keys are configured

Recent note:

- 2026-05-15 12:19:19 chat update says the initial Kit-compatible installer contract is implemented and smoke-tested with PHP 8.2.
- 2026-05-15 12:48:07 chat update says MapServer added `tools\populate-tiles.php`, tile population docs, optional `mapserver.populate` schema/sample config, GeoJSON PSGC matching, bbox and center/radius fallback modes, and regenerated checksums.
- 2026-05-15 14:03:04 chat update says MapServer hardened tile population validation, antimeridian documentation, thin/small polygon coverage, fixture coverage, and regenerated checksums.

### PBB Realtime

Path: `C:\wamp64\www\pbb\realtime`

Current status: `Ready for harness`

Local contract files:

- [x] `release.json`
- [x] `checksums.sha256`
- [x] `installer\install-run.php`
- [x] `installer\status.php`
- [x] `installer\schema\install.schema.json`
- [x] `public\installer\api\status.php`
- [x] `tools\populate-initial-data.php`
- [x] `installer\docs\realtime-populate.sample.json`

Reported capabilities:

- [x] CLI preflight report
- [x] unattended CLI runner with `--config`
- [x] `--report`
- [x] `--mode`
- [x] `--dry-run`
- [x] `--no-service-register`
- [x] CLI status output
- [x] browser status API
- [x] report/manifest/status normalization
- [x] packaged installer ZIP acceptance
- [x] websocket sandbox roundtrip acceptance
- [x] idempotent initial data population tool
- [x] population dry-run report
- [x] population of clients, policies, projects, media ingest settings, product query forwarding settings, and backend ingress secret digests
- [x] no raw secret printing in population reports

Pending Kit Setup validation:

- [ ] Add Realtime to a Kit Setup example config
- [ ] Run Kit `preflight` against Realtime
- [ ] Run Kit `install` into a temporary target
- [ ] Run Realtime population dry-run from Kit Setup
- [ ] Verify Kit can parse Realtime report/manifest/status
- [ ] Verify generated service artifacts are collected by Kit Setup

Recent notes:

- 2026-05-15 12:09:53 chat update says packaged installer acceptance now passes through websocket sandbox roundtrip, and remaining gaps were contract normalization.
- 2026-05-15 12:23:16 chat update says the first Kit Setup-facing contract layer is now implemented.
- 2026-05-15 13:15:18 chat update says Realtime implemented `tools\populate-initial-data.php`, declared it in `release.json`, extended schema/docs, added `checksums.sha256`, and verified population dry-run plus packaged installer ZIP acceptance.
- 2026-05-15 14:03:00 chat update says Realtime adopted the First Admin Contract, regenerated checksums, and verified weak/strong admin validation plus packaged installer ZIP acceptance.

### PBB Relay

Path: `C:\wamp64\www\pbb\relay`

Current status: `Ready for harness`

Local contract files:

- [x] `release.json`
- [x] `checksums.sha256`
- [x] `installer\install-run.php`
- [x] `installer\status.php`
- [x] `installer\schema\install.schema.json`

Existing installer work:

- [x] Browser installer proposal/docs
- [x] Installer mode gating
- [x] JSON-backed installer state
- [x] environment readiness checks
- [x] HQ identity validation
- [x] settings persistence
- [x] execution step
- [x] `.env` writing
- [x] migrations
- [x] first admin provisioning
- [x] install lock creation
- [x] packaged release extraction
- [x] cleanup handling
- [x] package builder command `php artisan relay:installer:build`
- [x] root bootstrap plus `installer.zip` packaging
- [x] standalone PHP installer runtime direction
- [x] Kit-facing unattended runner
- [x] common report output
- [x] common manifest output
- [x] service artifact declaration for `pbb-relay-worker`
- [x] compact build emits Kit installer files

Pending Kit contract work:

- [ ] Kit Setup fixture/sample config
- [ ] Run Kit preflight against local Relay checkout
- [ ] Run Kit install against generated compact Relay build output

Recent notes:

- 2026-03-21 chat updates show Relay has a mature browser installer and package builder.
- 2026-05-15 13:09:13 chat update says Relay added the first Kit Setup-facing installer contract layer, including root `release.json`, unattended runner, status command, schema, installer library, manifest/report output, worker service artifact, and compact build output with checksums.
- 2026-05-15 13:29:56 chat update says Relay added root `checksums.sha256` covering `release.json` and the Kit-facing installer contract files, while generated `relay:installer:build` outputs still emit their own release-artifact checksums.
- 2026-05-15 13:48:17 chat update says Relay adopted the First Admin Contract, exposed the related schema fields, regenerated root checksums, and verified weak-password rejection plus standardized-admin preflight.

### PBB Maestro

Path: `C:\wamp64\www\pbb\maestro`

Current status: `Ready for harness`

Local contract files:

- [x] `release.json`
- [x] `checksums.sha256`
- [x] `installer\install-run.php`
- [x] `installer\status.php`
- [x] `installer\index.php`
- [x] `installer\schema\install.schema.json`
- [x] `installer\docs\maestro-install.sample.json`
- [x] `installer\docs\post-install-checklist.md`
- [x] `tools\populate-initial-data.php`
- [x] `installer\docs\maestro-populate.sample.json`
- [x] `installer\docs\populate-initial-data.md`

Reported capabilities:

- [x] CLI `--config`
- [x] CLI `--report`
- [x] CLI `--mode`
- [x] `preflight`
- [x] `fresh`
- [x] `repair`
- [x] `upgrade`
- [x] `--dry-run`
- [x] `--no-service-register`
- [x] report, manifest, state, and log artifacts under `storage\app\installer`
- [x] `.env` backup/preservation before rewrite
- [x] migrations, seeders, admin bootstrap, and cache optimization
- [x] scheduler service artifact for `php artisan schedule:run`
- [x] status output
- [x] idempotent initial data population tool
- [x] application registration by `app_code`
- [x] optional telemetry token creation by label
- [x] placeholder-token rejection
- [x] no raw token values in population reports

Pending Kit Setup validation:

- [ ] Add Maestro to a Kit Setup example config
- [ ] Run Kit `preflight` against Maestro
- [ ] Run Kit dry-run or temporary-target install without mutating the live checkout
- [ ] Run Maestro population dry-run from Kit Setup
- [ ] Verify Kit can parse Maestro report, manifest, and status output
- [ ] Verify Kit can collect the Maestro scheduler service artifact

Recent notes:

- 2026-05-15 12:48:25 chat update says Maestro added the first Kit Setup-facing installer contract layer, including release metadata, installer entrypoints, schema, sample config, status output, checksums, and docs.
- Maestro reports PHP 8.2 lint, JSON parse checks, status output, preflight report, fresh dry-run with a non-placeholder admin password, `artisan test` with 12 passed / 76 assertions, and `node --check public\js\maestro.app.js`.
- A full mutating fresh install was not run against the live checkout because an active `.env` and database already exist.
- 2026-05-15 13:07:51 chat update says Maestro adopted the population-tool additive with `tools\populate-initial-data.php`, `maestro.populate` schema/sample docs, regenerated checksums, and dry-run verification without live DB mutation.
- 2026-05-15 13:52:02 chat update says Maestro adopted the First Admin Contract, updated schema/sample/checksums, redacts raw password handling, and verified weak-password fail plus strong-password dry-run.

### PBB Hotline

Path: `C:\wamp64\www\pbb\hotline`

Current status: `Ready for harness`

Local contract files:

- [x] `release.json`
- [x] `checksums.sha256`
- [x] `installer\index.php`
- [x] `installer\install-run.php`
- [x] `installer\status.php`
- [x] `installer\schema\install.schema.json`
- [x] `tools\populate-initial-data.php`

Existing installer planning:

- [x] `docs\pbb-github-release-installer-plan.md`
- [x] Draft direction for GitHub Release bundles
- [x] Draft `release.json` shape
- [x] Ecosystem installer integration expectations

Reported capabilities:

- [x] release metadata
- [x] unattended runner
- [x] browser installer entrypoint
- [x] status command
- [x] install schema
- [x] checksum coverage for Kit-facing installer files
- [x] First Admin Contract defaults
- [x] weak/placeholder admin password rejection
- [x] existing-admin unchanged behavior unless `admin.overwrite_existing=true`
- [x] raw password redaction from reports/manifests/status
- [x] population tool declared in `release.json`
- [x] service artifacts for web, queue, and scheduler
- [x] dependency declarations for Realtime, MapServer, Relay, Maestro, `ffmpeg`, and `ffprobe`

Pending Kit Setup validation:

- [ ] Add Hotline to a Kit Setup example config
- [ ] Run Kit `preflight` against Hotline
- [ ] Run Kit fresh dry-run or disposable-target install
- [ ] Run Hotline population dry-run from Kit Setup
- [ ] Verify Kit can parse Hotline report, manifest, and status output
- [ ] Verify Kit can collect Hotline web/queue/scheduler service artifacts

Recent notes:

- 2026-05-15 14:20:13 chat update says Hotline adopted the First Admin Contract, has root checksums covering `release.json` and Kit-facing installer files, and passed PHP lint, JSON parse, weak-password rejection, strong standardized-admin fresh dry-run, checksum verification, and disposable existing-admin bootstrap unchanged check.
- Local file check confirms `release.json`, `checksums.sha256`, installer entrypoints/schema, and `tools\populate-initial-data.php`.

## Kit Setup Next Actions

- [ ] Add `examples\kit-config.mapserver.json`
- [ ] Run Kit Setup against MapServer in `preflight`
- [ ] Run Kit Setup against MapServer in `install` using a temporary target
- [ ] Add MapServer optional tile population settings to a sample config
- [ ] Run MapServer tile population dry-run or tiny live fetch through Kit Setup
- [ ] Add `examples\kit-config.realtime.json`
- [ ] Run Kit Setup against Realtime in `preflight`
- [ ] Run Realtime population dry-run through Kit Setup
- [ ] Add `examples\kit-config.relay.json`
- [ ] Run Kit Setup against Relay in `preflight`
- [ ] Run Kit Setup against Relay generated compact build output
- [ ] Add `examples\kit-config.maestro.json`
- [ ] Run Kit Setup against Maestro in `preflight`
- [ ] Run Kit Setup against Maestro in dry-run or temporary-target mode
- [ ] Run Maestro population dry-run through Kit Setup
- [ ] Add `examples\kit-config.hotline.json`
- [ ] Run Kit Setup against Hotline in `preflight`
- [ ] Run Kit Setup against Hotline in dry-run or disposable-target mode
- [ ] Run Hotline population dry-run through Kit Setup
- [ ] Extend runner to verify `checksums.sha256` when present
- [ ] Extend runner to read each app status command after install
- [ ] Extend runner to collect install manifests
- [ ] Extend runner to collect service artifacts
- [ ] Extend runner to discover and execute enabled population tools
- [ ] Extend runner to collect population reports
- [ ] Add readiness report generation from `release.json` scan
