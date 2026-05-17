# PBB Installer Readiness Checklist

Last updated: 2026-05-15 18:40 Asia/Manila

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
| Kit Setup | Ready for harness | Yes | Finish report added at 2026-05-15 20:00 | Add final smoke checks, live DNS verification, credential checks, and web-server reload checks |
| PBB MapServer | Ready for harness | Yes | Kit population dry-run passed at 2026-05-15 16:35 | Run tiny live fetch through Kit Setup when upstream config is available |
| PBB Realtime | Ready for harness | Yes | Kit population dry-run passed at 2026-05-15 16:35 | Run disposable install path |
| PBB Relay | Ready for harness | Yes | Kit preflight passed at 2026-05-15 16:25 | Run against generated compact Relay build output |
| PBB Maestro | Ready for harness | Yes | Kit population dry-run passed at 2026-05-15 16:35 | Run temporary-target install |
| PBB Hotline | Ready for harness | Yes | Kit population dry-run passed at 2026-05-15 16:45 | Run disposable install path |
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
- [x] Real all-app local preflight using `examples\kit-config.local-all.example.json`
- [x] Shared admin `password_env` resolution
- [x] Run-scoped cross-app secret generation
- [x] Redacted secret report generation
- [x] All-app dry-run population through Kit Setup
- [x] Trusted package manifest dry-run verification
- [x] DNS plan generation for selected local app domains
- [x] Platform probes for Apache, MySQL/MariaDB, ffmpeg, ffprobe, and WAMP services
- [x] Optional open/available port checks in platform detection
- [x] Remote dependency DNS/HTTP health checks for split-machine installs
- [x] Consolidated installation plan summary for the visual review screen
- [x] Non-destructive 12-step visual stage report
- [x] Desktop app direction and runtime secret handling decisions recorded
- [x] Desktop installer shell scaffold
- [x] Desktop runtime config builder for Hub, app scopes, base path, DNS, SSL/Apache, and admin password env wiring
- [x] Desktop confirmation gates for package preparation, DNS apply, SSL apply, install, and populate
- [x] Desktop focused setup panels tied to the selected 12-step stage
- [x] Desktop per-stage validation and inline recovery guidance
- [x] Same-run action checkpoint file and desktop checkpoint grid
- [x] Per-app retry using runner `--app <app-id>` filtering
- [x] Finish report and desktop finish summary

Pending:

- [ ] Shared host preflight aggregation
- [x] Real app sample config
- [x] Release ZIP staging and safe target deployment support
- [x] Cryptographic signature verification for release ZIPs
- [x] Checksum verification
- [~] Service artifact collection/registration
- [x] Guarded Technitium DNS API apply
- [x] Live DNS verification from the installer host
- [x] Remote dependency runtime credential validation
- [x] Population tool discovery from `release.json`
- [x] Population tool config/report orchestration
- [x] SSL certificate/key validation, guarded PEM extraction, and guarded Apache vhost include generation/apply
- [~] Apache config test after guarded vhost apply
- [~] Desktop export packaging
- [x] Cross-app final smoke checks
- [ ] Protected secret persistence outside transient run storage

Recent note:

- 2026-05-15 12:18:40 chat update broadcasted the executable harness and stub fixture.
- 2026-05-15 13:00:08 chat update broadcasted the initial data population tool additive.
- 2026-05-15 16:25 local harness run `local_all_preflight_contract_2` passed MapServer, Maestro, Realtime, Relay, and Hotline preflight with checksum status `passed` for each app.
- 2026-05-15 16:35 local harness run `local_all_populate_secrets_1` generated shared secrets and passed MapServer, Maestro, and Realtime population dry-runs. Relay skipped cleanly because it declares no population tool. Hotline executed but returned `not_implemented`.
- 2026-05-15 16:45 local harness run `local_all_populate_hotline_1` passed all enabled population dry-runs. Relay skipped cleanly because it declares no population tool.
- 2026-05-15 16:55 local harness run `local_prepare_packages_1` passed trusted package manifest verification for all selected apps. Each package source matched release metadata and passed `checksums.sha256`; extraction remained dry-run/planned.
- 2026-05-15 17:05 local harness run `stub_archive_deploy_1` staged a trusted stub ZIP, verified extracted `release.json` and `checksums.sha256`, backed up the previous target, and deployed into `storage\installed\stub-app`.
- 2026-05-15 17:10 local harness run `stub_archive_signed_1` required and verified a detached OpenSSL SHA-256 signature before staging, checksum verification, backup, and deployment.
- 2026-05-15 17:15 local harness run `local_dns_plan_1` generated six DNS upsert records: the five standard app domains plus the Cebu relay alias.
- 2026-05-15 17:20 local harness run `local_dns_apply_plan_only_1` exercised `dns-apply` with `dns.update_mode=plan-only`; it embedded the six-record plan and skipped API calls as expected.
- 2026-05-15 17:30 local harness run `local_ssl_plan_coverage_1` validated the local `*.pbb.ph` certificate/key pair, confirmed SAN coverage for six planned hostnames, and generated five Apache HTTPS vhosts, including the Hub-provided Relay alias. The SSL planner also supports guarded PEM bundle extraction when explicitly enabled.
- 2026-05-17 update: Hub/uplink relay aliases are now excluded from local Technitium and local Apache aliases because those domains remain public coordination endpoints. Current DNS/SSL plans should cover only the selected local app domains.
- 2026-05-15 17:35 local harness run `pem_extract_smoke_1` extracted a disposable PEM bundle under `storage\pem-test`, wrote certificate/key/fullchain outputs, validated the cert/key match, and confirmed host coverage.
- 2026-05-15 17:40 local harness runs `local_ssl_apply_plan_only_1` and `ssl_apply_smoke_1` verified that `ssl-apply` skips by default, then writes a disposable Apache include under `storage\ssl-apply-test` when `ssl.web_server_update_mode=apply`.
- 2026-05-15 17:50 local harness run `platform_detect_tools_1` passed PHP extension checks plus Apache, MySQL/MariaDB, ffmpeg, ffprobe, `wampapache64`, and `wampmariadb64` probes. The only warning was the not-yet-created Apache include output folder.
- 2026-05-15 18:00 local harness runs `remote_check_none_1` and `remote_check_smoke_1` verified no-remote success and a disposable remote app DNS/HTTP health check against `https://hub.pbb.ph`.
- 2026-05-15 18:10 local harness run `consolidated_plan_1` wrote the visual review summary into `kit-report.json`: five local apps, five trusted packages, six DNS records, SSL coverage for six hostnames, and zero remote apps. Overall status was `warning` only because the Apache include output folder has not been created yet.
- 2026-05-15 18:20 local harness run `stage_report_1` produced 12 visual setup stages: 7 success, 3 warning, and 2 pending. Pending stages are install and finish; warnings are expected until the admin password environment value and Apache include output folder are provided.
- 2026-05-15 18:30 project owner selected desktop app as the installer form factor, approved runtime-only secret handling, and provided a Technitium token for runtime testing. The token is not recorded in repo files.
- 2026-05-15 18:40 desktop installer shell scaffold added under `desktop/`. It renders the 12-step stage report, runs backend actions through the PHP runner, and passes runtime secrets through environment variables without saving them.
- 2026-05-15 18:55 desktop runtime config builder added. Smoke test generated `storage\desktop-config-test\generated.json` with 3 local apps, 1 remote app, and 1 disabled app, then `stage-report` accepted it as `desktop_builder_stage_1`.
- 2026-05-15 19:05 desktop confirmation gates added. Guarded actions now require an explicit modal acknowledgement before `prepare-packages`, `dns-apply`, `ssl-apply`, `install`, or `populate` runs.
- 2026-05-15 19:15 desktop setup inputs split into focused panels tied to the selected stage. Hub token, Technitium token, database password, and admin password are shown only in relevant stages and are passed at runtime.
- 2026-05-15 19:25 desktop per-stage validation added. Build/run actions now surface missing required inputs and focus the relevant stage before proceeding.
- 2026-05-15 19:35 checkpoint smoke run `checkpoint_smoke_1` reused one run id across `detect`, `dns-plan`, and `stage-report`; `checkpoints.json` accumulated all three action states and the desktop shell can render the checkpoint grid.
- 2026-05-15 19:45 app retry smoke run `app_retry_smoke_1` ran `preflight --app pbb-maestro`; only Maestro appeared in `kit-report.json`, and `checkpoints.json` recorded `app_filter=pbb-maestro`.
- 2026-05-15 20:00 finish report smoke run reused `checkpoint_smoke_1`; `finish-report.json` summarized five app URLs/statuses, admin email, checkpoint/report paths, and expected follow-ups for an incomplete dry run.
- 2026-05-15 22:26 local harness run `dns_verify_smoke_1` generated `dns-verify.json`. The resolver check correctly failed because the sample config expected `127.0.0.1` while the current network DNS resolves PBB hostnames to `192.168.254.x` addresses.
- 2026-05-15 22:32 `ssl-apply` gained Apache config-test reporting after guarded include writes. Reload/restart remains manual until the service-control policy is finalized.
- 2026-05-15 22:39 `npm run package:desktop:dir` produced the unpacked Windows bundle. The product name later changed to Project Bantay Bayan, with the executable at `out\win-unpacked\Project Bantay Bayan.exe`. Full NSIS packaging is configured; code signing remains a release-management task.
- 2026-05-15 22:45 `smoke-check` added final app URL DNS/HTTP checks and is included in desktop checkpoints and finish follow-ups.
- 2026-05-15 22:52 `detect` gained optional platform port checks for open infrastructure ports and available app bind ports.
- 2026-05-15 22:58 `remote-check` gained optional bearer/header credential support using runtime-only token values, with redacted credential status in reports.
- 2026-05-15 23:03 `finish-report` now surfaces app install manifests and service declarations collected by the runner; automatic service registration remains a separate guarded policy decision.

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

- [x] Add MapServer to a Kit Setup example config
- [x] Add optional MapServer tile population settings to a Kit Setup example config
- [x] Run Kit `preflight` against MapServer
- [ ] Run Kit `install` into a temporary target
- [x] Run tile population dry-run through Kit orchestration
- [ ] Run tiny live fetch through Kit orchestration where upstream keys are configured
- [ ] Verify `/tiles/health`
- [ ] Verify `/api/status`
- [ ] Verify repeated sample tile `MISS` then `HIT` where upstream keys are configured

Recent note:

- 2026-05-15 12:19:19 chat update says the initial Kit-compatible installer contract is implemented and smoke-tested with PHP 8.2.
- 2026-05-15 12:48:07 chat update says MapServer added `tools\populate-tiles.php`, tile population docs, optional `mapserver.populate` schema/sample config, GeoJSON PSGC matching, bbox and center/radius fallback modes, and regenerated checksums.
- 2026-05-15 14:03:04 chat update says MapServer hardened tile population validation, antimeridian documentation, thin/small polygon coverage, fixture coverage, and regenerated checksums.
- 2026-05-15 16:25 Kit preflight passed with checksum status `passed`.
- 2026-05-15 16:35 Kit population dry-run passed: 10 tiles planned, 0 attempted because dry-run.

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

- [x] Add Realtime to a Kit Setup example config
- [x] Run Kit `preflight` against Realtime
- [ ] Run Kit `install` into a temporary target
- [x] Run Realtime population dry-run from Kit Setup
- [ ] Verify Kit can parse Realtime report/manifest/status
- [ ] Verify generated service artifacts are collected by Kit Setup

Recent notes:

- 2026-05-15 12:09:53 chat update says packaged installer acceptance now passes through websocket sandbox roundtrip, and remaining gaps were contract normalization.
- 2026-05-15 12:23:16 chat update says the first Kit Setup-facing contract layer is now implemented.
- 2026-05-15 13:15:18 chat update says Realtime implemented `tools\populate-initial-data.php`, declared it in `release.json`, extended schema/docs, added `checksums.sha256`, and verified population dry-run plus packaged installer ZIP acceptance.
- 2026-05-15 14:03:00 chat update says Realtime adopted the First Admin Contract, regenerated checksums, and verified weak/strong admin validation plus packaged installer ZIP acceptance.
- 2026-05-15 16:25 Kit preflight passed after Kit resolved shared `admin.password_env` into per-app config.
- 2026-05-15 16:35 Kit population dry-run passed with generated Realtime secrets resolved into the generated app config.

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

- [x] Kit Setup fixture/sample config
- [x] Run Kit preflight against local Relay checkout
- [ ] Run Kit install against generated compact Relay build output

Recent notes:

- 2026-03-21 chat updates show Relay has a mature browser installer and package builder.
- 2026-05-15 13:09:13 chat update says Relay added the first Kit Setup-facing installer contract layer, including root `release.json`, unattended runner, status command, schema, installer library, manifest/report output, worker service artifact, and compact build output with checksums.
- 2026-05-15 13:29:56 chat update says Relay added root `checksums.sha256` covering `release.json` and the Kit-facing installer contract files, while generated `relay:installer:build` outputs still emit their own release-artifact checksums.
- 2026-05-15 13:48:17 chat update says Relay adopted the First Admin Contract, exposed the related schema fields, regenerated root checksums, and verified weak-password rejection plus standardized-admin preflight.
- 2026-05-15 16:25 Kit preflight passed after Kit resolved shared `admin.password_env` into per-app config.
- 2026-05-15 16:35 Kit population action skipped Relay cleanly because no population tool is declared.

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

- [x] Add Maestro to a Kit Setup example config
- [x] Run Kit `preflight` against Maestro
- [ ] Run Kit dry-run or temporary-target install without mutating the live checkout
- [x] Run Maestro population dry-run from Kit Setup
- [ ] Verify Kit can parse Maestro report, manifest, and status output
- [ ] Verify Kit can collect the Maestro scheduler service artifact

Recent notes:

- 2026-05-15 12:48:25 chat update says Maestro added the first Kit Setup-facing installer contract layer, including release metadata, installer entrypoints, schema, sample config, status output, checksums, and docs.
- Maestro reports PHP 8.2 lint, JSON parse checks, status output, preflight report, fresh dry-run with a non-placeholder admin password, `artisan test` with 12 passed / 76 assertions, and `node --check public\js\maestro.app.js`.
- A full mutating fresh install was not run against the live checkout because an active `.env` and database already exist.
- 2026-05-15 13:07:51 chat update says Maestro adopted the population-tool additive with `tools\populate-initial-data.php`, `maestro.populate` schema/sample docs, regenerated checksums, and dry-run verification without live DB mutation.
- 2026-05-15 13:52:02 chat update says Maestro adopted the First Admin Contract, updated schema/sample/checksums, redacts raw password handling, and verified weak-password fail plus strong-password dry-run.
- 2026-05-15 16:25 Kit preflight passed with checksum status `passed`.
- 2026-05-15 16:35 Kit population dry-run passed and planned application records for Relay and Realtime.

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
- [x] explicit Hotline session env defaults for fresh installs
- [x] generic and namespaced Hotline session lifetime settings

Pending Kit Setup validation:

- [x] Add Hotline to a Kit Setup example config
- [x] Run Kit `preflight` against Hotline
- [ ] Run Kit fresh dry-run or disposable-target install
- [x] Run Hotline population dry-run from Kit Setup
- [ ] Verify Kit can parse Hotline report, manifest, and status output
- [ ] Verify Kit can collect Hotline web/queue/scheduler service artifacts

Recent notes:

- 2026-05-15 14:20:13 chat update says Hotline adopted the First Admin Contract, has root checksums covering `release.json` and Kit-facing installer files, and passed PHP lint, JSON parse, weak-password rejection, strong standardized-admin fresh dry-run, checksum verification, and disposable existing-admin bootstrap unchanged check.
- 2026-05-15 16:13:35 chat update says Hotline now writes explicit `SESSION_LIFETIME=15`, `HOTLINE_SESSION_LIFETIME=15`, `HOTLINE_CRITICAL_SESSION_LIFETIME=43200`, `HOTLINE_CITIZEN_SESSION_LIFETIME=43200`, and Hotline session cookie/path/domain values during fresh installs, with PHP lint, fresh dry-run, and checksum verification passing.
- Local file check confirms `release.json`, `checksums.sha256`, installer entrypoints/schema, and `tools\populate-initial-data.php`.
- 2026-05-15 16:25 Kit preflight passed with `normal_session_lifetime=15`, `citizen_session_lifetime=43200`, and `session_domain` forwarded into the generated app config.
- 2026-05-15 16:35 Kit population action invoked Hotline `tools\populate-initial-data.php`, but the tool returned `status=not_implemented`. Kit Setup posted a chat-log note asking Hotline to confirm or implement the first dry-run population slice.
- 2026-05-15 16:41:26 chat update says Hotline implemented the first dry-run population slice and regenerated checksums.
- 2026-05-15 16:45 Kit population dry-run passed. Hotline reported success with no configured source files and zero planned records.

## Kit Setup Next Actions

- [ ] Add `examples\kit-config.mapserver.json`
- [x] Run Kit Setup against MapServer in `preflight`
- [ ] Run Kit Setup against MapServer in `install` using a temporary target
- [ ] Add MapServer optional tile population settings to a sample config
- [x] Run MapServer tile population dry-run through Kit Setup
- [ ] Run MapServer tiny live fetch through Kit Setup
- [ ] Add `examples\kit-config.realtime.json`
- [x] Run Kit Setup against Realtime in `preflight`
- [x] Run Realtime population dry-run through Kit Setup
- [ ] Add `examples\kit-config.relay.json`
- [x] Run Kit Setup against Relay in `preflight`
- [ ] Run Kit Setup against Relay generated compact build output
- [ ] Add `examples\kit-config.maestro.json`
- [x] Run Kit Setup against Maestro in `preflight`
- [ ] Run Kit Setup against Maestro in dry-run or temporary-target mode
- [x] Run Maestro population dry-run through Kit Setup
- [ ] Add `examples\kit-config.hotline.json`
- [x] Run Kit Setup against Hotline in `preflight`
- [ ] Run Kit Setup against Hotline in dry-run or disposable-target mode
- [x] Run Hotline population dry-run through Kit Setup
- [x] Extend runner to verify `checksums.sha256` when present
- [x] Extend runner to read each app status command after install
- [ ] Extend runner to collect install manifests
- [ ] Extend runner to collect service artifacts
- [x] Extend runner to discover and execute enabled population tools
- [x] Extend runner to collect population reports
- [ ] Add readiness report generation from `release.json` scan
- [x] Add archive extraction/deployment support after trusted package manifest verification
- [x] Add cryptographic signature verification for production archives
