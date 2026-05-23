# Draft: Existing Install Discovery And Installer Cleanup

Status: draft

This note captures the proposed installer behavior for detecting existing PBB app installs before installation starts, presenting deliberate install choices, and removing app-side installer artifacts after a successful install.

## Goals

- Detect whether each app domain already resolves and responds before installing.
- Identify whether an existing app is local to this machine, remote on another machine, partially installed, or unknown.
- Give the admin an explicit choice per app: install, repair, replace, skip, or treat as remote.
- Avoid overwriting an existing local install unless the admin confirms that exact behavior.
- Remove app-side installer entrypoints after a successful installation so curious users cannot rerun them from the deployed app.

## Non-Goals

- Do not infer secrets from installed apps.
- Do not expose database credentials, hub tokens, admin passwords, filesystem paths, or certificate private key paths through public app endpoints.
- Do not make destructive choices automatically based only on DNS or HTTP checks.

## Proposed First Step: Install Discovery

Before package preparation or app installation, the installer should run a discovery step for every selected app.

Discovery should collect these signals:

- DNS: resolve the app host, record returned addresses, and compare them to the detected local machine IP.
- HTTP: make a GET request to the app URL and record status, redirect target, headers, and a minimal app identity response if available.
- Local filesystem: check the expected app install path and any local install manifest.
- Apache/vhost: check whether the app host is already mapped in Apache config and which document root is configured.
- Database: check whether the expected app database exists, without reading credentials from the app.

The discovery result should classify each app as one of:

- `not_installed`: no domain, no local path, no manifest, no matching vhost.
- `installed_local`: domain and/or vhost points to this machine and a valid local manifest exists.
- `installed_remote`: domain resolves to another machine and app identity confirms the app.
- `partial_install`: some local artifacts exist, but identity or manifest is incomplete.
- `conflict`: DNS, vhost, manifest, or filesystem disagree.
- `unknown`: probe could not determine the state safely.

## App Identity Contract

Each installed app should expose a non-sensitive identity endpoint. Suggested endpoint:

```text
/.well-known/pbb-app.json
```

Suggested response shape:

```json
{
  "schema_version": 1,
  "app_id": "pbb-realtime",
  "app_name": "PBB Realtime",
  "app_version": "1.0.0",
  "install_id": "01HY...",
  "installed_at": "2026-05-19T00:00:00+08:00",
  "kit_setup_version": "0.1.54",
  "base_url": "https://realtime.pbb.ph",
  "status": "ready"
}
```

Allowed fields should be limited to identity and support diagnostics. The endpoint must not include admin identity, database details, hub pairing secrets, local filesystem paths, machine usernames, or tokens.

## Local Install Manifest

Each app package should write a local manifest into a non-web-accessible location, for example:

```text
storage/app/installer/install-manifest.json
```

Suggested local manifest shape:

```json
{
  "schema_version": 1,
  "app_id": "pbb-realtime",
  "app_name": "PBB Realtime",
  "app_version": "1.0.0",
  "install_id": "01HY...",
  "installed_at": "2026-05-19T00:00:00+08:00",
  "kit_setup_version": "0.1.54",
  "install_scope": "local",
  "base_url": "https://realtime.pbb.ph",
  "install_path": "C:/wamp64/www/pbb-node/realtime",
  "public_path": "C:/wamp64/www/pbb-node/realtime/public"
}
```

The local manifest may contain local paths because it is not web-accessible. The public identity endpoint should expose only the safe subset.

## Admin Choices

After discovery, the installer should show a confirmation summary before automated install starts. For each app, the admin should choose one of:

- Install new: no existing install was detected.
- Repair: keep existing files/data where possible and re-run required install steps.
- Replace: overwrite app files while preserving configured data when supported.
- Fresh overwrite: remove app files and reset app data; this is destructive and requires explicit confirmation.
- Skip: leave the app untouched.
- Treat as remote: use the existing remote app and do not install locally.

For conflicts or partial installs, the UI should recommend repair or replace, but still show the evidence that led to that recommendation.

## Installer Artifact Cleanup

After a successful app install, the installer should remove or disable any app-side installer artifacts that could be run by a user.

Examples of artifacts to remove or disable:

- Web-accessible installer entrypoints.
- Temporary installer controllers or routes.
- Sample installer configs.
- Embedded package zips copied into the app tree.
- Build or bootstrap scripts that are only needed during installation.
- Any public route that can rerun install, migration, seeding, or pairing.

Artifacts to keep:

- Non-web-accessible install manifest.
- Non-web-accessible install report.
- Logs needed for support, stored outside public paths.

For failed installs, diagnostics may remain for troubleshooting, but they must not be web-accessible and must not contain secrets.

## Verification

The finish or smoke step should warn if any runnable app-side installer entrypoint remains after a successful install.

Suggested check:

- Scan declared installer artifact paths from the app package manifest.
- Confirm web-accessible installer URLs return 404 or 403.
- Confirm the local install manifest exists.
- Confirm the public identity endpoint responds with the expected `app_id`.

## Open Questions

- Final endpoint name: `/.well-known/pbb-app.json` or another ecosystem-wide helper convention.
- Whether public identity should be unauthenticated or limited to local-network requests.
- Exact replace semantics per app: preserve `.env`, storage, uploads, generated keys, and database by default unless fresh overwrite is selected.
- How to represent multiple detected installs for the same app domain.
- Whether the kit should delete its own desktop installer after all selected apps are installed successfully, or only remove app-side installer artifacts.
