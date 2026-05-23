# Project Bantay Bayan Data Prep Contract Template

This document defines the app-team contract for `Project Bantay Bayan Data Prep`.

Data Prep is the standalone operator-facing workflow. It is not an app tool `--mode`. App-owned tools should continue to use execution modes such as `initial`, `repair`, `refresh`, and `demo`.

## Goal

Data Prep prepares a working local PBB node after Kit Setup has installed and verified the apps.

The workflow has two distinct app-owned mutation steps:

1. `Prepare Data`: generate or import app-owned data, profiles, tokens, references, or cache.
2. `Apply Settings`: write generated connection details, profile references, URLs, and token values into the client apps that need to use them.

Data Prep then runs `Verify` to confirm the prepared data and applied settings are usable.

## Setup And Data Prep Boundaries

Kit Setup and Data Prep are separate operator workflows with different success criteria.

Kit Setup owns installation readiness and app availability:

- Detect host platform dependencies such as PHP, Apache/Nginx, MySQL/MariaDB, OpenSSL, DNS, certificates, and required PHP extensions.
- Select trusted app bundles from the Kit Setup package manifest.
- Extract selected apps into the configured install base path.
- Run app installer preflight checks.
- Run app installers to create environment files, prepare release database schema, create required install-time records, and create the standard first administrator account.
- Configure local DNS, web-server includes, certificates, firewall intent, runtime services, and smoke checks.
- Finish with installed apps reachable and able to load their health or smoke endpoints.

Kit Setup must not run Data Prep population as part of normal installation. A failed Data Prep run must not mark the core app installation as failed.

Data Prep owns post-install node jump-start data:

- Discover installed or network-reachable PBB apps after Setup has completed.
- Check whether each app is ready for Data Prep.
- Prepare fixed reference data, generated profiles, tokens, scopes, policies, map tiles, and other non-transactional starting data.
- Apply generated connection settings to client apps through app-owned tools.
- Verify that prepared data and applied settings are usable by the apps.
- Report app-by-app Data Prep status in an operator-readable grid.

Data Prep must not install apps, alter package extraction, create the first administrator account, rewrite web-server configuration, change DNS/certificate/firewall setup, or repair baseline installation failures. If those checks fail, Data Prep should report that Setup or app installation must be repaired first.

Data Prep must also be gated before discovery. On startup, Data Prep should check the local Kit Setup completion marker and stop in a locked state if Setup has not completed. In the locked state, Data Prep must not display app rows, scan DNS, scan the local network, inspect old app paths, run readiness checks, or enable `Start Populating`.

The machine-level completion marker is:

```text
C:\ProgramData\PBB\KitSetup\install-state.json
```

The marker is written only after `finish-report` succeeds. It contains the setup operator, machine/network summary, selected runtime binaries, non-secret Hub identity/location data, selected app topology, setup artifacts, and `data_prep.allowed=true`. Runtime data should include the exact `php_binary`, `apache_binary`, and `mysql_binary` selected during Setup so Data Prep does not fall back to machine defaults. Hub data should include the Hub record id, Relay-facing hub id, name/code/domain/deployment/status, location codes such as `country_code`, `reg_code`, `prov_code`, `citymun_code`, and `brgy_code`, plus redacted uplink/source metadata when available. It must not contain passwords, tokens, database passwords, client secrets, telemetry tokens, private keys, or other secret values.

Boundary examples:

- Laravel database schema creation belongs to Kit Setup/app installer install mode.
- Standard first administrator creation belongs to Kit Setup/app installer install mode.
- Hotline incident categories, incident types, fields, resources, teams, and inventories belong to Data Prep.
- Maestro Relay/Realtime application profiles and telemetry tokens belong to Data Prep.
- Realtime Hotline client profile, policies, and project scopes belong to Data Prep.
- MapServer tile prepopulation belongs to Data Prep and should use the Hub deployment scope/location codes from `install-state.json`.
- Relay/Realtime/Hotline service endpoint and token settings generated from other apps belong to Data Prep `Apply Settings`.

## Operator Grid

The Data Prep UI should present one row per app and one column per workflow step.

Recommended columns:

```text
APP | DISCOVERY | READINESS | PREPARE DATA | APPLY SETTINGS | VERIFY
```

The primary operator action below the grid should be:

```text
Start Populating
```

The button runs the selected automated Data Prep sequence after dry-run planning has passed or after the operator confirms the planned changes.

## Required Release Metadata

Apps that support Data Prep should declare a `data_prep` block in `release.json`.

Recommended shape:

```json
{
  "data_prep": {
    "version": 1,
    "capabilities": {
      "prepare_data": true,
      "apply_settings": true,
      "verify": true
    },
    "tools": {
      "prepare_data": {
        "path": "tools/data-prep/prepare.php",
        "config_section": "hotline.data_prep.prepare"
      },
      "apply_settings": {
        "path": "tools/data-prep/apply-settings.php",
        "config_section": "hotline.data_prep.apply_settings"
      },
      "verify": {
        "path": "tools/data-prep/verify.php",
        "config_section": "hotline.data_prep.verify"
      }
    }
  }
}
```

Existing app population tools may be mapped to `prepare_data` during migration. For example, an existing `tools/populate-initial-data.php` can satisfy `prepare_data` while the app team adds `apply_settings` and `verify`.

## Shared Install Defaults

Some app-owned values are the same for every local installation of that app. These values must not become Admin Inputs unless the site operator is expected to choose or rotate them per node.

When an app needs to carry shared install-wide keys, provider tokens, or default service credentials to every installation, the app team should provide them inside the trusted release bundle at:

```text
resources/kit-setup/shared-install-defaults.json
```

Recommended shape:

```json
{
  "schema_version": 1,
  "app_id": "pbb-mapserver",
  "values": {
    "mapserver": {
      "stadiamaps_api_key": "actual-shared-stadia-key",
      "maptiler_api_key": "actual-shared-maptiler-key"
    },
    "shared": {
      "secrets": {
        "values": {
          "stadiamaps_api_key": "actual-shared-stadia-key",
          "maptiler_api_key": "actual-shared-maptiler-key"
        }
      }
    }
  },
  "redaction": {
    "stadiamaps_api_key": "secret",
    "maptiler_api_key": "secret"
  }
}
```

Rules:

- The defaults file is for app-owned values that should be carried to all installations unchanged.
- The file must be included in `checksums.sha256` and in the canonical app bundle.
- The file must not be referenced as an operator form field.
- App reports must not print raw values from this file. Reports may show `configured=true`, value length, or a short hash.
- App teams must not commit live provider keys to public repositories unless the repository is explicitly approved for that secret class. A packaging step may inject the defaults file into the trusted release bundle.
- Kit Setup should merge only allowlisted keys from this file into generated app config. Unknown keys should be ignored and reported as warnings.
- If a value is truly site-specific, belongs to the operator, or must vary per node, it should be supplied by a protected deployment config or environment variable instead of this bundle defaults file.

For MapServer, the current shared install defaults are:

```json
{
  "values": {
    "mapserver": {
      "stadiamaps_api_key": "...",
      "maptiler_api_key": "..."
    },
    "shared": {
      "secrets": {
        "values": {
          "stadiamaps_api_key": "...",
          "maptiler_api_key": "..."
        }
      }
    }
  }
}
```

MapServer owns writing these into its installed `.env` as `STADIAMAPS_API_KEY` and `MAPTILER_API_KEY`. Kit Setup only carries the values from the trusted bundle/default config into MapServer's install config.

## MapServer Coverage Input

Kit Setup passes MapServer coverage as generated `mapserver.data_prep.prepare` config derived from the non-secret Hub data in `install-state.json`. MapServer owns resolving the scope/code into boundary geometry and tile coverage during `Prepare Data`.

Recommended generated shape:

```json
{
  "mapserver": {
    "data_prep": {
      "prepare": {
        "enabled": true,
        "dry_run": false,
        "source": "hub",
        "deployment_scope": "barangay",
        "base_url": "https://mapserver.pbb.ph",
        "reg_code": "00",
        "prov_code": "0000",
        "citymun_code": "000000",
        "brgy_code": "000000000",
        "barangay_code": "000000000",
        "psgc_code": "000000000",
        "codes": {
          "reg_code": "00",
          "prov_code": "0000",
          "citymun_code": "000000",
          "brgy_code": "000000000"
        }
      }
    }
  }
}
```

Deployment scope mapping:

- `barangay` uses `brgy_code`, with aliases `barangay_code` and `psgc_code`.
- `city`, `citymun`, or `municipality` uses `citymun_code`, with aliases `city_code` and `municipality_code`.
- `province` uses `prov_code`, with alias `province_code`.
- `region` uses `reg_code`, with alias `region_code`.
- `other` is passed as `other`; MapServer treats it as barangay fallback.

Kit may pass explicit `boundary_geojson`, `source_geojson`, `bbox`, or `center`/`radius_km` only when a future Hub contract provides that geometry. Without explicit geometry, MapServer should resolve the PSGC-style code through its own boundary resolver.

## Tool Layout

Recommended app layout:

```text
resources/data/{app}/
tools/data-prep/
|-- prepare.php
|-- apply-settings.php
`-- verify.php
```

Apps may keep existing population scripts for compatibility, but new Data Prep work should move toward the explicit three-tool layout.

## Common CLI Contract

Each Data Prep tool should accept:

```powershell
php <tool> --mode initial --config <config.json> --report <report.json> --dry-run
```

Required flags:

- `--mode initial|repair|refresh|demo`
- `--config <path>`
- `--report <path>`

Recommended flags:

- `--dry-run`
- `--verbose`

Mode meanings:

- `initial`: first jump-start data after install.
- `repair`: reconcile missing or invalid prepared data.
- `refresh`: repeatable updates for generated or source-backed data.
- `demo`: sample/demo data only when explicitly requested.

Do not require `--mode data-prep`. Data Prep is the workflow name, not the app tool execution mode.

## Step 1: Prepare Data

`Prepare Data` creates or validates app-owned data.

Examples:

- MapServer prepares barangay/current-location tile cache.
- Maestro creates Relay and Realtime application profiles and token records.
- Realtime creates Hotline client profile, policies, and project scopes.
- Hotline imports reference data such as incident categories, incident types, fields, resource categories, resources, default incident resources, team categories, teams, and team inventories.

Prepare reports may expose generated output references but must not expose raw secrets.

## Step 2: Apply Settings

`Apply Settings` writes generated values into the client app that consumes them.

Examples:

- Relay stores its Maestro telemetry token and Maestro endpoint settings.
- Realtime stores its Maestro telemetry token and Maestro endpoint settings.
- Hotline stores its Realtime client credentials and Realtime endpoint settings.
- Hotline stores Relay client or integration settings when Relay Data Prep support is ready.

MapServer currently only serves tile URLs for Hotline. Hotline already has `public/maps/operator-vector-style.json`, so MapServer may have no `Apply Settings` work for the initial scope.

Apply Settings must be owned by the app whose settings are being changed. Data Prep orchestrates and passes generated values, but it should not edit private app settings directly when an app-owned tool exists.

## Step 3: Verify

`Verify` confirms the final state is usable.

Examples:

- Maestro has Relay and Realtime app profiles with active token hashes.
- Maestro has fresh authenticated heartbeats from services whose tokens/settings were applied during Data Prep.
- Realtime has the Hotline client, policies, and project scopes.
- Hotline has the required reference data and required external service settings.
- MapServer tile endpoints or tile cache outputs are usable.

Verification reports must be safe to show operators.

### Maestro Heartbeat Verification

Installer smoke checks are infrastructure checks only. They confirm that app URLs, local runtime services, and websocket/proxy routes respond before app-to-app authentication has been established. Installer smoke must not require Maestro telemetry tokens or authenticated heartbeat visibility.

Data Prep `Verify` may use Maestro heartbeat state after `Apply Settings` has created profiles/tokens and written them into client apps. This is the correct phase to confirm that Relay and Realtime are authenticated, running, and visible to Maestro.

Recommended flow:

1. Maestro `Prepare Data` creates or refreshes application profiles and active token records for Relay and Realtime.
2. Relay and Realtime `Apply Settings` store the Maestro endpoint, app code, environment, and telemetry token through app-owned tools.
3. Kit Data Prep restarts only affected runtime services after settings are applied.
4. Data Prep `Verify` asks Maestro whether the expected applications have fresh heartbeats.

Current restart expectations:

- Relay requires `pbb-relay-worker` restart after `Apply Settings` because Maestro telemetry settings are written to `.env` and Laravel must rebind the telemetry implementation.
- Realtime does not strictly require restart because its services read Maestro telemetry settings from the database on each heartbeat/event send, but Kit may restart `pbb-realtime-websocket` and `pbb-realtime-media-dispatcher` to force immediate `worker.started` and fresh heartbeat records before verification.

Recommended Maestro verification response:

```json
{
  "schema_version": 1,
  "status": "success",
  "freshness_threshold_seconds": 60,
  "applications": [
    {
      "code": "relay",
      "environment": "production",
      "heartbeat_status": "fresh",
      "last_seen_at": "2026-05-22T10:20:30Z",
      "age_seconds": 8
    },
    {
      "code": "realtime",
      "environment": "production",
      "heartbeat_status": "fresh",
      "last_seen_at": "2026-05-22T10:20:31Z",
      "age_seconds": 7
    }
  ],
  "warnings": [],
  "errors": []
}
```

Heartbeat statuses:

- `fresh`: the expected app/environment reported within the freshness threshold.
- `stale`: the expected app/environment exists but the last heartbeat is older than the threshold.
- `missing`: no heartbeat has been received for the expected app/environment.
- `rejected`: telemetry was attempted but token/app/environment validation failed.

Default freshness threshold should be `60` seconds unless Maestro publishes a stricter app-specific threshold. Reports must not expose raw telemetry tokens.

## Report Shape

Every tool should write a machine-readable JSON report.

Recommended shape:

```json
{
  "schema_version": 1,
  "app": "pbb-realtime",
  "tool": "data_prep_prepare",
  "mode": "initial",
  "dry_run": true,
  "status": "success",
  "summary": "Realtime Hotline client data planned.",
  "sources": [],
  "results": [
    {
      "id": "hotline_client",
      "type": "client_profile",
      "action": "insert",
      "status": "success",
      "inserted": 1,
      "updated": 0,
      "skipped": 0,
      "failed": 0
    }
  ],
  "outputs": [
    {
      "id": "hotline_realtime_client",
      "kind": "client_credentials",
      "target_app": "pbb-hotline",
      "secret_ref": "runtime.realtime.hotline_client_token",
      "status": "generated"
    }
  ],
  "warnings": [],
  "errors": []
}
```

Reports must not print raw tokens, passwords, private keys, database passwords, or other secret values.

## Bundle And Setup.exe Requirement

Data Prep contract changes must be shipped through the trusted Kit Setup package flow.

App teams must use the standard release bundle filename:

```text
pbb-{app-code}-{semver}.zip
```

Examples:

```text
pbb-mapserver-1.0.0.zip
pbb-maestro-1.0.0.zip
pbb-realtime-1.0.0.zip
pbb-relay-1.1.0.zip
pbb-hotline-5.6.1.zip
```

Rules:

- `{app-code}` must match the canonical app code used in `release.json` and Kit Setup manifests, for example `mapserver`, `maestro`, `realtime`, `relay`, or `hotline`.
- `{semver}` must match the release version declared in `release.json`.
- Do not publish operator-facing bundles with suffixes such as `-baseline`, `-fixed`, `-test`, `-data-prep`, `-hotfix`, `-copy`, or experimental branch names.
- If a package is rebuilt for Data Prep changes, produce a new canonical release bundle and update the manifest checksum for that exact file.
- Experimental or diagnostic ZIPs may exist only outside the trusted bundled package directory. They must not be placed where Kit Setup package builds can include them.
- Kit Setup must package only manifest-selected canonical bundles. Broad globs such as `packages/**/*` must not be used for installer resources because they can include stale bundles.

When an app team adds or changes Data Prep metadata, tools, source data, reports, or checksums, the team must:

1. Regenerate the app package bundle.
2. Regenerate or update package checksums and release metadata.
3. Provide the updated bundle to Kit Setup.
4. Update Kit Setup's package manifest for the new bundle hash.
5. Rebuild the Kit Setup desktop installer package so `setup.exe` includes the updated bundled app package.

Data Prep cannot rely on unbundled local source changes for operator use. Local source checks are acceptable for development verification only; the operator-facing `setup.exe` must carry the trusted bundles.

## Initial App Scope

Current initial scope:

- MapServer: prepare current barangay/location tile cache.
- Maestro: prepare Relay and Realtime application profiles and runtime-injected telemetry tokens.
- Realtime: prepare Hotline client profile, policies, and project scopes.
- Hotline: prepare fixed reference data only.
- Relay: no confirmed Data Prep tool yet.

Hotline operator accounts, dispatch defaults, and demo data are out of the initial Data Prep scope unless a later workflow explicitly enables them.

## Migration Guidance

For the first implementation pass:

1. Treat current `populate` tools as `Prepare Data`.
2. Add explicit `Apply Settings` tools where generated server-side values must be stored by client apps.
3. Add explicit `Verify` tools after settings application.
4. Keep dry-run support for every mutating step.
5. Keep reports secret-safe and operator-readable.
