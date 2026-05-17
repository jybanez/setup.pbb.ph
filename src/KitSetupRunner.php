<?php

declare(strict_types=1);

final class KitSetupRunner
{
    private const VERSION = '0.1.22';
    private const MILESTONE = 1;
    private const DISPLAY_VERSION = 'v1-0.1.22';

    public function main(array $argv): int
    {
        try {
            $options = $this->parseArgs($argv);

            if (($options['help'] ?? false) === true) {
                $this->printHelp();
                return 0;
            }

            $configPath = (string) ($options['config'] ?? '');
            if ($configPath === '') {
                throw new InvalidArgumentException('Missing required --config path.');
            }

            $action = (string) ($options['action'] ?? 'plan');
            if (!in_array($action, ['detect', 'hub-resolve', 'prepare-packages', 'prepare-package-worker', 'dns-plan', 'dns-apply', 'dns-client-apply', 'dns-verify', 'ssl-plan', 'ssl-apply', 'remote-check', 'smoke-check', 'stage-report', 'finish-report', 'plan', 'preflight', 'install', 'populate'], true)) {
                throw new InvalidArgumentException('Unsupported --action. Use detect, hub-resolve, prepare-packages, dns-plan, dns-apply, dns-client-apply, dns-verify, ssl-plan, ssl-apply, remote-check, smoke-check, stage-report, finish-report, plan, preflight, install, or populate.');
            }

            $config = $this->readJsonFile($configPath);
            $this->validateKitConfig($config, $configPath);

            $runId = (string) ($options['run-id'] ?? $this->makeRunId());
            $runDir = (string) ($options['run-dir'] ?? $this->joinPath((string) $config['kit']['run_root'], $runId));
            $this->ensureDirectory($runDir);
            $this->ensureDirectory($this->joinPath($runDir, 'apps'));
            $this->ensureDirectory($this->joinPath($runDir, 'logs'));

            $context = [
                'action' => $action,
                'config_path' => $this->absolutePath($configPath),
                'run_id' => $runId,
                'run_dir' => $this->absolutePath($runDir),
                'started_at' => date(DATE_ATOM),
            ];

            if ($action === 'detect') {
                $report = $this->runPlatformDetect($config, $context);
                $reportPath = $this->joinPath($runDir, 'platform-report.json');
                $this->writeJsonFile($reportPath, $report);
                $this->recordCheckpoint($runDir, $context, $report, $reportPath);
                $this->writeLine('Platform detected: ' . $this->joinPath($runDir, 'platform-report.json'));
                return $report['status'] === 'failed' ? 1 : 0;
            }

            if ($action === 'hub-resolve') {
                try {
                    $result = $this->runHubResolve($config, $runDir, $context);
                    $reportPath = $this->joinPath($runDir, 'hub-report.json');
                    $this->writeJsonFile($reportPath, $result['report']);
                    $this->writeJsonFile($this->joinPath($runDir, 'hub-resolved-config.json'), $result['config']);
                    $this->recordCheckpoint($runDir, $context, $result['report'], $reportPath);
                    $this->writeLine('Hub resolved: ' . $this->joinPath($runDir, 'hub-report.json'));
                    return $result['report']['status'] === 'success' ? 0 : 1;
                } catch (Throwable $e) {
                    $report = $this->makeFailedHubReport($context, $e->getMessage());
                    $reportPath = $this->joinPath($runDir, 'hub-report.json');
                    $this->writeJsonFile($reportPath, $report);
                    $this->recordCheckpoint($runDir, $context, $report, $reportPath);
                    $this->writeLine('Hub resolve failed: ' . $this->joinPath($runDir, 'hub-report.json'), true);
                    return 1;
                }
            }

            if (($config['hub']['auto_resolve'] ?? false) === true) {
                $config = $this->runHubResolve($config, $runDir, $context)['config'];
            }

            if ($action === 'prepare-packages') {
                $report = $this->runPackagePrepare($config, $runDir, $context);
                $reportPath = $this->joinPath($runDir, 'package-report.json');
                $this->writeJsonFile($reportPath, $report);
                $this->recordCheckpoint($runDir, $context, $report, $reportPath);
                $this->writeLine('Package preparation report: ' . $this->joinPath($runDir, 'package-report.json'));
                return $report['status'] === 'success' || $report['status'] === 'warning' ? 0 : 1;
            }

            if ($action === 'prepare-package-worker') {
                $appId = (string) ($options['app'] ?? '');
                $reportPath = (string) ($options['worker-report'] ?? '');
                if ($appId === '' || $reportPath === '') {
                    throw new InvalidArgumentException('prepare-package-worker requires --app and --worker-report.');
                }
                $report = $this->runPackagePrepareWorker($config, $runDir, $context, $appId);
                $this->writeJsonFile($reportPath, $report);
                return ($report['status'] ?? '') === 'failed' ? 1 : 0;
            }

            if ($action === 'dns-plan') {
                $report = $this->runDnsPlan($config, $context);
                $reportPath = $this->joinPath($runDir, 'dns-plan.json');
                $this->writeJsonFile($reportPath, $report);
                $this->recordCheckpoint($runDir, $context, $report, $reportPath);
                $this->writeLine('DNS plan: ' . $this->joinPath($runDir, 'dns-plan.json'));
                return $report['status'] === 'failed' ? 1 : 0;
            }

            if ($action === 'dns-apply') {
                $report = $this->runDnsApply($config, $context);
                $reportPath = $this->joinPath($runDir, 'dns-apply.json');
                $this->writeJsonFile($reportPath, $report);
                $this->recordCheckpoint($runDir, $context, $report, $reportPath);
                $this->writeLine('DNS apply report: ' . $this->joinPath($runDir, 'dns-apply.json'));
                return $report['status'] === 'success' || $report['status'] === 'skipped' ? 0 : 1;
            }

            if ($action === 'dns-client-apply') {
                $report = $this->runDnsClientApply($config, $context);
                $reportPath = $this->joinPath($runDir, 'dns-client-apply.json');
                $this->writeJsonFile($reportPath, $report);
                $this->recordCheckpoint($runDir, $context, $report, $reportPath);
                $this->writeLine('DNS client apply report: ' . $this->joinPath($runDir, 'dns-client-apply.json'));
                return $report['status'] === 'success' || $report['status'] === 'skipped' ? 0 : 1;
            }

            if ($action === 'dns-verify') {
                $report = $this->runDnsVerify($config, $context);
                $reportPath = $this->joinPath($runDir, 'dns-verify.json');
                $this->writeJsonFile($reportPath, $report);
                $this->recordCheckpoint($runDir, $context, $report, $reportPath);
                $this->writeLine('DNS verification report: ' . $this->joinPath($runDir, 'dns-verify.json'));
                return $report['status'] === 'failed' ? 1 : 0;
            }

            if ($action === 'ssl-plan') {
                $report = $this->runSslPlan($config, $runDir, $context);
                $reportPath = $this->joinPath($runDir, 'ssl-plan.json');
                $this->writeJsonFile($reportPath, $report);
                $this->recordCheckpoint($runDir, $context, $report, $reportPath);
                $this->writeLine('SSL/vhost plan: ' . $this->joinPath($runDir, 'ssl-plan.json'));
                return $report['status'] === 'failed' ? 1 : 0;
            }

            if ($action === 'ssl-apply') {
                $report = $this->runSslApply($config, $runDir, $context);
                $reportPath = $this->joinPath($runDir, 'ssl-apply.json');
                $this->writeJsonFile($reportPath, $report);
                $this->recordCheckpoint($runDir, $context, $report, $reportPath);
                $this->writeLine('SSL/vhost apply report: ' . $this->joinPath($runDir, 'ssl-apply.json'));
                return in_array($report['status'], ['success', 'skipped'], true) ? 0 : 1;
            }

            if ($action === 'remote-check') {
                $report = $this->runRemoteDependencyCheck($config, $context);
                $reportPath = $this->joinPath($runDir, 'remote-check.json');
                $this->writeJsonFile($reportPath, $report);
                $this->recordCheckpoint($runDir, $context, $report, $reportPath);
                $this->writeLine('Remote dependency check: ' . $this->joinPath($runDir, 'remote-check.json'));
                return $report['status'] === 'failed' ? 1 : 0;
            }

            if ($action === 'smoke-check') {
                $report = $this->runSmokeCheck($config, $context);
                $reportPath = $this->joinPath($runDir, 'smoke-check.json');
                $this->writeJsonFile($reportPath, $report);
                $this->recordCheckpoint($runDir, $context, $report, $reportPath);
                $this->writeLine('Smoke check: ' . $this->joinPath($runDir, 'smoke-check.json'));
                return $report['status'] === 'failed' ? 1 : 0;
            }

            if ($action === 'stage-report') {
                $report = $this->runStageReport($config, $runDir, $context);
                $reportPath = $this->joinPath($runDir, 'stage-report.json');
                $this->writeJsonFile($reportPath, $report);
                $this->recordCheckpoint($runDir, $context, $report, $reportPath);
                $this->writeLine('Stage report: ' . $this->joinPath($runDir, 'stage-report.json'));
                return $report['status'] === 'failed' ? 1 : 0;
            }

            if ($action === 'finish-report') {
                $report = $this->runFinishReport($config, $runDir, $context);
                $reportPath = $this->joinPath($runDir, 'finish-report.json');
                $this->writeJsonFile($reportPath, $report);
                $this->recordCheckpoint($runDir, $context, $report, $reportPath);
                $this->writeLine('Finish report: ' . $this->joinPath($runDir, 'finish-report.json'));
                return $report['status'] === 'failed' ? 1 : 0;
            }

            $secretResult = $this->resolveKitSecrets($config, $runDir);
            $config = $secretResult['config'];

            $apps = $this->discoverApps($config);
            $orderedApps = $this->orderApps($apps);
            $appFilter = (string) ($options['app'] ?? '');
            if ($appFilter !== '') {
                $orderedApps = $this->filterOrderedApps($orderedApps, $appFilter);
                $context['app_filter'] = $appFilter;
            }

            $kitReport = [
                'schema_version' => 1,
                'kit_setup_version' => self::VERSION,
                'run_id' => $runId,
                'action' => $action,
                'status' => 'running',
                'started_at' => $context['started_at'],
                'finished_at' => null,
                'config_path' => $context['config_path'],
                'run_dir' => $context['run_dir'],
                'app_filter' => $appFilter !== '' ? $appFilter : null,
                'secrets' => $secretResult['report'],
                'apps' => [],
                'warnings' => [],
                'errors' => [],
            ];

            if ($action === 'plan') {
                $kitReport['installation_plan'] = $this->buildInstallationPlanSummary($config, $runDir, $context);
                foreach ($orderedApps as $app) {
                    $kitReport['apps'][] = $this->planApp($app, $config, $runDir, $runId);
                }
                $kitReport['status'] = (string) ($kitReport['installation_plan']['status'] ?? 'success');
                $kitReport['finished_at'] = date(DATE_ATOM);
                $reportPath = $this->joinPath($runDir, 'kit-report.json');
                $this->writeJsonFile($reportPath, $kitReport);
                $this->recordCheckpoint($runDir, $context, $kitReport, $reportPath);
                $this->writeLine('Plan completed: ' . $this->joinPath($runDir, 'kit-report.json'));
                return 0;
            }

            $failed = false;
            foreach ($orderedApps as $app) {
                $appResult = $action === 'populate'
                    ? $this->runAppPopulationTools($app, $config, $runDir, $runId)
                    : $this->runAppInstaller($app, $config, $runDir, $runId, $action);
                $kitReport['apps'][] = $appResult;
                if (!in_array($appResult['status'], ['success', 'warning', 'skipped'], true)) {
                    $failed = true;
                    if ($action === 'install') {
                        break;
                    }
                }
            }

            $kitReport['status'] = $failed ? 'failed' : 'success';
            $kitReport['finished_at'] = date(DATE_ATOM);
            $reportPath = $this->joinPath($runDir, 'kit-report.json');
            $this->writeJsonFile($reportPath, $kitReport);
            $this->recordCheckpoint($runDir, $context, $kitReport, $reportPath);
            $this->writeLine('Run report: ' . $this->joinPath($runDir, 'kit-report.json'));

            return $failed ? 1 : 0;
        } catch (Throwable $e) {
            $this->writeLine('ERROR: ' . $e->getMessage(), true);
            return 1;
        }
    }

    private function buildInstallationPlanSummary(array $config, string $runDir, array $context): array
    {
        $packageConfig = is_array($config['packages'] ?? null) ? $config['packages'] : [];
        $packagePlanConfig = $config;
        $packagePlanConfig['packages'] = $packageConfig;
        $packagePlanConfig['packages']['dry_run'] = true;

        $platform = $this->runPlatformDetect($config, $context);
        $dns = $this->runDnsPlan($config, $context);
        $ssl = $this->runSslPlan($config, $runDir, $context);
        $remote = $this->runRemoteDependencyCheck($config, $context);
        $packages = $this->runPackagePrepare($packagePlanConfig, $runDir, $context);

        $apps = [
            'local' => [],
            'remote' => [],
            'disabled' => [],
        ];
        foreach ($config['apps'] as $app) {
            if (!is_array($app)) {
                continue;
            }
            $id = (string) ($app['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $scope = (string) ($app['install_scope'] ?? 'local');
            if (($app['enabled'] ?? true) === false) {
                $scope = 'disabled';
            }
            if (!isset($apps[$scope])) {
                $apps[$scope] = [];
            }
            $apps[$scope][] = $id;
        }

        return [
            'status' => $this->aggregateStatuses([
                $platform['status'] ?? 'success',
                $dns['status'] ?? 'success',
                $ssl['status'] ?? 'success',
                $remote['status'] ?? 'success',
                $packages['status'] ?? 'success',
            ]),
            'apps' => $apps,
            'base_path' => $config['layout']['base_path'] ?? $config['paths']['apps_base'] ?? null,
            'platform' => [
                'status' => $platform['status'],
                'tools' => $platform['tools'] ?? [],
                'services' => $platform['services'] ?? [],
            ],
            'packages' => [
                'status' => $packages['status'],
                'manifest_path' => $packages['manifest_path'] ?? null,
                'selected_count' => count($packages['packages'] ?? []),
                'failed' => array_values(array_filter($packages['packages'] ?? [], static fn (array $package): bool => ($package['status'] ?? '') === 'failed')),
                'warnings' => array_values(array_filter($packages['packages'] ?? [], static fn (array $package): bool => ($package['status'] ?? '') === 'warning')),
            ],
            'dns' => [
                'status' => $dns['status'],
                'records' => $dns['records'] ?? [],
            ],
            'ssl' => [
                'status' => $ssl['status'],
                'certificate' => $ssl['certificate'] ?? null,
                'coverage' => $ssl['certificate_coverage'] ?? null,
                'apache_include' => $ssl['apache']['generated_include_path'] ?? null,
            ],
            'remote_dependencies' => [
                'status' => $remote['status'],
                'remote_apps' => $remote['remote_apps'] ?? [],
            ],
        ];
    }

    private function aggregateStatuses(array $statuses): string
    {
        if (in_array('failed', $statuses, true)) {
            return 'failed';
        }
        if (count(array_intersect($statuses, ['warning', 'skipped'])) > 0) {
            return 'warning';
        }
        return 'success';
    }

    private function runStageReport(array $config, string $runDir, array $context): array
    {
        $summary = $this->buildInstallationPlanSummary($config, $runDir, $context);
        $admin = $this->inspectAdminSetup($config);
        $hub = $this->inspectHubPairingState($config);
        $selectedApps = $this->inspectSelectedAppsState($config);
        $basePath = $this->inspectBasePathState($config);
        $stages = [
            $this->makeStage(1, 'Platform Check', $summary['platform']['status'] ?? 'warning', 'Host prerequisites detected.'),
            $this->makeStage(2, 'Hub Pairing', $hub['status'], $hub['message'], $hub),
            $this->makeStage(3, 'Select Apps', $selectedApps['status'], $selectedApps['message'], $selectedApps),
            $this->makeStage(4, 'Choose Base Path', $basePath['status'], $basePath['message'], $basePath),
            $this->makeStage(5, 'Prepare Trusted App Packages', $summary['packages']['status'] ?? 'warning', 'Trusted package plan completed.', $summary['packages']),
            $this->makeStage(6, 'Network & Local DNS', $summary['dns']['status'] ?? 'warning', 'Local DNS plan completed.', ['record_count' => count($summary['dns']['records'] ?? [])]),
            $this->makeStage(7, 'SSL & Web Server', $summary['ssl']['status'] ?? 'warning', 'SSL and vhost plan completed.', $summary['ssl']),
            $this->makeStage(8, 'Remote Dependency Check', $summary['remote_dependencies']['status'] ?? 'success', 'Remote dependency check completed.', $summary['remote_dependencies']),
            $this->makeStage(9, 'Admin Account', $admin['status'], $admin['message'], $admin),
            $this->makeStage(10, 'Installation Plan', $summary['status'], 'Consolidated installation plan is ready.'),
            $this->makeStage(11, 'Stage-By-Stage Install', 'pending', 'Waiting for administrator confirmation before install actions run.'),
            $this->makeStage(12, 'Finish', 'pending', 'Final service checks and report export run after installation.'),
        ];
        $status = $this->aggregateStatuses(array_map(static fn (array $stage): string => $stage['status'], array_filter(
            $stages,
            static fn (array $stage): bool => $stage['status'] !== 'pending'
        )));

        return [
            'schema_version' => 1,
            'kit_setup_version' => self::VERSION,
            'action' => 'stage-report',
            'status' => $status,
            'started_at' => $context['started_at'],
            'finished_at' => date(DATE_ATOM),
            'run_id' => $context['run_id'],
            'run_dir' => $context['run_dir'],
            'stages' => $stages,
            'installation_plan' => $summary,
            'checkpoints' => $this->readCheckpoints($runDir),
        ];
    }

    private function runFinishReport(array $config, string $runDir, array $context): array
    {
        $checkpoints = $this->readCheckpoints($runDir);
        $kitReport = $this->readOptionalJson($this->joinPath($runDir, 'kit-report.json'));
        $stageReport = $this->readOptionalJson($this->joinPath($runDir, 'stage-report.json'));
        $dnsReport = $this->readOptionalJson($this->joinPath($runDir, 'dns-verify.json')) ?: $this->readOptionalJson($this->joinPath($runDir, 'dns-apply.json')) ?: $this->readOptionalJson($this->joinPath($runDir, 'dns-plan.json'));
        $sslReport = $this->readOptionalJson($this->joinPath($runDir, 'ssl-apply.json')) ?: $this->readOptionalJson($this->joinPath($runDir, 'ssl-plan.json'));
        $remoteReport = $this->readOptionalJson($this->joinPath($runDir, 'remote-check.json'));
        $smokeReport = $this->readOptionalJson($this->joinPath($runDir, 'smoke-check.json'));
        $platformReport = $this->readOptionalJson($this->joinPath($runDir, 'platform-report.json'));

        $apps = [];
        if (is_array($kitReport) && isset($kitReport['apps']) && is_array($kitReport['apps'])) {
            foreach ($kitReport['apps'] as $app) {
                if (!is_array($app)) {
                    continue;
                }
                $apps[] = [
                    'id' => $app['id'] ?? null,
                    'name' => $app['name'] ?? ($app['id'] ?? null),
                    'status' => $app['status'] ?? null,
                    'url' => $this->appUrlFromConfig($config, (string) ($app['id'] ?? '')),
                    'report_path' => $app['report_path'] ?? null,
                    'manifest' => $app['manifest'] ?? null,
                    'services' => $app['services'] ?? [],
                ];
            }
        } else {
            foreach ($config['apps'] as $appConfig) {
                if (!is_array($appConfig) || ($appConfig['enabled'] ?? true) === false) {
                    continue;
                }
                $apps[] = [
                    'id' => $appConfig['id'] ?? null,
                    'name' => $appConfig['id'] ?? null,
                    'status' => 'not-run',
                    'url' => $appConfig['app_url'] ?? null,
                    'report_path' => null,
                    'manifest' => null,
                    'services' => [],
                ];
            }
        }

        $followUps = $this->buildFinishFollowUps($checkpoints, $apps, $dnsReport, $sslReport, $remoteReport, $smokeReport, $platformReport);
        $status = count(array_filter($followUps, static fn (array $item): bool => ($item['severity'] ?? '') === 'required')) > 0
            ? 'warning'
            : 'success';

        return [
            'schema_version' => 1,
            'kit_setup_version' => self::VERSION,
            'action' => 'finish-report',
            'status' => $status,
            'started_at' => $context['started_at'],
            'finished_at' => date(DATE_ATOM),
            'run_id' => $context['run_id'],
            'run_dir' => $context['run_dir'],
            'admin' => [
                'name' => $config['shared']['admin']['name'] ?? 'PBB Administrator',
                'email' => $config['shared']['admin']['email'] ?? 'admin@pbb.local',
            ],
            'apps' => $apps,
            'reports' => [
                'kit_report' => is_file($this->joinPath($runDir, 'kit-report.json')) ? $this->absolutePath($this->joinPath($runDir, 'kit-report.json')) : null,
                'stage_report' => is_file($this->joinPath($runDir, 'stage-report.json')) ? $this->absolutePath($this->joinPath($runDir, 'stage-report.json')) : null,
                'checkpoint_report' => is_file($this->joinPath($runDir, 'checkpoints.json')) ? $this->absolutePath($this->joinPath($runDir, 'checkpoints.json')) : null,
            ],
            'dns' => [
                'status' => is_array($dnsReport) ? ($dnsReport['status'] ?? null) : null,
                'records' => is_array($dnsReport) ? ($dnsReport['records'] ?? ($dnsReport['plan']['records'] ?? [])) : [],
                'verification' => is_array($dnsReport) && ($dnsReport['action'] ?? '') === 'dns-verify' ? ($dnsReport['results'] ?? []) : null,
            ],
            'ssl' => [
                'status' => is_array($sslReport) ? ($sslReport['status'] ?? null) : null,
                'certificate' => is_array($sslReport) ? ($sslReport['certificate'] ?? ($sslReport['plan']['certificate'] ?? null)) : null,
                'apache_include' => is_array($sslReport) ? ($sslReport['apache']['generated_include_path'] ?? ($sslReport['apply']['target'] ?? null)) : null,
            ],
            'remote_dependencies' => is_array($remoteReport) ? [
                'status' => $remoteReport['status'] ?? null,
                'remote_apps' => $remoteReport['remote_apps'] ?? [],
            ] : null,
            'smoke_checks' => is_array($smokeReport) ? [
                'status' => $smokeReport['status'] ?? null,
                'apps' => $smokeReport['apps'] ?? [],
            ] : null,
            'platform' => is_array($platformReport) ? [
                'status' => $platformReport['status'] ?? null,
                'tools' => $platformReport['tools'] ?? [],
                'services' => $platformReport['services'] ?? [],
            ] : null,
            'checkpoints' => $checkpoints,
            'follow_ups' => $followUps,
            'stage_report_status' => is_array($stageReport) ? ($stageReport['status'] ?? null) : null,
        ];
    }

    private function buildFinishFollowUps(array $checkpoints, array $apps, ?array $dnsReport, ?array $sslReport, ?array $remoteReport, ?array $smokeReport, ?array $platformReport): array
    {
        $items = [];
        foreach (['detect', 'plan', 'preflight'] as $requiredAction) {
            $status = $checkpoints['actions'][$requiredAction]['status'] ?? null;
            if (!in_array($status, ['success', 'warning'], true)) {
                $items[] = [
                    'severity' => 'required',
                    'message' => 'Run or fix required action: ' . $requiredAction,
                ];
            }
        }
        foreach ($apps as $app) {
            if (!in_array($app['status'] ?? '', ['success', 'warning', 'skipped'], true)) {
                $items[] = [
                    'severity' => 'required',
                    'message' => 'App needs attention: ' . (string) ($app['id'] ?? 'unknown') . ' status=' . (string) ($app['status'] ?? 'unknown'),
                ];
            }
        }
        if (!is_array($dnsReport)) {
            $items[] = ['severity' => 'recommended', 'message' => 'Run dns-plan or dns-apply before final handoff.'];
        } elseif (($dnsReport['status'] ?? '') === 'failed') {
            $items[] = ['severity' => 'required', 'message' => 'DNS report failed. Review DNS records before handoff.'];
        }
        if (!is_array($sslReport)) {
            $items[] = ['severity' => 'recommended', 'message' => 'Run ssl-plan or ssl-apply before final handoff.'];
        } elseif (($sslReport['status'] ?? '') === 'failed') {
            $items[] = ['severity' => 'required', 'message' => 'SSL/web-server report failed. Review certificate and vhost output.'];
        }
        if (is_array($remoteReport) && ($remoteReport['status'] ?? '') === 'failed') {
            $items[] = ['severity' => 'required', 'message' => 'Remote dependency check failed. Review remote app endpoints.'];
        }
        if (!is_array($smokeReport)) {
            $items[] = ['severity' => 'recommended', 'message' => 'Run smoke-check before final handoff.'];
        } elseif (($smokeReport['status'] ?? '') === 'failed') {
            $items[] = ['severity' => 'required', 'message' => 'Smoke check failed. Review app URLs before handoff.'];
        }
        if (is_array($platformReport) && ($platformReport['status'] ?? '') === 'failed') {
            $items[] = ['severity' => 'required', 'message' => 'Platform check failed. Fix host prerequisites.'];
        }
        return $items;
    }

    private function appUrlFromConfig(array $config, string $appId): ?string
    {
        foreach ($config['apps'] as $appConfig) {
            if (is_array($appConfig) && (string) ($appConfig['id'] ?? '') === $appId) {
                return isset($appConfig['app_url']) ? (string) $appConfig['app_url'] : null;
            }
        }
        return null;
    }

    private function makeStage(int $step, string $name, string $status, string $message, array $details = []): array
    {
        return [
            'step' => $step,
            'name' => $name,
            'status' => $status,
            'message' => $message,
            'details' => $details,
        ];
    }

    private function inspectAdminSetup(array $config): array
    {
        $admin = is_array($config['shared']['admin'] ?? null) ? $config['shared']['admin'] : [];
        $email = (string) ($admin['email'] ?? '');
        $name = (string) ($admin['name'] ?? '');
        $password = (string) ($admin['password'] ?? '');
        $passwordEnv = (string) ($admin['password_env'] ?? '');
        $passwordConfigured = $password !== '' || ($passwordEnv !== '' && getenv($passwordEnv) !== false && getenv($passwordEnv) !== '');
        $status = ($email === 'admin@pbb.local' && $name !== '' && $passwordConfigured) ? 'success' : 'warning';
        return [
            'status' => $status,
            'message' => $status === 'success' ? 'Standard administrator account is ready.' : 'Administrator password still needs to be provided.',
            'name' => $name,
            'email' => $email,
            'password_env' => $passwordEnv,
            'password_configured' => $passwordConfigured,
        ];
    }

    private function inspectHubPairingState(array $config): array
    {
        $hub = is_array($config['hub'] ?? null) ? $config['hub'] : [];
        $kit = is_array($config['kit'] ?? null) ? $config['kit'] : [];
        $hubId = $hub['hub_id'] ?? $kit['hub_record_id'] ?? null;
        $nodeId = (string) ($kit['node_id'] ?? '');
        $domain = (string) ($kit['domain'] ?? '');
        $status = ($hubId !== null && $nodeId !== '') ? 'success' : 'warning';
        return [
            'status' => $status,
            'message' => $status === 'success' ? 'Hub identity is configured.' : 'Hub ID and token must be resolved before install.',
            'hub_id' => $hubId,
            'node_id' => $nodeId,
            'domain' => $domain,
        ];
    }

    private function inspectSelectedAppsState(array $config): array
    {
        $selected = $config['machine']['selected_apps'] ?? [];
        if (!is_array($selected)) {
            $selected = [];
        }
        return [
            'status' => count($selected) > 0 ? 'success' : 'warning',
            'message' => count($selected) > 0 ? 'Apps have been selected for this machine.' : 'No local apps selected.',
            'selected_apps' => array_values(array_map('strval', $selected)),
        ];
    }

    private function inspectBasePathState(array $config): array
    {
        $basePath = (string) ($config['layout']['base_path'] ?? $config['paths']['apps_base'] ?? '');
        $parent = $basePath !== '' ? dirname($basePath) : '';
        $ready = $basePath !== '' && (is_dir($basePath) || (is_dir($parent) && is_writable($parent)));
        return [
            'status' => $ready ? 'success' : 'warning',
            'message' => $ready ? 'Base path can be created or used.' : 'Base path parent is not writable or not configured.',
            'base_path' => $basePath,
            'exists' => $basePath !== '' && is_dir($basePath),
            'parent_writable' => $parent !== '' && is_dir($parent) && is_writable($parent),
        ];
    }

    private function resolveKitSecrets(array $config, string $runDir): array
    {
        $secretConfig = $config['shared']['secrets'] ?? [];
        if (!is_array($secretConfig)) {
            $secretConfig = [];
        }

        $values = $secretConfig['values'] ?? [];
        if (!is_array($values)) {
            $values = [];
        }

        $definitions = $this->defaultSecretDefinitions();
        $generated = [];
        foreach ($definitions as $name => $definition) {
            $current = (string) ($values[$name] ?? '');
            if ($current === '' || $this->isPlaceholder($current)) {
                $values[$name] = $this->generateSecret((int) $definition['bytes']);
                $generated[] = $name;
            }
        }

        $secretConfig['values'] = $values;
        $secretConfig['generated_at'] = date(DATE_ATOM);
        $config['shared']['secrets'] = $secretConfig;

        $placeholderMap = [];
        foreach ($definitions as $name => $definition) {
            foreach ($definition['placeholders'] as $placeholder) {
                $placeholderMap[$placeholder] = (string) $values[$name];
            }
        }
        $config = $this->replaceSecretPlaceholders($config, $placeholderMap);

        $secretPath = $this->joinPath($runDir, 'secrets', 'kit-secrets.json');
        $this->writeJsonFile($secretPath, [
            'schema_version' => 1,
            'generated_at' => $secretConfig['generated_at'],
            'policy' => $secretConfig['policy'] ?? 'kit-provided',
            'values' => $values,
        ]);

        $report = [
            'status' => 'success',
            'policy' => $secretConfig['policy'] ?? 'kit-provided',
            'path' => $this->absolutePath($secretPath),
            'generated' => $generated,
            'available' => array_keys($values),
            'redacted_values' => $this->redactSecretValues($values),
        ];

        $this->writeJsonFile($this->joinPath($runDir, 'secret-report.json'), $report);

        return [
            'config' => $config,
            'report' => $report,
        ];
    }

    private function defaultSecretDefinitions(): array
    {
        return [
            'mapserver_purge_token' => [
                'bytes' => 32,
                'placeholders' => ['REPLACE_WITH_MAPSERVER_PURGE_TOKEN'],
            ],
            'maestro_telemetry_token' => [
                'bytes' => 32,
                'placeholders' => ['REPLACE_WITH_MAESTRO_TELEMETRY_TOKEN'],
            ],
            'realtime_token_secret' => [
                'bytes' => 48,
                'placeholders' => ['REPLACE_WITH_REALTIME_TOKEN_SECRET'],
            ],
            'realtime_backend_ingress_secret' => [
                'bytes' => 48,
                'placeholders' => ['REPLACE_WITH_REALTIME_BACKEND_SECRET'],
            ],
            'realtime_media_ingest_secret' => [
                'bytes' => 48,
                'placeholders' => ['REPLACE_WITH_REALTIME_MEDIA_INGEST_SECRET'],
            ],
            'relay_shared_secret' => [
                'bytes' => 48,
                'placeholders' => ['REPLACE_WITH_RELAY_SHARED_SECRET'],
            ],
            'hotline_media_assembly_token' => [
                'bytes' => 48,
                'placeholders' => ['REPLACE_WITH_HOTLINE_MEDIA_ASSEMBLY_TOKEN'],
            ],
        ];
    }

    private function generateSecret(int $bytes): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    private function isPlaceholder(string $value): bool
    {
        return $value === '' || strpos($value, 'REPLACE_WITH_') === 0 || strpos($value, 'replace-with-') === 0;
    }

    private function replaceSecretPlaceholders($value, array $placeholderMap)
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->replaceSecretPlaceholders($item, $placeholderMap);
            }
            return $value;
        }

        if (!is_string($value)) {
            return $value;
        }

        return $placeholderMap[$value] ?? $value;
    }

    private function redactSecretValues(array $values): array
    {
        $redacted = [];
        foreach ($values as $key => $value) {
            $value = (string) $value;
            $redacted[(string) $key] = [
                'configured' => $value !== '',
                'length' => strlen($value),
                'sha256_12' => $value === '' ? null : substr(hash('sha256', $value), 0, 12),
            ];
        }
        return $redacted;
    }

    private function runPackagePrepare(array $config, string $runDir, array $context): array
    {
        $packageConfig = $config['packages'] ?? [];
        if (!is_array($packageConfig)) {
            $packageConfig = [];
        }

        $manifestPath = (string) ($packageConfig['manifest_path'] ?? '');
        if ($manifestPath === '') {
            $manifestPath = $this->joinPath((string) ($packageConfig['base_path'] ?? 'packages'), 'packages.json');
        }
        if (!$this->isAbsolutePath($manifestPath)) {
            $manifestPath = $this->joinPath(getcwd(), $manifestPath);
        }

        $dryRun = ($packageConfig['dry_run'] ?? true) !== false;
        $signaturePolicy = (string) ($packageConfig['signature_policy'] ?? 'warn');
        $requireBuildMetadata = ($packageConfig['require_build_metadata'] ?? false) === true;
        $manifest = $this->readJsonFile($manifestPath);
        $manifestDir = dirname($manifestPath);
        $entries = $this->normalizePackageEntries($manifest['packages'] ?? []);
        $selectedApps = $this->selectedLocalAppIds($config);
        $allowedTargetRoots = $this->packageTargetRoots($config);
        $configuredMaxParallel = max(1, min(5, (int) ($packageConfig['max_parallel'] ?? 5)));
        $maxParallel = $dryRun ? 1 : $configuredMaxParallel;

        $totalApps = count($selectedApps);
        $results = !$dryRun && $maxParallel > 1 && $totalApps > 1
            ? $this->runPackagePrepareParallel($config, $context, $selectedApps, $maxParallel)
            : $this->runPackagePrepareSequential($config, $selectedApps, $entries, $manifestDir, $signaturePolicy, $dryRun, $runDir, $allowedTargetRoots, $requireBuildMetadata);
        $failed = false;
        $warning = false;
        foreach ($results as $result) {
            if (($result['status'] ?? '') === 'failed') {
                $failed = true;
            } elseif (($result['status'] ?? '') === 'warning') {
                $warning = true;
            }
        }

        return [
            'schema_version' => 1,
            'kit_setup_version' => self::VERSION,
            'action' => 'prepare-packages',
            'status' => $failed ? 'failed' : ($warning ? 'warning' : 'success'),
            'started_at' => $context['started_at'],
            'finished_at' => date(DATE_ATOM),
            'run_id' => $context['run_id'],
            'run_dir' => $context['run_dir'],
            'manifest_path' => $this->absolutePath($manifestPath),
            'dry_run' => $dryRun,
            'signature_policy' => $signaturePolicy,
            'require_build_metadata' => $requireBuildMetadata,
            'max_parallel' => $maxParallel,
            'selected_apps' => $selectedApps,
            'packages' => $results,
        ];
    }

    private function runPackagePrepareSequential(array $config, array $selectedApps, array $entries, string $manifestDir, string $signaturePolicy, bool $dryRun, string $runDir, array $allowedTargetRoots, bool $requireBuildMetadata): array
    {
        $results = [];
        $totalApps = count($selectedApps);
        foreach ($selectedApps as $index => $appId) {
            $results[] = $this->prepareSinglePackage($config, $appId, $index + 1, $totalApps, $entries, $manifestDir, $signaturePolicy, $dryRun, $runDir, $allowedTargetRoots, $requireBuildMetadata);
        }
        return $results;
    }

    private function prepareSinglePackage(array $config, string $appId, int $index, int $totalApps, array $entries, string $manifestDir, string $signaturePolicy, bool $dryRun, string $runDir, array $allowedTargetRoots, bool $requireBuildMetadata): array
    {
        $this->writeProgress('package', $appId, 'start', [
            'index' => $index,
            'total' => $totalApps,
            'message' => 'Preparing trusted package.',
        ]);
        $appConfig = $this->findAppConfig($config, $appId);
        $entry = $entries[$appId] ?? null;
        if (!is_array($entry)) {
            $this->writeProgress('package', $appId, 'failed', [
                'index' => $index,
                'total' => $totalApps,
                'message' => 'No trusted package manifest entry for selected app.',
            ]);
            return [
                'app_id' => $appId,
                'status' => 'failed',
                'message' => 'No trusted package manifest entry for selected app.',
            ];
        }

        return $this->preparePackageEntry($entry, $appConfig, $manifestDir, $signaturePolicy, $dryRun, $runDir, $allowedTargetRoots, $requireBuildMetadata, [
            'index' => $index,
            'total' => $totalApps,
        ]);
    }

    private function runPackagePrepareWorker(array $config, string $runDir, array $context, string $appId): array
    {
        $packageConfig = is_array($config['packages'] ?? null) ? $config['packages'] : [];
        $manifestPath = (string) ($packageConfig['manifest_path'] ?? '');
        if ($manifestPath === '') {
            $manifestPath = $this->joinPath((string) ($packageConfig['base_path'] ?? 'packages'), 'packages.json');
        }
        if (!$this->isAbsolutePath($manifestPath)) {
            $manifestPath = $this->joinPath(getcwd(), $manifestPath);
        }

        $manifest = $this->readJsonFile($manifestPath);
        $selectedApps = $this->selectedLocalAppIds($config);
        $index = array_search($appId, $selectedApps, true);
        return $this->prepareSinglePackage(
            $config,
            $appId,
            $index === false ? 1 : $index + 1,
            count($selectedApps),
            $this->normalizePackageEntries($manifest['packages'] ?? []),
            dirname($manifestPath),
            (string) ($packageConfig['signature_policy'] ?? 'warn'),
            ($packageConfig['dry_run'] ?? true) !== false,
            $runDir,
            $this->packageTargetRoots($config),
            ($packageConfig['require_build_metadata'] ?? false) === true
        );
    }

    private function runPackagePrepareParallel(array $config, array $context, array $selectedApps, int $maxParallel): array
    {
        $queue = $this->packageWorkerQueue($selectedApps);
        $active = [];
        $resultsByApp = [];
        $workerDir = $this->joinPath((string) $context['run_dir'], 'package-workers');
        $this->ensureDirectory($workerDir);

        while (count($queue) > 0 || count($active) > 0) {
            while (count($queue) > 0 && count($active) < $maxParallel) {
                $appId = array_shift($queue);
                if (!is_string($appId) || $appId === '') {
                    continue;
                }
                $this->writeProgress('package', $appId, 'worker-started', [
                    'message' => 'Package worker started.',
                ]);
                $active[$appId] = $this->startPackageWorker($context, $appId, $this->joinPath($workerDir, $appId . '.json'));
            }

            foreach (array_keys($active) as $appId) {
                $worker = $active[$appId];
                $this->drainWorkerPipe($worker, 'stdout');
                $this->drainWorkerPipe($worker, 'stderr');
                $status = proc_get_status($worker['process']);
                if (($status['running'] ?? false) === true) {
                    $now = time();
                    $lastHeartbeat = (int) ($worker['last_heartbeat_at'] ?? 0);
                    if ($now - $lastHeartbeat >= 3) {
                        $startedAt = (int) ($worker['started_at'] ?? $now);
                        $this->writeProgress('package', $appId, 'working', [
                            'message' => 'Still extracting, verifying, or copying package files.',
                            'elapsed_seconds' => max(0, $now - $startedAt),
                        ]);
                        $worker['last_heartbeat_at'] = $now;
                    }
                    $active[$appId] = $worker;
                    continue;
                }

                $this->drainWorkerPipe($worker, 'stdout');
                $this->drainWorkerPipe($worker, 'stderr');
                foreach ($worker['pipes'] as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
                $exitCode = proc_close($worker['process']);
                $result = $this->readOptionalJson($worker['report_path']);
                if (!is_array($result)) {
                    $result = [
                        'app_id' => $appId,
                        'status' => 'failed',
                        'message' => 'Package worker did not write a report.',
                        'exit_code' => $exitCode,
                    ];
                }
                if ($exitCode !== 0 && ($result['status'] ?? '') !== 'failed') {
                    $result['status'] = 'failed';
                    $result['message'] = 'Package worker exited with code ' . $exitCode . '.';
                }
                $resultsByApp[$appId] = $result;
                unset($active[$appId]);
            }

            if (count($active) > 0) {
                usleep(100000);
            }
        }

        $ordered = [];
        foreach ($selectedApps as $appId) {
            $ordered[] = $resultsByApp[$appId] ?? [
                'app_id' => $appId,
                'status' => 'failed',
                'message' => 'Package worker result was missing.',
            ];
        }
        return $ordered;
    }

    private function packageWorkerQueue(array $selectedApps): array
    {
        $preferred = ['pbb-hotline', 'pbb-maestro', 'pbb-realtime', 'pbb-relay', 'pbb-mapserver'];
        $queue = [];
        foreach ($preferred as $appId) {
            if (in_array($appId, $selectedApps, true)) {
                $queue[] = $appId;
            }
        }
        foreach ($selectedApps as $appId) {
            if (!in_array($appId, $queue, true)) {
                $queue[] = $appId;
            }
        }
        return $queue;
    }

    private function startPackageWorker(array $context, string $appId, string $reportPath): array
    {
        $script = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
        if ($script === '') {
            throw new RuntimeException('Unable to locate current runner script for package worker.');
        }
        $command = [
            PHP_BINARY,
            $script,
            '--config',
            (string) $context['config_path'],
            '--action',
            'prepare-package-worker',
            '--run-id',
            (string) $context['run_id'],
            '--run-dir',
            (string) $context['run_dir'],
            '--app',
            $appId,
            '--worker-report',
            $reportPath,
        ];
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open(implode(' ', array_map([$this, 'escapeArg'], $command)), $descriptorSpec, $pipes, (string) getcwd());
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start package worker for ' . $appId);
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        return [
            'process' => $process,
            'pipes' => $pipes,
            'report_path' => $reportPath,
            'started_at' => time(),
            'last_heartbeat_at' => 0,
        ];
    }

    private function drainWorkerPipe(array $worker, string $name): void
    {
        $index = $name === 'stderr' ? 2 : 1;
        $pipe = $worker['pipes'][$index] ?? null;
        if (!is_resource($pipe)) {
            return;
        }
        $chunk = stream_get_contents($pipe);
        if ($chunk === false || $chunk === '') {
            return;
        }
        if ($name === 'stderr') {
            fwrite(STDERR, $chunk);
            fflush(STDERR);
        } else {
            fwrite(STDOUT, $chunk);
            fflush(STDOUT);
        }
    }

    private function normalizePackageEntries($packages): array
    {
        if (!is_array($packages)) {
            return [];
        }

        $entries = [];
        foreach ($packages as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $appId = (string) ($entry['app_id'] ?? $entry['id'] ?? '');
            if ($appId !== '') {
                $entries[$appId] = $entry;
            }
        }
        return $entries;
    }

    private function writeProgress(string $scope, string $appId, string $step, array $data = []): void
    {
        $payload = array_merge([
            'scope' => $scope,
            'app_id' => $appId,
            'step' => $step,
        ], $data);
        $this->writeLine('PROGRESS: ' . json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    private function selectedLocalAppIds(array $config): array
    {
        $selected = $config['machine']['selected_apps'] ?? [];
        if (!is_array($selected) || count($selected) === 0) {
            $selected = array_map(static fn (array $app): string => (string) ($app['id'] ?? ''), $config['apps']);
        }

        $enabledLocal = [];
        foreach ($config['apps'] as $app) {
            if (!is_array($app)) {
                continue;
            }
            $id = (string) ($app['id'] ?? '');
            if ($id === '' || !in_array($id, $selected, true)) {
                continue;
            }
            if (($app['enabled'] ?? true) === false) {
                continue;
            }
            if ((string) ($app['install_scope'] ?? 'local') !== 'local') {
                continue;
            }
            $enabledLocal[] = $id;
        }
        return $enabledLocal;
    }

    private function findAppConfig(array $config, string $appId): array
    {
        foreach ($config['apps'] as $app) {
            if (is_array($app) && (string) ($app['id'] ?? '') === $appId) {
                return $app;
            }
        }
        return ['id' => $appId];
    }

    private function packageTargetRoots(array $config): array
    {
        $roots = [];
        foreach ([
            $config['layout']['base_path'] ?? null,
            $config['paths']['apps_base'] ?? null,
            $config['kit']['install_root'] ?? null,
        ] as $root) {
            if (is_string($root) && $root !== '') {
                $roots[] = $root;
            }
        }
        return array_values(array_unique($roots));
    }

    private function preparePackageEntry(array $entry, array $appConfig, string $manifestDir, string $signaturePolicy, bool $dryRun, string $runDir, array $allowedTargetRoots, bool $requireBuildMetadata, array $progress = []): array
    {
        $appId = (string) ($entry['app_id'] ?? $entry['id'] ?? $appConfig['id'] ?? '');
        $progressBase = [
            'index' => (int) ($progress['index'] ?? 0),
            'total' => (int) ($progress['total'] ?? 0),
        ];
        $sourceType = (string) ($entry['source_type'] ?? 'archive');
        $packagePath = (string) ($entry['path'] ?? '');
        $trusted = ($entry['trusted'] ?? false) === true;
        $targetPath = (string) ($appConfig['install_path'] ?? '');
        if ($packagePath !== '' && !$this->isAbsolutePath($packagePath)) {
            $packagePath = $this->joinPath($manifestDir, $packagePath);
        }

        $checks = [];
        $status = 'success';
        $warnings = [];
        $errors = [];

        if (!$trusted) {
            $errors[] = 'Package entry is not marked trusted.';
        }

        $signature = $this->verifyPackageSignature($entry, $packagePath, $manifestDir);
        $signatureStatus = (string) $signature['status'];
        if ($signaturePolicy === 'required' && $signatureStatus !== 'verified') {
            $errors[] = 'Package signature is required but not verified.';
        } elseif ($signaturePolicy === 'warn' && !in_array($signatureStatus, ['verified', 'local-trusted'], true)) {
            $warnings[] = 'Package signature is not verified.';
        }

        if ($packagePath === '' || (!is_dir($packagePath) && !is_file($packagePath))) {
            $errors[] = 'Package path does not exist.';
        }

        $release = null;
        $checksum = null;
        $archiveSha256 = null;
        $stagingPath = null;
        $backupPath = null;
        $targetPrepared = false;
        if ($sourceType === 'directory' && is_dir($packagePath)) {
            $this->writeProgress('package', $appId, 'validate', $progressBase + [
                'message' => 'Validating release folder.',
            ]);
            $releasePath = $this->joinPath($packagePath, 'release.json');
            if (is_file($releasePath)) {
                $release = $this->readJsonFile($releasePath);
                $checksum = $this->verifyChecksumsForPath($packagePath);
            } else {
                $errors[] = 'Directory package does not contain release.json.';
            }
        } elseif ($sourceType === 'zip' || $sourceType === 'archive') {
            if (is_file($packagePath)) {
                $this->writeProgress('package', $appId, 'hash', $progressBase + [
                    'message' => 'Checking package SHA-256.',
                ]);
                $archiveSha256 = hash_file('sha256', $packagePath);
                $expectedSha256 = (string) ($entry['sha256'] ?? '');
                if ($expectedSha256 !== '' && strtolower($expectedSha256) !== strtolower((string) $archiveSha256)) {
                    $errors[] = 'Archive SHA-256 does not match manifest.';
                }
                if (count($errors) === 0) {
                    if ($dryRun) {
                        $stagingPath = $this->joinPath($runDir, 'packages', $appId);
                    } else {
                        $this->writeProgress('package', $appId, 'extract', $progressBase + [
                            'message' => 'Extracting package to staging.',
                        ]);
                        $extracted = $this->extractPackageArchive($packagePath, $runDir, $appId);
                        $stagingPath = $extracted['staging_path'];
                        $releasePath = $this->joinPath($stagingPath, 'release.json');
                        if (is_file($releasePath)) {
                            $this->writeProgress('package', $appId, 'verify', $progressBase + [
                                'message' => 'Verifying release metadata and checksums.',
                            ]);
                            $release = $this->readJsonFile($releasePath);
                            $checksum = $this->verifyChecksumsForPath($stagingPath);
                        } else {
                            $errors[] = 'Extracted archive does not contain release.json at its root.';
                        }
                    }
                }
            }
        } elseif ($sourceType !== '') {
            $errors[] = 'Unsupported package source_type: ' . $sourceType;
        }

        if (is_array($release)) {
            if ((string) ($release['app'] ?? '') !== $appId) {
                $errors[] = 'release.json app does not match package app_id.';
            }
            $expectedVersion = (string) ($entry['version'] ?? '');
            if ($expectedVersion !== '' && (string) ($release['version'] ?? '') !== $expectedVersion) {
                $errors[] = 'release.json version does not match manifest version.';
            }
            if ($requireBuildMetadata && !array_key_exists('milestone', $release)) {
                $warnings[] = 'release.json does not declare milestone.';
            }
            if ($requireBuildMetadata && (!isset($release['build']) || !is_array($release['build']))) {
                $warnings[] = 'release.json does not declare build metadata.';
            } elseif ($requireBuildMetadata) {
                foreach (['version', 'id', 'built_at', 'git_commit'] as $buildField) {
                    if ((string) ($release['build'][$buildField] ?? '') === '') {
                        $warnings[] = 'release.json build metadata is missing ' . $buildField . '.';
                    }
                }
            }
        }

        if (is_array($checksum) && ($checksum['status'] ?? '') === 'failed') {
            $errors[] = 'Package checksum verification failed.';
        } elseif (is_array($checksum) && ($checksum['status'] ?? '') === 'warning') {
            $warnings[] = (string) ($checksum['message'] ?? 'Package checksum warning.');
        }

        if (count($errors) > 0) {
            $status = 'failed';
        } elseif (count($warnings) > 0) {
            $status = 'warning';
        }

        if (!$dryRun && $status !== 'failed' && is_string($stagingPath) && $stagingPath !== '') {
            try {
                $this->writeProgress('package', $appId, 'deploy', $progressBase + [
                    'message' => 'Copying package into selected base path.',
                ]);
                $deploy = $this->deployStagedPackage($stagingPath, $targetPath, $runDir, $appId, $allowedTargetRoots);
                $backupPath = $deploy['backup_path'];
                $targetPrepared = true;
            } catch (Throwable $e) {
                $status = 'failed';
                $errors[] = $e->getMessage();
            }
        }

        $this->writeProgress('package', $appId, $status === 'failed' ? 'failed' : 'complete', $progressBase + [
            'message' => $status === 'failed' ? implode(' ', $errors) : 'Package is ready.',
            'status' => $status,
        ]);

        return [
            'app_id' => $appId,
            'status' => $status,
            'source_type' => $sourceType,
            'path' => $this->absolutePath($packagePath),
            'trusted' => $trusted,
            'signature_status' => $signatureStatus,
            'signature' => $signature,
            'target_path' => $targetPath,
            'target_exists' => $targetPath !== '' && is_dir($targetPath),
            'dry_run' => $dryRun,
            'staging_path' => $stagingPath,
            'backup_path' => $backupPath,
            'target_prepared' => $targetPrepared,
            'extraction' => $dryRun ? 'planned' : ($status === 'failed' ? 'failed' : ($targetPrepared ? 'deployed' : 'staged')),
            'release' => is_array($release) ? [
                'app' => $release['app'] ?? null,
                'name' => $release['name'] ?? null,
                'version' => $release['version'] ?? null,
                'display_version' => $release['display_version'] ?? null,
                'milestone' => $release['milestone'] ?? null,
                'build' => $release['build'] ?? null,
            ] : null,
            'archive_sha256' => $archiveSha256,
            'checksum' => $checksum,
            'warnings' => $warnings,
            'errors' => $errors,
            'checks' => $checks,
        ];
    }

    private function verifyPackageSignature(array $entry, string $packagePath, string $manifestDir): array
    {
        $algorithm = (string) ($entry['signature_algorithm'] ?? '');
        if ($algorithm === '') {
            return [
                'status' => (string) ($entry['signature_status'] ?? 'missing'),
                'algorithm' => null,
                'signature_path' => null,
                'public_key_path' => null,
                'message' => 'No cryptographic signature metadata declared.',
            ];
        }

        $signaturePath = (string) ($entry['signature_path'] ?? '');
        $publicKeyPath = (string) ($entry['public_key_path'] ?? '');
        if ($signaturePath !== '' && !$this->isAbsolutePath($signaturePath)) {
            $signaturePath = $this->joinPath($manifestDir, $signaturePath);
        }
        if ($publicKeyPath !== '' && !$this->isAbsolutePath($publicKeyPath)) {
            $publicKeyPath = $this->joinPath($manifestDir, $publicKeyPath);
        }

        $result = [
            'status' => 'failed',
            'algorithm' => $algorithm,
            'signature_path' => $signaturePath !== '' ? $this->absolutePath($signaturePath) : null,
            'public_key_path' => $publicKeyPath !== '' ? $this->absolutePath($publicKeyPath) : null,
            'message' => '',
        ];

        if ($algorithm !== 'openssl-sha256') {
            $result['message'] = 'Unsupported signature algorithm.';
            return $result;
        }
        if (!is_file($packagePath)) {
            $result['message'] = 'Package path is not a file; cryptographic signatures currently apply to archive files.';
            return $result;
        }
        if ($signaturePath === '' || !is_file($signaturePath)) {
            $result['message'] = 'Signature file does not exist.';
            return $result;
        }
        if ($publicKeyPath === '' || !is_file($publicKeyPath)) {
            $result['message'] = 'Public key file does not exist.';
            return $result;
        }
        if (!function_exists('openssl_verify')) {
            $result['message'] = 'OpenSSL extension is not available.';
            return $result;
        }

        $data = file_get_contents($packagePath);
        $signature = file_get_contents($signaturePath);
        $publicKeyPem = file_get_contents($publicKeyPath);
        if ($data === false || $signature === false || $publicKeyPem === false) {
            $result['message'] = 'Unable to read package, signature, or public key.';
            return $result;
        }

        $publicKey = openssl_pkey_get_public($publicKeyPem);
        if ($publicKey === false) {
            $result['message'] = 'Unable to load public key.';
            return $result;
        }

        $verified = openssl_verify($data, $signature, $publicKey, OPENSSL_ALGO_SHA256);
        if ($verified === 1) {
            $result['status'] = 'verified';
            $result['message'] = 'Signature verified.';
        } elseif ($verified === 0) {
            $result['message'] = 'Signature verification failed.';
        } else {
            $result['message'] = 'OpenSSL signature verification error.';
        }

        return $result;
    }

    private function runDnsPlan(array $config, array $context): array
    {
        $dns = $config['dns'] ?? [];
        if (!is_array($dns)) {
            $dns = [];
        }
        $domains = $config['domains'] ?? [];
        if (!is_array($domains)) {
            $domains = [];
        }

        $zone = (string) ($dns['zone'] ?? $domains['zone'] ?? 'pbb.ph');
        $ttl = (int) ($dns['ttl'] ?? 300);
        $provider = (string) ($dns['provider'] ?? 'manual');
        $updateMode = (string) ($dns['update_mode'] ?? 'plan-only');
        $ipAddress = (string) ($config['machine']['ip_address'] ?? '');
        $warnings = [];
        $errors = [];

        if ($ipAddress === '' || filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            $errors[] = 'machine.ip_address must be a valid IP address for DNS planning.';
        }

        $records = [];
        $appDomainMap = [
            'pbb-mapserver' => 'mapserver',
            'pbb-maestro' => 'maestro',
            'pbb-realtime' => 'realtime',
            'pbb-relay' => 'relay',
            'pbb-hotline' => 'hotline',
        ];
        $selected = $this->selectedLocalAppIds($config);
        foreach ($selected as $appId) {
            $domainKey = $appDomainMap[$appId] ?? null;
            if ($domainKey === null) {
                continue;
            }
            $url = (string) ($domains[$domainKey] ?? '');
            if ($url === '') {
                $warnings[] = 'No domain configured for ' . $appId;
                continue;
            }
            $host = $this->hostFromUrlOrHost($url);
            if ($host === '') {
                $errors[] = 'Invalid domain configured for ' . $appId . ': ' . $url;
                continue;
            }
            $records[] = $this->makeDnsRecord($host, $zone, $ipAddress, $ttl, $appId, false);
        }

        $records = $this->dedupeDnsRecords($records);
        if (count($records) === 0) {
            $warnings[] = 'No DNS records were planned.';
        }

        return [
            'schema_version' => 1,
            'kit_setup_version' => self::VERSION,
            'action' => 'dns-plan',
            'status' => count($errors) > 0 ? 'failed' : (count($warnings) > 0 ? 'warning' : 'success'),
            'started_at' => $context['started_at'],
            'finished_at' => date(DATE_ATOM),
            'run_id' => $context['run_id'],
            'run_dir' => $context['run_dir'],
            'provider' => $provider,
            'update_mode' => $updateMode,
            'zone' => $zone,
            'ttl' => $ttl,
            'target_ip' => $ipAddress,
            'technitium' => [
                'base_url' => $dns['base_url'] ?? null,
                'token_env' => $dns['token_env'] ?? null,
                'token_configured' => $this->dnsTokenConfigured($dns),
                'apply_supported' => $provider === 'technitium',
            ],
            'records' => $records,
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    private function runDnsApply(array $config, array $context): array
    {
        $plan = $this->runDnsPlan($config, $context);
        $dns = $config['dns'] ?? [];
        if (!is_array($dns)) {
            $dns = [];
        }

        $apply = [
            'schema_version' => 1,
            'kit_setup_version' => self::VERSION,
            'action' => 'dns-apply',
            'status' => 'running',
            'started_at' => $context['started_at'],
            'finished_at' => null,
            'run_id' => $context['run_id'],
            'run_dir' => $context['run_dir'],
            'plan' => $plan,
            'results' => [],
            'warnings' => [],
            'errors' => [],
        ];

        if (($plan['status'] ?? '') === 'failed') {
            $apply['status'] = 'failed';
            $apply['errors'][] = 'DNS plan failed; apply skipped.';
            $apply['finished_at'] = date(DATE_ATOM);
            return $apply;
        }

        $updateMode = (string) ($dns['update_mode'] ?? 'plan-only');
        if ($updateMode !== 'apply') {
            $apply['status'] = 'skipped';
            $apply['warnings'][] = 'dns.update_mode is not apply; no Technitium API calls were made.';
            $apply['finished_at'] = date(DATE_ATOM);
            return $apply;
        }

        if (($dns['provider'] ?? '') !== 'technitium') {
            $apply['status'] = 'failed';
            $apply['errors'][] = 'dns-apply currently supports only provider=technitium.';
            $apply['finished_at'] = date(DATE_ATOM);
            return $apply;
        }

        $token = $this->getDnsToken($dns);
        if ($token === '') {
            $apply['status'] = 'failed';
            $apply['errors'][] = 'dns.token or dns.token_env is required when dns.update_mode=apply.';
            $apply['finished_at'] = date(DATE_ATOM);
            return $apply;
        }

        $baseUrl = rtrim((string) ($dns['base_url'] ?? 'http://localhost:5380'), '/');
        $failed = false;
        foreach ($plan['records'] as $record) {
            $result = $this->applyTechnitiumRecord($baseUrl, $token, $record);
            $apply['results'][] = $result;
            if (($result['status'] ?? '') !== 'success') {
                $failed = true;
            }
        }

        $apply['status'] = $failed ? 'failed' : 'success';
        $apply['finished_at'] = date(DATE_ATOM);
        return $apply;
    }

    private function runDnsClientApply(array $config, array $context): array
    {
        $dns = $config['dns'] ?? [];
        if (!is_array($dns)) {
            $dns = [];
        }

        $target = $this->dnsClientTargetServer($dns);
        $interfaceAlias = trim((string) ($dns['client_interface_alias'] ?? ''));
        $mode = (string) ($dns['client_update_mode'] ?? 'plan-only');
        $report = [
            'schema_version' => 1,
            'kit_setup_version' => self::VERSION,
            'action' => 'dns-client-apply',
            'status' => 'running',
            'started_at' => $context['started_at'],
            'finished_at' => null,
            'run_id' => $context['run_id'],
            'run_dir' => $context['run_dir'],
            'platform' => PHP_OS_FAMILY,
            'update_mode' => $mode,
            'target_nameserver' => $target,
            'interface_alias' => $interfaceAlias !== '' ? $interfaceAlias : null,
            'requires_admin' => true,
            'is_elevated' => null,
            'available_interfaces' => [],
            'before' => null,
            'after' => null,
            'warnings' => [],
            'errors' => [],
        ];

        if ($mode !== 'apply') {
            $report['status'] = 'skipped';
            $report['warnings'][] = 'dns.client_update_mode is not apply; client DNS settings were not changed.';
            $report['finished_at'] = date(DATE_ATOM);
            return $report;
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            $report['status'] = 'failed';
            $report['errors'][] = 'dns-client-apply currently supports Windows only.';
            $report['finished_at'] = date(DATE_ATOM);
            return $report;
        }

        if ($target === '' || filter_var($target, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            $report['status'] = 'failed';
            $report['errors'][] = 'A valid IPv4 dns.client_nameserver or Technitium base URL host is required.';
            $report['finished_at'] = date(DATE_ATOM);
            return $report;
        }

        $script = $this->makeWindowsDnsClientScript($target, $interfaceAlias);
        $scriptPath = $this->joinPath((string) $context['run_dir'], 'dns-client-apply.ps1');
        file_put_contents($scriptPath, $script);
        try {
            $process = $this->runProcess(['powershell.exe', '-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', $scriptPath], (string) getcwd());
        } catch (Throwable $e) {
            $report['status'] = 'failed';
            $report['errors'][] = 'Unable to run PowerShell DNS client update: ' . $e->getMessage();
            $report['finished_at'] = date(DATE_ATOM);
            return $report;
        }

        $stdout = (string) ($process['stdout'] ?? '');
        $decoded = json_decode($stdout, true);
        if (($process['exit_code'] ?? 1) !== 0 || !is_array($decoded)) {
            $report['status'] = 'failed';
            $report['errors'][] = 'Windows DNS client update failed. Run Kit Setup as Administrator and verify the adapter name.';
            if (($process['stderr'] ?? '') !== '') {
                $report['errors'][] = (string) $process['stderr'];
            }
            if ($stdout !== '') {
                $report['errors'][] = $stdout;
            }
            $report['finished_at'] = date(DATE_ATOM);
            return $report;
        }

        $report['is_elevated'] = $decoded['is_elevated'] ?? null;
        $report['available_interfaces'] = $decoded['available_interfaces'] ?? [];

        if (($decoded['status'] ?? '') === 'failed') {
            $report['status'] = 'failed';
            $report['errors'][] = (string) ($decoded['error'] ?? 'Windows DNS client update failed.');
            $report['finished_at'] = date(DATE_ATOM);
            return $report;
        }

        $report['status'] = 'success';
        $report['interface_alias'] = $decoded['interface_alias'] ?? $report['interface_alias'];
        $report['before'] = $decoded['before'] ?? [];
        $report['after'] = $decoded['after'] ?? [];
        $report['finished_at'] = date(DATE_ATOM);
        return $report;
    }

    private function runDnsVerify(array $config, array $context): array
    {
        $plan = $this->runDnsPlan($config, $context);
        $dns = $config['dns'] ?? [];
        if (!is_array($dns)) {
            $dns = [];
        }

        $report = [
            'schema_version' => 1,
            'kit_setup_version' => self::VERSION,
            'action' => 'dns-verify',
            'status' => 'running',
            'started_at' => $context['started_at'],
            'finished_at' => null,
            'run_id' => $context['run_id'],
            'run_dir' => $context['run_dir'],
            'plan' => $plan,
            'resolver' => [
                'mode' => (string) ($dns['verification_mode'] ?? 'system'),
                'nameserver' => (string) ($dns['verify_nameserver'] ?? ''),
            ],
            'results' => [],
            'warnings' => [],
            'errors' => [],
        ];

        if (($plan['status'] ?? '') === 'failed') {
            $report['status'] = 'failed';
            $report['errors'][] = 'DNS plan failed; verification skipped.';
            $report['finished_at'] = date(DATE_ATOM);
            return $report;
        }

        if (count($plan['records'] ?? []) === 0) {
            $report['status'] = 'warning';
            $report['warnings'][] = 'No DNS records were available for verification.';
            $report['finished_at'] = date(DATE_ATOM);
            return $report;
        }

        foreach ($plan['records'] as $record) {
            if (!is_array($record)) {
                continue;
            }
            $result = $this->verifyDnsRecord($record, $dns);
            $report['results'][] = $result;
            if (($result['status'] ?? '') === 'failed') {
                $report['errors'][] = 'DNS record did not resolve as expected: ' . (string) ($record['host'] ?? '');
            } elseif (($result['status'] ?? '') === 'warning') {
                $report['warnings'][] = 'DNS record verification warning: ' . (string) ($record['host'] ?? '');
            }
        }

        $report['status'] = count($report['errors']) > 0 ? 'failed' : (count($report['warnings']) > 0 ? 'warning' : 'success');
        $report['finished_at'] = date(DATE_ATOM);
        return $report;
    }

    private function verifyDnsRecord(array $record, array $dns): array
    {
        $host = (string) ($record['host'] ?? '');
        $type = strtoupper((string) ($record['type'] ?? 'A'));
        $expected = (string) ($record['value'] ?? '');
        $nameserver = trim((string) ($dns['verify_nameserver'] ?? ''));
        $addresses = $nameserver !== ''
            ? $this->resolveWithNslookup($host, $type, $nameserver)
            : $this->resolveWithSystemDns($host, $type);
        $matched = in_array($expected, $addresses, true);

        return [
            'record' => $record,
            'resolver' => $nameserver !== '' ? 'nslookup' : 'system',
            'nameserver' => $nameserver !== '' ? $nameserver : null,
            'resolved_addresses' => $addresses,
            'expected_address' => $expected,
            'status' => $matched ? 'success' : 'failed',
            'message' => $matched ? 'DNS record resolves to the expected address.' : 'DNS record does not resolve to the expected address.',
        ];
    }

    private function dnsClientTargetServer(array $dns): string
    {
        $configured = trim((string) ($dns['client_nameserver'] ?? ''));
        if ($configured !== '') {
            return $configured;
        }

        $baseUrl = trim((string) ($dns['base_url'] ?? ''));
        $host = $this->hostFromUrlOrHost($baseUrl);
        return $host;
    }

    private function makeWindowsDnsClientScript(string $target, string $interfaceAlias): string
    {
        $targetLiteral = str_replace("'", "''", $target);
        $aliasLiteral = str_replace("'", "''", $interfaceAlias);
        $script = <<<'POWERSHELL'
$ErrorActionPreference = 'Stop'
$target = '__TARGET__'
$requestedAlias = '__ALIAS__'
$principal = New-Object Security.Principal.WindowsPrincipal([Security.Principal.WindowsIdentity]::GetCurrent())
$isElevated = $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
$availableInterfaces = @(
    Get-NetIPConfiguration |
        Where-Object { $_.NetAdapter.Status -eq 'Up' -and $_.IPv4Address } |
        ForEach-Object {
            $dnsServers = @()
            try {
                $dnsServers = @(Get-DnsClientServerAddress -InterfaceAlias $_.InterfaceAlias -AddressFamily IPv4 -ErrorAction Stop).ServerAddresses
            } catch {
                $dnsServers = @()
            }
            [ordered]@{
                interface_alias = $_.InterfaceAlias
                interface_index = $_.InterfaceIndex
                status = $_.NetAdapter.Status
                ipv4 = @($_.IPv4Address | ForEach-Object { $_.IPAddress })
                gateway = @($_.IPv4DefaultGateway | ForEach-Object { $_.NextHop })
                dns_servers = $dnsServers
            }
        }
)
if (-not $isElevated) {
    [ordered]@{
        status = 'failed'
        error = 'Kit Setup is not running as Administrator. Windows DNS settings require elevated permissions.'
        is_elevated = $false
        available_interfaces = $availableInterfaces
    } | ConvertTo-Json -Depth 6
    exit 0
}
if ($requestedAlias -ne '') {
    $adapter = Get-NetAdapter -Name $requestedAlias -ErrorAction Stop
} else {
    $adapter = Get-NetIPConfiguration |
        Where-Object { $_.NetAdapter.Status -eq 'Up' -and $_.IPv4Address -and $_.IPv4DefaultGateway } |
        Select-Object -First 1 -ExpandProperty NetAdapter
    if ($null -eq $adapter) {
        $adapter = Get-NetAdapter | Where-Object { $_.Status -eq 'Up' } | Select-Object -First 1
    }
}
if ($null -eq $adapter) {
    throw 'No active network adapter was found.'
}
$before = @(Get-DnsClientServerAddress -InterfaceAlias $adapter.InterfaceAlias -AddressFamily IPv4).ServerAddresses
Set-DnsClientServerAddress -InterfaceAlias $adapter.InterfaceAlias -ServerAddresses $target
$after = @(Get-DnsClientServerAddress -InterfaceAlias $adapter.InterfaceAlias -AddressFamily IPv4).ServerAddresses
[ordered]@{
    status = 'success'
    is_elevated = $true
    available_interfaces = $availableInterfaces
    interface_alias = $adapter.InterfaceAlias
    interface_index = $adapter.ifIndex
    before = $before
    after = $after
} | ConvertTo-Json -Depth 4
POWERSHELL
;
        $script = str_replace('__TARGET__', $targetLiteral, $script);
        return str_replace('__ALIAS__', $aliasLiteral, $script);
    }

    private function resolveWithSystemDns(string $host, string $type): array
    {
        if ($host === '') {
            return [];
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        if ($type === 'AAAA') {
            $records = dns_get_record($host, DNS_AAAA);
            if (!is_array($records)) {
                return [];
            }
            $addresses = [];
            foreach ($records as $record) {
                if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
            return array_values(array_unique($addresses));
        }

        $addresses = gethostbynamel($host);
        return is_array($addresses) ? array_values(array_unique($addresses)) : [];
    }

    private function resolveWithNslookup(string $host, string $type, string $nameserver): array
    {
        if ($host === '') {
            return [];
        }

        try {
            $process = $this->runProcess(['nslookup', '-type=' . $type, $host, $nameserver], (string) getcwd());
        } catch (Throwable $e) {
            return [];
        }

        if (($process['exit_code'] ?? 1) !== 0) {
            return [];
        }

        $output = (string) ($process['stdout'] ?? '');
        $pattern = $type === 'AAAA'
            ? '/\b([0-9a-fA-F]{0,4}:[0-9a-fA-F:]{2,})\b/'
            : '/\b((?:\d{1,3}\.){3}\d{1,3})\b/';
        preg_match_all($pattern, $output, $matches);
        $addresses = [];
        foreach ($matches[1] ?? [] as $candidate) {
            if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                $addresses[] = $candidate;
            }
        }

        return array_values(array_unique($addresses));
    }

    private function applyTechnitiumRecord(string $baseUrl, string $token, array $record): array
    {
        $url = $baseUrl . '/api/zones/records/add';
        $params = [
            'domain' => (string) ($record['host'] ?? ''),
            'zone' => (string) ($record['zone'] ?? ''),
            'type' => (string) ($record['type'] ?? 'A'),
            'ttl' => (string) ($record['ttl'] ?? 300),
            'overwrite' => 'true',
            'ipAddress' => (string) ($record['value'] ?? ''),
            'comments' => 'Managed by PBB Kit Setup',
            'token' => $token,
        ];

        $response = $this->postFormJson($url, $params);
        $ok = ($response['json']['status'] ?? null) === 'ok';
        return [
            'record' => $record,
            'status' => $ok ? 'success' : 'failed',
            'http_status' => $response['http_status'],
            'api_status' => $response['json']['status'] ?? null,
            'error_message' => $ok ? null : ($response['json']['errorMessage'] ?? 'Technitium API request failed.'),
        ];
    }

    private function postFormJson(string $url, array $params): array
    {
        $body = http_build_query($params);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json',
                ],
                'content' => $body,
                'ignore_errors' => true,
                'timeout' => 30,
            ],
            'ssl' => $this->tlsOptions(),
        ]);

        $responseBody = file_get_contents($url, false, $context);
        $statusCode = $this->extractHttpStatusCode($http_response_header ?? []);
        if ($responseBody === false) {
            return [
                'http_status' => $statusCode,
                'json' => [
                    'status' => 'error',
                    'errorMessage' => 'Unable to call API endpoint.',
                ],
            ];
        }

        $json = json_decode($responseBody, true);
        if (!is_array($json)) {
            $json = [
                'status' => 'error',
                'errorMessage' => 'API returned non-JSON response.',
            ];
        }

        return [
            'http_status' => $statusCode,
            'json' => $json,
        ];
    }

    private function hostFromUrlOrHost(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $host = parse_url($value, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            return strtolower($host);
        }
        $value = preg_replace('/\/.*$/', '', $value);
        return is_string($value) ? strtolower($value) : '';
    }

    private function makeDnsRecord(string $host, string $zone, string $ipAddress, int $ttl, string $appId, bool $alias): array
    {
        $name = $this->relativeDnsName($host, $zone);
        return [
            'host' => $host,
            'zone' => $zone,
            'name' => $name,
            'type' => filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 'AAAA' : 'A',
            'value' => $ipAddress,
            'ttl' => $ttl,
            'app_id' => $appId,
            'alias' => $alias,
            'action' => 'upsert',
        ];
    }

    private function relativeDnsName(string $host, string $zone): string
    {
        $host = rtrim(strtolower($host), '.');
        $zone = rtrim(strtolower($zone), '.');
        if ($host === $zone) {
            return '@';
        }
        $suffix = '.' . $zone;
        if (str_ends_with($host, $suffix)) {
            return substr($host, 0, -strlen($suffix));
        }
        return $host;
    }

    private function dedupeDnsRecords(array $records): array
    {
        $deduped = [];
        foreach ($records as $record) {
            $key = implode('|', [
                $record['zone'] ?? '',
                $record['name'] ?? '',
                $record['type'] ?? '',
                $record['value'] ?? '',
            ]);
            $deduped[$key] = $record;
        }
        return array_values($deduped);
    }

    private function dnsTokenConfigured(array $dns): bool
    {
        $token = (string) ($dns['token'] ?? '');
        if ($token !== '' && !$this->isPlaceholder($token)) {
            return true;
        }
        $tokenEnv = (string) ($dns['token_env'] ?? '');
        if ($tokenEnv === '') {
            return false;
        }
        $value = getenv($tokenEnv);
        return is_string($value) && $value !== '';
    }

    private function getDnsToken(array $dns): string
    {
        $token = (string) ($dns['token'] ?? '');
        if ($token !== '' && !$this->isPlaceholder($token)) {
            return $token;
        }

        $tokenEnv = (string) ($dns['token_env'] ?? '');
        if ($tokenEnv === '') {
            return '';
        }

        $value = getenv($tokenEnv);
        return is_string($value) ? trim($value) : '';
    }

    private function runSslPlan(array $config, string $runDir, array $context): array
    {
        $ssl = $config['ssl'] ?? [];
        if (!is_array($ssl)) {
            $ssl = [];
        }

        $warnings = [];
        $errors = [];
        $certificateRoot = (string) ($ssl['certificate_root'] ?? $config['paths']['cert_root'] ?? '');
        $certificateFile = (string) ($ssl['certificate_file'] ?? '');
        $privateKeyFile = (string) ($ssl['private_key_file'] ?? '');
        $chainFile = (string) ($ssl['chain_file'] ?? '');
        if ($certificateFile === '' && $certificateRoot !== '') {
            $certificateFile = $this->joinPath($certificateRoot, 'pbb.ph.crt');
        }
        if ($privateKeyFile === '' && $certificateRoot !== '') {
            $privateKeyFile = $this->joinPath($certificateRoot, 'pbb.ph.key');
        }
        if ($chainFile === '' && $certificateRoot !== '') {
            $chainFile = $this->joinPath($certificateRoot, 'pbb.ph.fullchain.crt');
        }

        $pemUploadPath = (string) ($ssl['pem_upload_path'] ?? '');
        $pemInspection = $this->inspectPemBundle($pemUploadPath);
        if ($pemUploadPath !== '' && ($pemInspection['status'] ?? '') === 'failed') {
            $errors[] = (string) ($pemInspection['message'] ?? 'PEM bundle inspection failed.');
        }

        $pemExtraction = $this->extractPemBundle(
            $pemUploadPath,
            $certificateFile,
            $privateKeyFile,
            $chainFile,
            (bool) ($ssl['write_extracted_files'] ?? false),
            (bool) ($ssl['private_key_required'] ?? true)
        );
        if (($pemExtraction['status'] ?? '') === 'failed') {
            $errors[] = (string) ($pemExtraction['message'] ?? 'PEM bundle extraction failed.');
        }

        $certStatus = $this->inspectCertificateFile($certificateFile);
        $keyStatus = $this->inspectPrivateKeyFile($privateKeyFile);
        if (($certStatus['exists'] ?? false) === false) {
            $warnings[] = 'Certificate file does not exist yet: ' . $certificateFile;
        }
        if (($keyStatus['exists'] ?? false) === false && ($ssl['private_key_required'] ?? true) === true) {
            $warnings[] = 'Private key file does not exist yet: ' . $privateKeyFile;
        }

        $certKeyMatch = $this->inspectCertificateKeyPair($certificateFile, $privateKeyFile);
        if (($certKeyMatch['checked'] ?? false) === true && ($certKeyMatch['matches'] ?? false) !== true) {
            $errors[] = 'Certificate and private key do not match.';
        }

        $vhosts = $this->buildApacheVhosts($config, $certificateFile, $privateKeyFile, is_file($chainFile) ? $chainFile : '');
        if (count($vhosts['entries']) === 0) {
            $errors[] = 'No Apache vhosts were generated.';
        }

        $coverage = $this->inspectCertificateCoverage($certStatus, $vhosts['entries']);
        if (($coverage['checked'] ?? false) === true && count($coverage['missing_hosts'] ?? []) > 0) {
            $errors[] = 'Certificate does not cover all planned HTTPS hosts: ' . implode(', ', $coverage['missing_hosts']);
        }

        $generatedDir = $this->joinPath($runDir, 'web');
        $this->ensureDirectory($generatedDir);
        $generatedPath = $this->joinPath($generatedDir, 'pbb-apache-vhosts.conf');
        if (file_put_contents($generatedPath, $vhosts['content']) === false) {
            throw new RuntimeException('Unable to write Apache vhost plan: ' . $generatedPath);
        }

        return [
            'schema_version' => 1,
            'kit_setup_version' => self::VERSION,
            'action' => 'ssl-plan',
            'status' => count($errors) > 0 ? 'failed' : (count($warnings) > 0 ? 'warning' : 'success'),
            'started_at' => $context['started_at'],
            'finished_at' => date(DATE_ATOM),
            'run_id' => $context['run_id'],
            'run_dir' => $context['run_dir'],
            'certificate' => $certStatus,
            'private_key' => $keyStatus,
            'certificate_key_pair' => $certKeyMatch,
            'certificate_coverage' => $coverage,
            'chain_file' => [
                'path' => $chainFile,
                'exists' => $chainFile !== '' && is_file($chainFile),
            ],
            'pem_upload' => $pemInspection,
            'pem_extraction' => $pemExtraction,
            'apache' => [
                'generated_include_path' => $this->absolutePath($generatedPath),
                'configured_include_output' => $config['paths']['apache_include_output'] ?? null,
                'apply_supported' => false,
                'vhosts' => $vhosts['entries'],
            ],
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    private function runSslApply(array $config, string $runDir, array $context): array
    {
        $plan = $this->runSslPlan($config, $runDir, $context);
        $ssl = is_array($config['ssl'] ?? null) ? $config['ssl'] : [];
        $mode = (string) ($ssl['web_server_update_mode'] ?? 'plan-only');
        $apply = [
            'mode' => $mode,
            'status' => 'skipped',
            'source' => $plan['apache']['generated_include_path'] ?? null,
            'target' => $config['paths']['apache_include_output'] ?? null,
            'backup_path' => null,
            'reload_supported' => false,
            'config_test' => null,
            'message' => 'SSL web-server apply is not enabled.',
        ];
        $errors = [];
        if (($plan['status'] ?? '') === 'failed') {
            $errors[] = 'SSL plan failed; apply was not attempted.';
        }

        if (count($errors) === 0 && $mode === 'apply') {
            $source = (string) ($plan['apache']['generated_include_path'] ?? '');
            $target = (string) ($config['paths']['apache_include_output'] ?? '');
            if ($source === '' || !is_file($source)) {
                $errors[] = 'Generated Apache include file is missing.';
            } elseif ($target === '') {
                $errors[] = 'paths.apache_include_output is required when ssl.web_server_update_mode=apply.';
            } else {
                $this->ensureDirectory(dirname($target));
                if (is_file($target)) {
                    $backupDir = $this->joinPath($runDir, 'web-backups');
                    $this->ensureDirectory($backupDir);
                    $backupPath = $this->joinPath($backupDir, basename($target) . '.' . date('YmdHis') . '.bak');
                    if (!copy($target, $backupPath)) {
                        $errors[] = 'Unable to backup existing Apache include file.';
                    } else {
                        $apply['backup_path'] = $this->absolutePath($backupPath);
                    }
                }
                if (count($errors) === 0) {
                    if (!copy($source, $target)) {
                        $errors[] = 'Unable to write Apache include file.';
                    } else {
                        $apply['status'] = 'success';
                        $apply['message'] = 'Apache include file written. Reload is still manual.';
                        $apply['config_test'] = $this->runWebServerConfigTest($config);
                        if (($apply['config_test']['status'] ?? '') === 'failed') {
                            $errors[] = 'Web-server config test failed after writing Apache include.';
                            $apply['status'] = 'failed';
                        }
                    }
                }
            }
        }

        if ($mode !== 'apply' && count($errors) === 0) {
            $apply['message'] = 'Set ssl.web_server_update_mode=apply to write the generated Apache include file.';
        }

        $status = count($errors) > 0 ? 'failed' : $apply['status'];
        return [
            'schema_version' => 1,
            'kit_setup_version' => self::VERSION,
            'action' => 'ssl-apply',
            'status' => $status,
            'started_at' => $context['started_at'],
            'finished_at' => date(DATE_ATOM),
            'run_id' => $context['run_id'],
            'run_dir' => $context['run_dir'],
            'plan' => $plan,
            'apply' => $apply,
            'warnings' => [],
            'errors' => $errors,
        ];
    }

    private function runWebServerConfigTest(array $config): array
    {
        $platform = is_array($config['platform'] ?? null) ? $config['platform'] : [];
        $webServer = strtolower((string) ($platform['web_server'] ?? 'apache'));
        if ($webServer !== 'apache') {
            return [
                'status' => 'not-supported',
                'web_server' => $webServer,
                'message' => 'Automatic config test currently supports Apache only.',
            ];
        }

        $apacheBinary = (string) ($platform['apache_binary'] ?? '');
        if ($apacheBinary === '') {
            $apacheBinary = $this->findFirstGlobMatch('C:\\wamp64\\bin\\apache\\apache*\\bin\\httpd.exe');
        }
        if ($apacheBinary === '' || !is_file($apacheBinary)) {
            return [
                'status' => 'warning',
                'web_server' => 'apache',
                'command' => null,
                'message' => 'Apache binary is not configured; config test skipped.',
            ];
        }

        $process = $this->runProcess([$apacheBinary, '-t'], dirname($apacheBinary));
        return [
            'status' => $process['exit_code'] === 0 ? 'success' : 'failed',
            'web_server' => 'apache',
            'command' => $process['command'],
            'exit_code' => $process['exit_code'],
            'stdout' => $process['stdout'],
            'stderr' => $process['stderr'],
            'message' => $process['exit_code'] === 0 ? 'Apache config test passed.' : 'Apache config test failed.',
        ];
    }

    private function inspectPemBundle(string $pemUploadPath): array
    {
        if ($pemUploadPath === '') {
            return [
                'status' => 'not-configured',
                'path' => '',
                'exists' => false,
            ];
        }

        if (!is_file($pemUploadPath)) {
            return [
                'status' => 'failed',
                'path' => $pemUploadPath,
                'exists' => false,
                'message' => 'Configured PEM upload path does not exist.',
            ];
        }

        $contents = file_get_contents($pemUploadPath);
        if ($contents === false) {
            return [
                'status' => 'failed',
                'path' => $pemUploadPath,
                'exists' => true,
                'message' => 'Unable to read PEM bundle.',
            ];
        }

        preg_match_all('/-----BEGIN CERTIFICATE-----/', $contents, $certMatches);
        $hasPrivateKey = preg_match('/-----BEGIN (RSA |EC |)PRIVATE KEY-----/', $contents) === 1;
        return [
            'status' => count($certMatches[0]) > 0 ? 'success' : 'failed',
            'path' => $pemUploadPath,
            'exists' => true,
            'certificate_count' => count($certMatches[0]),
            'has_private_key' => $hasPrivateKey,
            'message' => count($certMatches[0]) > 0 ? 'PEM bundle contains certificate material.' : 'PEM bundle contains no certificates.',
        ];
    }

    private function extractPemBundle(
        string $pemUploadPath,
        string $certificateFile,
        string $privateKeyFile,
        string $chainFile,
        bool $writeFiles,
        bool $privateKeyRequired
    ): array {
        $result = [
            'status' => $pemUploadPath === '' ? 'not-configured' : ($writeFiles ? 'pending' : 'planned'),
            'write_enabled' => $writeFiles,
            'certificate_file' => $certificateFile,
            'private_key_file' => $privateKeyFile,
            'chain_file' => $chainFile,
            'certificate_count' => 0,
            'private_key_found' => false,
            'written' => [],
        ];
        if ($pemUploadPath === '') {
            return $result;
        }
        if (!is_file($pemUploadPath)) {
            $result['status'] = 'failed';
            $result['message'] = 'Configured PEM upload path does not exist.';
            return $result;
        }

        $contents = file_get_contents($pemUploadPath);
        if ($contents === false) {
            $result['status'] = 'failed';
            $result['message'] = 'Unable to read PEM bundle.';
            return $result;
        }

        preg_match_all('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $contents, $certificateMatches);
        preg_match('/-----BEGIN (?:RSA |EC |)PRIVATE KEY-----.*?-----END (?:RSA |EC |)PRIVATE KEY-----/s', $contents, $privateKeyMatch);
        $certificates = $certificateMatches[0] ?? [];
        $privateKey = $privateKeyMatch[0] ?? '';
        $result['certificate_count'] = count($certificates);
        $result['private_key_found'] = $privateKey !== '';

        if (count($certificates) === 0) {
            $result['status'] = 'failed';
            $result['message'] = 'PEM bundle contains no certificate blocks.';
            return $result;
        }
        if ($privateKeyRequired && $privateKey === '') {
            $result['status'] = 'failed';
            $result['message'] = 'PEM bundle contains no private key block.';
            return $result;
        }
        if (!$writeFiles) {
            return $result;
        }

        $root = dirname($certificateFile);
        $this->ensureDirectory($root);
        $written = [];
        if (file_put_contents($certificateFile, $this->normalizePemBlock($certificates[0])) === false) {
            $result['status'] = 'failed';
            $result['message'] = 'Unable to write certificate file.';
            return $result;
        }
        $written[] = 'certificate_file';

        if ($privateKey !== '') {
            $this->ensureDirectory(dirname($privateKeyFile));
            if (file_put_contents($privateKeyFile, $this->normalizePemBlock($privateKey)) === false) {
                $result['status'] = 'failed';
                $result['message'] = 'Unable to write private key file.';
                return $result;
            }
            @chmod($privateKeyFile, 0600);
            $written[] = 'private_key_file';
        }

        if ($chainFile !== '') {
            $this->ensureDirectory(dirname($chainFile));
            $fullChain = implode(PHP_EOL, array_map([$this, 'normalizePemBlock'], $certificates));
            if (file_put_contents($chainFile, $fullChain) === false) {
                $result['status'] = 'failed';
                $result['message'] = 'Unable to write fullchain file.';
                return $result;
            }
            $written[] = 'chain_file';
        }

        $result['status'] = 'success';
        $result['written'] = $written;
        return $result;
    }

    private function normalizePemBlock(string $block): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $block)) . PHP_EOL;
    }

    private function inspectCertificateFile(string $certificateFile): array
    {
        $result = [
            'path' => $certificateFile,
            'exists' => $certificateFile !== '' && is_file($certificateFile),
            'subject' => null,
            'valid_from' => null,
            'valid_to' => null,
            'dns_names' => [],
            'fingerprint_sha256' => null,
        ];
        if (!$result['exists']) {
            return $result;
        }

        $contents = file_get_contents($certificateFile);
        $parsed = $contents === false ? false : openssl_x509_parse($contents);
        if (is_array($parsed)) {
            $result['subject'] = $parsed['subject'] ?? null;
            $result['valid_from'] = isset($parsed['validFrom_time_t']) ? date(DATE_ATOM, (int) $parsed['validFrom_time_t']) : null;
            $result['valid_to'] = isset($parsed['validTo_time_t']) ? date(DATE_ATOM, (int) $parsed['validTo_time_t']) : null;
            $extensions = $parsed['extensions']['subjectAltName'] ?? '';
            if (is_string($extensions) && $extensions !== '') {
                foreach (explode(',', $extensions) as $name) {
                    $name = trim($name);
                    if (str_starts_with($name, 'DNS:')) {
                        $result['dns_names'][] = substr($name, 4);
                    }
                }
            }
        }
        $fingerprint = is_string($contents) ? openssl_x509_fingerprint($contents, 'sha256') : false;
        $result['fingerprint_sha256'] = is_string($fingerprint) ? $fingerprint : null;
        return $result;
    }

    private function inspectPrivateKeyFile(string $privateKeyFile): array
    {
        $result = [
            'path' => $privateKeyFile,
            'exists' => $privateKeyFile !== '' && is_file($privateKeyFile),
            'readable' => false,
            'valid' => null,
        ];
        if (!$result['exists']) {
            return $result;
        }

        $contents = file_get_contents($privateKeyFile);
        $result['readable'] = $contents !== false;
        if ($contents !== false) {
            $key = openssl_pkey_get_private($contents);
            $result['valid'] = $key !== false;
        }
        return $result;
    }

    private function inspectCertificateKeyPair(string $certificateFile, string $privateKeyFile): array
    {
        $result = [
            'checked' => false,
            'matches' => null,
        ];
        if ($certificateFile === '' || $privateKeyFile === '' || !is_file($certificateFile) || !is_file($privateKeyFile)) {
            return $result;
        }

        $certificate = file_get_contents($certificateFile);
        $privateKey = file_get_contents($privateKeyFile);
        if ($certificate === false || $privateKey === false) {
            return $result;
        }

        $result['checked'] = true;
        $result['matches'] = openssl_x509_check_private_key($certificate, $privateKey);
        return $result;
    }

    private function inspectCertificateCoverage(array $certStatus, array $vhostEntries): array
    {
        $hosts = [];
        foreach ($vhostEntries as $entry) {
            $host = (string) ($entry['server_name'] ?? '');
            if ($host !== '') {
                $hosts[] = $host;
            }
            foreach (($entry['server_aliases'] ?? []) as $alias) {
                $alias = (string) $alias;
                if ($alias !== '') {
                    $hosts[] = $alias;
                }
            }
        }
        $hosts = array_values(array_unique($hosts));
        $dnsNames = array_values(array_unique(array_map('strtolower', $certStatus['dns_names'] ?? [])));
        $result = [
            'checked' => ($certStatus['exists'] ?? false) === true && count($dnsNames) > 0 && count($hosts) > 0,
            'dns_names' => $dnsNames,
            'planned_hosts' => $hosts,
            'covered_hosts' => [],
            'missing_hosts' => [],
        ];
        if (!$result['checked']) {
            return $result;
        }

        foreach ($hosts as $host) {
            if ($this->certificateCoversHost($dnsNames, strtolower($host))) {
                $result['covered_hosts'][] = $host;
            } else {
                $result['missing_hosts'][] = $host;
            }
        }
        return $result;
    }

    private function certificateCoversHost(array $dnsNames, string $host): bool
    {
        foreach ($dnsNames as $dnsName) {
            if ($dnsName === $host) {
                return true;
            }
            if (str_starts_with($dnsName, '*.')) {
                $suffix = substr($dnsName, 1);
                if (str_ends_with($host, $suffix) && substr_count($host, '.') === substr_count($suffix, '.')) {
                    return true;
                }
            }
        }
        return false;
    }

    private function buildApacheVhosts(array $config, string $certificateFile, string $privateKeyFile, string $chainFile): array
    {
        $domainMap = [
            'pbb-mapserver' => 'mapserver',
            'pbb-maestro' => 'maestro',
            'pbb-realtime' => 'realtime',
            'pbb-relay' => 'relay',
            'pbb-hotline' => 'hotline',
        ];
        $domains = is_array($config['domains'] ?? null) ? $config['domains'] : [];
        $entries = [];
        $blocks = [
            '# Generated by PBB Kit Setup. Review before including in Apache.',
            '# Generated at ' . date(DATE_ATOM),
            '',
        ];

        foreach ($this->selectedLocalAppIds($config) as $appId) {
            $domainKey = $domainMap[$appId] ?? null;
            $app = $this->findAppConfig($config, $appId);
            if ($domainKey === null || !isset($domains[$domainKey])) {
                continue;
            }

            $host = $this->hostFromUrlOrHost((string) $domains[$domainKey]);
            $documentRoot = (string) ($app['public_path'] ?? $app['install_path'] ?? '');
            if ($host === '' || $documentRoot === '') {
                continue;
            }

            $aliases = [];

            $entries[] = [
                'app_id' => $appId,
                'server_name' => $host,
                'server_aliases' => $aliases,
                'document_root' => $documentRoot,
            ];
            $blocks[] = $this->renderApacheVhostBlock($host, $aliases, $documentRoot, $certificateFile, $privateKeyFile, $chainFile);
        }

        return [
            'entries' => $entries,
            'content' => implode(PHP_EOL, $blocks) . PHP_EOL,
        ];
    }

    private function renderApacheVhostBlock(string $host, array $aliases, string $documentRoot, string $certificateFile, string $privateKeyFile, string $chainFile): string
    {
        $docRoot = $this->apachePath($documentRoot);
        $cert = $this->apachePath($certificateFile);
        $key = $this->apachePath($privateKeyFile);
        $chain = $chainFile !== '' ? $this->apachePath($chainFile) : '';
        $lines = [
            '<VirtualHost *:80>',
            '    ServerName ' . $host,
        ];
        foreach ($aliases as $alias) {
            $lines[] = '    ServerAlias ' . $alias;
        }
        $lines[] = '    Redirect permanent / https://' . $host . '/';
        $lines[] = '</VirtualHost>';
        $lines[] = '';
        $lines[] = '<VirtualHost *:443>';
        $lines[] = '    ServerName ' . $host;
        foreach ($aliases as $alias) {
            $lines[] = '    ServerAlias ' . $alias;
        }
        $lines[] = '    DocumentRoot "' . $docRoot . '"';
        $lines[] = '    SSLEngine on';
        $lines[] = '    SSLCertificateFile "' . $cert . '"';
        $lines[] = '    SSLCertificateKeyFile "' . $key . '"';
        if ($chain !== '') {
            $lines[] = '    SSLCertificateChainFile "' . $chain . '"';
        }
        $lines[] = '    <Directory "' . $docRoot . '">';
        $lines[] = '        Options FollowSymLinks';
        $lines[] = '        AllowOverride All';
        $lines[] = '        Require all granted';
        $lines[] = '    </Directory>';
        $lines[] = '</VirtualHost>';
        $lines[] = '';
        return implode(PHP_EOL, $lines);
    }

    private function apachePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    private function deployStagedPackage(string $stagingPath, string $targetPath, string $runDir, string $appId, array $allowedTargetRoots): array
    {
        if ($targetPath === '') {
            throw new RuntimeException('Package target path is required for deployment.');
        }
        if (!$this->isPathInsideAnyRoot($targetPath, $allowedTargetRoots)) {
            throw new RuntimeException('Refusing to deploy package outside allowed Kit install roots: ' . $targetPath);
        }
        if (!is_dir($stagingPath) || !is_file($this->joinPath($stagingPath, 'release.json'))) {
            throw new RuntimeException('Staged package is not a verified release directory: ' . $stagingPath);
        }

        $backupPath = null;
        if (is_dir($targetPath)) {
            $backupRoot = $this->joinPath($runDir, 'package-backups');
            $this->ensureDirectory($backupRoot);
            $backupPath = $this->joinPath($backupRoot, $this->safePathSegment($appId) . '-' . date('YmdHis'));
            if (!rename($targetPath, $backupPath)) {
                throw new RuntimeException('Unable to backup existing package target: ' . $targetPath);
            }
        }

        $this->copyDirectory($stagingPath, $targetPath);

        return [
            'backup_path' => $backupPath,
        ];
    }

    private function extractPackageArchive(string $archivePath, string $runDir, string $appId): array
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('PHP ZipArchive extension is required for package extraction.');
        }

        $stagingRoot = $this->joinPath($runDir, 'packages');
        $this->ensureDirectory($stagingRoot);
        $stagingPath = $this->joinPath($stagingRoot, $this->safePathSegment($appId));
        if (is_dir($stagingPath)) {
            $this->removeDirectory($stagingPath);
        }
        $this->ensureDirectory($stagingPath);

        $zip = new ZipArchive();
        $opened = $zip->open($archivePath);
        if ($opened !== true) {
            throw new RuntimeException('Unable to open package archive: ' . $archivePath);
        }

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                $normalized = str_replace('\\', '/', $name);
                if ($normalized === '' || str_starts_with($normalized, '/') || preg_match('/(^|\/)\.\.(\/|$)/', $normalized) === 1) {
                    throw new RuntimeException('Unsafe archive path: ' . $name);
                }
            }

            if (!$zip->extractTo($stagingPath)) {
                throw new RuntimeException('Unable to extract package archive: ' . $archivePath);
            }
        } finally {
            $zip->close();
        }

        $root = $this->normalizeExtractedRoot($stagingPath);
        return [
            'staging_path' => $root,
        ];
    }

    private function normalizeExtractedRoot(string $stagingPath): string
    {
        if (is_file($this->joinPath($stagingPath, 'release.json'))) {
            return $stagingPath;
        }

        $entries = array_values(array_filter(scandir($stagingPath) ?: [], static fn (string $entry): bool => $entry !== '.' && $entry !== '..'));
        if (count($entries) === 1) {
            $candidate = $this->joinPath($stagingPath, $entries[0]);
            if (is_dir($candidate) && is_file($this->joinPath($candidate, 'release.json'))) {
                return $candidate;
            }
        }

        return $stagingPath;
    }

    private function safePathSegment(string $value): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $value);
        return is_string($safe) && $safe !== '' ? $safe : 'package';
    }

    private function parseArgs(array $argv): array
    {
        $options = [];
        $count = count($argv);
        for ($i = 1; $i < $count; $i++) {
            $arg = (string) $argv[$i];
            if ($arg === '--help' || $arg === '-h') {
                $options['help'] = true;
                continue;
            }
            if (strpos($arg, '--') !== 0) {
                throw new InvalidArgumentException('Unexpected argument: ' . $arg);
            }

            $name = substr($arg, 2);
            $value = true;
            if (strpos($name, '=') !== false) {
                [$name, $value] = explode('=', $name, 2);
            } else {
                $next = $argv[$i + 1] ?? null;
                if (is_string($next) && strpos($next, '--') !== 0) {
                    $value = $next;
                    $i++;
                }
            }

            $options[$name] = $value;
        }

        return $options;
    }

    private function printHelp(): void
    {
        $this->writeLine('PBB Kit Setup ' . self::VERSION);
        $this->writeLine('Usage: php bin/kit-setup.php --config <path> [--action detect|hub-resolve|prepare-packages|dns-plan|dns-apply|dns-client-apply|dns-verify|ssl-plan|ssl-apply|remote-check|smoke-check|stage-report|finish-report|plan|preflight|install|populate] [--run-dir <path>] [--run-id <id>] [--app <app-id>]');
    }

    private function validateKitConfig(array $config, string $configPath): void
    {
        foreach (['schema_version', 'kit', 'runtime', 'apps'] as $field) {
            if (!array_key_exists($field, $config)) {
                throw new RuntimeException("Kit config {$configPath} is missing {$field}.");
            }
        }
        if ((int) $config['schema_version'] !== 1) {
            throw new RuntimeException('Unsupported kit config schema_version.');
        }
        if (!is_array($config['apps']) || count($config['apps']) === 0) {
            throw new RuntimeException('Kit config must include at least one app.');
        }
        $phpBinary = (string) ($config['runtime']['php_binary'] ?? '');
        if ($phpBinary === '' || !is_file($phpBinary)) {
            throw new RuntimeException('Configured runtime.php_binary does not exist: ' . $phpBinary);
        }
    }

    private function runPlatformDetect(array $config, array $context): array
    {
        $requiredExtensions = $config['platform']['required_php_extensions'] ?? [
            'curl',
            'json',
            'openssl',
            'pdo',
            'mbstring',
        ];
        if (!is_array($requiredExtensions)) {
            $requiredExtensions = [];
        }

        $extensionChecks = [];
        foreach ($requiredExtensions as $extension) {
            $name = (string) $extension;
            $extensionChecks[] = [
                'name' => $name,
                'status' => extension_loaded($name) ? 'passed' : 'failed',
            ];
        }

        $paths = $config['paths'] ?? [];
        if (!is_array($paths)) {
            $paths = [];
        }
        $platform = $config['platform'] ?? [];
        if (!is_array($platform)) {
            $platform = [];
        }

        $pathChecks = [];
        foreach ([
            'apps_base' => $paths['apps_base'] ?? ($config['layout']['base_path'] ?? $config['kit']['install_root'] ?? ''),
            'cert_root' => $paths['cert_root'] ?? '',
            'apache_include_output' => $paths['apache_include_output'] ?? '',
        ] as $name => $path) {
            $path = (string) $path;
            if ($path === '') {
                $pathChecks[] = [
                    'name' => $name,
                    'path' => '',
                    'status' => 'not-configured',
                ];
                continue;
            }

            $existing = is_dir($path) || is_file($path);
            $parent = is_dir($path) ? $path : dirname($path);
            $pathChecks[] = [
                'name' => $name,
                'path' => $path,
                'exists' => $existing,
                'parent_exists' => is_dir($parent),
                'parent_writable' => is_dir($parent) && is_writable($parent),
                'status' => ($existing || (is_dir($parent) && is_writable($parent))) ? 'passed' : 'warning',
            ];
        }

        $failed = array_filter($extensionChecks, static fn (array $check): bool => $check['status'] === 'failed');
        $warnings = array_filter($pathChecks, static fn (array $check): bool => $check['status'] === 'warning' || $check['status'] === 'not-configured');
        $toolChecks = $this->detectPlatformTools($platform);
        $serviceChecks = $this->detectPlatformServices($config);
        $portChecks = $this->detectPlatformPorts($platform);
        $toolWarnings = array_filter($toolChecks, static fn (array $check): bool => in_array($check['status'], ['warning', 'not-found', 'not-configured'], true));
        $serviceWarnings = array_filter($serviceChecks, static fn (array $check): bool => in_array($check['status'], ['warning', 'not-found', 'stopped', 'not-configured'], true));
        $portWarnings = array_filter($portChecks, static fn (array $check): bool => in_array($check['status'], ['warning', 'not-configured'], true));

        return [
            'schema_version' => 1,
            'kit_setup_version' => self::VERSION,
            'action' => 'detect',
            'status' => count($failed) > 0 ? 'failed' : ((count($warnings) + count($toolWarnings) + count($serviceWarnings) + count($portWarnings)) > 0 ? 'warning' : 'success'),
            'started_at' => $context['started_at'],
            'finished_at' => date(DATE_ATOM),
            'run_id' => $context['run_id'],
            'run_dir' => $context['run_dir'],
            'platform' => [
                'detected_os_family' => PHP_OS_FAMILY,
                'configured_os' => $config['platform']['os'] ?? null,
                'web_server' => $config['platform']['web_server'] ?? null,
                'stack' => $config['platform']['stack'] ?? null,
            ],
            'php' => [
                'binary' => (string) ($config['runtime']['php_binary'] ?? ''),
                'version' => PHP_VERSION,
                'sapi' => PHP_SAPI,
                'extensions' => $extensionChecks,
            ],
            'openssl' => [
                'available' => defined('OPENSSL_VERSION_TEXT'),
                'version' => defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : null,
            ],
            'tools' => $toolChecks,
            'services' => $serviceChecks,
            'ports' => $portChecks,
            'paths' => $pathChecks,
        ];
    }

    private function detectPlatformTools(array $platform): array
    {
        $defaultApache = $this->findFirstGlobMatch('C:\\wamp64\\bin\\apache\\apache*\\bin\\httpd.exe');
        $defaultMysql = $this->findFirstGlobMatch('C:\\wamp64\\bin\\mariadb\\mariadb*\\bin\\mysql.exe')
            ?: $this->findFirstGlobMatch('C:\\wamp64\\bin\\mysql\\mysql*\\bin\\mysql.exe');
        $checks = [
            $this->inspectExecutableTool('apache', (string) ($platform['apache_binary'] ?? $defaultApache), ['-v']),
            $this->inspectExecutableTool('mysql', (string) ($platform['mysql_binary'] ?? $defaultMysql), ['--version']),
            $this->inspectExecutableTool('ffmpeg', (string) ($platform['ffmpeg_binary'] ?? ''), ['-version']),
            $this->inspectExecutableTool('ffprobe', (string) ($platform['ffprobe_binary'] ?? ''), ['-version']),
        ];

        return $checks;
    }

    private function inspectExecutableTool(string $name, string $binary, array $versionArgs): array
    {
        $result = [
            'name' => $name,
            'binary' => $binary,
            'exists' => $binary !== '' && is_file($binary),
            'status' => 'not-configured',
            'version_line' => null,
        ];
        if ($binary === '') {
            return $result;
        }
        if (!$result['exists']) {
            $result['status'] = 'not-found';
            return $result;
        }

        try {
            $process = $this->runProcess(array_merge([$binary], $versionArgs), getcwd());
            $output = trim($process['stdout'] !== '' ? $process['stdout'] : $process['stderr']);
            $lines = preg_split('/\r\n|\r|\n/', $output);
            $result['version_line'] = is_array($lines) && isset($lines[0]) ? trim($lines[0]) : null;
            $result['exit_code'] = $process['exit_code'];
            $result['status'] = $process['exit_code'] === 0 ? 'passed' : 'warning';
        } catch (Throwable $e) {
            $result['status'] = 'warning';
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    private function detectPlatformServices(array $config): array
    {
        $runtime = is_array($config['runtime'] ?? null) ? $config['runtime'] : [];
        $platform = is_array($config['platform'] ?? null) ? $config['platform'] : [];
        $manager = (string) ($runtime['service_manager'] ?? 'manual');
        $services = $platform['service_names'] ?? [];
        if (!is_array($services)) {
            $services = [];
        }
        if ($manager === 'manual') {
            return [[
                'name' => 'service_manager',
                'manager' => $manager,
                'status' => 'manual',
            ]];
        }
        if (count($services) === 0) {
            return [[
                'name' => 'service_manager',
                'manager' => $manager,
                'status' => 'not-configured',
                'message' => 'No platform.service_names entries configured.',
            ]];
        }

        $checks = [];
        foreach ($services as $name => $serviceName) {
            $checks[] = $this->inspectService((string) $name, (string) $serviceName, $manager);
        }
        return $checks;
    }

    private function detectPlatformPorts(array $platform): array
    {
        $configured = $platform['port_checks'] ?? [];
        if (!is_array($configured) || count($configured) === 0) {
            return [[
                'name' => 'port_checks',
                'status' => 'not-configured',
                'message' => 'No platform.port_checks entries configured.',
            ]];
        }

        $checks = [];
        foreach ($configured as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $name = (string) ($entry['name'] ?? ('port_' . (string) ($entry['port'] ?? 'unknown')));
            $host = (string) ($entry['host'] ?? '127.0.0.1');
            $port = (int) ($entry['port'] ?? 0);
            $expect = (string) ($entry['expect'] ?? 'open');
            if ($port <= 0 || $port > 65535) {
                $checks[] = [
                    'name' => $name,
                    'host' => $host,
                    'port' => $port,
                    'expect' => $expect,
                    'status' => 'warning',
                    'message' => 'Invalid port check configuration.',
                ];
                continue;
            }

            $checks[] = $expect === 'available'
                ? $this->inspectAvailablePort($name, $host, $port)
                : $this->inspectOpenPort($name, $host, $port);
        }

        return $checks;
    }

    private function inspectOpenPort(string $name, string $host, int $port): array
    {
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, 1.0);
        if (is_resource($socket)) {
            fclose($socket);
            return [
                'name' => $name,
                'host' => $host,
                'port' => $port,
                'expect' => 'open',
                'status' => 'passed',
                'message' => 'Port is reachable.',
            ];
        }

        return [
            'name' => $name,
            'host' => $host,
            'port' => $port,
            'expect' => 'open',
            'status' => 'warning',
            'message' => $errstr !== '' ? $errstr : 'Port is not reachable.',
        ];
    }

    private function inspectAvailablePort(string $name, string $host, int $port): array
    {
        $errno = 0;
        $errstr = '';
        $server = @stream_socket_server('tcp://' . $host . ':' . $port, $errno, $errstr);
        if (is_resource($server)) {
            fclose($server);
            return [
                'name' => $name,
                'host' => $host,
                'port' => $port,
                'expect' => 'available',
                'status' => 'passed',
                'message' => 'Port is available for binding.',
            ];
        }

        return [
            'name' => $name,
            'host' => $host,
            'port' => $port,
            'expect' => 'available',
            'status' => 'warning',
            'message' => $errstr !== '' ? $errstr : 'Port is already in use or cannot be bound.',
        ];
    }

    private function inspectService(string $name, string $serviceName, string $manager): array
    {
        $result = [
            'name' => $name,
            'service_name' => $serviceName,
            'manager' => $manager,
            'status' => 'not-configured',
            'state' => null,
        ];
        if ($serviceName === '') {
            return $result;
        }

        try {
            if ($manager === 'windows-service') {
                $process = $this->runProcess(['sc.exe', 'query', $serviceName], getcwd());
                $output = trim($process['stdout'] . PHP_EOL . $process['stderr']);
                $result['exit_code'] = $process['exit_code'];
                if ($process['exit_code'] !== 0) {
                    $result['status'] = 'not-found';
                    return $result;
                }
                if (preg_match('/STATE\s*:\s*\d+\s+([A-Z_]+)/i', $output, $matches) === 1) {
                    $state = strtoupper($matches[1]);
                    $result['state'] = $state;
                    $result['status'] = $state === 'RUNNING' ? 'passed' : 'stopped';
                    return $result;
                }
            }

            if ($manager === 'systemd') {
                $process = $this->runProcess(['systemctl', 'is-active', $serviceName], getcwd());
                $state = trim($process['stdout'] !== '' ? $process['stdout'] : $process['stderr']);
                $result['exit_code'] = $process['exit_code'];
                $result['state'] = $state;
                $result['status'] = $state === 'active' ? 'passed' : 'stopped';
                return $result;
            }

            $result['status'] = 'manual';
        } catch (Throwable $e) {
            $result['status'] = 'warning';
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    private function findFirstGlobMatch(string $pattern): string
    {
        $matches = glob($pattern);
        if (!is_array($matches) || count($matches) === 0) {
            return '';
        }
        rsort($matches, SORT_NATURAL);
        foreach ($matches as $match) {
            if (is_file($match)) {
                return $match;
            }
        }
        return '';
    }

    private function runRemoteDependencyCheck(array $config, array $context): array
    {
        $checks = [];
        $errors = [];
        $warnings = [];
        foreach ($config['apps'] as $app) {
            if (!is_array($app) || ($app['enabled'] ?? true) === false || (string) ($app['install_scope'] ?? 'local') !== 'remote') {
                continue;
            }

            $check = $this->inspectRemoteApp($app);
            $checks[] = $check;
            if (($check['status'] ?? '') === 'failed') {
                $errors[] = (string) ($check['message'] ?? ('Remote dependency failed: ' . ($app['id'] ?? 'unknown')));
            } elseif (($check['status'] ?? '') === 'warning') {
                $warnings[] = (string) ($check['message'] ?? ('Remote dependency warning: ' . ($app['id'] ?? 'unknown')));
            }
        }

        return [
            'schema_version' => 1,
            'kit_setup_version' => self::VERSION,
            'action' => 'remote-check',
            'status' => count($errors) > 0 ? 'failed' : (count($warnings) > 0 ? 'warning' : 'success'),
            'started_at' => $context['started_at'],
            'finished_at' => date(DATE_ATOM),
            'run_id' => $context['run_id'],
            'run_dir' => $context['run_dir'],
            'remote_apps' => $checks,
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    private function inspectRemoteApp(array $app): array
    {
        $remote = is_array($app['remote'] ?? null) ? $app['remote'] : [];
        $appId = (string) ($app['id'] ?? 'unknown');
        $healthUrl = (string) ($remote['health_url'] ?? $app['app_url'] ?? '');
        $expectedStatuses = $remote['expected_http_statuses'] ?? [200, 204, 301, 302];
        if (!is_array($expectedStatuses)) {
            $expectedStatuses = [200, 204, 301, 302];
        }
        $expectedStatuses = array_map('intval', $expectedStatuses);
        $timeoutSeconds = (float) ($remote['timeout_seconds'] ?? 5);
        $auth = $this->buildRemoteAuthHeaders($remote);
        $host = $this->hostFromUrlOrHost($healthUrl);
        $dns = $this->inspectDnsHost($host);
        $http = $this->inspectHttpEndpoint($healthUrl, $timeoutSeconds, $auth['headers']);
        $status = 'success';
        $message = 'Remote dependency is reachable.';
        if ($healthUrl === '' || $host === '') {
            $status = 'failed';
            $message = 'Remote app is missing a valid app_url or remote.health_url.';
        } elseif (($auth['status'] ?? '') === 'failed') {
            $status = 'failed';
            $message = (string) ($auth['message'] ?? 'Remote credential configuration failed.');
        } elseif (($dns['status'] ?? '') !== 'passed') {
            $status = 'failed';
            $message = 'Remote app host cannot be resolved: ' . $host;
        } elseif (($http['status'] ?? '') !== 'passed') {
            $status = 'failed';
            $message = 'Remote app health endpoint is not reachable: ' . $healthUrl;
        } elseif (!in_array((int) ($http['http_status'] ?? 0), $expectedStatuses, true)) {
            $status = 'warning';
            $message = 'Remote app returned unexpected HTTP status: ' . (string) ($http['http_status'] ?? 'unknown');
        }

        return [
            'app_id' => $appId,
            'health_url' => $healthUrl,
            'host' => $host,
            'expected_http_statuses' => $expectedStatuses,
            'credential' => [
                'status' => $auth['status'],
                'type' => $auth['type'],
                'header' => $auth['header'],
                'message' => $auth['message'],
            ],
            'dns' => $dns,
            'http' => $http,
            'status' => $status,
            'message' => $message,
        ];
    }

    private function buildRemoteAuthHeaders(array $remote): array
    {
        $auth = is_array($remote['auth'] ?? null) ? $remote['auth'] : [];
        $type = strtolower((string) ($auth['type'] ?? 'none'));
        if ($type === '' || $type === 'none') {
            return [
                'status' => 'not-configured',
                'type' => 'none',
                'header' => null,
                'headers' => [],
                'message' => 'No remote credential configured.',
            ];
        }

        $token = (string) ($auth['token'] ?? '');
        if ($token === '' || $this->isPlaceholder($token)) {
            $envName = (string) ($auth['token_env'] ?? '');
            if ($envName !== '') {
                $envValue = getenv($envName);
                $token = is_string($envValue) ? trim($envValue) : '';
            }
        }

        if ($token === '') {
            return [
                'status' => 'failed',
                'type' => $type,
                'header' => $auth['header'] ?? ($type === 'bearer' ? 'Authorization' : null),
                'headers' => [],
                'message' => 'Remote credential token is configured but no runtime value was supplied.',
            ];
        }

        if ($type === 'bearer') {
            return [
                'status' => 'configured',
                'type' => 'bearer',
                'header' => 'Authorization',
                'headers' => ['Authorization' => 'Bearer ' . $token],
                'message' => 'Bearer credential will be sent to the remote health endpoint.',
            ];
        }

        if ($type === 'header') {
            $header = (string) ($auth['header'] ?? '');
            if ($header === '') {
                return [
                    'status' => 'failed',
                    'type' => 'header',
                    'header' => null,
                    'headers' => [],
                    'message' => 'remote.auth.header is required for header credentials.',
                ];
            }
            return [
                'status' => 'configured',
                'type' => 'header',
                'header' => $header,
                'headers' => [$header => $token],
                'message' => 'Header credential will be sent to the remote health endpoint.',
            ];
        }

        return [
            'status' => 'failed',
            'type' => $type,
            'header' => null,
            'headers' => [],
            'message' => 'Unsupported remote.auth.type: ' . $type,
        ];
    }

    private function inspectDnsHost(string $host): array
    {
        $result = [
            'host' => $host,
            'addresses' => [],
            'status' => 'not-configured',
        ];
        if ($host === '') {
            return $result;
        }
        $addresses = gethostbynamel($host);
        if (is_array($addresses) && count($addresses) > 0) {
            $result['addresses'] = $addresses;
            $result['status'] = 'passed';
            return $result;
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $result['addresses'] = [$host];
            $result['status'] = 'passed';
            return $result;
        }
        $result['status'] = 'failed';
        return $result;
    }

    private function inspectHttpEndpoint(string $url, float $timeoutSeconds, array $headers = []): array
    {
        $result = [
            'url' => $url,
            'http_status' => null,
            'status' => 'not-configured',
        ];
        if ($url === '') {
            return $result;
        }

        $headerLines = [
            'User-Agent: PBB-Kit-Setup/' . self::VERSION,
        ];
        foreach ($headers as $name => $value) {
            $name = trim((string) $name);
            $value = trim((string) $value);
            if ($name !== '' && $value !== '') {
                $headerLines[] = $name . ': ' . $value;
            }
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => max(1, $timeoutSeconds),
                'ignore_errors' => true,
                'header' => implode("\r\n", $headerLines) . "\r\n",
            ],
            'ssl' => $this->tlsOptions(),
        ]);
        $body = @file_get_contents($url, false, $context);
        $headers = $http_response_header ?? [];
        $status = null;
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches) === 1) {
                $status = (int) $matches[1];
                break;
            }
        }
        $result['http_status'] = $status;
        $result['status'] = ($body !== false && $status !== null) ? 'passed' : 'failed';
        return $result;
    }

    private function runSmokeCheck(array $config, array $context): array
    {
        $checks = [];
        $errors = [];
        $warnings = [];
        foreach ($config['apps'] as $app) {
            if (!is_array($app) || ($app['enabled'] ?? true) === false) {
                continue;
            }

            $check = $this->inspectAppSmokeEndpoint($app);
            $checks[] = $check;
            if (($check['status'] ?? '') === 'failed') {
                $errors[] = (string) ($check['message'] ?? ('Smoke check failed: ' . ($app['id'] ?? 'unknown')));
            } elseif (($check['status'] ?? '') === 'warning') {
                $warnings[] = (string) ($check['message'] ?? ('Smoke check warning: ' . ($app['id'] ?? 'unknown')));
            }
        }

        return [
            'schema_version' => 1,
            'kit_setup_version' => self::VERSION,
            'action' => 'smoke-check',
            'status' => count($errors) > 0 ? 'failed' : (count($warnings) > 0 ? 'warning' : 'success'),
            'started_at' => $context['started_at'],
            'finished_at' => date(DATE_ATOM),
            'run_id' => $context['run_id'],
            'run_dir' => $context['run_dir'],
            'apps' => $checks,
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    private function inspectAppSmokeEndpoint(array $app): array
    {
        $appId = (string) ($app['id'] ?? 'unknown');
        $smoke = is_array($app['smoke'] ?? null) ? $app['smoke'] : [];
        $url = (string) ($smoke['url'] ?? $app['app_url'] ?? '');
        $expectedStatuses = $smoke['expected_http_statuses'] ?? [200, 204, 301, 302];
        if (!is_array($expectedStatuses)) {
            $expectedStatuses = [200, 204, 301, 302];
        }
        $expectedStatuses = array_map('intval', $expectedStatuses);
        $timeoutSeconds = (float) ($smoke['timeout_seconds'] ?? 5);
        $host = $this->hostFromUrlOrHost($url);
        $dns = $this->inspectDnsHost($host);
        $http = $this->inspectHttpEndpoint($url, $timeoutSeconds);

        $status = 'success';
        $message = 'App URL is reachable.';
        if ($url === '' || $host === '') {
            $status = 'failed';
            $message = 'App is missing a valid app_url or smoke.url.';
        } elseif (($dns['status'] ?? '') !== 'passed') {
            $status = 'failed';
            $message = 'App host cannot be resolved: ' . $host;
        } elseif (($http['status'] ?? '') !== 'passed') {
            $status = 'failed';
            $message = 'App URL is not reachable: ' . $url;
        } elseif (!in_array((int) ($http['http_status'] ?? 0), $expectedStatuses, true)) {
            $status = 'warning';
            $message = 'App URL returned unexpected HTTP status: ' . (string) ($http['http_status'] ?? 'unknown');
        }

        return [
            'app_id' => $appId,
            'install_scope' => (string) ($app['install_scope'] ?? 'local'),
            'url' => $url,
            'host' => $host,
            'expected_http_statuses' => $expectedStatuses,
            'dns' => $dns,
            'http' => $http,
            'status' => $status,
            'message' => $message,
        ];
    }

    private function runHubResolve(array $config, string $runDir, array $context): array
    {
        $hubConfig = $config['hub'] ?? [];
        if (!is_array($hubConfig)) {
            throw new RuntimeException('hub config must be an object when using hub-resolve.');
        }

        $baseUrl = rtrim((string) ($hubConfig['base_url'] ?? 'https://hub.pbb.ph'), '/');
        $hubId = (int) ($hubConfig['hub_id'] ?? 0);
        if ($hubId <= 0) {
            throw new RuntimeException('hub.hub_id is required for hub-resolve.');
        }

        $token = $this->getHubToken($hubConfig);
        if ($token === '') {
            throw new RuntimeException('hub.token or hub.token_env is required for hub-resolve.');
        }

        $response = $this->fetchHubRecord($baseUrl, $hubId, $token);
        $hub = $response['hub'];
        $allowedStatuses = $hubConfig['allowed_statuses'] ?? ['planned', 'provisioning', 'active'];
        if (!is_array($allowedStatuses)) {
            $allowedStatuses = ['planned', 'provisioning', 'active'];
        }

        $hubStatus = (string) ($hub['status'] ?? '');
        $statusAllowed = in_array($hubStatus, array_map('strval', $allowedStatuses), true);
        $resolvedConfig = $this->applyHubRecordToConfig($config, $hub, $baseUrl, $hubId);

        $report = [
            'schema_version' => 1,
            'kit_setup_version' => self::VERSION,
            'action' => 'hub-resolve',
            'status' => $statusAllowed ? 'success' : 'failed',
            'started_at' => $context['started_at'],
            'finished_at' => date(DATE_ATOM),
            'run_id' => $context['run_id'],
            'run_dir' => $context['run_dir'],
            'hub' => $this->redactHubForReport($hub),
            'http_status' => $response['http_status'],
            'resolved_config_path' => $this->absolutePath($this->joinPath($runDir, 'hub-resolved-config.json')),
            'warnings' => [],
            'errors' => [],
        ];

        if (!$statusAllowed) {
            $report['errors'][] = 'Hub status is not allowed for setup: ' . $hubStatus;
        }

        return [
            'report' => $report,
            'config' => $resolvedConfig,
        ];
    }

    private function makeFailedHubReport(array $context, string $message): array
    {
        return [
            'schema_version' => 1,
            'kit_setup_version' => self::VERSION,
            'action' => 'hub-resolve',
            'status' => 'failed',
            'started_at' => $context['started_at'],
            'finished_at' => date(DATE_ATOM),
            'run_id' => $context['run_id'],
            'run_dir' => $context['run_dir'],
            'hub' => null,
            'warnings' => [],
            'errors' => [$message],
        ];
    }

    private function getHubToken(array $hubConfig): string
    {
        $token = (string) ($hubConfig['token'] ?? '');
        if ($token !== '' && strpos($token, 'REPLACE_WITH_') !== 0) {
            return $token;
        }

        $tokenEnv = (string) ($hubConfig['token_env'] ?? '');
        if ($tokenEnv === '') {
            return '';
        }

        $value = getenv($tokenEnv);
        return is_string($value) ? trim($value) : '';
    }

    private function fetchHubRecord(string $baseUrl, int $hubId, string $token): array
    {
        $url = $baseUrl . '/api/hubs/' . $hubId;
        $request = $this->httpGet($url, [
            'Accept: application/json',
            'Authorization: Bearer ' . $token,
        ]);
        $body = $request['body'];
        $statusCode = (int) $request['status_code'];
        if ($body === false) {
            $expectedCaFile = $this->expectedBundledCaFile();
            $caDetail = is_file($expectedCaFile)
                ? ' CA bundle: ' . $expectedCaFile
                : ' CA bundle missing: ' . $expectedCaFile;
            $transport = (string) ($request['transport'] ?? 'unknown');
            $detail = (string) ($request['error'] ?? '');
            throw new RuntimeException('Unable to call Hub API via ' . $transport . ': ' . $url . '. ' . $detail . $caDetail);
        }

        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            throw new RuntimeException('Hub API returned non-JSON response from ' . $url);
        }

        if ($statusCode < 200 || $statusCode >= 300 || ($payload['status'] ?? false) !== true) {
            $message = (string) ($payload['error'] ?? 'Hub API request failed.');
            throw new RuntimeException("Hub API request failed ({$statusCode}): {$message}");
        }

        $hub = $payload['data']['hub'] ?? null;
        if (!is_array($hub)) {
            throw new RuntimeException('Hub API response does not include data.hub.');
        }

        return [
            'http_status' => $statusCode,
            'hub' => $hub,
        ];
    }

    private function httpGet(string $url, array $headers): array
    {
        $curlResult = null;
        if (function_exists('curl_init')) {
            $curlResult = $this->httpGetWithCurl($url, $headers);
            if ($curlResult['body'] !== false) {
                return $curlResult;
            }
        }

        $streamResult = $this->httpGetWithStream($url, $headers);
        if ($streamResult['body'] === false && is_array($curlResult)) {
            $streamResult['error'] = trim((string) $streamResult['error'] . ' cURL fallback error: ' . (string) $curlResult['error']);
        }
        return $streamResult;
    }

    private function httpGetWithCurl(string $url, array $headers): array
    {
        $handle = curl_init($url);
        if ($handle === false) {
            return [
                'transport' => 'curl',
                'body' => false,
                'status_code' => 0,
                'error' => 'Unable to initialize cURL.',
            ];
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'PBB-Kit-Setup/' . self::VERSION,
        ];
        $caFile = $this->bundledCaFile();
        if ($caFile !== '') {
            $options[CURLOPT_CAINFO] = $caFile;
        }
        curl_setopt_array($handle, $options);

        $body = curl_exec($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        return [
            'transport' => 'curl',
            'body' => is_string($body) ? $body : false,
            'status_code' => $statusCode,
            'error' => $error,
        ];
    }

    private function httpGetWithStream(string $url, array $headers): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => $headers,
                'ignore_errors' => true,
                'timeout' => 30,
            ],
            'ssl' => $this->tlsOptions(),
        ]);

        $body = @file_get_contents($url, false, $context);
        $statusCode = $this->extractHttpStatusCode($http_response_header ?? []);
        $lastError = error_get_last();
        $detail = is_array($lastError) && isset($lastError['message'])
            ? (string) $lastError['message']
            : '';

        return [
            'transport' => 'stream',
            'body' => $body,
            'status_code' => $statusCode,
            'error' => $detail,
        ];
    }

    private function extractHttpStatusCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', (string) $header, $matches)) {
                return (int) $matches[1];
            }
        }
        return 0;
    }

    private function tlsOptions(): array
    {
        $options = [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ];
        $caFile = $this->bundledCaFile();
        if ($caFile !== '') {
            $options['cafile'] = $caFile;
        }
        return $options;
    }

    private function bundledCaFile(): string
    {
        $path = $this->expectedBundledCaFile();
        return is_file($path) ? $path : '';
    }

    private function expectedBundledCaFile(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'certs' . DIRECTORY_SEPARATOR . 'cacert.pem';
    }

    private function applyHubRecordToConfig(array $config, array $hub, string $baseUrl, int $hubId): array
    {
        $relayHubId = (string) ($hub['relay_hub_id'] ?? '');
        if ($relayHubId === '') {
            $relayHubId = (string) ($hub['id'] ?? $hubId);
        }

        $config['kit']['hub_record_id'] = (int) ($hub['id'] ?? $hubId);
        $config['kit']['node_id'] = $relayHubId;
        $config['kit']['node_name'] = (string) ($hub['name'] ?? '');
        $config['kit']['deployment'] = (string) ($hub['deployment'] ?? '');
        $config['kit']['domain'] = (string) ($hub['domain'] ?? '');
        $config['kit']['location_codes'] = [
            'country_code' => $hub['country_code'] ?? null,
            'reg_code' => $hub['reg_code'] ?? null,
            'prov_code' => $hub['prov_code'] ?? null,
            'citymun_code' => $hub['citymun_code'] ?? null,
            'brgy_code' => $hub['brgy_code'] ?? null,
        ];

        if (!isset($config['shared']) || !is_array($config['shared'])) {
            $config['shared'] = [];
        }

        $config['shared']['hub'] = [
            'base_url' => $baseUrl,
            'hub_id' => (int) ($hub['id'] ?? $hubId),
            'relay_hub_id' => $relayHubId,
            'name' => (string) ($hub['name'] ?? ''),
            'code' => $hub['code'] ?? null,
            'deployment' => $hub['deployment'] ?? null,
            'domain' => $hub['domain'] ?? null,
            'status' => $hub['status'] ?? null,
            'country_code' => $hub['country_code'] ?? null,
            'reg_code' => $hub['reg_code'] ?? null,
            'prov_code' => $hub['prov_code'] ?? null,
            'citymun_code' => $hub['citymun_code'] ?? null,
            'brgy_code' => $hub['brgy_code'] ?? null,
            'uplinks' => $hub['uplinks'] ?? [],
            'sources' => $hub['sources'] ?? [],
        ];

        return $config;
    }

    private function redactHubForReport(array $hub): array
    {
        if (isset($hub['token']) && is_array($hub['token'])) {
            $hub['token'] = [
                'has_token' => (bool) ($hub['token']['has_token'] ?? false),
                'is_active' => (bool) ($hub['token']['is_active'] ?? false),
                'last_used_at' => $hub['token']['last_used_at'] ?? null,
                'revoked_at' => $hub['token']['revoked_at'] ?? null,
                'issued_at' => $hub['token']['issued_at'] ?? null,
            ];
        }
        return $hub;
    }

    private function discoverApps(array $config): array
    {
        $apps = [];
        foreach ($config['apps'] as $appConfig) {
            if (($appConfig['enabled'] ?? true) === false) {
                continue;
            }

            $id = (string) ($appConfig['id'] ?? '');
            if ($id === '') {
                throw new RuntimeException('Each app entry must include id.');
            }
            if (isset($apps[$id])) {
                throw new RuntimeException('Duplicate app id in kit config: ' . $id);
            }

            $scope = (string) ($appConfig['install_scope'] ?? 'local');
            if ($scope !== 'local') {
                continue;
            }

            $releasePath = (string) ($appConfig['release_path'] ?? '');
            if ($releasePath === '' || !is_dir($releasePath)) {
                throw new RuntimeException("Release path for {$id} must be an extracted release directory: {$releasePath}");
            }

            $releaseJsonPath = $this->joinPath($releasePath, 'release.json');
            $release = $this->readJsonFile($releaseJsonPath);
            foreach (['app', 'version', 'installer'] as $field) {
                if (!array_key_exists($field, $release)) {
                    throw new RuntimeException("Release {$releaseJsonPath} is missing {$field}.");
                }
            }
            if ((string) $release['app'] !== $id) {
                throw new RuntimeException("Release app id mismatch for {$id}; release.json says {$release['app']}.");
            }

            $unattended = (string) ($release['installer']['unattended'] ?? '');
            if ($unattended === '') {
                throw new RuntimeException("Release {$id} must declare installer.unattended.");
            }
            $unattendedPath = $this->joinPath($releasePath, $unattended);
            if (!is_file($unattendedPath)) {
                throw new RuntimeException("Release {$id} unattended installer does not exist: {$unattendedPath}");
            }

            $status = (string) ($release['installer']['status'] ?? '');
            $statusPath = $status !== '' ? $this->joinPath($releasePath, $status) : '';

            $apps[$id] = [
                'id' => $id,
                'config' => $appConfig,
                'release_path' => $this->absolutePath($releasePath),
                'release' => $release,
                'unattended_path' => $this->absolutePath($unattendedPath),
                'status_path' => $statusPath !== '' && is_file($statusPath) ? $this->absolutePath($statusPath) : null,
                'depends_on' => array_values($appConfig['depends_on'] ?? []),
            ];
        }

        return $apps;
    }

    private function orderApps(array $apps): array
    {
        $ordered = [];
        $visiting = [];
        $visited = [];

        $visit = function (string $id) use (&$visit, &$apps, &$ordered, &$visiting, &$visited): void {
            if (isset($visited[$id])) {
                return;
            }
            if (isset($visiting[$id])) {
                throw new RuntimeException('Cyclic app dependency detected at ' . $id);
            }
            if (!isset($apps[$id])) {
                throw new RuntimeException('Unknown app dependency: ' . $id);
            }

            $visiting[$id] = true;
            foreach ($apps[$id]['depends_on'] as $dependencyId) {
                $dependencyId = (string) $dependencyId;
                if (!isset($apps[$dependencyId])) {
                    continue;
                }
                $visit($dependencyId);
            }
            unset($visiting[$id]);
            $visited[$id] = true;
            $ordered[] = $apps[$id];
        };

        foreach (array_keys($apps) as $id) {
            $visit((string) $id);
        }

        return $ordered;
    }

    private function filterOrderedApps(array $orderedApps, string $appFilter): array
    {
        $filtered = array_values(array_filter($orderedApps, static function (array $app) use ($appFilter): bool {
            return (string) ($app['id'] ?? '') === $appFilter;
        }));
        if (count($filtered) === 0) {
            throw new RuntimeException('Requested --app was not found among enabled local apps: ' . $appFilter);
        }
        return $filtered;
    }

    private function planApp(array $app, array $kitConfig, string $runDir, string $runId): array
    {
        $appConfigPath = $this->joinPath($runDir, 'apps' . DIRECTORY_SEPARATOR . $app['id'] . '.config.json');
        $appReportPath = $this->joinPath($runDir, 'apps' . DIRECTORY_SEPARATOR . $app['id'] . '.report.json');
        $generatedConfig = $this->buildAppConfig($app, $kitConfig, $runId);
        $this->writeJsonFile($appConfigPath, $generatedConfig);

        return [
            'id' => $app['id'],
            'name' => $app['release']['name'] ?? $app['id'],
            'version' => $app['release']['version'],
            'status' => 'planned',
            'release_path' => $app['release_path'],
            'installer' => $app['unattended_path'],
            'config_path' => $this->absolutePath($appConfigPath),
            'report_path' => $this->absolutePath($appReportPath),
            'depends_on' => $app['depends_on'],
            'checksum' => $this->verifyChecksums($app),
        ];
    }

    private function runAppInstaller(array $app, array $kitConfig, string $runDir, string $runId, string $action): array
    {
        $plan = $this->planApp($app, $kitConfig, $runDir, $runId);
        $phpBinary = (string) $kitConfig['runtime']['php_binary'];
        $mode = $action === 'preflight' ? 'preflight' : (string) ($app['config']['mode'] ?? 'fresh');

        $command = [
            $phpBinary,
            $app['unattended_path'],
            '--mode',
            $mode,
            '--config',
            $plan['config_path'],
            '--report',
            $plan['report_path'],
        ];

        $process = $this->runProcess($command, $app['release_path']);
        $appReport = is_file($plan['report_path'])
            ? $this->readJsonFile($plan['report_path'])
            : null;

        $status = $process['exit_code'] === 0 ? 'success' : 'failed';
        if (is_array($appReport) && ($appReport['status'] ?? '') === 'warning') {
            $status = 'warning';
        }

        $statusResult = $this->runAppStatus($app, $kitConfig);
        $manifest = $this->readAppArtifact($app, 'install_manifest');

        return array_merge($plan, [
            'status' => $status,
            'mode' => $mode,
            'exit_code' => $process['exit_code'],
            'stdout' => $process['stdout'],
            'stderr' => $process['stderr'],
            'app_report_status' => is_array($appReport) ? ($appReport['status'] ?? null) : null,
            'status_command' => $statusResult,
            'manifest' => $manifest,
            'services' => is_array($appReport) ? ($appReport['services'] ?? []) : [],
        ]);
    }

    private function runAppPopulationTools(array $app, array $kitConfig, string $runDir, string $runId): array
    {
        $plan = $this->planApp($app, $kitConfig, $runDir, $runId);
        $tools = $this->normalizeInstallerTools($app['release']['installer']['tools'] ?? []);
        $enabledTools = [];
        foreach ($tools as $name => $tool) {
            if (strpos((string) $name, 'populate') === false) {
                continue;
            }
            $sectionPath = (string) ($tool['config_section'] ?? '');
            if ($sectionPath === '') {
                $sectionPath = $this->inferPopulationSection($plan['config_path'], $app['id']);
                if ($sectionPath === '') {
                    continue;
                }
                $tool['config_section'] = $sectionPath;
            }
            if (!$this->isPopulationEnabled($plan['config_path'], $sectionPath)) {
                continue;
            }
            $enabledTools[$name] = $tool;
        }

        if (count($enabledTools) === 0) {
            return array_merge($plan, [
                'status' => 'skipped',
                'mode' => 'populate',
                'population_tools' => [],
                'message' => 'No enabled population tools declared for this app.',
            ]);
        }

        $failed = false;
        $toolResults = [];
        foreach ($enabledTools as $name => $tool) {
            $toolResults[] = $this->runPopulationTool($app, $kitConfig, $runDir, $runId, (string) $name, $tool, $plan['config_path']);
            $last = $toolResults[count($toolResults) - 1];
            if (($last['status'] ?? '') !== 'success') {
                $failed = true;
            }
        }

        return array_merge($plan, [
            'status' => $failed ? 'failed' : 'success',
            'mode' => 'populate',
            'population_tools' => $toolResults,
        ]);
    }

    private function runPopulationTool(array $app, array $kitConfig, string $runDir, string $runId, string $name, array $tool, string $appConfigPath): array
    {
        $relativePath = (string) ($tool['path'] ?? '');
        $toolPath = $relativePath !== '' ? $this->joinPath($app['release_path'], $relativePath) : '';
        $reportPath = $this->joinPath($runDir, 'apps' . DIRECTORY_SEPARATOR . $app['id'] . '.' . $name . '.report.json');
        if ($toolPath === '' || !is_file($toolPath)) {
            return [
                'name' => $name,
                'status' => 'failed',
                'message' => 'Population tool not found: ' . $toolPath,
            ];
        }

        $config = $this->readJsonFile($appConfigPath);
        if (($tool['standard_contract'] ?? true) === false) {
            return $this->runCompatibilityPopulationTool($app, $kitConfig, $name, $tool, $config, $reportPath, $toolPath);
        }

        $mode = (string) ($config['mode'] ?? 'initial');
        if ($mode === 'fresh' || $mode === 'preflight') {
            $mode = 'initial';
        }

        $command = [
            (string) $kitConfig['runtime']['php_binary'],
            $toolPath,
            '--mode',
            $mode,
            '--config',
            $appConfigPath,
            '--report',
            $reportPath,
        ];

        if ($this->hasDryRunEnabled($config, (string) ($tool['config_section'] ?? ''))) {
            $command[] = '--dry-run';
        }

        $process = $this->runProcess($command, $app['release_path']);
        $report = is_file($reportPath) ? $this->readJsonFile($reportPath) : null;

        return [
            'name' => $name,
            'path' => $this->absolutePath($toolPath),
            'report_path' => $this->absolutePath($reportPath),
            'status' => $process['exit_code'] === 0 ? 'success' : 'failed',
            'exit_code' => $process['exit_code'],
            'stdout' => $process['stdout'],
            'stderr' => $process['stderr'],
            'report_status' => is_array($report) ? ($report['status'] ?? null) : null,
        ];
    }

    private function runCompatibilityPopulationTool(array $app, array $kitConfig, string $name, array $tool, array $config, string $reportPath, string $toolPath): array
    {
        $sectionPath = (string) ($tool['config_section'] ?? '');
        $section = $sectionPath !== '' ? $this->getNestedValue($config, $sectionPath) : null;
        if (!is_array($section)) {
            $section = [];
        }

        $command = [
            (string) $kitConfig['runtime']['php_binary'],
            $toolPath,
            '--base-url',
            (string) ($config['app']['app_url'] ?? $app['config']['app_url'] ?? ''),
            '--report',
            $reportPath,
        ];

        $populate = is_array($section['populate'] ?? null) ? $section['populate'] : $section;
        $optionMap = [
            'source_geojson' => 'source-geojson',
            'brgy_code' => 'brgy-code',
            'barangay' => 'barangay',
            'city' => 'city',
            'bbox' => 'bbox',
            'center' => 'center',
            'radius_km' => 'radius-km',
            'zooms' => 'zooms',
            'types' => 'types',
            'max_tiles' => 'max-tiles',
            'limit' => 'limit',
            'timeout' => 'timeout',
        ];

        foreach ($optionMap as $key => $flag) {
            if (!array_key_exists($key, $populate) || $populate[$key] === null || $populate[$key] === '') {
                continue;
            }
            $command[] = '--' . $flag;
            $command[] = is_array($populate[$key]) ? implode(',', $populate[$key]) : (string) $populate[$key];
        }

        if (($populate['dry_run'] ?? true) !== false) {
            $command[] = '--dry-run';
        }

        $process = $this->runProcess($command, $app['release_path']);
        $report = is_file($reportPath) ? $this->readJsonFile($reportPath) : null;

        return [
            'name' => $name,
            'path' => $this->absolutePath($toolPath),
            'contract' => 'compatibility',
            'report_path' => $this->absolutePath($reportPath),
            'status' => $process['exit_code'] === 0 ? 'success' : 'failed',
            'exit_code' => $process['exit_code'],
            'stdout' => $process['stdout'],
            'stderr' => $process['stderr'],
            'report_status' => is_array($report) ? ($report['status'] ?? null) : null,
        ];
    }

    private function buildAppConfig(array $app, array $kitConfig, string $runId): array
    {
        $appConfig = $app['config'];
        $dependencies = $kitConfig['shared']['dependencies'] ?? [];
        $database = $appConfig['database'] ?? ($kitConfig['shared']['database'] ?? null);

        $result = [
            'schema_version' => 1,
            'mode' => (string) ($appConfig['mode'] ?? 'fresh'),
            'kit' => [
                'run_id' => $runId,
                'node_id' => (string) $kitConfig['kit']['node_id'],
                'operator' => (string) ($kitConfig['kit']['operator'] ?? ''),
                'timezone' => (string) $kitConfig['kit']['timezone'],
            ],
            'app' => [
                'install_path' => (string) $appConfig['install_path'],
                'public_path' => (string) ($appConfig['public_path'] ?? ''),
                'app_url' => (string) $appConfig['app_url'],
                'app_env' => (string) ($appConfig['app_env'] ?? 'production'),
                'app_debug' => (bool) ($appConfig['app_debug'] ?? false),
            ],
            'services' => [
                'target_os' => PHP_OS_FAMILY === 'Windows' ? 'windows' : 'linux',
                'manager' => (string) ($kitConfig['runtime']['service_manager'] ?? 'manual'),
                'startup_mode' => (string) ($appConfig['startup_mode'] ?? 'automatic'),
                'registration_mode' => (string) ($kitConfig['runtime']['service_registration_mode'] ?? 'generate'),
            ],
            'dependencies' => $dependencies,
            'secrets' => $kitConfig['shared']['secrets'] ?? ['policy' => 'app-generated'],
            'options' => [
                'run_migrations' => true,
                'seed_initial_data' => true,
                'write_env' => true,
                'cache_config' => true,
                'validate_after_install' => true,
            ],
        ];

        foreach (['session_domain', 'force_https'] as $appOption) {
            if (array_key_exists($appOption, $appConfig)) {
                $result['app'][$appOption] = $appConfig[$appOption];
            }
        }

        if (is_array($database)) {
            $result['database'] = $database;
        }

        foreach (['admin', 'config'] as $key) {
            if (isset($appConfig[$key]) && is_array($appConfig[$key])) {
                if ($key === 'config') {
                    foreach ($appConfig[$key] as $section => $value) {
                        $result[(string) $section] = $value;
                    }
                } else {
                    $result[$key] = $this->resolveAdminConfig($appConfig[$key]);
                }
            }
        }

        if (!isset($result['admin']) && isset($kitConfig['shared']['admin']) && is_array($kitConfig['shared']['admin'])) {
            $result['admin'] = $this->resolveAdminConfig($kitConfig['shared']['admin']);
        }

        return $result;
    }

    private function resolveAdminConfig(array $admin): array
    {
        $passwordEnv = (string) ($admin['password_env'] ?? '');
        if ($passwordEnv !== '') {
            $password = getenv($passwordEnv);
            if (is_string($password) && $password !== '') {
                $admin['password'] = $password;
            }
        }

        unset($admin['password_env']);
        return $admin;
    }

    private function verifyChecksums(array $app): array
    {
        return $this->verifyChecksumsForPath((string) $app['release_path']);
    }

    private function verifyChecksumsForPath(string $releasePath): array
    {
        $checksumPath = $this->joinPath($releasePath, 'checksums.sha256');
        if (!is_file($checksumPath)) {
            return [
                'status' => 'warning',
                'path' => $checksumPath,
                'message' => 'checksums.sha256 not found.',
                'checked_count' => 0,
                'failed' => [],
            ];
        }

        $lines = file($checksumPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new RuntimeException('Unable to read checksum file: ' . $checksumPath);
        }

        $checked = [];
        $failed = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            if (!preg_match('/^([a-fA-F0-9]{64})\s+\*?(.+)$/', $line, $matches)) {
                $failed[] = ['path' => $line, 'message' => 'Invalid checksum line.'];
                continue;
            }
            $expected = strtolower($matches[1]);
            $relativePath = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $matches[2]));
            $path = $this->joinPath($releasePath, $relativePath);
            if (!is_file($path)) {
                $failed[] = ['path' => $relativePath, 'message' => 'File listed in checksum does not exist.'];
                continue;
            }
            $actual = hash_file('sha256', $path);
            $entry = [
                'path' => $relativePath,
                'expected' => $expected,
                'actual' => $actual,
                'status' => $actual === $expected ? 'passed' : 'failed',
            ];
            $checked[] = $entry;
            if ($entry['status'] !== 'passed') {
                $failed[] = $entry;
            }
        }

        return [
            'status' => count($failed) === 0 ? 'passed' : 'failed',
            'path' => $this->absolutePath($checksumPath),
            'checked_count' => count($checked),
            'failed' => $failed,
        ];
    }

    private function runAppStatus(array $app, array $kitConfig): ?array
    {
        if (!is_string($app['status_path']) || $app['status_path'] === '') {
            return null;
        }

        $command = [
            (string) $kitConfig['runtime']['php_binary'],
            $app['status_path'],
        ];

        $installPath = (string) ($app['config']['install_path'] ?? '');
        if ($installPath !== '') {
            $command[] = '--install-path';
            $command[] = $installPath;
        }

        $process = $this->runProcess($command, $app['release_path']);
        $decoded = json_decode($process['stdout'], true);

        return [
            'path' => $app['status_path'],
            'exit_code' => $process['exit_code'],
            'status' => $process['exit_code'] === 0 ? 'success' : 'failed',
            'stdout_json' => is_array($decoded) ? $decoded : null,
            'stderr' => $process['stderr'],
        ];
    }

    private function readAppArtifact(array $app, string $artifactKey): ?array
    {
        $relativePath = (string) ($app['release']['artifacts'][$artifactKey] ?? '');
        if ($relativePath === '') {
            return null;
        }

        $path = $this->joinPath($app['release_path'], $relativePath);
        if (!is_file($path)) {
            return [
                'path' => $this->absolutePath($path),
                'exists' => false,
            ];
        }

        return [
            'path' => $this->absolutePath($path),
            'exists' => true,
            'json' => $this->readJsonFile($path),
        ];
    }

    private function normalizeInstallerTools($tools): array
    {
        if (!is_array($tools)) {
            return [];
        }

        $normalized = [];
        foreach ($tools as $name => $tool) {
            if (is_string($tool)) {
                $normalized[(string) $name] = [
                    'path' => $tool,
                    'config_section' => '',
                    'required' => false,
                    'standard_contract' => false,
                ];
            } elseif (is_array($tool)) {
                $tool['standard_contract'] = $tool['standard_contract'] ?? true;
                $normalized[(string) $name] = $tool;
            }
        }

        return $normalized;
    }

    private function inferPopulationSection(string $configPath, string $appId): string
    {
        $config = $this->readJsonFile($configPath);
        $candidates = [];
        $shortId = preg_replace('/^pbb[-_]/', '', strtolower($appId));
        if (is_string($shortId) && $shortId !== '') {
            $candidates[] = $shortId;
        }

        foreach ($config as $key => $value) {
            if (is_array($value) && isset($value['populate']) && is_array($value['populate'])) {
                $candidates[] = (string) $key;
            }
        }

        foreach (array_values(array_unique($candidates)) as $candidate) {
            $section = $this->getNestedValue($config, $candidate . '.populate');
            if (is_array($section) && ($section['enabled'] ?? false) === true) {
                return $candidate . '.populate';
            }
        }

        return '';
    }

    private function isPopulationEnabled(string $configPath, string $sectionPath): bool
    {
        $config = $this->readJsonFile($configPath);
        $section = $this->getNestedValue($config, $sectionPath);
        if (!is_array($section)) {
            return false;
        }
        return ($section['enabled'] ?? false) === true;
    }

    private function hasDryRunEnabled(array $config, string $sectionPath): bool
    {
        if ($sectionPath === '') {
            return true;
        }

        $section = $this->getNestedValue($config, $sectionPath);
        if (!is_array($section)) {
            return true;
        }

        return ($section['dry_run'] ?? ($section['options']['dry_run'] ?? true)) !== false;
    }

    private function getNestedValue(array $data, string $path)
    {
        $current = $data;
        foreach (explode('.', $path) as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return null;
            }
            $current = $current[$part];
        }
        return $current;
    }

    private function runProcess(array $command, string $cwd): array
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $commandLine = implode(' ', array_map([$this, 'escapeArg'], $command));
        $process = proc_open($commandLine, $descriptorSpec, $pipes, $cwd);
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start process: ' . $commandLine);
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [
            'command' => $commandLine,
            'exit_code' => $exitCode,
            'stdout' => trim((string) $stdout),
            'stderr' => trim((string) $stderr),
        ];
    }

    private function escapeArg(string $arg): string
    {
        return escapeshellarg($arg);
    }

    private function readJsonFile(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('JSON file does not exist: ' . $path);
        }
        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException('Unable to read JSON file: ' . $path);
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid JSON file: ' . $path . ' - ' . json_last_error_msg());
        }
        return $data;
    }

    private function readOptionalJson(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        try {
            return $this->readJsonFile($path);
        } catch (Throwable $e) {
            return null;
        }
    }

    private function writeJsonFile(string $path, array $data): void
    {
        $this->ensureDirectory(dirname($path));
        if (array_key_exists('kit_setup_version', $data) && !array_key_exists('kit_setup', $data)) {
            $data['kit_setup'] = $this->kitSetupMetadata();
        }
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Unable to encode JSON for ' . $path);
        }
        if (file_put_contents($path, $json . PHP_EOL) === false) {
            throw new RuntimeException('Unable to write JSON file: ' . $path);
        }
    }

    private function kitSetupMetadata(): array
    {
        return [
            'milestone' => self::MILESTONE,
            'version' => self::VERSION,
            'display_version' => self::DISPLAY_VERSION,
        ];
    }

    private function recordCheckpoint(string $runDir, array $context, array $report, string $reportPath): void
    {
        $checkpoints = $this->readCheckpoints($runDir);
        $action = (string) ($report['action'] ?? $context['action'] ?? 'unknown');
        $entry = [
            'action' => $action,
            'status' => (string) ($report['status'] ?? 'unknown'),
            'updated_at' => date(DATE_ATOM),
            'started_at' => $report['started_at'] ?? $context['started_at'] ?? null,
            'finished_at' => $report['finished_at'] ?? null,
            'report_path' => $this->absolutePath($reportPath),
            'run_id' => $context['run_id'] ?? null,
            'app_filter' => $context['app_filter'] ?? ($report['app_filter'] ?? null),
        ];
        if (isset($report['apps']) && is_array($report['apps'])) {
            $entry['apps'] = array_map(static function (array $app): array {
                return [
                    'id' => $app['id'] ?? $app['app_id'] ?? null,
                    'status' => $app['status'] ?? null,
                ];
            }, $report['apps']);
        }
        $checkpoints['actions'][$action] = $entry;
        $checkpoints['latest_action'] = $action;
        $checkpoints['updated_at'] = $entry['updated_at'];
        $this->writeJsonFile($this->joinPath($runDir, 'checkpoints.json'), $checkpoints);
    }

    private function readCheckpoints(string $runDir): array
    {
        $path = $this->joinPath($runDir, 'checkpoints.json');
        if (!is_file($path)) {
            return [
                'schema_version' => 1,
                'run_dir' => $this->absolutePath($runDir),
                'updated_at' => null,
                'latest_action' => null,
                'actions' => [],
            ];
        }
        try {
            $data = $this->readJsonFile($path);
        } catch (Throwable $e) {
            return [
                'schema_version' => 1,
                'run_dir' => $this->absolutePath($runDir),
                'updated_at' => null,
                'latest_action' => null,
                'actions' => [],
                'warning' => 'Unable to read checkpoints: ' . $e->getMessage(),
            ];
        }
        if (!isset($data['actions']) || !is_array($data['actions'])) {
            $data['actions'] = [];
        }
        return $data;
    }

    private function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }
        if (!mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException('Unable to create directory: ' . $path);
        }
    }

    private function removeDirectory(string $path): void
    {
        $real = realpath($path);
        if ($real === false || !is_dir($real)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $itemPath = $item->getPathname();
            if ($item->isDir()) {
                if (!rmdir($itemPath)) {
                    throw new RuntimeException('Unable to remove directory: ' . $itemPath);
                }
            } elseif (!unlink($itemPath)) {
                throw new RuntimeException('Unable to remove file: ' . $itemPath);
            }
        }

        if (!rmdir($real)) {
            throw new RuntimeException('Unable to remove directory: ' . $real);
        }
    }

    private function copyDirectory(string $source, string $target): void
    {
        if (!is_dir($source)) {
            throw new RuntimeException('Copy source is not a directory: ' . $source);
        }
        $this->ensureDirectory($target);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen($source) + 1);
            $destination = $this->joinPath($target, $relativePath);
            if ($item->isDir()) {
                $this->ensureDirectory($destination);
            } else {
                $this->ensureDirectory(dirname($destination));
                if (!copy($item->getPathname(), $destination)) {
                    throw new RuntimeException('Unable to copy package file: ' . $item->getPathname());
                }
            }
        }
    }

    private function joinPath(string ...$parts): string
    {
        $path = '';
        foreach ($parts as $part) {
            $part = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $part);
            if ($path === '') {
                $path = rtrim($part, DIRECTORY_SEPARATOR);
                continue;
            }
            $path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($part, DIRECTORY_SEPARATOR);
        }
        return $path;
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }
        return preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1 || str_starts_with($path, '/') || str_starts_with($path, '\\\\');
    }

    private function isPathInsideAnyRoot(string $path, array $roots): bool
    {
        $normalizedPath = $this->normalizePathForCompare($path);
        foreach ($roots as $root) {
            if (!is_string($root) || $root === '') {
                continue;
            }
            $normalizedRoot = rtrim($this->normalizePathForCompare($root), '/');
            if ($normalizedRoot !== '' && ($normalizedPath === $normalizedRoot || str_starts_with($normalizedPath, $normalizedRoot . '/'))) {
                return true;
            }
        }
        return false;
    }

    private function normalizePathForCompare(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $real = realpath($path);
        if ($real !== false) {
            $path = str_replace('\\', '/', $real);
        }
        return rtrim(strtolower($path), '/');
    }

    private function absolutePath(string $path): string
    {
        $resolved = realpath($path);
        return $resolved !== false ? $resolved : $path;
    }

    private function makeRunId(): string
    {
        return 'kit_' . date('Ymd_His');
    }

    private function writeLine(string $line, bool $stderr = false): void
    {
        $stream = $stderr ? STDERR : STDOUT;
        fwrite($stream, $line . PHP_EOL);
        fflush($stream);
    }
}
