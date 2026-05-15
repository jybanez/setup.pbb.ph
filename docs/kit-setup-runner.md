# Kit Setup Runner

## Purpose

`bin/kit-setup.php` is the first executable orchestration skeleton for PBB Kit Setup.

It currently supports:

- reading a Kit Setup config JSON
- validating required top-level config fields
- detecting baseline platform readiness, host tools, and service state
- verifying Kit-owned package manifest entries before install
- planning local DNS records for Technitium
- validating SSL certificate/key material and generating Apache vhost includes
- discovering app release directories through `release.json`
- validating each app has an unattended installer
- resolving node identity and location from `hub.pbb.ph`
- ordering apps by `depends_on`
- generating per-app unattended config files
- verifying app `checksums.sha256` files during planning
- running app installers in `preflight` or install mode
- running post-install data preparation tools through the separate `populate` action
- collecting installer status output, install manifests, service declarations, and app reports
- aggregating app reports into `kit-report.json`

## Commands

Use the explicit WAMP PHP 8.2 binary. The default `php` on this workstation may point to PHP 5.6.

Detect platform:

```powershell
& "C:\wamp64\bin\php\php8.2.29\php.exe" "C:\wamp64\www\pbb\kit-setup\bin\kit-setup.php" --config "C:\wamp64\www\pbb\kit-setup\examples\kit-config.local-all.example.json" --action detect
```

`detect` writes `platform-report.json`. It checks required PHP extensions, configured paths, OpenSSL, Apache, MySQL/MariaDB, ffmpeg, ffprobe, configured services such as WAMP's `wampapache64` and `wampmariadb64`, and optional port checks.

Port checks can assert that infrastructure ports are already open, or that app bind ports are still available:

```json
{
  "platform": {
    "port_checks": [
      { "name": "http", "host": "127.0.0.1", "port": 80, "expect": "open" },
      { "name": "realtime_ws", "host": "127.0.0.1", "port": 8080, "expect": "available" }
    ]
  }
}
```

Resolve Hub identity:

```powershell
$env:PBB_HUB_TOKEN = "<hub token from hub.pbb.ph>"
& "C:\wamp64\bin\php\php8.2.29\php.exe" "C:\wamp64\www\pbb\kit-setup\bin\kit-setup.php" --config "C:\wamp64\www\pbb\kit-setup\examples\kit-config.local-all.example.json" --action hub-resolve
```

Verify trusted app packages:

```powershell
& "C:\wamp64\bin\php\php8.2.29\php.exe" "C:\wamp64\www\pbb\kit-setup\bin\kit-setup.php" --config "C:\wamp64\www\pbb\kit-setup\examples\kit-config.local-all.example.json" --action prepare-packages
```

Plan local DNS records:

```powershell
& "C:\wamp64\bin\php\php8.2.29\php.exe" "C:\wamp64\www\pbb\kit-setup\bin\kit-setup.php" --config "C:\wamp64\www\pbb\kit-setup\examples\kit-config.local-all.example.json" --action dns-plan
```

Apply local DNS records when `dns.update_mode=apply`:

```powershell
$env:PBB_TECHNITIUM_TOKEN = "<technitium api token>"
& "C:\wamp64\bin\php\php8.2.29\php.exe" "C:\wamp64\www\pbb\kit-setup\bin\kit-setup.php" --config "C:\wamp64\www\pbb\kit-setup\examples\kit-config.local-all.example.json" --action dns-apply
```

Verify local DNS resolution:

```powershell
& "C:\wamp64\bin\php\php8.2.29\php.exe" "C:\wamp64\www\pbb\kit-setup\bin\kit-setup.php" --config "C:\wamp64\www\pbb\kit-setup\examples\kit-config.local-all.example.json" --action dns-verify
```

Plan SSL and Apache vhosts:

```powershell
& "C:\wamp64\bin\php\php8.2.29\php.exe" "C:\wamp64\www\pbb\kit-setup\bin\kit-setup.php" --config "C:\wamp64\www\pbb\kit-setup\examples\kit-config.local-all.example.json" --action ssl-plan
```

Apply the generated Apache include when `ssl.web_server_update_mode=apply`:

```powershell
& "C:\wamp64\bin\php\php8.2.29\php.exe" "C:\wamp64\www\pbb\kit-setup\bin\kit-setup.php" --config "C:\wamp64\www\pbb\kit-setup\examples\kit-config.local-all.example.json" --action ssl-apply
```

Check remote app dependencies:

```powershell
& "C:\wamp64\bin\php\php8.2.29\php.exe" "C:\wamp64\www\pbb\kit-setup\bin\kit-setup.php" --config "C:\wamp64\www\pbb\kit-setup\examples\kit-config.local-all.example.json" --action remote-check
```

Run final app URL smoke checks:

```powershell
& "C:\wamp64\bin\php\php8.2.29\php.exe" "C:\wamp64\www\pbb\kit-setup\bin\kit-setup.php" --config "C:\wamp64\www\pbb\kit-setup\examples\kit-config.local-all.example.json" --action smoke-check
```

Generate final handoff report:

```powershell
& "C:\wamp64\bin\php\php8.2.29\php.exe" "C:\wamp64\www\pbb\kit-setup\bin\kit-setup.php" --config "C:\wamp64\www\pbb\kit-setup\examples\kit-config.local-all.example.json" --action finish-report
```

Generate the visual stage report:

```powershell
& "C:\wamp64\bin\php\php8.2.29\php.exe" "C:\wamp64\www\pbb\kit-setup\bin\kit-setup.php" --config "C:\wamp64\www\pbb\kit-setup\examples\kit-config.local-all.example.json" --action stage-report
```

Plan only:

```powershell
& "C:\wamp64\bin\php\php8.2.29\php.exe" "C:\wamp64\www\pbb\kit-setup\bin\kit-setup.php" --config "C:\wamp64\www\pbb\kit-setup\examples\kit-config.stub.json" --action plan
```

The `plan` action now includes an `installation_plan` block in `kit-report.json` for the visual review screen. It combines safe planner outputs for platform readiness, trusted packages, DNS records, SSL/vhost coverage, remote dependencies, selected local apps, disabled apps, and remote apps.

Run preflight:

```powershell
& "C:\wamp64\bin\php\php8.2.29\php.exe" "C:\wamp64\www\pbb\kit-setup\bin\kit-setup.php" --config "C:\wamp64\www\pbb\kit-setup\examples\kit-config.stub.json" --action preflight
```

Run install:

```powershell
& "C:\wamp64\bin\php\php8.2.29\php.exe" "C:\wamp64\www\pbb\kit-setup\bin\kit-setup.php" --config "C:\wamp64\www\pbb\kit-setup\examples\kit-config.stub.json" --action install
```

Run enabled data preparation tools after installation:

```powershell
& "C:\wamp64\bin\php\php8.2.29\php.exe" "C:\wamp64\www\pbb\kit-setup\bin\kit-setup.php" --config "C:\wamp64\www\pbb\kit-setup\examples\kit-config.stub.json" --action populate
```

Optional flags:

- `--run-id <id>`: stable run id for repeatable smoke tests.
- `--run-dir <path>`: override the output folder.
- `--app <app-id>`: limit `plan`, `preflight`, `install`, or `populate` to one enabled local app for retry/resume.

## Local All-App Example

`examples/kit-config.local-all.example.json` points at the current sibling PBB projects:

- `C:\wamp64\www\mapserver`
- `C:\wamp64\www\pbb\maestro`
- `C:\wamp64\www\pbb\realtime`
- `C:\wamp64\www\pbb\relay`
- `C:\wamp64\www\pbb\hotline`

Before running it for a real install, replace all `REPLACE_WITH_...` placeholders. The shared first administrator is supplied once under `shared.admin` and is injected into app configs that do not override `admin`:

- name: `PBB Administrator`
- email: `admin@pbb.local`
- password: supplied by the installing admin during Kit Setup

Population sections are disabled by default in the example. Set an app's `*.populate.enabled` value to `true` only when the separate data preparation workflow should invoke that app's data tool. Population is not part of the required install path.

## Hub Resolution

Kit Setup can resolve the node identity from Hub before generating app configs. The visual installer should ask the installer for:

- Hub ID
- Hub token

The runner calls:

```http
GET https://hub.pbb.ph/api/hubs/{hub}
Authorization: Bearer <hub-token>
```

The Hub response is mapped into the Kit Setup config:

- `kit.hub_record_id`: numeric HQ hub record id
- `kit.node_id`: `relay_hub_id`, the stable Relay-facing identity
- `kit.node_name`: Hub display name
- `kit.deployment`: deployment level such as `barangay`, `city`, or `province`
- `kit.domain`: Hub domain
- `kit.location_codes`: PSGC-style location codes
- `shared.hub`: Hub details, uplinks, and sources for apps that need topology context

`hub-resolve` writes:

```text
storage/runs/{run-id}/
|-- hub-report.json
`-- hub-resolved-config.json
```

`hub-report.json` is safe to review because it includes only token metadata, not the bearer token. `hub-resolved-config.json` is generated runtime output and stays under ignored `storage/`.

## Output Layout

Default run output:

```text
storage/runs/{run-id}/
|-- kit-report.json
|-- apps/
|   |-- {app}.config.json
|   `-- {app}.report.json
`-- logs/
```

Each app entry in `kit-report.json` includes the generated config path, report path, checksum verification result, release metadata, installer command output, and any collected app artifacts.

Each action also updates:

```text
storage/runs/{run-id}/checkpoints.json
```

The checkpoint file records the latest status and report path per action. Reusing the same `--run-id` lets a desktop UI or operator see which actions already passed, failed, or were skipped before rerunning a stage.

When `--app` is used with an app action, the checkpoint records `app_filter` and only that app appears in the generated `kit-report.json`.

The runner does not yet register services or perform cross-app HTTP smoke checks. Those are next layers after the installer harness and app contracts stabilize.

## First Admin Password

Kit Setup can keep the shared first-admin password out of checked-in config by using:

```json
{
  "shared": {
    "admin": {
      "name": "PBB Administrator",
      "email": "admin@pbb.local",
      "password_env": "PBB_FIRST_ADMIN_PASSWORD"
    }
  }
}
```

During a run, `password_env` is resolved into the per-app generated `admin.password` value. The generated app config stays under ignored `storage/runs/`.

## Shared Secrets

Kit Setup resolves known cross-app secret placeholders at run time. The checked-in config may keep placeholders such as:

```json
{
  "shared": {
    "secrets": {
      "policy": "kit-provided",
      "values": {
        "realtime_backend_ingress_secret": "REPLACE_WITH_REALTIME_BACKEND_SECRET",
        "relay_shared_secret": "REPLACE_WITH_RELAY_SHARED_SECRET"
      }
    }
  }
}
```

When a value is blank or still uses a `REPLACE_WITH_...` placeholder, Kit Setup generates a run-scoped secret, replaces matching placeholders in the generated per-app configs, and writes:

```text
storage/runs/{run-id}/
|-- secret-report.json
`-- secrets/
    `-- kit-secrets.json
```

`secret-report.json` contains only names, lengths, and short hashes. `kit-secrets.json` contains raw values and remains under ignored runtime storage.

## Package Preparation

Kit Setup verifies trusted app package sources before app installers run. The current action is non-mutating when `packages.dry_run` is true:

```json
{
  "packages": {
    "source": "bundled",
    "base_path": "C:\\wamp64\\www\\pbb\\kit-setup\\packages",
    "manifest_path": "C:\\wamp64\\www\\pbb\\kit-setup\\packages\\packages.local.example.json",
    "dry_run": true,
    "signature_policy": "warn"
  }
}
```

The manifest lists Kit-owned package sources by app id. The local harness uses trusted directory sources; production bundles should use signed archives.

Signed archive entries use detached OpenSSL SHA-256 signatures:

```json
{
  "app_id": "pbb-realtime",
  "version": "1.0.0",
  "source_type": "zip",
  "path": "C:\\pbb\\packages\\pbb-realtime.zip",
  "sha256": "archive-sha256-hex",
  "trusted": true,
  "signature_algorithm": "openssl-sha256",
  "signature_path": "C:\\pbb\\packages\\pbb-realtime.zip.sig",
  "public_key_path": "C:\\pbb\\packages\\pbb-release-public.pem"
}
```

`prepare-packages` writes `package-report.json` with the selected apps, trusted source path, target path, release metadata, checksum status, signature status, and extraction state. It fails if a selected local app has no manifest entry, is untrusted, has mismatched release metadata, or fails checksum verification.

For archive packages with `packages.dry_run=false`, Kit Setup extracts the ZIP into `storage/runs/{run-id}/packages/{app}`, verifies `release.json` and `checksums.sha256` after extraction, backs up an existing target under `storage/runs/{run-id}/package-backups`, and copies the verified package into the target path. Deployment is refused when the target is outside the configured Kit install roots.

## DNS Plan

`dns-plan` derives local DNS records from selected local apps, configured standard domains, the relay alias, and `machine.ip_address`.

It writes:

```text
storage/runs/{run-id}/dns-plan.json
```

The report includes A or AAAA `upsert` records for:

- `mapserver.pbb.ph`
- `maestro.pbb.ph`
- `relay.pbb.ph`
- `realtime.pbb.ph`
- `hotline.pbb.ph`
- the Hub-provided relay alias when configured

Technitium token material is not written to the report; the report only says whether a token is configured.

`dns-apply` embeds the DNS plan and only calls Technitium when `dns.update_mode` is set to `apply`. It uses the Technitium `/api/zones/records/add` endpoint with bearer-token authentication, `overwrite=true`, and form parameters for `domain`, `zone`, `type`, `ttl`, and `ipAddress`.

`dns-verify` reruns the DNS plan, resolves each planned hostname from the installer host, and compares the resolved address list with `machine.ip_address`. By default it uses the system resolver. Set `dns.verify_nameserver` to force `nslookup` against a specific DNS server:

```json
{
  "dns": {
    "verification_mode": "system",
    "verify_nameserver": "192.168.254.1"
  }
}
```

The verification report is written to `dns-verify.json`. A failure means the DNS client path does not yet resolve to the machine IP, even if Technitium accepted the API write.

## SSL And Web Server Plan

`ssl-plan` validates the configured certificate and private key without writing private key material to reports. The default file names are derived from `ssl.certificate_root`:

```json
{
  "ssl": {
    "certificate_root": "C:\\wamp64\\certs\\pbb.ph",
    "certificate_file": "C:\\wamp64\\certs\\pbb.ph\\pbb.ph.crt",
    "private_key_file": "C:\\wamp64\\certs\\pbb.ph\\pbb.ph.key",
    "chain_file": "C:\\wamp64\\certs\\pbb.ph\\pbb.ph.fullchain.crt",
    "pem_upload_path": "",
    "write_extracted_files": false,
    "web_server_update_mode": "plan-only",
    "private_key_required": true
  }
}
```

It writes:

```text
storage/runs/{run-id}/
|-- ssl-plan.json
`-- web/
    `-- pbb-apache-vhosts.conf
```

When `ssl.pem_upload_path` is set, Kit Setup inspects the PEM bundle and reports how many certificate blocks it contains and whether a private key block exists. When `ssl.write_extracted_files=true`, it writes the leaf certificate, private key, and fullchain to the configured output paths. The private key content is never written to reports.

The report includes certificate subject, validity dates, SAN DNS names, fingerprint, key validity, cert/key match status, planned hostname coverage, generated vhost paths, and whether an Apache include apply step is supported. `ssl-apply` is guarded by `ssl.web_server_update_mode`; it skips writes unless the mode is `apply`, then copies the generated Apache include to `paths.apache_include_output` with a backup of any existing file. After a successful write, Kit Setup runs an Apache config test with the configured `platform.apache_binary` and records the result under `apply.config_test`. Web-server reload is still manual.

## Remote Dependency Check

`remote-check` handles split-machine deployments. Apps with `"install_scope": "remote"` are not installed locally, but Kit Setup can still verify they are reachable before installing dependent local apps.

Remote apps may declare:

```json
{
  "install_scope": "remote",
  "app_url": "https://realtime.pbb.ph",
  "remote": {
    "health_url": "https://realtime.pbb.ph/installer/status.php",
    "expected_http_statuses": [200],
    "timeout_seconds": 5,
    "auth": {
      "type": "bearer",
      "token_env": "PBB_REMOTE_REALTIME_TOKEN"
    }
  }
}
```

The report writes DNS resolution results, credential configuration status, HTTP status, and a pass/warning/fail state for each remote app. If `remote.health_url` is omitted, Kit Setup checks `app_url`. Supported credential modes are `bearer` and `header`; raw token values are not written to reports.

## Smoke Check

`smoke-check` verifies the final app links before handoff. For each enabled app, Kit Setup resolves the host from `app_url` and performs an HTTP GET. Apps may override the smoke URL:

```json
{
  "id": "pbb-hotline",
  "app_url": "https://hotline.pbb.ph",
  "smoke": {
    "url": "https://hotline.pbb.ph/health",
    "expected_http_statuses": [200],
    "timeout_seconds": 5
  }
}
```

The report writes `smoke-check.json` with DNS results, HTTP status, expected status codes, and a pass/warning/fail state for each enabled app.

## Visual Stage Report

`stage-report` writes `stage-report.json` with one object for each step in the 12-step non-technical setup flow. It is non-destructive: it uses the safe planners and checks, then marks install and finish stages as `pending` until the administrator confirms.

The stage statuses are intended for a wizard UI:

- `success`: ready to continue
- `warning`: needs attention but can often be resolved in the UI
- `failed`: cannot continue
- `pending`: intentionally waiting for a later step

## Finish Report

`finish-report` writes:

```text
storage/runs/{run-id}/finish-report.json
```

It summarizes app URLs, app statuses, admin login email, key report paths, DNS/SSL/platform state, remote dependency state, checkpoints, and required or recommended follow-ups for final handoff. When prior install actions produced app manifests or service artifact declarations, the finish report includes those per app under `apps[].manifest` and `apps[].services`.

## Data Preparation Tool Contracts

Preferred tools should be declared in `release.json` with metadata and should accept:

```powershell
php tools/populate-initial-data.php --mode initial --config path\to\app.config.json --report path\to\tool.report.json --dry-run
```

Kit Setup only runs a tool when its configured `config_section` has `"enabled": true`. These tools are intended for post-install data preparation, refresh, or repair workflows, not required installation.

Compatibility support exists for MapServer's current `populate_tiles` script, which is declared as a string path in `release.json`. Kit Setup maps `mapserver.populate` settings into that script's existing flags, including `--base-url`, `--center`, `--radius-km`, `--zooms`, `--types`, `--max-tiles`, `--dry-run`, and `--report`.

## Stub App

The stub app release lives at:

```text
fixtures/stub-app-release/
```

It exists so Kit Setup can be developed before the real app installers are ready. It implements the same unattended contract as real apps:

```powershell
php installer/install-run.php --mode preflight --config path\to\app.config.json --report path\to\app.report.json
```
