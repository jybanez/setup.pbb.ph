# External Machine Dry Run

Use this when testing Kit Setup on a different Windows or Linux machine without changing DNS, Apache, or app folders.

## Safety Defaults

Keep these settings until the reports are reviewed:

```json
{
  "packages": {
    "dry_run": true
  },
  "dns": {
    "update_mode": "plan-only"
  },
  "ssl": {
    "write_extracted_files": false,
    "web_server_update_mode": "plan-only"
  }
}
```

## Runtime Secrets

Set these as environment variables instead of writing them into config files.

PowerShell:

```powershell
$env:PBB_HUB_TOKEN = "<hub token>"
$env:PBB_TECHNITIUM_TOKEN = "<technitium token>"
$env:PBB_FIRST_ADMIN_PASSWORD = "<temporary admin password>"
$env:PBB_MYSQL_PASSWORD = "<database password>"
```

Bash:

```bash
export PBB_HUB_TOKEN="<hub token>"
export PBB_TECHNITIUM_TOKEN="<technitium token>"
export PBB_FIRST_ADMIN_PASSWORD="<temporary admin password>"
export PBB_MYSQL_PASSWORD="<database password>"
```

## Recommended Command Order

Windows/WAMP example:

```powershell
$php = "C:\wamp64\bin\php\php8.2.29\php.exe"
$kit = "C:\wamp64\www\pbb\kit-setup\bin\kit-setup.php"
$config = "C:\wamp64\www\pbb\kit-setup\examples\kit-config.local-all.example.json"

& $php $kit --config $config --action detect --run-id dryrun_detect
& $php $kit --config $config --action stage-report --run-id dryrun_stage
& $php $kit --config $config --action plan --run-id dryrun_plan
& $php $kit --config $config --action prepare-packages --run-id dryrun_packages
& $php $kit --config $config --action preflight --run-id dryrun_preflight
& $php $kit --config $config --action dns-plan --run-id dryrun_dns
& $php $kit --config $config --action dns-verify --run-id dryrun_dns_verify
& $php $kit --config $config --action ssl-plan --run-id dryrun_ssl
& $php $kit --config $config --action remote-check --run-id dryrun_remote
& $php $kit --config $config --action smoke-check --run-id dryrun_smoke
```

Linux example:

```bash
php /opt/pbb/kit-setup/bin/kit-setup.php --config /opt/pbb/kit-setup/examples/kit-config.local-all.example.json --action detect --run-id dryrun_detect
php /opt/pbb/kit-setup/bin/kit-setup.php --config /opt/pbb/kit-setup/examples/kit-config.local-all.example.json --action stage-report --run-id dryrun_stage
php /opt/pbb/kit-setup/bin/kit-setup.php --config /opt/pbb/kit-setup/examples/kit-config.local-all.example.json --action plan --run-id dryrun_plan
php /opt/pbb/kit-setup/bin/kit-setup.php --config /opt/pbb/kit-setup/examples/kit-config.local-all.example.json --action prepare-packages --run-id dryrun_packages
php /opt/pbb/kit-setup/bin/kit-setup.php --config /opt/pbb/kit-setup/examples/kit-config.local-all.example.json --action preflight --run-id dryrun_preflight
php /opt/pbb/kit-setup/bin/kit-setup.php --config /opt/pbb/kit-setup/examples/kit-config.local-all.example.json --action dns-plan --run-id dryrun_dns
php /opt/pbb/kit-setup/bin/kit-setup.php --config /opt/pbb/kit-setup/examples/kit-config.local-all.example.json --action ssl-plan --run-id dryrun_ssl
php /opt/pbb/kit-setup/bin/kit-setup.php --config /opt/pbb/kit-setup/examples/kit-config.local-all.example.json --action remote-check --run-id dryrun_remote
php /opt/pbb/kit-setup/bin/kit-setup.php --config /opt/pbb/kit-setup/examples/kit-config.local-all.example.json --action smoke-check --run-id dryrun_smoke
```

## Reports To Review

Reports are written under:

```text
storage/runs/{run-id}/
```

Key reports:

- `platform-report.json`
- `stage-report.json`
- `kit-report.json`
- `package-report.json`
- app preflight reports under `apps/`
- `dns-plan.json`
- `dns-verify.json`
- `ssl-plan.json`
- `remote-check.json`
- smoke check report

Do not enable `dns.update_mode=apply`, `ssl.web_server_update_mode=apply`, or `packages.dry_run=false` until these reports look correct.

## What To Send Back

When testing on another machine, send back:

- `stage-report.json`
- `kit-report.json`
- `platform-report.json`
- any failed action report

Do not send files under `storage/runs/*/secrets/`.
