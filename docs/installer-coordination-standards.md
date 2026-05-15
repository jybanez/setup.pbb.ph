# PBB Installer Coordination Standards

## Purpose

PBB Kit Setup is the ecosystem-level installer for a machine/node. It should coordinate app installers, shared runtime configuration, service ordering, health checks, and final validation.

Each app still owns its own install logic. Kit Setup should call app installers through a predictable contract instead of duplicating app-specific bootstrap code.

The concrete app-side contract is defined in [App Installer Template](app-installer-template.md).

## Repositories Reviewed

### PBB Relay Server

Path: `C:\wamp64\www\pbb\relay`

Role:

- Store-and-forward hub relay service for offline-first app communication.
- Laravel 12, PHP `^8.2`, Vite frontend.
- Queue-based delivery and handler processing.
- Provides diagnostics/status API and admin/operator UI.
- Integrates with Maestro through worker telemetry when enabled.

Installer status:

- Has browser-installer direction and docs:
  - `docs/hub-relay-browser-installer-proposal.md`
  - `docs/hub-relay-installer-bootstrap-package-spec.md`
  - `docs/hub-relay-standalone-installer-runtime-proposal.md`
- Has installer configuration at `config/installer.php`.
- Has build command `php artisan relay:installer:build`.
- Preferred direction is a small permanent `index.php`, a temporary standalone PHP installer runtime, and a separate Relay release ZIP.

Runtime commands and services:

- Web app through normal Laravel/PHP hosting.
- Queue worker should run for relay deliveries and handlers, commonly:
  - `php artisan queue:work --queue=relay-deliveries,relay-handlers`
- Optional HQ registry sync:
  - `php artisan relay:hq-sync`
- Optional Maestro telemetry requires `RELAY_MAESTRO_*` env settings.

Health surface:

- Public shared-service status: `GET /api/status`
- Admin and diagnostics surfaces under `/relay`.

### PBB Maestro Server

Path: `C:\wamp64\www\pbb\maestro`

Role:

- Worker monitoring service for PBB background processes.
- Laravel 12, PHP `^8.2`, Vite frontend.
- Owns telemetry ingestion, stale detection, worker/event grids, application registration, and telemetry tokens.
- Does not start, stop, scale, or supervise worker processes in V1.

Installer status:

- No app-specific installer folder found yet.
- Uses standard Laravel setup scripts in `composer.json`.
- Should adopt the common app-installer contract so Kit Setup can install it before telemetry producers.

Runtime commands and services:

- Web app through normal Laravel/PHP hosting.
- Scheduled stale reconciliation exists:
  - `php artisan maestro:reconcile-stale-workers`
- Machine-to-machine telemetry endpoints:
  - `POST /api/v1/telemetry/workers/heartbeat`
  - `POST /api/v1/telemetry/worker-events`

Health and integration surface:

- Browser-authenticated operator APIs under `/api/v1/*`.
- Telemetry auth accepts:
  - `Authorization: Bearer <token>`
  - `X-Telemetry-Token: <token>`
- Telemetry payloads must include `app_code` matching the registered application.

### PBB Realtime Server

Path: `C:\wamp64\www\pbb\realtime`

Role:

- Shared realtime gateway for websocket transport, presence, app events, signaling, and media-event fanout.
- Laravel 10, PHP `^8.1`, Ratchet, Vite frontend.
- Owns token admission, websocket gateway, backend event ingress, operator/admin UI, SDK docs, and Maestro process telemetry integration.

Installer status:

- Most mature installer reference among reviewed projects.
- Has `installer/` and browser installer scaffold under `public/installer/`.
- Has installer docs covering fresh/upgrade/repair, quickstart, UI/actions, troubleshooting, clean host checklists, and post-install checklist.
- Has build script:
  - `installer/build-installer.ps1`
- Installer currently handles preflight, `.env` writing, `APP_KEY`, migrations, initial admin bootstrap, reports/logs, Windows/Linux service artifact generation, and packaged installer websocket acceptance.
- Remaining Kit-readiness gap: normalize the release package around the common root `release.json`, CLI unattended entrypoint, status command, install schema, status shape, and report/manifest shape.

Runtime commands and services:

- Web app through normal Laravel/PHP hosting.
- Main websocket daemon:
  - `php artisan realtime:serve`
- Optional separate media dispatcher:
  - `php artisan realtime:dispatch`
- Optional telemetry pruning:
  - `php artisan realtime:prune-usage-telemetry`

Health surface:

- `GET /api/health`
- `GET /api/ready`
- `GET /api/metrics`
- Websocket public URL normally maps to `/realtime`.

### PBB MapServer

Path: `C:\wamp64\www\mapserver`

Role:

- Plain PHP map tile proxy/cache service for the PBB ecosystem.
- Proxies and caches raster, vector, terrain, glyph, and POI tile requests.
- Provides lightweight health and operational status endpoints.
- Supports secured purge endpoints for individual cached tile assets.
- Uses vendored `helpers.pbb.ph` assets for the homepage/status UI.

Installer status:

- No dedicated app installer or package manifest was found.
- No Composer, Node, or Laravel build step is present.
- Current setup is documented in `README.md` and expects `index.php`, `config.php`, `.htaccess`, a writable cache/log storage path, PHP cURL, and environment variables.
- Kit Setup can support MapServer through a lightweight file/env/rewrite installer contract rather than the fuller Laravel app installer flow.

Runtime commands and services:

- No background daemon found.
- Runs through the web server as a plain PHP app.
- Requires Apache rewrite support for `/tiles/*` routes to reach `index.php`.

Configuration inputs:

- `TILES_CACHE_ROOT`
- `OSM_TILE_BASE_URL`
- `VECTOR_TILE_BASE_URL`
- `GLYPHS_BASE_URL`
- `TERRAIN_TILE_BASE_URL`
- `POI_BASE_URL`
- `TILES_LOG_FILE`
- `TILES_PURGE_TOKEN`
- `TILES_CURL_SSL_VERIFY`
- `TILES_CURL_CA_BUNDLE`
- `STADIAMAPS_API_KEY` when Stadia vector/glyph defaults are used
- `MAPTILER_API_KEY` when MapTiler terrain/POI defaults are used

Health and integration surface:

- `GET /tiles/health`
- `GET /health`
- `GET /api/status`
- Tile routes:
  - `GET /tiles/raster/{z}/{x}/{y}.png`
  - `GET /tiles/vector/{z}/{x}/{y}.pbf`
  - `GET /tiles/terrain/{z}/{x}/{y}.png`
  - `GET /tiles/glyphs/{fontstack}/{range}.pbf`
  - `GET /tiles/poi/{z}/{x}/{y}.pbf`
- Secured purge routes:
  - `POST /tiles/purge/raster/{z}/{x}/{y}.png`
  - `POST /tiles/purge/vector/{z}/{x}/{y}.pbf`
  - `POST /tiles/purge/terrain/{z}/{x}/{y}.png`
  - `POST /tiles/purge/glyphs/{fontstack}/{range}.pbf`
  - `POST /tiles/purge/poi/{z}/{x}/{y}.pbf`

### PBB Hotline

Path: `C:\wamp64\www\pbb\hotline`

Role:

- Emergency Hotline system covering citizen, operator, command, and admin workflows.
- Laravel 12, PHP `^8.2`, Vite frontend.
- Uses Realtime for discovery, presence, call flow, media notifications, citizen location, and product-query responses.
- Uses Relay for periodic SITREP delivery to city/municipality.
- Uses MapLibre and PBB MapServer tile/style sources for operator maps.
- Requires `ffmpeg`/`ffprobe` for media processing.

Installer status:

- Has release-installer planning doc:
  - `docs/pbb-github-release-installer-plan.md`
- Direction is GitHub Releases with versioned archives, `release.json`, checksums, dependency constraints, install notes, and a predictable contract for an ecosystem installer.
- App-level installer is planned but not yet as implemented as Realtime.

Runtime commands and services:

- Web app through normal Laravel/PHP hosting.
- Queue worker expected for async Laravel work if queue-backed processing is enabled.
- Media binaries resolved from:
  - `HOTLINE_FFMPEG_BINARY` / `HOTLINE_FFPROBE_BINARY`
  - repo-local `bin/ffmpeg/`
  - system `PATH`

Health/integration surface:

- Planned release manifest healthcheck example:
  - `/api/bootstrap?surface=public`
- Realtime backend callback:
  - `POST /api/internal/realtime/product-query`
- Realtime backend secret setting:
  - `realtime_backend_ingress_secret`

## Kit Setup Ownership Boundary

Kit Setup owns:

- Host-level preflight checks across all selected apps.
- Shared answers such as install root, PHP binary, database host, base domains, service naming, and node identity.
- Install ordering and dependency wiring.
- Calling each app installer in interactive or unattended mode.
- Collecting app installer status, manifests, logs, and health results.
- Creating or presenting OS service registration plans.
- Final smoke checks across the installed kit.

Each app installer owns:

- Its own release extraction and file layout.
- Its own `.env` keys and validation.
- Its own migrations and seed/bootstrap records.
- Its own admin/bootstrap users.
- Its own app-specific service artifacts.
- Its own repair and rollback guidance.
- Its own healthcheck semantics.

Kit Setup must not copy app internals directly unless an app explicitly exposes that as part of its installer contract.

## Required App Installer Contract

Every PBB app installer should expose the same high-level capabilities.

### Install Modes

Required modes:

- `fresh`: install a new app instance.
- `upgrade`: update an existing app while preserving environment, data, uploads, generated artifacts, and service identity.
- `repair`: re-run validation and fix missing generated state such as app key, migrations, admin bootstrap, service artifacts, or install manifest.

Optional mode:

- `uninstall`: remove app files and service registrations only when explicitly requested and backed by a clear backup/confirmation path.

### Required Files In Release Bundle

Each release bundle should include:

- `release.json`
- app source needed for production runtime
- production frontend assets, or a clear build step
- `composer.lock`
- `package-lock.json` / equivalent when Node build is required
- app installer entrypoint
- install scripts or action endpoints
- checksum file
- rollback notes
- post-install checklist

Release bundles should prefer including `vendor/` and built assets for offline-first hub installs unless bundle size becomes unmanageable.

### `release.json` Shape

```json
{
  "app": "pbb-realtime",
  "name": "PBB Realtime",
  "version": "1.0.0",
  "display_version": "v1-1.0.0",
  "release_date": "2026-05-15",
  "requires": {
    "php": ">=8.2",
    "mysql": ">=8.0",
    "node": "optional-if-assets-built",
    "apps": {
      "pbb-maestro": ">=1.0.0"
    }
  },
  "installer": {
    "entrypoint": "installer/index.php",
    "unattended_entrypoint": "installer/install-run.php",
    "schema": "installer/install.schema.json"
  },
  "health": {
    "http": "/api/health",
    "ready": "/api/ready"
  },
  "services": [
    {
      "name": "pbb-realtime",
      "command": "php artisan realtime:serve",
      "kind": "daemon",
      "required": true
    }
  ]
}
```

### Unattended Input

Each installer should accept a JSON config file with:

- mode
- install path
- app URL
- database credentials
- admin bootstrap
- service settings
- dependency endpoints and secrets
- generated-secret policy

Realtime already has a useful model in `installer/realtime-install.sample.json`.

### Installer Output

Each installer must write:

- `install-manifest.json`
- `install-report.json`
- `install.log`
- generated service artifacts
- rollback notes or rollback manifest

The report should include:

- app id and version
- install mode
- install path
- PHP binary used
- database target without password
- generated URLs
- created/updated service definitions
- healthcheck results
- warnings
- failed checks with remediation text

### Status Endpoint Or File

Each installer should support a machine-readable status output:

```json
{
  "app": "pbb-realtime",
  "status": "installed",
  "version": "1.0.0",
  "installed_at": "2026-05-15T00:00:00+08:00",
  "health": "healthy",
  "services": [
    {
      "name": "pbb-realtime-websocket",
      "status": "running"
    }
  ],
  "warnings": []
}
```

This can be exposed as a local installer API during install and persisted to disk after install.

## Cross-App Install Order

Recommended baseline order:

1. Host/runtime preflight.
2. Shared database provisioning or credential validation.
3. Maestro, because other services can register telemetry after it exists.
4. Relay, because product apps such as Hotline need store-and-forward delivery.
5. Realtime, because product apps depend on token admission, websocket transport, and backend event ingress.
6. MapServer, because Hotline operator maps depend on local tile/style sources.
7. Hotline, because it depends on Relay, Realtime, media binaries, and MapServer.
8. Final cross-app smoke checks.

Some deployments may install Realtime before Relay. Kit Setup should allow dependency-aware reordering, but it must surface missing dependencies before product app install.

## Shared Host Preflight

Kit Setup should validate these once before app installers run:

- OS: Windows or Linux.
- PHP CLI path and version.
- Required PHP extensions aggregated across selected apps:
  - `json`
  - `openssl`
  - `mbstring`
  - `fileinfo`
  - `zip`
  - `pdo`
  - app-specific database driver such as `pdo_mysql`
  - `curl`, required by MapServer tile proxying
- Composer availability unless app bundles include `vendor/`.
- Node/npm availability only if selected app bundles require local asset build.
- MySQL/MariaDB connectivity.
- Write access to selected install root.
- Web server document root rules.
- Port availability for websocket services and any local tile service.
- Ability to create service artifacts or instructions for the selected service manager.
- `ffmpeg`/`ffprobe` for Hotline when media processing is enabled.
- MapServer upstream API keys or fully configured non-key upstream URLs.
- Apache rewrite availability for MapServer-style `/tiles/*` routes when deployed on WAMP/Apache.

## Shared Service Standards

Every app service definition should declare:

- stable service id
- display name
- app id
- command
- working directory
- required environment file
- log path
- restart policy
- startup mode
- healthcheck
- Maestro telemetry app code, if applicable

Suggested service ids:

- `pbb-maestro-web`
- `pbb-maestro-scheduler`
- `pbb-relay-web`
- `pbb-relay-worker`
- `pbb-realtime-web`
- `pbb-realtime-websocket`
- `pbb-realtime-media-dispatcher`
- `pbb-mapserver-web`
- `pbb-hotline-web`
- `pbb-hotline-worker`
- `pbb-hotline-scheduler`

## Shared Secret Handling

Kit Setup may generate or collect shared secrets, but ownership must be explicit.

Recommended rules:

- App-specific secrets are generated by the app installer unless another app must know them.
- Cross-app secrets are generated once by Kit Setup, passed to the relevant app installers, and recorded only in protected local manifests.
- Plain-text secrets must not appear in install reports intended for casual operator viewing.
- Reports may show whether a secret is set, when it was generated, and which apps received it.

Known cross-app secrets/settings:

- Relay to Maestro telemetry token.
- Realtime to Maestro telemetry token.
- Hotline to Realtime backend ingress secret.
- Hotline Realtime project/client settings.
- Hotline Relay client credentials for SITREP.

## Health And Final Smoke Checks

Kit Setup final validation should include:

- Maestro web/API is reachable.
- Relay `/api/status` reports healthy or degraded with explained warnings.
- Relay worker service is running and can emit Maestro heartbeat when telemetry is configured.
- Realtime `/api/health` and `/api/ready` pass.
- Realtime websocket daemon is running and reachable at configured public websocket URL.
- Realtime process appears in Maestro when telemetry is configured.
- MapServer `/tiles/health` and `/api/status` pass.
- MapServer sample tile requests return `X-Cache: MISS` then `X-Cache: HIT` for a repeated request when upstream credentials are configured.
- Hotline public bootstrap passes.
- Hotline can reach configured Realtime backend/project settings.
- Hotline can reach Relay for SITREP delivery credentials.
- Hotline can resolve `ffmpeg` and `ffprobe`.

## Immediate Gaps

- MapServer needs a release manifest and lightweight installer/status/report contract; its current repo is a plain PHP app without a dedicated installer.
- Maestro needs an app-level installer or release bundle contract.
- Hotline needs its planned release installer implemented.
- Relay and Realtime installer contracts should be normalized so Kit Setup can call both with the same unattended/status/report pattern.
- Automatic OS service registration should be standardized; Realtime currently generates artifacts but does not fully register services.
