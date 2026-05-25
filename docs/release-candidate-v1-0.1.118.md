# Release Candidate: v1-0.1.118

Status: release candidate for operator testing.

Installer artifact:

```text
C:\wamp64\www\pbb\kit-setup\out\Project Bantay Bayan Setup 0.1.118.exe
```

SHA256:

```text
6CA2B316D242230AC76B84A00CEE7EF9971DE33BFA619F53C12EDA085BD37C8D
```

## Scope

This release candidate covers the current Windows/WAMP local-node setup flow and the separate post-install Data Prep workflow.

The setup flow expects:

- WAMPServer installed on the setup machine.
- WAMP Apache service running.
- WAMP MySQL or MariaDB service running.
- Technitium DNS reachable on the local network through `http://dns.<zone>:5380`, normally `http://dns.pbb.ph:5380`.
- Administrator-provided Hub token, Technitium token, first-admin password, database values, SSL/Apache values, and app install decisions.

## Setup Startup Gate

The Setup window shows a Startup Requirements gate before Admin Inputs are available.

The gate checks:

- `C:\wamp64` exists.
- Apache and MySQL/MariaDB binaries are discoverable under WAMP.
- WAMP Apache service is running.
- WAMP MySQL or MariaDB service is running.
- Technitium responds like Technitium at `http://dns.<zone>:5380`.

The gate is setup-only. Project Bantay Bayan Data Prep does not show or run this gate.

## Technitium URL And Windows DNS Client

The Technitium URL may remain a domain name such as:

```text
http://dns.pbb.ph:5380
```

Windows DNS client configuration is different: Windows expects DNS server addresses as IPv4 addresses. Kit Setup therefore uses the domain URL to discover/probe Technitium, then derives an IPv4 target for `dns.client_nameserver`.

When the hidden `Windows DNS Server` field is blank and `Set this machine to use local DNS` is enabled, Start Installation derives the IPv4 target from:

1. Startup-gate `resolved_ips`.
2. Startup-gate HTTP socket `remote_address`.
3. Refreshed Technitium discovery `remote_address`.
4. Refreshed Technitium discovery `resolved_ips`.
5. Successful discovery candidate `remote_address` or `resolved_ips`.

The derived IPv4 is filled into the hidden DNS client field before validation and before runtime config generation.

Start Installation does not force the Startup Requirements gate to rerun. The operator can rerun the gate explicitly through `Check Again`.

## Data Prep Boundary

Project Bantay Bayan Data Prep is a separate post-install workflow.

Data Prep is gated by the Kit Setup completion marker and `install-state.json`, not by the Setup startup gate. In Data Prep mode:

- The Startup Requirements panel is hidden.
- Data Prep loads selected app topology and runtime paths from the completed setup state.
- Data Prep remains locked if Kit Setup has not completed successfully.
- Data Prep should not install apps, alter package extraction, create the first admin account, change DNS/certificate/firewall setup, or repair baseline setup failures.

## Verification Performed

The v1-0.1.118 package was verified with:

- `npm run check:desktop`
- WAMP PHP lint for `src\KitSetupRunner.php`
- Packaged version check for `0.1.118` / `v1-0.1.118`
- Packaged static probes confirming Data Prep hides the Startup Requirements gate
- Prior v1-0.1.117 packaged probes confirming the hidden Windows DNS Server IPv4 fallback path

## Operator Test Focus

For release-candidate testing, confirm:

- Setup blocks before Admin Inputs when WAMP or Technitium requirements are missing.
- Setup passes the gate when WAMP Apache/MySQL/MariaDB are running and `dns.pbb.ph:5380` reaches Technitium.
- Start Installation does not show the hidden Windows DNS Server warning when Technitium URL is `http://dns.pbb.ph:5380` and the DNS client target can be derived.
- Data Prep opens without the Startup Requirements panel.
- Data Prep remains locked only when the Kit Setup completion marker is missing or invalid.
