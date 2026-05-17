# PBB Node Kit Non-Technical Install Flow

This is the target administrator-facing setup flow for PBB Kit Setup.

## 1. Platform Check

Kit Setup detects Windows/Linux, PHP, Apache, MySQL/MariaDB, OpenSSL, ffmpeg/ffprobe, and required PHP extensions. The administrator provides or confirms the executable paths for PHP, Apache `httpd.exe`, and MySQL/MariaDB `mysql.exe`, plus the generated runtime config path. The administrator sees readiness states: ready, needs attention, or cannot continue.

## 2. Hub Pairing

The administrator enters the Hub ID and Hub token from `hub.pbb.ph`. Kit Setup fetches the canonical Hub record and asks the administrator to confirm the location, relay hub ID, deployment type, relay alias/domain, and uplink.

## 3. Select Apps For This Machine

The administrator checks which apps this machine should run locally:

- PBB MapServer
- PBB Maestro
- PBB Realtime
- PBB Relay
- PBB Hotline

Unchecked apps that are required by selected apps become remote dependencies.

## 4. Choose Base Path

The administrator chooses one base folder. Kit Setup derives app folders from it:

```text
basepath/mapserver
basepath/maestro
basepath/realtime
basepath/relay
basepath/hotline
```

## 5. Admin & Database

The administrator provides shared database connection details and the first administrator password.

The database fields are shared across Laravel-style apps while each app keeps its own database name:

- host, usually `127.0.0.1`
- port, usually `3306`
- username
- password

The first administrator identity stays standard:

- Name: `PBB Administrator`
- Email: `admin@pbb.local`
- Password: supplied by the installing admin

## 6. Prepare Trusted App Packages

Kit Setup creates folders for selected local apps, verifies trusted bundled or officially fetched packages, and extracts each selected app into its derived folder. The administrator cannot upload or manually select app installers.

## 7. Preflight Apps

Kit Setup runs each selected local app installer in preflight mode. App preflight checks PHP/runtime requirements, expected files, writable folders, database connectivity, existing install state, and app-specific blockers. No app mutation should happen in this step.

## 8. Install Apps

Kit Setup runs the app installers after package preparation and preflight pass. Installers write environment files, run migrations, seed required initial data, create the standard first administrator account, write install manifests, and generate service artifacts when supported.

## 9. Network & Local DNS

The administrator provides this machine IP and Technitium DNS API access. Kit Setup plans local DNS records for the standard offline app domains only. Hub/uplink relay domains remain public coordination domains and are not written into local Technitium. Kit Setup can also, with explicit confirmation, set this Windows machine's preferred DNS server to the local Technitium server before verification.

## 10. SSL & Web Server

The administrator provides the official certificate bundle or selects the existing local certificate folder. Kit Setup validates the certificate/key material, checks domain coverage, and generates OS-aware web-server include files. Current implementation can generate and guarded-apply an Apache HTTPS vhost include; web-server reload checks are still pending.

## 11. Remote & Smoke Checks

Kit Setup verifies DNS, HTTPS health endpoints, and required credentials/secrets for required apps not installed locally. After DNS and SSL are configured, final smoke checks verify reachable local app URLs.

## 12. Finish

Kit Setup shows app URLs, admin login email, service status, DNS/cert/vhost status, setup report path, and any manual follow-up needed.

## Review Plan Action

The desktop action bar includes a `5 Review Plan` action after the administrator provides platform, Hub, app selection, base path, admin, and database inputs. It writes the consolidated `plan` report with local apps, remote dependencies, base path, app package versions, domains/DNS records, certificate path, databases, and services.

## Required Setup Sequence

For the main install path, the administrator should run:

```text
6 Packages -> 7 Preflight -> 8 Install -> 9 DNS -> 10 SSL -> 11 Remote/Smoke -> 12 Finish
```

## Separate Tool: Data Preparation

Operational data preparation should run after the node kit is installed and verified.

This separate tool/workflow can prepare or refresh:

- MapServer boundaries and tile cache
- Maestro application records and telemetry setup
- Realtime clients, policies, projects, and media ingest settings
- Hotline catalogs, teams, operators, dispatch defaults, and demo/reference data

Population is intentionally separate because it is repeatable, source-dependent, and may need to run again after Hub data or local operational data changes. A population failure should not make the core node installation look failed.

## Future Roadmap

PBB Node Appliance OS is a future packaging target: a prebuilt Linux image with Kit Setup and trusted packages already present, running this same setup flow on first boot.

The installer form factor target is a desktop app backed by the same report-driven runner actions.
