# Desktop Installer Shell

The desktop shell is the first UI layer for Kit Setup.

It lives under:

```text
desktop/
```

The shell does not duplicate installer logic. It calls the PHP runner actions and renders their JSON reports.

## Current Capabilities

- Shows the 12-step setup stage list.
- Provides stage inputs for platform executable paths, Hub ID, app local/remote/off scope, base path, admin/database credentials, DNS, and SSL/Apache.
- Front-loads all administrator-owned inputs on the first stage so manual testing and future automation use the same setup contract.
- Uses the vendored official PBB Helper UI bundle for the workflow stepper, app scope segmented controls, and package progress.
- Shows the Kit Setup version in the sidebar.
- Keeps the sidebar and main content scroll areas independent.
- Shows per-stage validation and inline recovery guidance.
- Shows same-run action checkpoints for retry/resume visibility.
- Shows a finish summary with app links, admin email, report paths, and follow-ups.
- Keeps data preparation as a separate post-install action rather than a required installer stage.
- Builds a generated runtime config under `storage/desktop-configs/` instead of editing checked-in examples.
- Requires explicit confirmation before guarded or mutating actions run.
- Runs `stage-report` and renders stage state.
- Runs individual backend actions:
  - `detect`
  - `hub-resolve`
  - `plan`
  - `prepare-packages`
  - `preflight`
  - `install`
  - `dns-plan`
  - `dns-apply`
  - `dns-client-apply`
  - `dns-verify`
  - `firewall-apply`
  - `service-plan`
  - `service-start`
  - `service-stop`
  - `service-verify`
  - `ssl-plan`
  - `ssl-apply`
  - `remote-check`
  - `smoke-check`
  - `populate` / Data Prep
  - `finish-report`
- Accepts Hub token, Technitium token, first-admin password, and database password as runtime-only fields.
- Accepts the first administrator name/email as setup inputs, while still defaulting to `PBB Administrator` and `admin@pbb.local`.
- Accepts the Windows Firewall apply choice and writes `platform.firewall.update_mode` into generated runtime config.
- Passes runtime secrets through environment variables.
- Does not save tokens or passwords.
- Labels action buttons with the stage number they belong to, such as `6 Packages`, `7 Preflight`, `8 Install`, `9 Apply DNS`, `10 Firewall`, `10 Apply SSL`, and `12 Finish`.
- Provides a `Run Automated Install` entry point that validates the full admin input set, asks for one whole-run confirmation, then executes the ordered setup sequence under the same setup-session run id.
- Runs `service-plan`, `service-start`, and `service-verify` before `smoke-check` so app-declared runtime services such as Realtime's websocket daemon are installed as WinSW Windows services, started, and verified before public endpoint smoke tests.
- Uses only the vendored WinSW wrapper for app runtime services. `service-stop` stops matching WinSW services before overwrite/repair and during current-run cleanup; reports include generated service file paths and WinSW command output for troubleshooting.
- The Windows uninstaller runs `bin\cleanup-winsw-services.ps1`, which stops/uninstalls WinSW services under `C:\ProgramData\PBB\Services` and also removes matching Kit-managed Windows service registrations with `sc.exe delete`, so app runtime services do not remain in Windows Services after Kit Setup is removed.

## Confirmation Gates

The shell blocks these actions behind a confirmation modal:

- `prepare-packages`
- `dns-apply`
- `dns-client-apply`
- `firewall-apply`
- `ssl-apply`
- `install`
- `populate` / Data Prep

The modal summarizes the current config state, such as `packages.dry_run`, `dns.update_mode`, `ssl.web_server_update_mode`, selected local apps, and target Apache include path.

The automated install entry point uses one whole-run browser confirmation and then passes confirmed intent into the guarded actions in sequence. If any runner action exits non-zero or reports `failed`, the sequence stops at that action and leaves the same-session checkpoints available for diagnosis and retry.

## Per-Stage Validation

The renderer validates key setup inputs before building runtime config or running actions:

- PHP/config path for platform checks.
- Apache `httpd.exe` and MySQL/MariaDB `mysql.exe` paths are accepted in the platform stage to avoid stale template path warnings.
- Hub ID and Hub token for Hub pairing.
- At least one local app for app selection.
- Base path for local installation.
- Database host, port, username, and first administrator password before app preflight/install/data preparation.
- First administrator name and email before app preflight/install/data preparation.
- Machine IP/host, DNS zone, Technitium URL, and token when DNS apply is enabled.
- Certificate folder or PEM bundle, plus Apache include target when web-server apply is enabled.

These checks are UI guidance only. Backend runner actions still perform the authoritative validation and write reports.

## Retry And Resume

Every backend action writes or updates:

```text
storage/runs/{run-id}/checkpoints.json
```

The desktop shell reuses one setup-session run id while the app is open, so manual step-by-step testing writes checkpoints into the same run folder. It reads this file after each action and renders a checkpoint grid. Each checkpoint shows whether a runner action is still pending, succeeded, warned, skipped, or failed. Clicking an item reruns that action through the same guarded flow.

The runner writes action-specific reports for the shared app actions so same-session manual runs do not overwrite earlier evidence:

- `plan-report.json`
- `preflight-report.json`
- `install-report.json`
- `populate-report.json`

It still refreshes `kit-report.json` as a compatibility alias for the latest app action.

App reports from `plan`, `preflight`, `install`, and `populate` also populate a per-app retry panel. Each app row can rerun `preflight`, `install`, or post-install Data Prep for only that app through the runner's `--app <app-id>` filter.

## Finish Summary

The Finish action runs `finish-report` and renders:

- app ids, statuses, and URLs
- standard admin email
- required or recommended follow-ups
- report/checkpoint paths in the raw JSON detail

## Run Locally

Install dependencies once:

```powershell
npm install
```

The current scaffold uses Electron `^42.1.0` because the older Electron line initially tested by the scaffold had npm audit advisories.

Start the shell:

```powershell
npm run desktop
```

Validate JavaScript syntax:

```powershell
npm run check:desktop
```

Build an unpacked Windows desktop bundle for smoke testing:

```powershell
npm run package:desktop:dir
```

The output is:

```text
out\win-unpacked\Project Bantay Bayan.exe
```

A full NSIS installer target is configured for later release packaging:

```powershell
npm run package:desktop:win
```

Production installer output uses the Project Bantay Bayan branding and bundled trusted app packages. Code-signing certificate procurement is still a release-management task.

## Backend Contract

The desktop app uses:

```text
bin/kit-setup.php
```

Reports are read from:

```text
storage/runs/{run-id}/
```

The shell currently assumes the runner remains report-driven and that mutating actions stay guarded by config flags:

- `packages.dry_run`
- `dns.update_mode`
- `platform.firewall.update_mode`
- `ssl.write_extracted_files`
- `ssl.web_server_update_mode`

Generated desktop configs are written under:

```text
storage/desktop-configs/
```

These files may contain machine paths and non-secret setup choices, but not the raw Hub token, Technitium token, database password, or first-admin password.

## Next UI Work

- Add richer per-action previews from the latest plan reports.
- Add packaged runtime detection for Linux.
- Add production release branding, signing, and update policy.
