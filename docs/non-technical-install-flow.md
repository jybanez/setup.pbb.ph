# PBB Node Kit Non-Technical Install Flow

This is the target administrator-facing setup flow for PBB Kit Setup.

## 1. Admin Inputs & Platform Check

For Windows local-node installs, WampServer and Technitium DNS Server must be installed before Kit Setup starts the automated setup flow. Reference installers and the startup-gate contract are documented in [Windows Install Requirements](windows-install-requirements.md).

Kit Setup first collects all administrator-owned inputs needed for an automated run. This includes platform executable paths, Hub pairing, local/remote/off app topology, install base path, shared database credentials, first administrator identity, DNS settings, firewall intent, and SSL/Apache paths. Kit Setup detects Windows/Linux, PHP, Apache, MySQL/MariaDB, OpenSSL, ffmpeg/ffprobe, and required PHP extensions. The administrator sees readiness states: ready, needs attention, or cannot continue before any mutating action runs.

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

The administrator provides shared database connection details and the first administrator identity.

The database fields are shared across Laravel-style apps while each app keeps its own database name:

- host, usually `127.0.0.1`
- port, usually `3306`
- username
- password

For fresh installs, each selected app database must be empty before app tables are imported. Kit Setup owns this database readiness step: if a database already contains tables, the setup flow should either stop with remediation or require an explicit destructive confirmation to clear/recreate that app database before handing control to the app installer.

The first administrator identity defaults to:

- Name: `PBB Administrator`
- Email: `admin@pbb.local`
- Password: supplied by the installing admin

The desktop shell lets the administrator override name/email when the deployment needs a site-specific first account.

## 6. Prepare Trusted App Packages

Kit Setup creates folders for selected local apps, verifies trusted bundled or officially fetched packages, and extracts each selected app into its derived folder. The administrator cannot upload or manually select app installers.

## 7. Preflight Apps

Kit Setup runs each selected local app installer in preflight mode. App preflight checks PHP/runtime requirements, expected files, writable folders, database connectivity, existing install state, and app-specific blockers. No app mutation should happen in this step.

## 8. Install Apps

Kit Setup runs the app installers after package preparation and preflight pass. Installers write environment files, prepare the current release database schema, seed required initial data, create the standard first administrator account, write install manifests, and generate service artifacts when supported. For fresh Laravel installs, app installers should use their packaged current-schema baseline instead of replaying the full development migration history.

## 9. Network & Local DNS

The administrator provides this machine IP and Technitium DNS API access. Kit Setup plans local DNS records for the standard offline app domains only. Hub/uplink relay domains remain public coordination domains and are not written into local Technitium. Kit Setup can also, with explicit confirmation, set this Windows machine's preferred DNS server to the local Technitium server before verification.

## 10. Firewall, SSL & Web Server

Kit Setup can open the host Windows Firewall for inbound web traffic with explicit confirmation, using the standard Project Bantay Bayan HTTP and HTTPS rules. The administrator provides the official certificate bundle or selects the existing local certificate folder. Kit Setup validates the certificate/key material, checks domain coverage, and generates OS-aware web-server include files. Current implementation can generate and guarded-apply an Apache HTTPS vhost include; web-server reload checks are still pending.

## 11. Remote & Smoke Checks

Kit Setup verifies DNS, HTTPS health endpoints, and required credentials/secrets for required apps not installed locally. After DNS and SSL are configured, final smoke checks verify reachable local app URLs.

## 12. Finish

Kit Setup shows app URLs, admin login email, service status, DNS/cert/vhost status, setup report path, and any manual follow-up needed.

## Review Plan Action

The desktop action bar includes a `5 Review Plan` action after the administrator provides platform, Hub, app selection, base path, admin, and database inputs. It writes the consolidated `plan` report with local apps, remote dependencies, base path, app package versions, domains/DNS records, certificate path, databases, and services.

## Required Setup Sequence

For manual testing, the administrator should run:

```text
6 Packages -> 7 Preflight -> 8 Install -> 9 DNS -> 10 Firewall/SSL -> 11 Remote/Smoke -> 12 Finish
```

The intended automated path is:

```text
Admin Inputs -> Validate All -> Review Plan -> Run Automated Install -> Finish Report
```

The desktop `Run Automated Install` action runs the ordered sequence with one setup-session run id and stops on the first failed runner report so the administrator can inspect checkpoints and retry the specific failed action.

## Separate Tool: Project Bantay Bayan Data Prep

Operational data preparation should run after the node kit is installed and verified.

This separate tool/workflow can prepare or refresh:

- MapServer boundaries and tile cache
- Maestro application records and telemetry setup
- Realtime clients, policies, projects, and media ingest settings
- Hotline reference data: incident categories, incident types, incident type fields, resource categories, resources needed, team categories, and teams

Population is intentionally separate because it is repeatable, source-dependent, and may need to run again after Hub data or local operational data changes. A population failure should not make the core node installation look failed.

In this context, Data Prep is the standalone operator-facing app/workflow. The app-owned population tools still use execution modes such as `initial`, `repair`, `refresh`, and `demo`.

## Future Roadmap

PBB Node Appliance OS is a future packaging target: a prebuilt Linux image with Kit Setup and trusted packages already present, running this same setup flow on first boot.

The installer form factor target is a desktop app backed by the same report-driven runner actions.
