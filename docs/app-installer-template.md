# PBB App Installer Template

## Purpose

This guide defines the installer contract every PBB app should expose so `PBB Kit Setup` can orchestrate installs, upgrades, repairs, health checks, service registration, and cross-app wiring.

The app installer remains owned by the app. Kit Setup should not know app internals beyond this contract.

## Required Installer Capabilities

Each PBB app installer must support:

- interactive browser install
- unattended install from a JSON file
- preflight-only validation
- install execution
- repair execution
- upgrade execution
- machine-readable status output
- machine-readable install report
- health checks
- generated service artifacts or service registration instructions

Recommended modes:

- `fresh`
- `upgrade`
- `repair`
- `preflight`

Optional modes:

- `backup`
- `rollback`
- `uninstall`

## Release Bundle Layout

Recommended release archive:

```text
pbb-{app}-{version}.zip
|-- release.json
|-- checksums.sha256
|-- app/
|   |-- app files...
|   |-- public/
|   |-- vendor/
|   `-- .env.example
|-- installer/
|   |-- index.php
|   |-- install-run.php
|   |-- status.php
|   |-- schema/
|   |   `-- install.schema.json
|   |-- templates/
|   |   |-- windows-service.ps1
|   |   `-- systemd.service
|   `-- docs/
|      |-- post-install-checklist.md
|      |-- rollback.md
|      `-- troubleshooting.md
|-- tools/
|   `-- populate-initial-data.php
`-- docs/
   `-- release-notes.md
```

For plain PHP apps like MapServer, `app/` can simply contain `index.php`, `config.php`, `.htaccess`, assets, and storage placeholders.

For Laravel apps, app bundles should prefer including `vendor/` and production frontend assets for offline-friendly hub installation. If dependencies are intentionally not bundled, `release.json` must declare the required install-time build commands.

## `release.json`

Every bundle must include `release.json` at the archive root.

```json
{
  "schema_version": 1,
  "app": "pbb-realtime",
  "name": "PBB Realtime",
  "version": "1.0.0",
  "display_version": "v1-1.0.0",
  "release_date": "2026-05-15",
  "release_name": "Initial Installer Contract",
  "type": "laravel",
  "requires": {
    "os": ["windows", "linux"],
    "php": ">=8.2",
    "php_extensions": ["json", "openssl", "mbstring", "fileinfo", "zip", "pdo", "pdo_mysql"],
    "mysql": ">=8.0",
    "node": "not-required-assets-built",
    "composer": "not-required-vendor-bundled",
    "apps": {
      "pbb-maestro": ">=1.0.0"
    }
  },
  "installer": {
    "interactive": "installer/index.php",
    "unattended": "installer/install-run.php",
    "status": "installer/status.php",
    "schema": "installer/schema/install.schema.json",
    "tools": {
      "populate_initial_data": {
        "path": "tools/populate-initial-data.php",
        "description": "Load optional first-run operational data.",
        "config_section": "realtime.populate"
      }
    }
  },
  "health": {
    "http": "/api/health",
    "ready": "/api/ready",
    "status": "/api/status"
  },
  "services": [
    {
      "id": "pbb-realtime-websocket",
      "name": "PBB Realtime WebSocket",
      "kind": "daemon",
      "command": "php artisan realtime:serve",
      "required": true,
      "healthcheck": "/api/metrics"
    }
  ],
  "artifacts": {
    "install_manifest": "storage/app/installer/install-manifest.json",
    "install_report": "storage/app/installer/install-report.json",
    "install_log": "storage/app/installer/install.log"
  }
}
```

## Unattended Config

Kit Setup will pass each app installer a JSON file. Each app may add app-specific sections, but the common shape must be preserved.

```json
{
  "schema_version": 1,
  "mode": "fresh",
  "kit": {
    "run_id": "kit_20260515_001",
    "node_id": "hub-a",
    "operator": "installer-admin",
    "timezone": "Asia/Manila"
  },
  "app": {
    "install_path": "C:\\pbb\\apps\\realtime",
    "public_path": "C:\\wamp64\\www\\realtime",
    "app_url": "https://realtime.hub-a.pbb.ph",
    "app_env": "production",
    "app_debug": false
  },
  "database": {
    "driver": "mysql",
    "host": "127.0.0.1",
    "port": 3306,
    "database": "pbb_realtime",
    "username": "pbb_realtime",
    "password": "replace-with-real-password"
  },
  "admin": {
    "strategy": "create_if_missing",
    "name": "PBB Administrator",
    "email": "admin@pbb.local",
    "password": "replace-with-real-password",
    "must_change_password": false,
    "overwrite_existing": false
  },
  "services": {
    "target_os": "windows",
    "manager": "windows-service",
    "startup_mode": "automatic",
    "registration_mode": "generate"
  },
  "dependencies": {
    "maestro": {
      "base_url": "https://maestro.hub-a.pbb.ph",
      "app_code": "realtime",
      "telemetry_token": "replace-with-real-token"
    },
    "realtime": {
      "base_url": "https://realtime.hub-a.pbb.ph"
    },
    "relay": {
      "base_url": "https://relay.hub-a.pbb.ph"
    },
    "mapserver": {
      "base_url": "https://maps.hub-a.pbb.ph"
    }
  },
  "secrets": {
    "policy": "kit-generated",
    "values": {}
  },
  "options": {
    "run_migrations": true,
    "write_env": true,
    "cache_config": true,
    "validate_after_install": true
  }
}
```

Rules:

- Passwords and tokens may be omitted in interactive mode.
- Unattended mode must fail if required secrets are missing.
- Installers must not print raw secrets in normal output or reports.
- App-specific config belongs under an app-named section, for example `"realtime"`, `"relay"`, `"hotline"`, or `"mapserver"`.
- Optional first-run population config belongs under the app section as `"populate"` or a narrower app-owned key, for example `"mapserver.populate"`.

## First Admin Contract

Kit Setup should collect the first administrator password once and pass the same normalized `admin` block to every Laravel-style PBB app installer.

Standard first admin identity:

```json
{
  "admin": {
    "strategy": "create_if_missing",
    "name": "PBB Administrator",
    "email": "admin@pbb.local",
    "password": "provided-once-in-kit-setup",
    "password_env": "PBB_FIRST_ADMIN_PASSWORD",
    "must_change_password": false,
    "overwrite_existing": false
  }
}
```

Rules:

- `name` should default to `PBB Administrator`.
- `email` should default to `admin@pbb.local`.
- `password` is provided once in Kit Setup, then passed to each app installer through its unattended config.
- Kit Setup examples may use `password_env` so local harness runs can inject the password without storing it in the config file.
- Installers must reject blank, placeholder, or weak first-admin passwords.
- Installers must create the admin only when missing unless `overwrite_existing` is explicitly true.
- Installers must not print or persist the raw password in reports, manifests, logs, or status output.
- `must_change_password` defaults to false for offline municipal kit installs, but Kit Setup may set it to true for stricter deployment profiles.
- Population tools may attach roles, ownership, app registrations, or seed records to this admin identity, but core first-admin creation belongs in the installer flow.

## CLI Contract

Every installer should expose a non-interactive runner.

Recommended command:

```powershell
php installer/install-run.php --config C:\pbb\kit-runs\realtime.json --report C:\pbb\kit-runs\realtime-report.json
```

Required flags:

- `--config <path>`: unattended config JSON.
- `--report <path>`: where to write final report JSON.

Recommended flags:

- `--mode fresh|upgrade|repair|preflight`: override config mode.
- `--dry-run`: validate and plan without writing.
- `--no-service-register`: generate service artifacts only.
- `--verbose`: include detailed progress.

Exit codes:

- `0`: success.
- `1`: validation failed or install failed.
- `2`: config file is invalid.
- `3`: unsupported mode or platform.
- `4`: dependency unavailable.
- `5`: partial install completed but final validation failed.

The command must write the report file even on failure when possible.

## Browser Contract

Interactive app installers should expose:

- `/installer/`
- `/installer/api/preflight`
- `/installer/api/config`
- `/installer/api/install`
- `/installer/api/status`
- `/installer/api/report`

The browser UI may be app-specific, but the API responses should match the status and report shapes in this guide.

Kit Setup may open the app installer in an iframe or a browser tab for manual handoff, but unattended mode is the primary automation path.

## Installer State Machine

Recommended states:

```text
new
preflight_passed
configured
installing
installed
repairing
upgrading
failed
rolled_back
```

Installers should persist state so refreshes and retries do not corrupt the target.

Recommended state file:

```text
storage/app/installer/state.json
```

Plain PHP apps may use:

```text
storage/installer/state.json
```

## Preflight Checks

Every installer should implement `preflight` without making destructive changes.

Common checks:

- target OS supported
- PHP version supported
- required PHP extensions loaded
- install path is safe and writable
- public path is safe and writable
- web server route/rewrite requirements are met or can be generated
- database credentials work, when a database is required
- selected ports are available, when ports are required
- dependency app URLs are reachable, when required
- required secrets are present and not placeholders
- storage/log/cache directories can be created
- release bundle checksum verification passed

Preflight result shape:

```json
{
  "status": "passed",
  "checks": [
    {
      "id": "php.version",
      "label": "PHP version",
      "status": "passed",
      "message": "PHP 8.2.29 is supported."
    },
    {
      "id": "database.connection",
      "label": "Database connection",
      "status": "failed",
      "message": "Could not connect to MySQL.",
      "remediation": "Verify DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, and DB_PASSWORD."
    }
  ]
}
```

Check statuses:

- `passed`
- `warning`
- `failed`
- `skipped`

## Install Sequence

Recommended app installer sequence:

1. Load and validate config.
2. Verify release checksums.
3. Run preflight.
4. Create backup or restore point for upgrade/repair.
5. Prepare filesystem.
6. Write `.env` or app config.
7. Generate app keys/secrets owned by the app.
8. Install dependencies only if not bundled.
9. Run migrations or schema setup.
10. Seed/bootstrap the Kit-provided first admin and required app records.
11. Generate service artifacts.
12. Optionally register services if permitted.
13. Optimize/cache production runtime.
14. Run app health checks.
15. Write install manifest and report.

Installers should stop before mutation if preflight fails.

If failure happens after mutation, the report must say which steps completed and whether rollback is available.

## Install Manifest

The install manifest is a durable local record for future upgrade/repair orchestration.

```json
{
  "schema_version": 1,
  "app": "pbb-realtime",
  "name": "PBB Realtime",
  "version": "1.0.0",
  "installed_at": "2026-05-15T04:00:00+08:00",
  "install_mode": "fresh",
  "install_path": "C:\\pbb\\apps\\realtime",
  "public_path": "C:\\wamp64\\www\\realtime",
  "app_url": "https://realtime.hub-a.pbb.ph",
  "environment": "production",
  "database": {
    "driver": "mysql",
    "host": "127.0.0.1",
    "port": 3306,
    "database": "pbb_realtime",
    "username": "pbb_realtime"
  },
  "services": [
    {
      "id": "pbb-realtime-websocket",
      "manager": "windows-service",
      "registered": false,
      "artifact": "storage/app/installer/generated/pbb-realtime-websocket.ps1"
    }
  ],
  "health": {
    "last_checked_at": "2026-05-15T04:03:00+08:00",
    "status": "healthy"
  }
}
```

The manifest must not contain raw passwords or tokens.

## Install Report

The install report is run-specific and should be safe to show to operators.

```json
{
  "schema_version": 1,
  "app": "pbb-realtime",
  "version": "1.0.0",
  "run_id": "kit_20260515_001",
  "mode": "fresh",
  "status": "success",
  "started_at": "2026-05-15T04:00:00+08:00",
  "finished_at": "2026-05-15T04:03:00+08:00",
  "summary": "PBB Realtime installed successfully.",
  "steps": [
    {
      "id": "preflight",
      "status": "success",
      "message": "All required checks passed."
    },
    {
      "id": "migrate",
      "status": "success",
      "message": "Database migrations completed."
    }
  ],
  "urls": {
    "app": "https://realtime.hub-a.pbb.ph",
    "health": "https://realtime.hub-a.pbb.ph/api/health"
  },
  "services": [
    {
      "id": "pbb-realtime-websocket",
      "status": "artifact-generated",
      "message": "Service artifact generated; registration requires host approval."
    }
  ],
  "warnings": [],
  "errors": []
}
```

Step statuses:

- `pending`
- `running`
- `success`
- `warning`
- `failed`
- `skipped`

## Status Output

The status command or endpoint should be cheap and machine-readable.

```json
{
  "schema_version": 1,
  "app": "pbb-mapserver",
  "version": "1.0.0",
  "installed": true,
  "status": "healthy",
  "mode": "installed",
  "health": {
    "http": "ok",
    "ready": "ok",
    "details": {
      "cache_ready": true,
      "services_running": true
    }
  },
  "services": [],
  "warnings": []
}
```

Allowed top-level status values:

- `not-installed`
- `installing`
- `healthy`
- `degraded`
- `unhealthy`
- `failed`
- `unknown`

## Service Artifacts

Apps that need background processes should generate service artifacts for the host.

Service definition fields:

```json
{
  "id": "pbb-relay-worker",
  "name": "PBB Relay Worker",
  "kind": "worker",
  "command": "php artisan queue:work --queue=relay-deliveries,relay-handlers",
  "working_directory": "C:\\pbb\\apps\\relay",
  "env_file": "C:\\pbb\\apps\\relay\\.env",
  "log_file": "C:\\pbb\\apps\\relay\\storage\\logs\\worker.log",
  "startup_mode": "automatic",
  "restart_policy": "always",
  "healthcheck": {
    "type": "maestro",
    "app_code": "relay",
    "max_stale_seconds": 60
  }
}
```

Apps should support three service registration modes:

- `generate`: create scripts/unit files only.
- `register`: register services automatically when permissions allow.
- `manual`: print exact commands for an operator to run.

## Initial Data Population Tools

Apps may expose app-owned tools that prepare data after the installer has prepared the runtime. This is additive to the installer contract: install should make the app runnable, while data preparation should load optional operational data, default policy, seed records, reference data, or runtime cache.

Use this pattern when data loading is useful but should remain explicit, repeatable, and app-specific. Do not hide large data population inside `fresh` install unless the data is required for the app to boot.

Kit Setup treats population as a separate post-install data preparation workflow, not as a required installer stage. A failed population run should not imply that core installation failed.

Recommended tool layout:

```text
tools/
|-- populate-initial-data.php
|-- populate-reference-data.php
|-- populate-demo-data.php
`-- populate-runtime-cache.php
```

Apps can expose one general tool or multiple focused tools. `release.json` should declare them under `installer.tools`. The recommended metadata shape is:

```json
{
  "installer": {
    "tools": {
      "populate_initial_data": {
        "path": "tools/populate-initial-data.php",
        "description": "Load first-run app records and defaults.",
        "config_section": "hotline.populate",
        "required": false
      }
    }
  }
}
```

For compatibility, Kit Setup may also accept a simple string path such as:

```json
{
  "installer": {
    "tools": {
      "populate_tiles": "tools/populate-tiles.php"
    }
  }
}
```

Population tools should support the same automation basics as installers:

```powershell
php tools/populate-initial-data.php --config C:\pbb\kit-runs\hotline.populate.json --report C:\pbb\kit-runs\hotline.populate-report.json --dry-run
```

Required flags:

- `--config <path>`: app-specific population config JSON.
- `--report <path>`: where to write population report JSON.

Recommended flags:

- `--dry-run`: validate sources and plan without writing.
- `--mode initial|repair|refresh|demo`: app-owned population mode.
- `--verbose`: include detailed progress.

Population tools must be:

- idempotent by default
- safe to rerun without duplicate records
- explicit about whether they insert, update, skip, or overwrite
- able to validate source files before mutation
- careful not to print raw secrets
- app-owned; Kit Setup should not interpret internal source formats

Recommended config shape:

```json
{
  "schema_version": 1,
  "mode": "initial",
  "kit": {
    "run_id": "kit_20260515_001",
    "node_id": "hub-a",
    "timezone": "Asia/Manila"
  },
  "app": {
    "install_path": "C:\\pbb\\apps\\hotline",
    "app_url": "https://hotline.hub-a.pbb.ph"
  },
  "hotline": {
    "populate": {
      "enabled": true,
      "sources": {
        "incident_types": "C:\\pbb\\kit-data\\hotline\\incident-types.json",
        "teams": "C:\\pbb\\kit-data\\hotline\\teams.json",
        "operators": "C:\\pbb\\kit-data\\hotline\\operators.json"
      },
      "options": {
        "overwrite_existing": false,
        "include_demo_data": false
      }
    }
  }
}
```

Recommended population report shape:

```json
{
  "schema_version": 1,
  "app": "pbb-hotline",
  "tool": "populate_initial_data",
  "run_id": "kit_20260515_001",
  "mode": "initial",
  "dry_run": false,
  "status": "success",
  "started_at": "2026-05-15T12:55:00+08:00",
  "finished_at": "2026-05-15T12:56:00+08:00",
  "summary": "Initial Hotline data populated.",
  "sources": [
    {
      "id": "incident_types",
      "path": "C:\\pbb\\kit-data\\hotline\\incident-types.json",
      "status": "success"
    }
  ],
  "results": [
    {
      "id": "incident_types",
      "inserted": 12,
      "updated": 0,
      "skipped": 3,
      "failed": 0
    }
  ],
  "warnings": [],
  "errors": []
}
```

Suggested first-run population ownership:

- Relay: HQ identity, route profiles, queue policies, app registrations.
- Maestro: known app/process profiles, health checks, scheduler definitions.
- Realtime: project scopes, room policies, event/query/media settings, backend secrets.
- MapServer: tile cache population for barangays, bounding boxes, or center/radius areas.
- Hotline: incident types, response teams, roles, operators, dispatch defaults, SITREP routing.

Kit Setup should run population tools only when explicitly enabled in the kit config and launched from the data preparation workflow. The default installer path should be install first, health check second, final cross-app smoke last.

## App-Specific Sections

### Relay

Expected app-specific config:

```json
{
  "relay": {
    "hub_id": "hub-a",
    "hq_api_base_url": "https://hub.pbb.ph",
    "targets": [],
    "maestro_enabled": true
  }
}
```

Expected services:

- web
- queue worker for `relay-deliveries,relay-handlers`

Expected health:

- `/api/status`

### Maestro

Expected app-specific config:

```json
{
  "maestro": {
    "stale_threshold_seconds": 45,
    "telemetry_token_header": "X-Telemetry-Token"
  }
}
```

Expected services:

- web
- scheduler or scheduled command runner

Expected health:

- web/API reachability
- telemetry token creation and ingestion smoke when requested

### Realtime

Expected app-specific config:

```json
{
  "realtime": {
    "service_name": "PBB Realtime Hub A",
    "token_audience": "pbb-realtime",
    "token_signing_secret": "replace-with-secret",
    "trusted_issuers": ["hub-a.pbb.ph"],
    "public_websocket_url": "wss://realtime.hub-a.pbb.ph/realtime",
    "ws_bind_address": "127.0.0.1",
    "ws_port": 8080,
    "allowed_origins": ["https://hotline.hub-a.pbb.ph"]
  }
}
```

Expected services:

- web
- `php artisan realtime:serve`
- optional `php artisan realtime:dispatch`

Expected health:

- `/api/health`
- `/api/ready`
- websocket connection smoke

### MapServer

Expected app-specific config:

```json
{
  "mapserver": {
    "cache_root": "C:\\pbb\\data\\mapserver\\tiles",
    "log_file": "C:\\pbb\\logs\\mapserver\\tiles.log",
    "purge_token": "replace-with-secret",
    "raster_base_url": "https://tile.openstreetmap.org/{z}/{x}/{y}.png",
    "vector_base_url": "https://tiles.example.test/{z}/{x}/{y}.pbf",
    "glyphs_base_url": "https://tiles.example.test/fonts/{fontstack}/{range}.pbf",
    "terrain_base_url": "https://terrain.example.test/{z}/{x}/{y}.png",
    "poi_base_url": "https://poi.example.test/{z}/{x}/{y}.pbf",
    "curl_ssl_verify": true,
    "curl_ca_bundle": "",
    "populate": {
      "enabled": true,
      "source_geojson": "C:\\pbb\\kit-data\\psgc\\barangays.geojson",
      "brgy_code": "072217001",
      "zooms": "12-16",
      "types": "raster",
      "max_tiles": 5000,
      "dry_run": false
    }
  }
}
```

Expected services:

- web only

Expected health:

- `/tiles/health`
- `/api/status`
- repeated sample tile request should show cache behavior when upstream credentials are configured

Expected population:

- optional tile cache population through `tools/populate-tiles.php`
- source GeoJSON matched by PSGC/code or barangay/city
- bbox or center/radius fallback when polygon data is unavailable

### Hotline

Expected app-specific config:

```json
{
  "app": {
    "session_domain": "hotline.pbb.ph"
  },
  "hotline": {
    "normal_session_lifetime": 15,
    "citizen_session_lifetime": 43200,
    "ffmpeg_binary": "C:\\pbb\\bin\\ffmpeg\\ffmpeg.exe",
    "ffprobe_binary": "C:\\pbb\\bin\\ffmpeg\\ffprobe.exe",
    "realtime_backend_ingress_secret": "replace-with-secret",
    "sitrep_enabled": true
  }
}
```

Expected services:

- web
- queue worker if async processing is enabled
- scheduler if periodic SITREP is scheduled

Expected health:

- `/api/bootstrap?surface=public`
- Realtime configured project smoke
- Relay SITREP credential smoke
- `ffmpeg` / `ffprobe` resolution

## Kit Setup Orchestration Flow

Kit Setup should call app installers in this pattern:

1. Read each app `release.json`.
2. Validate release compatibility against selected kit profile.
3. Generate app unattended config JSON.
4. Run `preflight` for all apps.
5. Stop if any required preflight fails.
6. Run each app installer in dependency order.
7. Read each app report.
8. Read each app install manifest.
9. Register or present service artifacts.
10. Run app population tools when explicitly enabled.
11. Read each population report.
12. Run final cross-app health checks.
13. Write a Kit Setup report that links all app reports and population reports.

Recommended Kit Setup run folder:

```text
C:\pbb\kit-runs\kit_20260515_001\
|-- kit-config.json
|-- kit-report.json
|-- apps\
|   |-- maestro.config.json
|   |-- maestro.report.json
|   |-- maestro.populate.config.json
|   |-- maestro.populate-report.json
|   |-- relay.config.json
|   |-- relay.report.json
|   `-- ...
`-- logs\
   `-- kit-setup.log
```

## Minimum Acceptance Checklist For App Teams

Before an app installer is considered Kit Setup-ready:

- `release.json` exists and validates.
- unattended config schema exists.
- `preflight` can run without mutation.
- `fresh` install can run from JSON.
- `repair` can run from JSON.
- `upgrade` can preserve `.env`, data, uploads, and generated artifacts.
- report JSON is written on success and failure.
- manifest JSON is written on success.
- status endpoint or status command returns the common shape.
- service artifacts are generated for required background processes.
- health checks are app-owned and documented.
- secrets are never printed raw in reports or normal output.
- app docs name all required Kit Setup dependency inputs.
- optional population tools are declared in `release.json` when first-run data loading is supported.
- population tools support dry-run, idempotent rerun, source validation, and machine-readable reports.
