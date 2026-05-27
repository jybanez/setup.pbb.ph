# Pre-Build Verification Checklist

This checklist is mandatory before producing a new operator-facing Kit Setup installer.

The goal is to catch app bundle and installer-contract mismatches before Jojo or an operator sees them during setup.

## 1. Confirm Intended Inputs

- Confirm target province/build profile, for example Cebu `0722`.
- Confirm selected bundled apps.
- Confirm whether the build is a test build, release candidate, or official release.
- Confirm expected output filename. Province-tied release artifacts use:

```text
pbb-kit-setup-v1-{kit_version}-ph-{province_code}-{province_slug}.exe
```

Example:

```text
pbb-kit-setup-v1-0.1.123-ph-0722-cebu.exe
```

## 2. App Bundle Manifest Integrity

For `packages/packages.bundled.json`:

- Every `packages[].path` exists.
- Every `packages[].sha256` matches the ZIP on disk.
- Every `supplemental_packages[].path` exists.
- Every `supplemental_packages[].sha256` matches the ZIP on disk.
- The embedded `release.json.build.id` and `release.json.build.git_commit` match the app team's handoff.
- The manifest points only to the intended current canonical bundles.

## 3. Bundle Contents Versus Installer Behavior

For every bundled PHP/Laravel-style app, inspect both the ZIP contents and installer source/report contract.

Required checks:

- If the bundle excludes `database/seeders`, the installer must not run `db:seed` during fresh Kit installs.
- If the installer runs any command matching `db:seed`, `DatabaseSeeder`, `SettingsSeeder`, or another seeder class, the required seeder files must exist in the ZIP or the installer must safely skip the command for baseline-schema installs.
- If `database/factories` is excluded, no installer command may rely on factories.
- If `tests` are excluded, no installer command may reference test fixtures.
- If optional binaries are excluded, installer preflight must mark them optional or external-only.

Specific seed checks:

- Maestro: no production seeders are expected; fresh `baseline_schema` install must skip seeders.
- Realtime: no production seeders are expected; installer must reject or skip seeders and use Data Prep tools.
- Relay: no production seeders are expected; installer must not run Laravel seeders.
- Hotline: no production seeders are expected; fresh `baseline_schema` install must not run `SettingsSeeder` unless `database/seeders/SettingsSeeder.php` is present.

Do not build if any app has a missing-runtime-artifact condition, even if package checksum verification passes.

## 4. Required Bundle Audits

Run or reproduce the app handoff audit for each changed bundle:

- internal `checksums.sha256` scan has no missing or bad files
- required installer entrypoints exist
- required Data Prep tools exist
- baseline schema exists when release metadata declares one
- app-owned runtime assets exist
- dev artifacts are absent
- production Composer dependencies are used
- Laravel package discovery does not include dev providers

## 5. Kit Runner And Desktop Checks

Run:

```powershell
& "C:\wamp64\bin\php\php8.2.29\php.exe" -l src\KitSetupRunner.php
npm run check:desktop
```

Also run source package hash verification against `packages/packages.bundled.json`, including supplemental packages.

## 6. Package Harness Checks

Before building the desktop installer, run a non-production package harness when package handling changed or a package class changed.

Required harness cases:

- MapServer small runtime bundle plus selected province boundary pack.
- Any app whose installer recently changed.
- Any app whose bundle pruning changed runtime file presence.

The harness must prove the app deploys to a temporary install root and that expected supplemental payloads land in the installed app.

## 7. Installer Build Checks

After `npm run package:desktop:win`:

- Confirm `out\Project Bantay Bayan Setup {version}.exe` exists.
- Record SHA-256 and size.
- Confirm packaged `resources\app\package.json` has the intended Kit version.
- Confirm packaged `resources\app\packages\packages.bundled.json` matches source intent.
- Re-run packaged bundle hash verification, including supplemental packages.
- Probe critical packaged ZIP contents, especially recently changed bundles.

## 8. Release Documentation And Chat

Before handing the build to Jojo:

- Create or update the release candidate doc.
- Include installer path, SHA-256, size, and changed bundles.
- Include the verification checklist summary.
- Announce the build in `C:\wamp64\www\pbb\chat_log.md`.
- If this is a province-tied official release, create the province-named distributable copy.

## Stop Conditions

Stop and do not build if:

- any bundle hash mismatches the manifest
- any installer references a file/class/tool absent from the production ZIP
- any fresh baseline install path still depends on excluded seeders
- any package harness fails
- any packaged resource check differs from source intent
- any app team handoff is incomplete or internally contradictory

