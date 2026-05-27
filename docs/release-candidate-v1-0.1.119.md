# Release Candidate: v1-0.1.119

Status: release candidate rebuild for operator testing.

Installer artifact:

```text
C:\wamp64\www\pbb\kit-setup\out\Project Bantay Bayan Setup 0.1.119.exe
```

SHA256:

```text
039CF6825A0754CD53F46C91DCC05894C7B238DDB3D5F32F766D69F4E4FD55F6
```

## Change From v1-0.1.118

This candidate keeps the v1-0.1.118 setup shell behavior and refreshes the embedded Hotline bundle.

Embedded Hotline bundle:

```text
C:\wamp64\www\pbb\kit-setup\packages\bundled\pbb-hotline-5.6.1.zip
```

Hotline bundle metadata:

- Version: `5.6.1`
- Build ID: `pbb-hotline-5.6.1-20260527-main-sitrep-cleanup`
- Build git commit: `a1d793a`
- SHA256: `4a8c744091b2fdfc7aabd76af16910a0c7c26422b0e0745a0e6a752601b91276`
- Update flags: `requires_database_migration=true`, `requires_data_prep_rerun=true`, `requires_service_restart=true`

The Hotline bundle is intentionally a same-version rebuild under `pbb-hotline-5.6.1.zip` during RC hardening. The exact ZIP hash and `release.json.build.id` identify the refreshed artifact.

## Carried Forward From v1-0.1.118

- Setup mode shows the Startup Requirements gate before Admin Inputs.
- Setup mode keeps the gate result until the operator clicks `Check Again`; Start Installation does not force a gate rerun.
- `http://dns.pbb.ph:5380` may remain the Technitium URL, but the Windows DNS client target is resolved/probed to an IPv4 address before validation.
- Data Prep mode hides the Startup Requirements gate and is gated only by the completed Kit Setup state.

## Operator Test Focus

For this rebuild, retest the normal install path plus Hotline-specific checks:

- Trusted package preparation accepts the refreshed Hotline bundle hash.
- Hotline fresh install applies any required database migration from the refreshed bundle.
- Data Prep rerun is performed after install because the Hotline bundle declares `requires_data_prep_rerun=true`.
- Hotline runtime services are restarted after settings/data changes because the bundle declares `requires_service_restart=true`.
- SITREP/manual-reporting surfaces and approved legacy cleanup/runtime default fixes are present after install.
