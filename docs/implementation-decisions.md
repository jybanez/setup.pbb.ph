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
```

Reports must redact token material. Generated runtime config and raw generated secrets stay under ignored `storage/`.

## Technitium DNS

Technitium is the target local DNS provider for current PBB node kits.

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
