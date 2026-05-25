# PBB Kit Setup Implementation Decisions

This records current product and implementation decisions for the installer.

## Installer Form Factor

Kit Setup will target a desktop installer application.

The backend runner remains CLI-friendly and report-driven so the desktop UI can call actions, read JSON reports, and show a stage-by-stage flow without duplicating installer logic.

Recommended desktop path:

- Phase 1: keep the PHP runner as the installation engine.
- Phase 2: build a desktop shell that drives runner actions and renders reports.
- Phase 3: package the desktop app with the trusted app bundles and runtime prerequisites.

Electron is the pragmatic first desktop shell because it is straightforward on Windows and Linux and can drive the existing PHP runner without a larger native toolchain requirement. Tauri can be revisited later if installer size becomes more important than build simplicity.

## Secret Handling

Runtime secrets must not be checked into config files.

Kit Setup accepts these as runtime inputs:

- Hub token
- Technitium token
- First administrator password
- Database credentials

CLI harnesses should pass secrets through environment variables:

```powershell
$env:PBB_HUB_TOKEN = "<hub token>"
$env:PBB_TECHNITIUM_TOKEN = "<technitium token>"
$env:PBB_FIRST_ADMIN_PASSWORD = "<admin password>"
$env:PBB_MYSQL_PASSWORD = "<database password>"
```

Reports must redact token and password material. Generated runtime config and raw generated secrets stay under ignored `storage/`.

## Technitium DNS

Technitium is the target local DNS provider for current PBB node kits.

For the desktop setup flow, Technitium must be reachable before Admin Inputs are shown. The setup startup gate probes `http://dns.<zone>:5380`, normally `http://dns.pbb.ph:5380`, and verifies that the HTTP response looks like Technitium.

The Technitium API URL may use a domain name. The Windows DNS client target may not rely on a domain name because the machine may need DNS working before it can resolve that domain. When `Set this machine to use local DNS` is enabled and the hidden Windows DNS Server input is blank, Kit Setup derives the IPv4 target from the startup gate or refreshed Technitium discovery and writes that IPv4 to `dns.client_nameserver`.

## Windows Firewall

Kit Setup owns host-level Windows Firewall inbound rules needed for local app access. The guarded `firewall-apply` action replaces same-named Project Bantay Bayan HTTP/HTTPS rules and opens TCP 80 and 443 when `platform.firewall.update_mode=apply`. App installers should not create global firewall rules directly under Kit orchestration.

Known local test credential:

- Username: `admin`
- Token name: `Kit Installer Token`
- Token value: supplied by the project owner and must be entered only at runtime

The checked-in config uses:

```json
{
  "dns": {
    "provider": "technitium",
    "base_url": "http://localhost:5380",
    "token_env": "PBB_TECHNITIUM_TOKEN",
    "zone": "pbb.ph",
    "ttl": 300,
    "update_mode": "plan-only"
  }
}
```

`dns-apply` must remain guarded by `dns.update_mode=apply`.

## Apache Include Strategy

Kit Setup should generate one Apache include file for all selected local PBB app vhosts.

Recommended Windows/WAMP target:

```text
C:\wamp64\apache-vhosts\pbb-vhosts.conf
```

Apache should include this file through one stable line in the Apache config:

```apache
IncludeOptional "C:/wamp64/apache-vhosts/pbb-vhosts.conf"
```

Kit Setup can safely update the generated include file when `ssl.web_server_update_mode=apply`. It should back up any existing include file first, then test Apache config before offering restart/reload.

Apps must not write this include or any other global Apache/Nginx file directly when running under Kit. Apps should declare app-scoped web-server requirements as structured release/install metadata, and Kit Setup should merge those requirements into the generated include. Realtime websocket routing is the first concrete case: the app owns the need for a `/realtime` websocket proxy and required proxy modules, while Kit owns rendering, applying, backing up, and testing the host config.

Linux should use the same pattern with a distro-appropriate target path, for example:

```text
/etc/apache2/sites-available/pbb-vhosts.conf
```

Reload/restart support is not implemented yet.

## Trusted App Package Source

In simple terms: Kit Setup needs to know where the official app installers come from.

The administrator should never browse for or upload arbitrary installer files. Instead, Kit Setup should ship with, or fetch from an official release source, signed ZIP bundles for each app:

- MapServer ZIP
- Maestro ZIP
- Realtime ZIP
- Relay ZIP
- Hotline ZIP

Each ZIP should have:

- `release.json`
- `checksums.sha256`
- app installer files
- detached signature file, such as `.sig`

Kit Setup verifies the ZIP checksum and signature before extracting it.

Current local development uses trusted sibling directories and a signed stub ZIP fixture. Production should replace those with real signed release ZIPs from an official PBB release location.

## External Machine Testing

For another machine, start with dry-run behavior:

- keep `packages.dry_run=true`
- keep `dns.update_mode=plan-only`
- keep `ssl.web_server_update_mode=plan-only`
- run `detect`, `stage-report`, `plan`, `prepare-packages`, `dns-plan`, `ssl-plan`, and `remote-check`

Only after the reports look correct should the machine test enable apply modes.

## Data Preparation Boundary

Optional data population is not part of the required installer flow.

The installer should make the node kit operational: packages placed, app configs written, fresh app databases verified empty or explicitly reset before each release's current-schema baseline is imported, first admin created, DNS/SSL/web-server configuration prepared, services generated or registered, and health/smoke checks completed.

Runtime service declarations are now a formal app-to-Kit contract. Apps that require long-running processes must declare them with the exact `runtime_services` array in `release.json` and repeat the resolved array in install reports/manifests. Kit Setup owns service planning, start/register strategy, health verification, and ordering before smoke checks. The first concrete case is Realtime's websocket daemon: smoke should not infer the daemon command from a failed public `wss://` check; it should verify the declared service health check first.

Fresh database clearing is destructive. Kit Setup owns the operator-confirmed reset step before app installers run, because Kit also owns app database creation/provisioning. It must never silently drop tables simply because an app reports `not_installed`. App installers remain responsible for refusing unsafe baseline imports into non-empty databases and for defining any safe partial-install resume policy.

Data preparation is a separate post-install workflow. It can still reuse the app-owned population contracts already implemented by MapServer, Maestro, Realtime, and Hotline, but it should be presented as a separate app/workflow such as:

```text
Project Bantay Bayan Data Prep
```

This keeps the core install path simpler and safer. Data preparation may be repeated later to refresh tiles, boundaries, reference records, routing data, teams, policies, or local cache. A failed data preparation run should not imply that the node installation itself failed.

`Data Prep` is the product/workflow name. Individual app population tools should receive app-owned execution modes such as `initial`, `repair`, `refresh`, or `demo`; `data-prep` is not a required CLI mode for app tools.

Data Prep does not show or run the setup Startup Requirements gate. It is gated by the Kit Setup completion marker and `install-state.json`, then uses the completed setup state for selected apps and runtime paths.

## Installer Ownership Boundaries

Kit Setup owns host-level binary discovery and selected install paths. App installers running under Kit must use the current PHP runtime and Kit-provided platform binaries instead of scanning the local machine for alternate WAMP/XAMPP/tool paths.

Apps own their required runtime folders, but those folders should be created under the app install path Kit provides. Any external app-owned cache, log, upload, or generated-data path must be explicitly provided by Kit config for a named purpose and reported back in the app manifest/report.

This boundary is intentionally strict: if Kit does not provide a binary or path an app requires, the app should fail clearly during preflight/install instead of guessing. That keeps every app using the same validated machine context.

Laravel app installers also own creation of standard in-root runtime directories before cache warmup. Fresh installs must ensure `storage/framework/cache`, `storage/framework/sessions`, `storage/framework/views`, `storage/logs`, and `bootstrap/cache` exist before `config:cache`, `route:cache`, or `view:cache`. This prevents Laravel from caching a false `view.compiled` value and failing `view:cache` with `View path not found`.
