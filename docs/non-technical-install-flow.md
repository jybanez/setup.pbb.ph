# PBB Node Kit Non-Technical Install Flow

This is the target administrator-facing setup flow for PBB Kit Setup.

## 1. Platform Check

Kit Setup detects Windows/Linux, PHP, Apache, MySQL/MariaDB, OpenSSL, ffmpeg/ffprobe, and required PHP extensions. The administrator sees only readiness states: ready, needs attention, or cannot continue.

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

## 5. Prepare Trusted App Packages

Kit Setup creates folders for selected local apps, verifies trusted bundled or officially fetched packages, and extracts each selected app into its derived folder. The administrator cannot upload or manually select app installers.

## 6. Network & Local DNS

The administrator provides this machine IP and Technitium DNS API access. Kit Setup plans local DNS records for the standard offline app domains only. Hub/uplink relay domains remain public coordination domains and are not written into local Technitium. Kit Setup can also, with explicit confirmation, set this Windows machine's preferred DNS server to the local Technitium server before verification.

## 7. SSL & Web Server

The administrator provides the official certificate bundle or selects the existing local certificate folder. Kit Setup validates the certificate/key material, checks domain coverage, and generates OS-aware web-server include files. Current implementation can generate and guarded-apply an Apache HTTPS vhost include; web-server reload checks are still pending.

## 8. Remote Dependency Check

Kit Setup verifies DNS, HTTPS health endpoints, and required credentials/secrets for required apps not installed locally.

## 9. Admin Account

The administrator provides only the password. The identity stays standard:

- Name: `PBB Administrator`
- Email: `admin@pbb.local`

## 10. Installation Plan

Kit Setup shows a final summary of local apps, remote dependencies, base path, app package versions, domains/DNS records, certificate path, databases, and services.

## 11. Stage-By-Stage Install

Kit Setup generates configs, runs app installers, configures services, runs migrations, and runs smoke checks. It does not perform optional operational data population as part of the required install path.

## 12. Finish

Kit Setup shows app URLs, admin login email, service status, DNS/cert/vhost status, setup report path, and any manual follow-up needed.

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
