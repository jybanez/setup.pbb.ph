# Release Candidate: v1-0.1.120

Status: release candidate rebuild for operator testing.

Installer artifact:

```text
C:\wamp64\www\pbb\kit-setup\out\Project Bantay Bayan Setup 0.1.120.exe
```

SHA256:

```text
E11744F5E897A8779FECD6783C196645F911F33E30FA60F554039D62A3F7F4C6
```

## Change From v1-0.1.119

This candidate keeps the v1-0.1.119 setup shell behavior and refreshes the embedded Hotline bundle to the final production-dependency SITREP group preset build.

Embedded Hotline bundle:

```text
C:\wamp64\www\pbb\kit-setup\packages\bundled\pbb-hotline-5.6.1.zip
```

Hotline bundle metadata:

- Version: `5.6.1`
- Build ID: `pbb-hotline-5.6.1-20260527-sitrep-group-presets`
- Build git commit: `3fdfd49`
- SHA256: `1be24b85cd54a849d2048df57efe929483e7df3d07826ddaf44bfd9038740abd`
- Update flags: `requires_database_migration=true`, `requires_data_prep_rerun=true`, `requires_service_restart=true`

This replaces the superseded oversized Hotline bundle hash `174afb63028543d6f00d1eeb13c65229ca846b7737defbaf3575160f651e0879`.

## Carried Forward

- Setup mode shows the Startup Requirements gate before Admin Inputs.
- Start Installation does not force the startup gate to rerun.
- The hidden Windows DNS Server target is derived as IPv4 when Technitium URL uses `http://dns.pbb.ph:5380`.
- Data Prep mode hides the setup-only Startup Requirements gate.
