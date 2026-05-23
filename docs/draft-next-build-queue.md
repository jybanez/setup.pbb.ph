# Draft: Build Queue Checklist

Status: living checklist for items discussed during installer testing. Do not treat an item as shipped until it appears under "Built / Released".

## Status Legend

- [x] Implemented in the current worktree, pending packaged test build or release confirmation.
- [ ] Queued for a future build.
- [ ] Needs investigation or upstream feedback before implementation can be completed.

## Implemented, Pending UI Verification

- [x] Remove legacy admin input forms after Helper property editor migration.
  - The Helper property editor is the single admin input surface.
  - The old per-stage input form should not render as a fallback because it bloats Kit Setup and can block automated install.

- [x] Fix automated install confirmation to use an action modal.
  - The modal should show all admin inputs for verification before automation starts.
  - The admin confirms the complete input set once.
  - Admin input panels are hidden while automated install runs.

- [x] Use `ui.password` for password/token fields.
  - Hub token.
  - Database password.
  - Admin password.
  - Technitium token.
  - Future secret fields.

- [x] Replace the raw admin input layout with the Helper property editor pattern.
  - Platform paths, hub pairing, app scope, base path, database/admin account, DNS, SSL/Apache, and firewall choices should read as one structured configuration surface.

- [x] Make "Apps on this machine" selected/focused state visible.
  - Selected, focused, and hover states should be obvious in the Helper dark theme.

- [x] Fix package preparation progress styling in dark theme.
  - Package cards should not render as light panels inside the dark setup shell.
  - Text contrast must be readable for package names, status messages, labels, and progress percentages.

- [x] Add an overall automated install progress bar.
  - Show current phase, current action, and weighted percent complete.
  - Keep package-level progress as detailed progress during package preparation.

- [x] Improve websocket smoke-check diagnostics.
  - Distinguish DNS, TCP/TLS socket, TLS peer verification, Apache closed socket, HTTP status, and malformed/no response cases.
  - Group public WSS proxy diagnostics with local runtime service health.

- [x] Add runtime service failure cleanup design.
  - Record PIDs for processes launched by the current run.
  - Stop only processes started by the current run when verification fails.
  - Do not kill manually started or pre-existing matching processes.

- [x] Integrate Helper `ui.path.picker` for local path inputs.
  - Replace plain property-editor text rows plus separate browse actions for PHP, Apache, MySQL/MariaDB, config JSON, install base, cert folder, PEM bundle, and Apache include paths.
  - Refresh Kit's vendored Helper bundle from `C:\wamp64\www\hotline-helpers`.
  - Kit provides native picker and validation adapters through `pickFile`, `pickFolder`, save-file selection, and path validation IPC.

- [x] Add local IP auto-detection.
  - Pre-fill Machine IP from the active local network adapter.
  - Keep the field editable because multi-NIC machines may need manual selection.

- [x] Use the Helper dark theme consistently.
  - Remove remaining light-theme surfaces inside the setup shell.
  - Confirm property editor, path picker, progress, modal, and report surfaces are visually consistent.

- [x] Activate the setup workflow stepper.
  - Reflect current automated install phase and checkpoint results.
  - States should include pending, active/running, completed, warning, and failed.
  - Clicking a step should jump to the relevant panel/report where available.

- [x] Reframe the left sidebar as stage status and troubleshooting.
  - Automated install is the primary flow.
  - Sidebar remains for diagnostics, manual reruns, and failed-stage details.
  - Consider labeling it "Stage Status" or "Advanced / Troubleshooting".
  - Visually de-emphasize or collapse it during normal automated runs.

- [x] Add existing-install discovery scaffold at startup.
  - Check configured app paths and local install manifests before install.
  - Show installed/path-found/not-found state in the desktop UI.

- [x] Clean firewall entries during uninstall.
  - Any firewall rules created by install should be removed by uninstall.
  - Rule names should be deterministic and scoped to PBB Kit Setup.

- [x] Add explicit `service-stop` action for Kit-managed runtime services.
  - Provide a manual stop/recovery path for services started during verification or smoke testing.
  - Report which services were stopped and which were left alone because Kit did not start them.

- [x] Show successful-install alert and close the installer.
  - After the automated install sequence reaches successful finish, show a native success dialog.
  - Close and quit the installer after the admin closes the success dialog.
  - Manual troubleshooting actions and manual `finish-report` runs should not auto-close the installer.

- [x] Hide manual action buttons unless automated installation fails.
  - Automated installation is the primary path.
  - Manual action buttons are hidden during normal input and install flow.
  - If the automated sequence stops on a failed action, troubleshooting actions become visible.

- [x] Remove app-side installer artifacts after successful app install.
  - Keep the Windows desktop installer for now.
  - Remove deployed app `/installer` and `/public/installer` entrypoints after the app install succeeds.
  - Retain `storage/app/installer` and `storage/installer` reports/manifests for support diagnostics.

- [x] Increase desktop timeout for heavy planning actions.
  - `plan` and `stage-report` can scan trusted packages and deployed release checksums.
  - These actions should use the long action timeout instead of the 120-second quick action timeout.

- [x] Move app progress App column subtext from internal app id to app domain.
  - App cell shows app name with the user-facing domain below it.
  - Internal app id remains available through hover/title context.

- [x] Add a visual active-step orbit to the workflow stepper.
  - The current workflow step marker uses a subtle animated circular border.
  - Reduced-motion OS settings disable the animation.

- [x] Move stage metric strip into the top header.
  - Status, success, warnings, and pending are compact header metrics.
  - The large standalone metric strip is removed from the normal flow.

- [x] Use Helper `ui.alert` for successful install completion.
  - The success dialog uses the Helper success variant.
  - The installer quits after the admin closes the success dialog.

- [x] Keep troubleshooting blocks hidden unless dev tools are open.
  - Resume checkpoints, raw finish summary, stage detail, and runner output are hidden in normal admin flow.
  - Dev tools mode restores those blocks for diagnostics.

- [x] Add admin-facing Helper alerts for automated install warnings and failures.
  - Warnings and failures are relayed through Helper dialogs.
  - Raw troubleshooting blocks remain hidden unless dev tools are open.

- [x] Add install-base disk space estimate.
  - Disk space is estimated per selected app package and install base drive.
  - The Install Base admin input section shows required and free target drive space.
  - Automated install separately checks staging space and blocks when target or staging space is insufficient.

## Queued For Next Build

- [x] Add existing-install decision UI.
  - Add lightweight GET/DNS checks where possible to identify reachable apps and resolved IP.
  - Present overwrite, repair/reinstall, skip, or use existing options.
  - Feed the chosen decision into package preparation/install instead of only reporting discovery.

- [x] Redesign automated-install confirmation modal around hub, app decisions, and admin inputs.
  - Use a larger Helper action modal, likely `xl`, with three main content columns.
  - Column 1: hub details, including hub id, name, domain, relay hub id, deployment/scope, and status.
  - Column 2: selected apps with existing-install discovery details, detected versions/paths/domains where available, running service/process state, file-lock or overwrite risk, and the explicit action selected for each app.
  - Column 3: other administrator-owned inputs for final confirmation, including install base, database/admin account summary, machine IP, DNS options, SSL/Apache options, and firewall options.
  - On Start, open the modal in busy state, fetch hub details, and run app existing-install discovery before enabling the final Start Installation action.
  - If hub details cannot be resolved or are incomplete, fail closed: show an alert asking the admin to verify hub information and close the confirmation modal.
  - If any selected app has an unsafe existing install state, require an explicit action such as upgrade/repair, overwrite, skip, or block installation for that app.
  - Automated install must consume the confirmed per-app decisions from the modal instead of inferring overwrite behavior later.

- [x] Auto-detect and require Technitium DNS for local automated installs.
  - Detect active adapter DNS servers and probe likely Technitium URLs such as `http://<dns-server>:5380`.
  - Also try configured/saved Technitium URL and `dns.<zone>` when available, but do not scan the whole subnet.
  - Verify more than an open port: HTTP response should look like Technitium before prefilling the URL.
  - If no Technitium instance is reachable, block automated installation for local apps.
  - Show a Helper alert dialog explaining that Technitium DNS is required to localize PBB app domains, focus `Technitium URL`, and give an example URL.
  - If DNS apply is enabled, require a Technitium token and prefer a lightweight token validation call before Start Installation is enabled.

- [x] Detect existing Windows installer installation before setup.
  - Check Windows uninstall registry entries for Project Bantay Bayan / Kit Setup.
  - Capture installed version, install location, publisher/display name, and uninstall command where available.
  - Show this separately from app-level deployed-app discovery.
  - Block or route to repair/upgrade flow if a conflicting Windows installer install is detected.

- [x] Add Helper elapsed-time readouts to automated installation.
  - Use Helper `createElapsedTime` instead of a custom timer.
  - Show total elapsed time for the full automated install near the overall progress header.
  - Freeze the elapsed time on success, failure, or manual stop.
  - Consider action-level elapsed time for long-running phases such as package preparation, install, and populate.

## Refinement / Future

- [ ] Add per-app internal install progress when app installers emit structured progress.
- [ ] Design PWA/client app installers for operator and command terminals, especially Hotline.
- [ ] Decide whether to expose raw troubleshooting blocks through an explicit UI toggle in addition to dev tools mode.

## Needs Upstream Feedback Or Investigation

- [x] Promote Kit-managed runtime processes into permanent Windows services.
  - Use the vendored WinSW wrapper only; NSSM is intentionally unsupported and there is no alternate wrapper fallback.
  - `service-start` prepares `C:\ProgramData\PBB\Services\<service-id>`, writes WinSW XML, installs missing Windows services, starts them, and verifies health.
  - `service-stop` stops declared WinSW services before overwrite/repair and during current-run cleanup.

## Built / Released

- [x] `0.1.57` correction build.
  - Removed duplicate legacy admin inputs.
  - Fixed automated install action modal handling.
  - Kept Helper property editor as the only admin input UI.
  - Test run `setup_session_1779199741699` reached successful finish report on 2026-05-20 Manila time.
  - Verified successful package preparation, plan, preflight, install, DNS, firewall, SSL, service-plan, service-start, service-verify, remote-check, smoke-check, populate, and finish.
  - Realtime public WSS smoke check passed with HTTP 101 and first message type `session.awaiting-auth`.
  - Realtime media dispatcher `process` health check passed.

- [x] Runtime service start before service verification.
  - Runtime services declared by app manifests are started before verification/smoke checks when Kit owns them.
  - Verified by successful `service-start`, `service-verify`, and `smoke-check` in run `setup_session_1779199741699`.

- [x] Realtime websocket public WSS smoke check.
  - Verified `wss://realtime.pbb.ph/realtime` returns HTTP 101 through Apache.
  - Verified first websocket message type `session.awaiting-auth`.

- [x] Media dispatcher process health check.
  - `pbb-realtime-media-dispatcher` process health check now reports `success`.

## Notes

- This checklist tracks requested installer work, not every code-level implementation detail.
- Move items into "Built / Released" only after the build is packaged and handed off for testing.
