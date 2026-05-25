# PBB Kit Setup

This project coordinates installation of multiple Project Bantay Bayan applications on a target machine.

Official repository: https://github.com/jybanez/setup.pbb.ph

The intent is not to replace each app's own installer. Each PBB app should ship a self-contained installer or release bundle, while Kit Setup orchestrates the complete machine setup from start to finish.

Start here:

- [Release Candidate v1-0.1.118](docs/release-candidate-v1-0.1.118.md)
- [Non-Technical Install Flow](docs/non-technical-install-flow.md)
- [Implementation Decisions](docs/implementation-decisions.md)
- [Desktop Installer Shell](docs/desktop-installer-shell.md)
- [Implementation Checklist](docs/implementation-checklist.md)
- [External Machine Dry Run](docs/external-machine-dry-run.md)
- [Installer Coordination Standards](docs/installer-coordination-standards.md)
- [App Installer Template](docs/app-installer-template.md)
- [Installer Readiness Checklist](docs/installer-readiness-checklist.md)
- [Kit Setup Runner](docs/kit-setup-runner.md)

Smoke-test the current runner with the stub app fixture:

```powershell
& "C:\wamp64\bin\php\php8.2.29\php.exe" "C:\wamp64\www\pbb\kit-setup\bin\kit-setup.php" --config "C:\wamp64\www\pbb\kit-setup\examples\kit-config.stub.json" --action preflight
```

`examples\kit-config.stub.json` includes a local directory package manifest so planner actions such as `plan` and `stage-report` can run without generated ZIP fixtures. Use `examples\kit-config.stub-archive.example.json` when specifically testing signed archive extraction/deployment.
