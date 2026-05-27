# App Bundle Packaging Standard

Project Bantay Bayan apps distributed through Kit Setup must be packaged as production runtime bundles, not snapshots of local development checkouts.

This standard applies to PHP/Laravel-style app bundles consumed by Kit Setup, including Hotline, Maestro, Realtime, and Relay when applicable. MapServer is excluded from the Composer-specific parts because its bundle size is driven by map/data payloads rather than Laravel vendor dependencies.

## Goals

- Keep the desktop installer smaller.
- Avoid shipping development dependencies to operator machines.
- Make app bundle contents repeatable and auditable.
- Let Kit consume bundles without running Composer or npm on the target machine.
- Keep local developer checkouts untouched during packaging.

## Required Build Pattern

App teams must build canonical bundles from a temporary clean packaging stage.

The packaging stage should:

1. Copy only release-required source, installer, tools, public assets, metadata, and docs.
2. Install production Composer dependencies into the stage.
3. Build production frontend assets before the ZIP is produced.
4. Generate `release.json`, `checksums.sha256`, and any app-owned schema/baseline artifacts from the final staged files.
5. ZIP the staged production tree.

For Laravel-style apps that bundle `vendor/`, the Composer command must be equivalent to:

```powershell
composer install --no-dev --optimize-autoloader
```

Use the target PHP runtime for dependency resolution when possible, for example the same WAMP PHP version Kit uses during local-node installs.

The packaging step must not delete or rewrite the local checkout's development `vendor/` tree. Realtime's current pattern is the reference model: create a temporary packaging stage, run Composer there, then zip that staged tree.

## Required Exclusions

Production ZIPs must not include local or development-only artifacts:

- `.git`
- `.env`
- `node_modules`
- `tests`
- local logs
- local caches
- temporary build folders
- package-builder scratch folders
- CI-only scaffolding unless explicitly needed by the installer

Laravel-style bundles must exclude Composer dev packages such as:

- `vendor/phpunit`
- `vendor/mockery`
- `vendor/fakerphp`
- `vendor/nunomaduro/collision`
- `vendor/spatie/laravel-ignition`
- `vendor/laravel/sail`
- `vendor/laravel/pint`

`database/factories` and `database/seeders` should be excluded unless the app installer explicitly requires them for production install or Data Prep. Prefer app-owned production baseline schema and production data-prep tools over development seeders.

## Package Discovery Check

Laravel package discovery output must not register dev-only providers in the production bundle.

Before handoff, verify that `bootstrap/cache/packages.php` or the equivalent generated discovery metadata does not contain providers for Sail, Collision, Ignition, Pint, PHPUnit tooling, or other dev-only packages.

## Bundle Audit

Before handing a bundle to Kit, app teams must run an audit and report:

- previous ZIP size, if replacing an existing bundle
- new ZIP size
- entry count
- archive SHA-256
- `release.json.build.id`
- `release.json.build.git_commit`
- internal `checksums.sha256` result
- dev-artifact scan result
- confirmation that production Composer dependencies were used

The audit should confirm:

- `checksums.sha256` has no missing or bad files
- dev packages listed above are absent
- `tests`, `node_modules`, `.env`, and `.git` are absent
- required runtime files, installer tools, Data Prep tools, frontend assets, and release metadata are present

## Installer Contract Compatibility

The bundle audit must compare production ZIP contents against installer behavior. This is separate from checksum verification: a ZIP can be checksum-clean and still be unusable if the installer calls a file or class that pruning removed.

Before Kit builds an installer, each Laravel-style bundle must pass these compatibility checks:

- If `database/seeders` is excluded, fresh Kit installs must not run `db:seed`.
- If a specific seeder command is still used, such as `SettingsSeeder` or `DatabaseSeeder`, the required seeder file must exist in the ZIP or the installer must safely skip that command for `baseline_schema` installs.
- If `database/factories` is excluded, installer/bootstrap code must not rely on factories.
- If optional tools such as `ffprobe` are excluded, installer preflight must report them as optional or external-only.
- Fresh `baseline_schema` installs should rely on the baseline schema, installer bootstrap, and Data Prep tools rather than development seeders.

Kit's pre-build checklist treats any mismatch here as a stop condition. See [Pre-Build Verification Checklist](pre-build-verification-checklist.md).

## Kit Handoff

After producing a canonical bundle:

1. Copy the ZIP to `C:\wamp64\www\pbb\kit-setup\packages\bundled\`.
2. Update `C:\wamp64\www\pbb\kit-setup\packages\packages.bundled.json` with the new SHA-256.
3. Announce the handoff in the shared chat log with the audit details.
4. Wait for Kit Setup to rebuild the desktop installer before treating the change as operator-facing.

Local app source changes or a refreshed bundle alone do not update the operator-facing installer. The Kit desktop installer must be rebuilt so `setup.exe` embeds the new canonical ZIP.

## Current Reference

Realtime's 2026-05-27 production-pruned rebuild is the reference implementation for this standard:

- previous bundle: about 28.99 MB with dev vendor present
- production-pruned bundle: about 8.03 MB
- build stage used `composer install --no-dev --optimize-autoloader`
- dev packages and test/tooling folders were absent
- local development checkout remained untouched

Hotline's same-day correction from an oversized 103 MB bundle to an 83 MB production-dependency bundle shows the same issue at a larger scale.
