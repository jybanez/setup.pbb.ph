# Kit Setup Runner

## Purpose

`bin/kit-setup.php` is the first executable orchestration skeleton for PBB Kit Setup.

It currently supports:

- reading a Kit Setup config JSON
- validating required top-level config fields
- discovering app release directories through `release.json`
- validating each app has an unattended installer
- ordering apps by `depends_on`
- generating per-app unattended config files
- verifying app `checksums.sha256` files during planning
- running app installers in `preflight` or install mode
- running enabled initial-data population tools through the `populate` action
- collecting installer status output, install manifests, service declarations, and app reports
- aggregating app reports into `kit-report.json`

## Commands

Use the explicit WAMP PHP 8.2 binary. The default `php` on this workstation may point to PHP 5.6.

Plan only:

```powershell
& "C:\wamp64\bin\php\php8.2.29\php.exe" "C:\wamp64\www\pbb\kit-setup\bin\kit-setup.php" --config "C:\wamp64\www\pbb\kit-setup\examples\kit-config.stub.json" --action plan
```

Run preflight:

```powershell
& "C:\wamp64\bin\php\php8.2.29\php.exe" "C:\wamp64\www\pbb\kit-setup\bin\kit-setup.php" --config "C:\wamp64\www\pbb\kit-setup\examples\kit-config.stub.json" --action preflight
```

Run install:

```powershell
& "C:\wamp64\bin\php\php8.2.29\php.exe" "C:\wamp64\www\pbb\kit-setup\bin\kit-setup.php" --config "C:\wamp64\www\pbb\kit-setup\examples\kit-config.stub.json" --action install
```

Run enabled population tools:

```powershell
& "C:\wamp64\bin\php\php8.2.29\php.exe" "C:\wamp64\www\pbb\kit-setup\bin\kit-setup.php" --config "C:\wamp64\www\pbb\kit-setup\examples\kit-config.stub.json" --action populate
```

Optional flags:

- `--run-id <id>`: stable run id for repeatable smoke tests.
- `--run-dir <path>`: override the output folder.

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

Population sections are disabled by default in the example. Set an app's `*.populate.enabled` value to `true` only when Kit Setup should invoke that app's initial-data tool.

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

The runner does not yet register services or perform cross-app HTTP smoke checks. Those are next layers after the installer harness and app contracts stabilize.

## Population Tool Contracts

Preferred tools should be declared in `release.json` with metadata and should accept:

```powershell
php tools/populate-initial-data.php --mode initial --config path\to\app.config.json --report path\to\tool.report.json --dry-run
```

Kit Setup only runs a tool when its configured `config_section` has `"enabled": true`.

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
