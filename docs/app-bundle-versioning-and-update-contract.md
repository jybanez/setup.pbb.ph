# App Bundle Versioning And Update Contract

Project Bantay Bayan apps are distributed to Kit Setup as trusted ZIP bundles. This document defines the bundle identity and update metadata each app should prepare before Kit starts enforcing update protocols.

For production bundle contents and dependency pruning, also follow [App Bundle Packaging Standard](app-bundle-packaging-standard.md).

## Goals

- Make every app bundle uniquely identifiable.
- Separate app product version from exact bundle build identity.
- Let apps declare which installed versions can safely accept an update.
- Prevent accidental reuse of the same version for different released contents.
- Give Kit enough metadata to plan future app update, repair, refresh, rollback, and Data Prep rerun workflows.

## Bundle Naming

Canonical bundle filenames must use:

```text
pbb-{app-code}-{semver}.zip
```

Examples:

```text
pbb-mapserver-1.0.0.zip
pbb-maestro-1.0.0.zip
pbb-realtime-1.0.0.zip
pbb-relay-1.1.0.zip
pbb-hotline-5.6.1.zip
```

Rules:

- `{app-code}` must match the canonical app code used by Kit and `release.json`.
- `{semver}` must match `release.json.version`.
- Do not add suffixes like `-fixed`, `-test`, `-hotfix`, `-data-prep`, branch names, timestamps, or copies to canonical bundle filenames.
- Experimental bundles must not be placed in Kit's trusted bundled package path.

Supplemental app-owned payloads may use a more specific filename when they are not standalone apps. MapServer boundary packs use:

```text
pbb-mapserver-boundaries-province-{prov_code}.zip
```

These packs must be declared from the owning app's `packages.bundled.json` entry under `supplemental_packages`, include a SHA-256 hash, and extract only into app-owned runtime paths. The main MapServer app bundle remains `pbb-mapserver-1.0.0.zip`; province packs carry the deployment-scoped boundary data.

## Required Identity Fields

Every canonical app bundle must include `release.json` with stable identity fields:

```json
{
  "schema_version": 1,
  "app": "pbb-hotline",
  "version": "5.6.1",
  "display_version": "v1-5.6.1",
  "build": {
    "version": "5.6.1",
    "id": "pbb-hotline-20260524-001",
    "built_at": "2026-05-24T00:00:00+08:00",
    "git_commit": "abcdef1234567890",
    "builder": "pbb-hotline-release-build"
  }
}
```

Rules:

- `release.json.app` must match the Kit app id, for example `pbb-hotline`.
- `release.json.version` must match the filename version.
- `build.id` must be unique for every produced bundle, even if the app version is unchanged during testing.
- `build.git_commit` should be present for source-controlled app builds.
- `packages.bundled.json.sha256` identifies the exact immutable ZIP artifact submitted to Kit.

## Version Versus Build

App version and bundle build are different concepts.

```text
app version:  5.6.1
build id:     pbb-hotline-20260524-001
archive hash: 52b00448f97146ec8fac554e499d5fe4ad1f810bb206bafbfdf567cb34ba665a
```

Kit will eventually track all three in install state:

- app id
- app version
- display version
- build id
- git commit
- archive sha256
- installed at
- install mode

This lets Kit distinguish:

- same version, same hash: already installed
- same version, different hash: same-version rebuild or hotfix candidate
- higher version: update candidate
- lower version: rollback or downgrade candidate

## Same-Version Rebuilds

During active pre-release or test coordination, app teams may refresh a canonical bundle while keeping the same app version.

That is allowed only while the bundle is not considered immutable release content.

Rules for same-version rebuilds:

- `build.id` must change.
- `build.git_commit` should reflect the source revision.
- `checksums.sha256` inside the bundle must be regenerated after all file changes.
- `packages.bundled.json.sha256` must be updated to the new archive hash.
- The team must describe what changed in the chat log.

Once a bundle is accepted for release, the same app version must not be overwritten with different contents. Any behavior, installer, Data Prep, schema, asset, config, or runtime change after release should bump the app version.

## Update Metadata

Each app should prepare an `update` block in `release.json`.

Testing or same-version rebuild example:

```json
{
  "update": {
    "contract_version": 1,
    "channel": "testing",
    "immutable_release": false,
    "from_versions": ["5.6.1"],
    "compatibility": "same-version-rebuild",
    "requires_database_migration": false,
    "requires_data_prep_rerun": false,
    "requires_service_restart": true,
    "rollback_supported": true
  }
}
```

Patch update example:

```json
{
  "update": {
    "contract_version": 1,
    "channel": "release",
    "immutable_release": true,
    "from_versions": [">=5.6.1 <5.7.0"],
    "compatibility": "patch",
    "requires_database_migration": false,
    "requires_data_prep_rerun": false,
    "requires_service_restart": true,
    "rollback_supported": true
  }
}
```

Minor or major update example:

```json
{
  "update": {
    "contract_version": 1,
    "channel": "release",
    "immutable_release": true,
    "from_versions": [">=5.6.0 <5.7.0"],
    "compatibility": "minor",
    "requires_database_migration": true,
    "requires_data_prep_rerun": true,
    "requires_service_restart": true,
    "rollback_supported": false
  }
}
```

## Compatibility Values

Apps should use one of these compatibility values:

```text
same-version-rebuild
patch
minor
major
repair
rollback
unsupported
```

Meaning:

- `same-version-rebuild`: same app version, different build/hash, allowed during testing or explicitly approved repair.
- `patch`: compatible patch update within the same minor line.
- `minor`: feature or contract update that may require migration or Data Prep rerun.
- `major`: breaking update requiring explicit planning.
- `repair`: same version and expected hash, redeploy app files or restore missing files.
- `rollback`: app-owned downgrade path to a previous version/build.
- `unsupported`: Kit must not apply this bundle to the detected installed version.

## Future Kit Enforcement

Kit does not enforce this full contract yet. Teams should start preparing their bundles now.

Planned enforcement phases:

1. Preparation phase:
   - Teams add update metadata.
   - Kit continues accepting current bundles.
   - Kit may document warnings only.

2. Warning phase:
   - Kit warns when `release.json.update` is missing.
   - Kit warns when `build.id` is missing or reused.
   - Kit warns when same version has a different hash without `same-version-rebuild`.

3. Enforcement phase:
   - Kit blocks canonical bundles with filename/version mismatch.
   - Kit blocks released same-version hash changes unless declared as an approved same-version rebuild.
   - Kit blocks update attempts not allowed by `from_versions`.
   - Kit requires app-owned update metadata before running update workflows.

## App Team Checklist

Before submitting a canonical bundle to Kit:

- Confirm filename follows `pbb-{app-code}-{semver}.zip`.
- Confirm `release.json.app` and `release.json.version` match the filename.
- Confirm `release.json.build.id` is unique.
- Confirm `release.json.build.git_commit` is accurate when available.
- Confirm `release.json.update` is present or planned.
- Confirm the ZIP follows [App Bundle Packaging Standard](app-bundle-packaging-standard.md), including production Composer dependencies only where applicable.
- Regenerate internal `checksums.sha256` after all file changes.
- Verify internal checksum scan reports no missing or bad files.
- Update `packages.bundled.json` with the archive SHA-256.
- Announce the app id, version, build id, archive hash, and update compatibility in the shared chat log.
