<?php

declare(strict_types=1);

final class KitSetupRunner
{
    private const VERSION = '0.1.120';
    private const MILESTONE = 1;
    private const DISPLAY_VERSION = 'v1-0.1.120';
    private const SERVICE_WRAPPER = 'winsw';
    private ?string $progressFile = null;

    public function main(array $argv): int
    {
        $action = 'plan';
        $runDir = '';
        $runId = '';
        $context = null;

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
            if (!in_array($action, ['detect', 'hub-resolve', 'prepare-packages', 'prepare-package-worker', 'dns-plan', 'dns-apply', 'dns-client-apply', 'dns-verify', 'firewall-apply', 'service-plan', 'service-start', 'service-stop', 'service-verify', 'ssl-plan', 'ssl-apply', 'remote-check', 'smoke-check', 'stage-report', 'finish-report', 'plan', 'preflight', 'install', 'populate'], true)) {
                throw new InvalidArgumentException('Unsupported --action. Use detect, hub-resolve, prepare-packages, dns-plan, dns-apply, dns-client-apply, dns-verify, firewall-apply, service-plan, service-start, service-stop, service-verify, ssl-plan, ssl-apply, remote-check, smoke-check, stage-report, finish-report, plan, preflight, install, or populate.');
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
            $appFilter = (string) ($options['app'] ?? '');
            if ($appFilter !== '') {
                $context['app_filter'] = $appFilter;
            }

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
                $result = $this->runHubResolve($config, $runDir, $context);
                $config = $result['config'];
                $this->writeJsonFile($this->joinPath($runDir, 'hub-resolved-config.json'), $config);
            } else {
                $config = $this->applyResolvedHubConfigFromRunDir($config, $runDir);
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
                $progressPath = (string) ($options['progress-file'] ?? '');
                if ($appId === '' || $reportPath === '') {
                    throw new InvalidArgumentException('prepare-package-worker requires --app and --worker-report.');
                }
                if ($progressPath !== '') {
                    $this->progressFile = $progressPath;
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

            if ($action === 'firewall-apply') {
                $report = $this->runFirewallApply($config, $context);
                $reportPath = $this->joinPath($runDir, 'firewall-apply.json');
                $this->writeJsonFile($reportPath, $report);
                $this->recordCheckpoint($runDir, $context, $report, $reportPath);
                $this->writeLine('Firewall apply report: ' . $this->joinPath($runDir, 'firewall-apply.json'));
                return in_array($report['status'], ['success', 'skipped'], true) ? 0 : 1;
            }

            if ($action === 'service-plan') {
                $report = $this->runRuntimeServicePlan($config, $context);
                $reportPath = $this->joinPath($runDir, 'service-plan.json');
                $this->writeJsonFile($reportPath, $report);
                $this->recordCheckpoint($runDir, $context, $report, $reportPath);
                $this->writeLine('Runtime service plan: ' . $this->joinPath($runDir, 'service-plan.json'));
                return $report['status'] === 'failed' ? 1 : 0;
            }

            if ($action === 'service-start') {
                $report = $this->runRuntimeServiceStart($config, $context);
                $reportPath = $this->joinPath($runDir, 'service-start.json');
                $this->writeJsonFile($reportPath, $report);
                $this->recordCheckpoint($runDir, $context, $report, $reportPath);
                $this->writeLine('Runtime service start: ' . $this->joinPath($runDir, 'service-start.json'));
                return $report['status'] === 'failed' ? 1 : 0;
            }

            if ($action === 'service-stop') {
                $report = $this->runRuntimeServiceStop($config, $context);
                $reportPath = $this->joinPath($runDir, 'service-stop.json');
                $this->writeJsonFile($reportPath, $report);
                $this->recordCheckpoint($runDir, $context, $report, $reportPath);
                $this->writeLine('Runtime service stop: ' . $this->joinPath($runDir, 'service-stop.json'));
                return $report['status'] === 'failed' ? 1 : 0;
            }

            if ($action === 'service-verify') {
                $report = $this->runRuntimeServiceVerify($config, $context);
                $reportPath = $this->joinPath($runDir, 'service-verify.json');
                $this->writeJsonFile($reportPath, $report);
                $this->recordCheckpoint($runDir, $context, $report, $reportPath);
                $this->writeLine('Runtime service verification: ' . $this->joinPath($runDir, 'service-verify.json'));
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
                if (($config['data_prep']['readiness_check'] ?? false) === true) {
                    $config = $this->applyInstallStateToDataPrepConfig($config, $this->assertDataPrepAllowed());
                }
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
                if (($report['status'] ?? '') === 'success') {
                    $statePath = $this->installStatePath();
                    $this->writeJsonFile($statePath, $this->buildInstallState($config, $report, $context, $reportPath));
                    $report['install_state_path'] = $this->absolutePath($statePath);
                    $this->writeJsonFile($reportPath, $report);
                }
                $this->recordCheckpoint($runDir, $context, $report, $reportPath);
                $this->writeLine('Finish report: ' . $this->joinPath($runDir, 'finish-report.json'));
                return $report['status'] === 'failed' ? 1 : 0;
            }

            $requireInstaller = true;
            if ($action === 'populate') {
                $installState = $this->assertDataPrepAllowed();
                $config = $this->applyInstallStateToDataPrepConfig($config, $installState);
                $requireInstaller = false;
            }
            $config = $this->applySharedInstallDefaultsToKitConfig($config);

            $secretResult = $this->resolveKitSecrets($config, $runDir);
            $config = $secretResult['config'];

            $apps = $this->discoverApps($config, $requireInstaller);
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
                $reportPath = $this->kitActionReportPath($runDir, $action);
                $this->writeJsonFile($reportPath, $kitReport);
                $this->writeJsonFile($this->joinPath($runDir, 'kit-report.json'), $kitReport);
                $this->recordCheckpoint($runDir, $context, $kitReport, $reportPath);
                $this->writeLine('Plan completed: ' . $reportPath);
                return 0;
            }

            $failed = false;
            if (in_array($action, ['preflight', 'install'], true)) {
                $kitReport['database_provisioning'] = $this->provisionAppDatabases($orderedApps, $config, $action);
                if (($kitReport['database_provisioning']['status'] ?? 'success') === 'failed') {
                    $failed = true;
                    $kitReport['status'] = 'failed';
                    $kitReport['finished_at'] = date(DATE_ATOM);
                    $reportPath = $this->kitActionReportPath($runDir, $action);
                    $this->writeJsonFile($reportPath, $kitReport);
                    $this->writeJsonFile($this->joinPath($runDir, 'kit-report.json'), $kitReport);
                    $this->recordCheckpoint($runDir, $context, $kitReport, $reportPath);
                    $this->writeLine('Run report: ' . $reportPath);
                    return 1;
                }
            }
            if ($action === 'populate' && (string) ($config['data_prep']['step'] ?? '') === 'post_apply_verify') {
                $postApply = $this->runDataPrepPostApplyVerification($orderedApps, $config, $runDir, $runId, $context);
                $kitReport['data_prep_post_apply'] = $postApply;
                $finalResult = $postApply['heartbeat_verify']['final_result'] ?? null;
                if (is_array($finalResult)) {
                    $kitReport['apps'][] = $finalResult;
                }
                foreach (($postApply['warnings'] ?? []) as $warning) {
                    $kitReport['warnings'][] = (string) $warning;
                }
                foreach (($postApply['errors'] ?? []) as $error) {
                    $kitReport['errors'][] = (string) $error;
                }
                $kitReport['status'] = ($postApply['status'] ?? '') === 'failed' ? 'failed' : ((($postApply['status'] ?? '') === 'warning') ? 'warning' : 'success');
                $kitReport['finished_at'] = date(DATE_ATOM);
                $reportPath = $this->kitActionReportPath($runDir, $action);
                $this->writeJsonFile($reportPath, $kitReport);
                $this->writeJsonFile($this->joinPath($runDir, 'kit-report.json'), $kitReport);
                $this->recordCheckpoint($runDir, $context, $kitReport, $reportPath);
                $this->writeLine('Run report: ' . $reportPath);
                return $kitReport['status'] === 'failed' ? 1 : 0;
            }
            foreach ($orderedApps as $app) {
                try {
                    $appResult = $action === 'populate'
                        ? $this->runAppPopulationTools($app, $config, $runDir, $runId)
                        : $this->runAppInstaller($app, $config, $runDir, $runId, $action);
                } catch (Throwable $e) {
                    $appResult = [
                        'id' => $app['id'] ?? null,
                        'name' => $app['name'] ?? ($app['id'] ?? null),
                        'status' => 'failed',
                        'mode' => $action,
                        'message' => $e->getMessage(),
                        'errors' => [$e->getMessage()],
                    ];
                }
                $kitReport['apps'][] = $appResult;
                if (!in_array($appResult['status'], ['success', 'warning', 'skipped'], true)) {
                    $failed = true;
                    $message = (string) ($appResult['message'] ?? '');
                    $label = (string) ($appResult['name'] ?? $appResult['id'] ?? 'app');
                    if ($message !== '') {
                        $kitReport['errors'][] = $label . ': ' . $message;
                    }
                    if ($action === 'install') {
                        break;
                    }
                } elseif (($appResult['status'] ?? '') === 'warning') {
                    $message = (string) ($appResult['message'] ?? '');
                    $label = (string) ($appResult['name'] ?? $appResult['id'] ?? 'app');
                    if ($message !== '') {
                        $kitReport['warnings'][] = $label . ': ' . $message;
                    }
                }
            }

            if ($action === 'populate' && $appFilter === '' && (string) ($config['data_prep']['step'] ?? '') === '') {
                $postApply = $this->runDataPrepPostApplyVerification($orderedApps, $config, $runDir, $runId, $context);
                $kitReport['data_prep_post_apply'] = $postApply;
                foreach (($postApply['warnings'] ?? []) as $warning) {
                    $kitReport['warnings'][] = (string) $warning;
                }
                foreach (($postApply['errors'] ?? []) as $error) {
                    $kitReport['errors'][] = (string) $error;
                }
                if (($postApply['status'] ?? '') === 'failed') {
                    $failed = true;
                }
            }

            $kitReport['status'] = $failed ? 'failed' : 'success';
            $kitReport['finished_at'] = date(DATE_ATOM);
            $reportPath = $this->kitActionReportPath($runDir, $action);
            $this->writeJsonFile($reportPath, $kitReport);
            $this->writeJsonFile($this->joinPath($runDir, 'kit-report.json'), $kitReport);
            $this->recordCheckpoint($runDir, $context, $kitReport, $reportPath);
            $this->writeLine('Run report: ' . $reportPath);

            return $failed ? 1 : 0;
        } catch (Throwable $e) {
            $this->writeLine('ERROR: ' . $e->getMessage(), true);
            if ($runDir !== '' && is_dir($runDir) && is_array($context)) {
                $report = $this->makeFailedActionReport($context, $action, $e->getMessage());
                $reportPath = $this->kitActionReportPath($runDir, $action);
                try {
                    $this->writeJsonFile($reportPath, $report);
                    $this->writeJsonFile($this->joinPath($runDir, 'kit-report.json'), $report);
                    $this->recordCheckpoint($runDir, $context, $report, $reportPath);
                } catch (Throwable $writeError) {
                    $this->writeLine('ERROR: Failed to write failure report: ' . $writeError->getMessage(), true);
                }
            }
            return 1;
        }
    }

    private function makeFailedActionReport(array $context, string $action, string $message): array
    {
        return [
            'schema_version' => 1,
            'kit_setup_version' => self::VERSION,
            'run_id' => $context['run_id'] ?? null,
            'action' => $action,
            'status' => 'failed',
            'started_at' => $context['started_at'] ?? null,
            'finished_at' => date(DATE_ATOM),
            'config_path' => $context['config_path'] ?? null,
            'run_dir' => $context['run_dir'] ?? null,
            'app_filter' => $context['app_filter'] ?? null,
            'apps' => [],
            'warnings' => [],
            'errors' => [$message],
            'message' => $message,
        ];
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
            $this->makeStage(5, 'Admin & Database', $admin['status'], $admin['message'], $admin),
            $this->makeStage(6, 'Prepare Trusted App Packages', $summary['packages']['status'] ?? 'warning', 'Trusted package plan completed.', $summary['packages']),
            $this->makeStage(7, 'Preflight Apps', 'pending', 'Waiting for app preflight checks.'),
            $this->makeStage(8, 'Install Apps', 'pending', 'Waiting for administrator confirmation before app installers run.'),
            $this->makeStage(9, 'Network & Local DNS', $summary['dns']['status'] ?? 'warning', 'Local DNS plan completed.', ['record_count' => count($summary['dns']['records'] ?? [])]),
            $this->makeStage(10, 'SSL & Web Server', $summary['ssl']['status'] ?? 'warning', 'SSL and vhost plan completed.', $summary['ssl']),
            $this->makeStage(11, 'Remote & Smoke Checks', $summary['remote_dependencies']['status'] ?? 'success', 'Remote dependency check completed.', $summary['remote_dependencies']),
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
        $installReport = $this->readFirstCheckpointReport($checkpoints, ['install']);
        $kitReport = $installReport
            ?: $this->readFirstCheckpointReport($checkpoints, ['preflight', 'plan', 'populate'])
            ?: $this->readOptionalJson($this->joinPath($runDir, 'kit-report.json'));
        $stageReport = $this->readFirstCheckpointReport($checkpoints, ['stage-report'])
            ?: $this->readOptionalJson($this->joinPath($runDir, 'stage-report.json'));
        $packageReport = $this->readFirstCheckpointReport($checkpoints, ['prepare-packages'])
            ?: $this->readOptionalJson($this->joinPath($runDir, 'package-report.json'));
        $dnsReport = $this->readFirstCheckpointReport($checkpoints, ['dns-verify', 'dns-apply', 'dns-plan'])
            ?: $this->readOptionalJson($this->joinPath($runDir, 'dns-verify.json'))
            ?: $this->readOptionalJson($this->joinPath($runDir, 'dns-apply.json'))
            ?: $this->readOptionalJson($this->joinPath($runDir, 'dns-plan.json'));
        $firewallReport = $this->readFirstCheckpointReport($checkpoints, ['firewall-apply'])
            ?: $this->readOptionalJson($this->joinPath($runDir, 'firewall-apply.json'));
        $serviceReport = $this->readFirstCheckpointReport($checkpoints, ['service-verify', 'service-start', 'service-plan'])
            ?: $this->readOptionalJson($this->joinPath($runDir, 'service-verify.json'))
            ?: $this->readOptionalJson($this->joinPath($runDir, 'service-start.json'))
            ?: $this->readOptionalJson($this->joinPath($runDir, 'service-plan.json'));
        $sslReport = $this->readFirstCheckpointReport($checkpoints, ['ssl-apply', 'ssl-plan'])
            ?: $this->readOptionalJson($this->joinPath($runDir, 'ssl-apply.json'))
            ?: $this->readOptionalJson($this->joinPath($runDir, 'ssl-plan.json'));
        $remoteReport = $this->readFirstCheckpointReport($checkpoints, ['remote-check'])
            ?: $this->readOptionalJson($this->joinPath($runDir, 'remote-check.json'));
        $smokeReport = $this->readFirstCheckpointReport($checkpoints, ['smoke-check'])
            ?: $this->readOptionalJson($this->joinPath($runDir, 'smoke-check.json'));
        $platformReport = $this->readFirstCheckpointReport($checkpoints, ['detect'])
            ?: $this->readOptionalJson($this->joinPath($runDir, 'platform-report.json'));

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
                    'runtime_services' => $app['runtime_services'] ?? [],
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
                    'runtime_services' => $this->appRuntimeServices((string) ($appConfig['id'] ?? ''), $appConfig, $config),
                ];
            }
        }

        $followUps = $this->buildFinishFollowUps($checkpoints, $apps, $dnsReport, $sslReport, $remoteReport, $smokeReport, $platformReport, $serviceReport);
        $status = count(array_filter($followUps, static fn (array $item): bool => ($item['severity'] ?? '') === 'required')) > 0
            ? 'warning'
            : 'success';

        $installerCleanup = null;
        if ($status === 'success') {
            $installerCleanup = $this->cleanupSelectedAppInstallerArtifacts($config);
        }

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
            'runtime' => $this->runtimeState($config),
            'apps' => $apps,
            'reports' => [
                'kit_report' => $this->checkpointReportPath($checkpoints, 'install') ?: (is_file($this->joinPath($runDir, 'kit-report.json')) ? $this->absolutePath($this->joinPath($runDir, 'kit-report.json')) : null),
                'package_report' => $this->checkpointReportPath($checkpoints, 'prepare-packages') ?: (is_file($this->joinPath($runDir, 'package-report.json')) ? $this->absolutePath($this->joinPath($runDir, 'package-report.json')) : null),
                'stage_report' => $this->checkpointReportPath($checkpoints, 'stage-report') ?: (is_file($this->joinPath($runDir, 'stage-report.json')) ? $this->absolutePath($this->joinPath($runDir, 'stage-report.json')) : null),
                'dns_report' => $this->firstCheckpointReportPath($checkpoints, ['dns-verify', 'dns-apply', 'dns-plan']),
                'firewall_report' => $this->checkpointReportPath($checkpoints, 'firewall-apply'),
                'service_report' => $this->firstCheckpointReportPath($checkpoints, ['service-verify', 'service-start', 'service-plan']),
                'ssl_report' => $this->firstCheckpointReportPath($checkpoints, ['ssl-apply', 'ssl-plan']),
                'remote_report' => $this->checkpointReportPath($checkpoints, 'remote-check'),
                'smoke_report' => $this->checkpointReportPath($checkpoints, 'smoke-check'),
                'checkpoint_report' => is_file($this->joinPath($runDir, 'checkpoints.json')) ? $this->absolutePath($this->joinPath($runDir, 'checkpoints.json')) : null,
            ],
            'packages' => is_array($packageReport) ? [
                'status' => $packageReport['status'] ?? null,
                'packages' => $packageReport['packages'] ?? [],
            ] : null,
            'dns' => [
                'status' => is_array($dnsReport) ? ($dnsReport['status'] ?? null) : null,
                'records' => is_array($dnsReport) ? ($dnsReport['records'] ?? ($dnsReport['plan']['records'] ?? [])) : [],
                'verification' => is_array($dnsReport) && ($dnsReport['action'] ?? '') === 'dns-verify' ? ($dnsReport['results'] ?? []) : null,
            ],
            'firewall' => is_array($firewallReport) ? [
                'status' => $firewallReport['status'] ?? null,
                'rules' => $firewallReport['rules'] ?? [],
            ] : null,
            'runtime_services' => is_array($serviceReport) ? [
                'status' => $serviceReport['status'] ?? null,
                'services' => $serviceReport['runtime_services'] ?? [],
            ] : [
                'status' => null,
                'services' => $this->collectRuntimeServices($config),
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
            'installer_cleanup' => $installerCleanup,
            'stage_report_status' => is_array($stageReport) ? ($stageReport['status'] ?? null) : null,
        ];
    }

    private function cleanupSelectedAppInstallerArtifacts(array $config): array
    {
        $results = [];
        $warnings = [];
        foreach (($config['apps'] ?? []) as $appConfig) {
            if (!is_array($appConfig) || ($appConfig['enabled'] ?? true) === false) {
                continue;
            }
            if ((string) ($appConfig['install_scope'] ?? 'local') !== 'local') {
                continue;
            }

            $appId = (string) ($appConfig['id'] ?? '');
            $releasePath = (string) ($appConfig['release_path'] ?? $appConfig['install_path'] ?? '');
            if ($appId === '' || $releasePath === '') {
                continue;
            }

            $release = [];
            $releaseJsonPath = $this->joinPath($releasePath, 'release.json');
            if (is_file($releaseJsonPath)) {
                try {
                    $candidate = $this->readJsonFile($releaseJsonPath);
                    if (is_array($candidate)) {
                        $release = $candidate;
                    }
                } catch (Throwable $e) {
                    $warnings[] = 'Unable to read release metadata for installer cleanup: ' . $e->getMessage();
                }
            }

            $result = $this->cleanupAppInstallerArtifacts([
                'id' => $appId,
                'release_path' => $releasePath,
                'release' => $release,
            ]);
            $results[] = [
                'app_id' => $appId,
                'status' => $result['status'] ?? 'warning',
                'removed' => $result['removed'] ?? [],
                'skipped' => $result['skipped'] ?? [],
                'warnings' => $result['warnings'] ?? [],
            ];
            foreach (($result['warnings'] ?? []) as $warning) {
                $warnings[] = $appId . ': ' . $warning;
            }
        }

        return [
            'status' => count($warnings) > 0 ? 'warning' : 'success',
            'apps' => $results,
            'warnings' => $warnings,
        ];
    }

    private function buildFinishFollowUps(array $checkpoints, array $apps, ?array $dnsReport, ?array $sslReport, ?array $remoteReport, ?array $smokeReport, ?array $platformReport, ?array $serviceReport = null): array
    {
        $items = [];
        foreach (['plan', 'preflight'] as $requiredAction) {
            $status = $checkpoints['actions'][$requiredAction]['status'] ?? null;
            if (!in_array($status, ['success', 'warning'], true)) {
                $items[] = [
                    'severity' => 'required',
                    'message' => 'Run or fix required action: ' . $requiredAction,
                ];
            }
        }
        $detectStatus = $checkpoints['actions']['detect']['status'] ?? null;
        $stageReportStatus = $checkpoints['actions']['stage-report']['status'] ?? null;
        if (!in_array($detectStatus, ['success', 'warning'], true) && !in_array($stageReportStatus, ['success', 'warning'], true)) {
            $items[] = [
                'severity' => 'required',
                'message' => 'Run or fix required action: detect or stage-report',
            ];
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
        $smokeRequiredServices = [];
        foreach ($apps as $app) {
            foreach (($app['runtime_services'] ?? []) as $service) {
                if (is_array($service) && ($service['required_for_smoke'] ?? false) === true) {
                    $smokeRequiredServices[] = $service;
                }
            }
        }
        if (count($smokeRequiredServices) > 0 && !is_array($serviceReport)) {
            $items[] = ['severity' => 'required', 'message' => 'Run service-start and service-verify before smoke-check. Runtime services are required for final handoff.'];
        } elseif (is_array($serviceReport) && ($serviceReport['status'] ?? '') === 'failed') {
            $items[] = ['severity' => 'required', 'message' => 'Runtime service verification failed. Start or fix required services before smoke-check.'];
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

    private function readFirstCheckpointReport(array $checkpoints, array $actions): ?array
    {
        foreach ($actions as $action) {
            $path = $this->checkpointReportPath($checkpoints, $action);
            if ($path === null) {
                continue;
            }
            $report = $this->readOptionalJson($path);
            if (is_array($report)) {
                return $report;
            }
        }
        return null;
    }

    private function firstCheckpointReportPath(array $checkpoints, array $actions): ?string
    {
        foreach ($actions as $action) {
            $path = $this->checkpointReportPath($checkpoints, $action);
            if ($path !== null) {
                return $path;
            }
        }
        return null;
    }

    private function checkpointReportPath(array $checkpoints, string $action): ?string
    {
        $path = $checkpoints['actions'][$action]['report_path'] ?? null;
        if (!is_string($path) || $path === '' || !is_file($path)) {
            return null;
        }
        return $this->absolutePath($path);
    }

    private function kitActionReportPath(string $runDir, string $action): string
    {
        $filenames = [
            'plan' => 'plan-report.json',
            'preflight' => 'preflight-report.json',
            'install' => 'install-report.json',
            'populate' => 'populate-report.json',
        ];
        return $this->joinPath($runDir, $filenames[$action] ?? 'kit-report.json');
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
        $envPassword = $passwordEnv !== '' ? getenv($passwordEnv) : false;
        $resolvedPassword = $password !== '' ? $password : (is_string($envPassword) ? $envPassword : '');
        $passwordConfigured = $resolvedPassword !== '';
        $passwordStrong = $passwordConfigured && $this->isStrongAdminPassword($resolvedPassword);
        $status = ($email === 'admin@pbb.local' && $name !== '' && $passwordStrong) ? 'success' : 'warning';
        return [
            'status' => $status,
            'message' => $status === 'success' ? 'Standard administrator account is ready.' : 'Administrator password must be at least 12 characters and include uppercase, lowercase, and a number.',
            'name' => $name,
            'email' => $email,
            'password_env' => $passwordEnv,
            'password_configured' => $passwordConfigured,
            'password_strength' => $passwordStrong ? 'passed' : 'failed',
        ];
    }

    private function isStrongAdminPassword(string $password): bool
    {
        return strlen($password) >= 12
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/[0-9]/', $password) === 1;
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

        $existingSecretPath = $this->joinPath($runDir, 'secrets', 'kit-secrets.json');
        $existingSecretFile = $this->readOptionalJson($existingSecretPath);
        $existingValues = is_array($existingSecretFile['values'] ?? null) ? $existingSecretFile['values'] : [];

        $definitions = $this->defaultSecretDefinitions();
        $generated = [];
        $reused = [];
        foreach ($definitions as $name => $definition) {
            $current = (string) ($values[$name] ?? '');
            if ($current === '' || $this->isPlaceholder($current)) {
                $existing = (string) ($existingValues[$name] ?? '');
                if ($existing !== '' && !$this->isPlaceholder($existing)) {
                    $values[$name] = $existing;
                    $reused[] = $name;
                } else {
                    $values[$name] = $this->generateSecret((int) $definition['bytes']);
                    $generated[] = $name;
                }
            }
        }

        $optionalDefinitions = $this->optionalSecretDefinitions();
        foreach ($optionalDefinitions as $name => $definition) {
            $current = (string) ($values[$name] ?? '');
            $envName = (string) ($definition['env'] ?? '');
            $envValue = $envName !== '' ? getenv($envName) : false;
            $existing = (string) ($existingValues[$name] ?? '');
            if (($current === '' || $this->isPlaceholder($current)) && $existing !== '' && !$this->isPlaceholder($existing)) {
                $values[$name] = $existing;
                $reused[] = $name;
            } elseif (($current === '' || $this->isPlaceholder($current)) && is_string($envValue) && trim($envValue) !== '') {
                $values[$name] = trim($envValue);
            } elseif ($current === '' || $this->isPlaceholder($current)) {
                $values[$name] = '';
            }
        }

        $secretConfig['values'] = $values;
        $secretConfig['generated_at'] = date(DATE_ATOM);
        $config['shared']['secrets'] = $secretConfig;

        $placeholderMap = [];
        foreach (array_merge($definitions, $optionalDefinitions) as $name => $definition) {
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
            'reused' => array_values(array_unique($reused)),
            'available' => array_keys($values),
            'redacted_values' => $this->redactSecretValues($values),
        ];

        $this->writeJsonFile($this->joinPath($runDir, 'secret-report.json'), $report);

        return [
            'config' => $config,
            'report' => $report,
        ];
    }

    private function optionalSecretDefinitions(): array
    {
        return [
            'stadiamaps_api_key' => [
                'env' => 'STADIAMAPS_API_KEY',
                'placeholders' => ['REPLACE_WITH_STADIAMAPS_API_KEY'],
            ],
            'maptiler_api_key' => [
                'env' => 'MAPTILER_API_KEY',
                'placeholders' => ['REPLACE_WITH_MAPTILER_API_KEY'],
            ],
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
            'maestro_relay_telemetry_token' => [
                'bytes' => 32,
                'placeholders' => ['REPLACE_WITH_MAESTRO_RELAY_TELEMETRY_TOKEN'],
            ],
            'maestro_realtime_telemetry_token' => [
                'bytes' => 32,
                'placeholders' => ['REPLACE_WITH_MAESTRO_REALTIME_TELEMETRY_TOKEN'],
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
        $progressByApp = [];
        $workerDir = $this->joinPath((string) $context['run_dir'], 'package-workers');
        $this->ensureDirectory($workerDir);
        foreach ($selectedApps as $appId) {
            if (is_string($appId) && $appId !== '') {
                $progressByApp[$appId] = [
                    'app_id' => $appId,
                    'step' => 'pending',
                    'status' => 'pending',
                    'message' => 'Waiting for package preparation.',
                    'percent' => 0,
                ];
            }
        }
        $lastSummaryAt = 0.0;

        while (count($queue) > 0 || count($active) > 0) {
            while (count($queue) > 0 && count($active) < $maxParallel) {
                $appId = array_shift($queue);
                if (!is_string($appId) || $appId === '') {
                    continue;
                }
                $this->writeProgress('package', $appId, 'worker-started', [
                    'message' => 'Package worker started.',
                    'percent' => 1,
                ]);
                $progressByApp[$appId] = [
                    'app_id' => $appId,
                    'step' => 'worker-started',
                    'status' => 'running',
                    'message' => 'Package worker started.',
                    'percent' => 1,
                    'updated_at' => date(DATE_ATOM),
                ];
                $active[$appId] = $this->startPackageWorker(
                    $context,
                    $appId,
                    $this->joinPath($workerDir, $appId . '.json'),
                    $this->joinPath($workerDir, $appId . '.progress.json')
                );
            }

            foreach (array_keys($active) as $appId) {
                $worker = $active[$appId];
                $this->drainWorkerPipe($worker, 'stdout');
                $this->drainWorkerPipe($worker, 'stderr');
                $progressPayload = $this->pollWorkerProgress($worker, $appId);
                if (is_array($progressPayload)) {
                    $progressByApp[$appId] = $this->mergePackageProgressState($progressByApp[$appId] ?? [], $progressPayload);
                }
                $status = proc_get_status($worker['process']);
                if (($status['running'] ?? false) === true || !$this->workerPipesClosed($worker)) {
                    $now = time();
                    $lastHeartbeat = (int) ($worker['last_heartbeat_at'] ?? 0);
                    if ($now - $lastHeartbeat >= 3) {
                        $progressByApp[$appId]['elapsed_seconds'] = max(0, $now - (int) ($worker['started_at'] ?? $now));
                        $progressByApp[$appId]['updated_at'] = date(DATE_ATOM);
                        $worker['last_heartbeat_at'] = $now;
                    }
                    $active[$appId] = $worker;
                    continue;
                }

                $this->drainWorkerPipe($worker, 'stdout');
                $this->drainWorkerPipe($worker, 'stderr');
                $progressPayload = $this->pollWorkerProgress($worker, $appId, true);
                if (is_array($progressPayload)) {
                    $progressByApp[$appId] = $this->mergePackageProgressState($progressByApp[$appId] ?? [], $progressPayload);
                }
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
                $progressByApp[$appId] = $this->mergePackageProgressState($progressByApp[$appId] ?? [], [
                    'app_id' => $appId,
                    'step' => ($result['status'] ?? '') === 'failed' ? 'failed' : 'complete',
                    'status' => (string) ($result['status'] ?? 'failed'),
                    'message' => (string) ($result['message'] ?? ((($result['status'] ?? '') === 'failed') ? 'Package preparation failed.' : 'Package is ready.')),
                    'percent' => 100,
                    'updated_at' => date(DATE_ATOM),
                ]);
                unset($active[$appId]);
            }

            $now = microtime(true);
            if ($now - $lastSummaryAt >= 0.5 || count($active) === 0) {
                $this->writePackageProgressSummary($selectedApps, $progressByApp);
                $lastSummaryAt = $now;
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

    private function startPackageWorker(array $context, string $appId, string $reportPath, string $progressPath): array
    {
        $script = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
        if ($script === '') {
            throw new RuntimeException('Unable to locate current runner script for package worker.');
        }
        $stdoutPath = $reportPath . '.stdout.log';
        $stderrPath = $reportPath . '.stderr.log';
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
            '--progress-file',
            $progressPath,
        ];
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['file', $stdoutPath, 'a'],
            2 => ['file', $stderrPath, 'a'],
        ];
        $process = proc_open(implode(' ', array_map([$this, 'escapeArg'], $command)), $descriptorSpec, $pipes, (string) getcwd());
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start package worker for ' . $appId);
        }
        fclose($pipes[0]);
        return [
            'process' => $process,
            'pipes' => $pipes,
            'report_path' => $reportPath,
            'progress_path' => $progressPath,
            'stdout_path' => $stdoutPath,
            'stderr_path' => $stderrPath,
            'stdout_offset' => 0,
            'stderr_offset' => 0,
            'last_progress_hash' => '',
            'started_at' => time(),
            'last_heartbeat_at' => 0,
        ];
    }

    private function pollWorkerProgress(array &$worker, string $appId, bool $force = false): ?array
    {
        $path = (string) ($worker['progress_path'] ?? '');
        if ($path === '' || !is_file($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }
        $hash = sha1($raw);
        if (!$force && $hash === (string) ($worker['last_progress_hash'] ?? '')) {
            return null;
        }
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return null;
        }
        $worker['last_progress_hash'] = $hash;
        $step = (string) ($payload['step'] ?? 'working');
        unset($payload['scope'], $payload['app_id'], $payload['step']);
        $this->writeProgress('package', $appId, $step, $payload);
        return array_merge([
            'app_id' => $appId,
            'step' => $step,
        ], $payload);
    }

    private function mergePackageProgressState(array $current, array $next): array
    {
        $currentPercent = is_numeric($current['percent'] ?? null) ? (float) $current['percent'] : 0.0;
        $nextPercent = is_numeric($next['percent'] ?? null) ? (float) $next['percent'] : $currentPercent;
        $merged = array_merge($current, $next);
        $merged['percent'] = max($currentPercent, $nextPercent);
        if (!isset($merged['status']) || $merged['status'] === '') {
            $step = (string) ($merged['step'] ?? '');
            $merged['status'] = match ($step) {
                'complete' => 'success',
                'failed' => 'failed',
                'pending' => 'pending',
                default => 'running',
            };
        }
        return $merged;
    }

    private function writePackageProgressSummary(array $selectedApps, array $progressByApp): void
    {
        $apps = [];
        $complete = 0;
        $failed = 0;
        $running = 0;
        $totalPercent = 0.0;
        foreach ($selectedApps as $appId) {
            if (!is_string($appId) || $appId === '') {
                continue;
            }
            $progress = $progressByApp[$appId] ?? [
                'app_id' => $appId,
                'step' => 'pending',
                'status' => 'pending',
                'message' => 'Waiting for package preparation.',
                'percent' => 0,
            ];
            $status = (string) ($progress['status'] ?? 'pending');
            if ($status === 'success' || $status === 'failed') {
                $complete++;
                $totalPercent += 100;
            } else {
                $percent = is_numeric($progress['percent'] ?? null) ? (float) $progress['percent'] : 0.0;
                $totalPercent += max(0.0, min(100.0, $percent));
                if ($status === 'running') {
                    $running++;
                }
            }
            if ($status === 'failed') {
                $failed++;
            }
            $apps[] = $progress;
        }

        $total = count($apps);
        $overallPercent = $total > 0 ? (int) round($totalPercent / $total) : 0;
        $this->writeLine('PROGRESS: ' . json_encode([
            'scope' => 'package',
            'step' => 'summary',
            'complete' => $complete,
            'failed' => $failed,
            'running' => $running,
            'total' => $total,
            'overall_percent' => $overallPercent,
            'apps' => $apps,
            'updated_at' => date(DATE_ATOM),
        ], JSON_UNESCAPED_SLASHES));
    }

    private function drainWorkerPipe(array &$worker, string $name): void
    {
        $index = $name === 'stderr' ? 2 : 1;
        $pipe = $worker['pipes'][$index] ?? null;
        if (!is_resource($pipe)) {
            $this->drainWorkerLogFile($worker, $name);
            return;
        }
        $chunk = '';
        for ($i = 0; $i < 20; $i++) {
            $part = fread($pipe, 8192);
            if ($part === false || $part === '') {
                break;
            }
            $chunk .= $part;
            if (strlen($part) < 8192) {
                break;
            }
        }
        if ($chunk === false || $chunk === '') {
            return;
        }
        if ($name === 'stdout' && (string) ($worker['progress_path'] ?? '') !== '') {
            $chunk = $this->removeProgressLines($chunk);
            if ($chunk === '') {
                return;
            }
        }
        if ($name === 'stderr') {
            fwrite(STDERR, $chunk);
            fflush(STDERR);
        } else {
            fwrite(STDOUT, $chunk);
            fflush(STDOUT);
        }
    }

    private function drainWorkerLogFile(array &$worker, string $name): void
    {
        $pathKey = $name === 'stderr' ? 'stderr_path' : 'stdout_path';
        $offsetKey = $name === 'stderr' ? 'stderr_offset' : 'stdout_offset';
        $path = (string) ($worker[$pathKey] ?? '');
        if ($path === '' || !is_file($path)) {
            return;
        }
        $offset = (int) ($worker[$offsetKey] ?? 0);
        $size = filesize($path);
        if (!is_int($size) || $size <= $offset) {
            return;
        }
        $handle = fopen($path, 'rb');
        if (!is_resource($handle)) {
            return;
        }
        try {
            fseek($handle, $offset);
            $chunk = stream_get_contents($handle, max(0, $size - $offset));
        } finally {
            fclose($handle);
        }
        if (!is_string($chunk) || $chunk === '') {
            $worker[$offsetKey] = $size;
            return;
        }
        $worker[$offsetKey] = $size;
        if ($name === 'stdout' && (string) ($worker['progress_path'] ?? '') !== '') {
            $chunk = $this->removeProgressLines($chunk);
            if ($chunk === '') {
                return;
            }
        }
        if ($name === 'stderr') {
            fwrite(STDERR, $chunk);
            fflush(STDERR);
        } else {
            fwrite(STDOUT, $chunk);
            fflush(STDOUT);
        }
    }

    private function removeProgressLines(string $chunk): string
    {
        $lines = preg_split('/(\r\n|\n|\r)/', $chunk);
        if (!is_array($lines)) {
            return $chunk;
        }

        $kept = [];
        foreach ($lines as $line) {
            if ($line === '' || str_starts_with($line, 'PROGRESS:')) {
                continue;
            }
            $kept[] = $line;
        }

        return $kept === [] ? '' : implode(PHP_EOL, $kept) . PHP_EOL;
    }

    private function workerPipesClosed(array $worker): bool
    {
        foreach ([1, 2] as $index) {
            $pipe = $worker['pipes'][$index] ?? null;
            if (is_resource($pipe) && !feof($pipe)) {
                return false;
            }
        }
        return true;
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
            'updated_at' => date(DATE_ATOM),
        ], $data);
        if ($this->progressFile !== null && $this->progressFile !== '') {
            $this->writeProgressFile($this->progressFile, $payload);
        }
        $this->writeLine('PROGRESS: ' . json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    private function writeProgressFile(string $path, array $payload): void
    {
        $this->ensureDirectory(dirname($path));
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return;
        }
        $temporary = $path . '.tmp';
        if (file_put_contents($temporary, $json . PHP_EOL, LOCK_EX) === false) {
            return;
        }
        @rename($temporary, $path);
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
        $targetPath = (string) ($appConfig['release_path'] ?? $appConfig['install_path'] ?? '');
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
                'percent' => 25,
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
                    'percent' => 5,
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
                            'percent' => 10,
                        ]);
                        $extracted = $this->extractPackageArchive($packagePath, $runDir, $appId, function (int $current, int $total) use ($appId, $progressBase): void {
                            $percent = $total > 0 ? 10 + (int) floor(($current / $total) * 45) : 10;
                            $this->writeProgress('package', $appId, 'extract', $progressBase + [
                                'message' => sprintf('Extracting package files (%d/%d).', $current, $total),
                                'current' => $current,
                                'total_files' => $total,
                                'percent' => min(55, $percent),
                            ]);
                        });
                        $stagingPath = $extracted['staging_path'];
                        $releasePath = $this->joinPath($stagingPath, 'release.json');
                        if (is_file($releasePath)) {
                            $this->writeProgress('package', $appId, 'verify', $progressBase + [
                                'message' => 'Verifying release metadata and checksums.',
                                'percent' => 58,
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
            if (strtolower((string) ($release['type'] ?? '')) === 'laravel') {
                $installerDatabase = $release['installer']['database'] ?? null;
                $freshInstallStrategy = is_array($installerDatabase)
                    ? (string) ($installerDatabase['fresh_install_strategy'] ?? '')
                    : '';
                $baselineSchema = is_array($installerDatabase)
                    ? ($installerDatabase['baseline_schema'] ?? null)
                    : null;
                $baselinePath = is_array($baselineSchema)
                    ? (string) ($baselineSchema['path'] ?? '')
                    : '';

                if ($freshInstallStrategy !== 'baseline_schema' || $baselinePath === '') {
                    $warnings[] = 'Laravel release does not declare installer.database baseline_schema metadata for fresh installs.';
                } else {
                    $releaseRoot = null;
                    if (is_string($stagingPath) && $stagingPath !== '') {
                        $releaseRoot = $stagingPath;
                    } elseif ($sourceType === 'directory' && is_dir($packagePath)) {
                        $releaseRoot = $packagePath;
                    }

                    if (is_string($releaseRoot) && $releaseRoot !== '') {
                        $normalizedBaselinePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $baselinePath);
                        if (!is_file($this->joinPath($releaseRoot, $normalizedBaselinePath))) {
                            $warnings[] = 'Laravel release declares baseline schema metadata, but the schema artifact was not found: ' . $baselinePath . '.';
                        }
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
                    'percent' => 60,
                ]);
                $deploy = $this->deployStagedPackage($stagingPath, $targetPath, $runDir, $appId, $allowedTargetRoots, function (int $current, int $total) use ($appId, $progressBase): void {
                    $percent = $total > 0 ? 60 + (int) floor(($current / $total) * 35) : 60;
                    $this->writeProgress('package', $appId, 'deploy', $progressBase + [
                        'message' => sprintf('Copying package files (%d/%d).', $current, $total),
                        'current' => $current,
                        'total_files' => $total,
                        'percent' => min(95, $percent),
                    ]);
                });
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
            'percent' => 100,
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
            'pbb-stub' => 'stub',
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

    private function runFirewallApply(array $config, array $context): array
    {
        $platform = is_array($config['platform'] ?? null) ? $config['platform'] : [];
        $firewall = is_array($platform['firewall'] ?? null) ? $platform['firewall'] : [];
        $mode = (string) ($firewall['update_mode'] ?? 'apply');
        $rules = $this->normalizeFirewallRules($firewall['inbound_rules'] ?? null);
        $report = [
            'schema_version' => 1,
            'kit_setup_version' => self::VERSION,
            'action' => 'firewall-apply',
            'status' => 'running',
            'started_at' => $context['started_at'],
            'finished_at' => null,
            'run_id' => $context['run_id'],
            'run_dir' => $context['run_dir'],
            'platform' => PHP_OS_FAMILY,
            'update_mode' => $mode,
            'requires_admin' => true,
            'rules' => [],
            'warnings' => [],
            'errors' => [],
        ];

        if ($mode !== 'apply') {
            $report['status'] = 'skipped';
            $report['rules'] = $rules;
            $report['warnings'][] = 'platform.firewall.update_mode is not apply; firewall rules were not changed.';
            $report['finished_at'] = date(DATE_ATOM);
            return $report;
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            $report['status'] = 'failed';
            $report['errors'][] = 'firewall-apply currently supports Windows only.';
            $report['finished_at'] = date(DATE_ATOM);
            return $report;
        }

        if ($rules === []) {
            $report['status'] = 'skipped';
            $report['warnings'][] = 'No firewall inbound rules were configured.';
            $report['finished_at'] = date(DATE_ATOM);
            return $report;
        }

        $failed = false;
        foreach ($rules as $rule) {
            $ruleResult = $rule;
            $delete = $this->runProcess([
                'netsh',
                'advfirewall',
                'firewall',
                'delete',
                'rule',
                'name=' . $rule['name'],
            ], (string) getcwd());
            $ruleResult['delete_exit_code'] = $delete['exit_code'];
            $ruleResult['delete_stdout'] = trim((string) $delete['stdout']);
            $ruleResult['delete_stderr'] = trim((string) $delete['stderr']);

            $add = $this->runProcess([
                'netsh',
                'advfirewall',
                'firewall',
                'add',
                'rule',
                'name=' . $rule['name'],
                'dir=in',
                'action=allow',
                'protocol=' . $rule['protocol'],
                'localport=' . (string) $rule['local_port'],
                'profile=' . $rule['profile'],
                'enable=yes',
            ], (string) getcwd());
            $ruleResult['add_exit_code'] = $add['exit_code'];
            $ruleResult['add_stdout'] = trim((string) $add['stdout']);
            $ruleResult['add_stderr'] = trim((string) $add['stderr']);
            $ruleResult['status'] = $add['exit_code'] === 0 ? 'success' : 'failed';
            if ($ruleResult['status'] === 'failed') {
                $failed = true;
                $report['errors'][] = 'Failed to add firewall rule: ' . $rule['name'];
            }
            $report['rules'][] = $ruleResult;
        }

        $report['status'] = $failed ? 'failed' : 'success';
        $report['finished_at'] = date(DATE_ATOM);
        return $report;
    }

    private function normalizeFirewallRules($rules): array
    {
        if (!is_array($rules) || $rules === []) {
            $rules = [
                [
                    'name' => 'Project Bantay Bayan HTTP',
                    'protocol' => 'TCP',
                    'local_port' => 80,
                    'profile' => 'any',
                ],
                [
                    'name' => 'Project Bantay Bayan HTTPS',
                    'protocol' => 'TCP',
                    'local_port' => 443,
                    'profile' => 'any',
                ],
            ];
        }

        $normalized = [];
        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $port = (int) ($rule['local_port'] ?? $rule['port'] ?? 0);
            $protocol = strtoupper((string) ($rule['protocol'] ?? 'TCP'));
            $name = trim((string) ($rule['name'] ?? ''));
            $profile = strtolower((string) ($rule['profile'] ?? 'any'));
            if ($name === '') {
                $name = 'Project Bantay Bayan ' . $protocol . ' ' . $port;
            }
            if ($port <= 0 || $port > 65535 || !in_array($protocol, ['TCP', 'UDP'], true)) {
                continue;
            }
            if (!in_array($profile, ['any', 'domain', 'private', 'public'], true)) {
                $profile = 'any';
            }
            $normalized[] = [
                'name' => $name,
                'protocol' => $protocol,
                'local_port' => $port,
                'profile' => $profile,
            ];
        }

        return $normalized;
    }

    private function runRuntimeServicePlan(array $config, array $context): array
    {
        $services = array_map(fn (array $service): array => $this->withWinSwServiceMetadata($service), $this->collectRuntimeServices($config));

        return [
            'schema_version' => 1,
            'kit_setup_version' => self::VERSION,
            'action' => 'service-plan',
            'status' => 'success',
            'started_at' => $context['started_at'],
            'finished_at' => date(DATE_ATOM),
            'run_id' => $context['run_id'],
            'run_dir' => $context['run_dir'],
            'service_wrapper' => self::SERVICE_WRAPPER,
            'service_root' => $this->winSwServiceRoot(),
            'wrapper_binary' => $this->winSwSourceBinaryPath(),
            'service_count' => count($services),
            'runtime_services' => $services,
            'warnings' => [],
            'errors' => [],
        ];
    }

    private function runRuntimeServiceStart(array $config, array $context): array
    {
        $services = $this->collectRuntimeServices($config);
        $starts = [];
        $warnings = [];
        $errors = [];

        $serviceRunDir = $this->joinPath((string) $context['run_dir'], 'runtime-services');
        $this->ensureDirectory($serviceRunDir);

        foreach ($services as $service) {
            $start = $this->startRuntimeService($service, $serviceRunDir);
            $starts[] = $start;
            if (($start['status'] ?? '') === 'failed') {
                $errors[] = (string) ($start['message'] ?? ('Runtime service start failed: ' . ($service['id'] ?? 'unknown')));
            } elseif (($start['status'] ?? '') === 'warning') {
                $warnings[] = (string) ($start['message'] ?? ('Runtime service start warning: ' . ($service['id'] ?? 'unknown')));
            }
        }

        return [
            'schema_version' => 1,
            'kit_setup_version' => self::VERSION,
            'action' => 'service-start',
            'status' => count($errors) > 0 ? 'failed' : (count($warnings) > 0 ? 'warning' : 'success'),
            'started_at' => $context['started_at'],
            'finished_at' => date(DATE_ATOM),
            'run_id' => $context['run_id'],
            'run_dir' => $context['run_dir'],
            'service_wrapper' => self::SERVICE_WRAPPER,
            'service_root' => $this->winSwServiceRoot(),
            'service_count' => count($services),
            'runtime_services' => $starts,
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    private function runRuntimeServiceVerify(array $config, array $context): array
    {
        $services = $this->collectRuntimeServices($config);
        $checks = [];
        $warnings = [];
        $errors = [];

        foreach ($services as $service) {
            $check = $this->inspectRuntimeService($service);
            $checks[] = $check;
            if (($check['status'] ?? '') === 'failed') {
                $errors[] = (string) ($check['message'] ?? ('Runtime service failed: ' . ($service['id'] ?? 'unknown')));
            } elseif (($check['status'] ?? '') === 'warning') {
                $warnings[] = (string) ($check['message'] ?? ('Runtime service warning: ' . ($service['id'] ?? 'unknown')));
            }
        }

        $cleanup = null;
        if (count($errors) > 0) {
            $cleanup = $this->stopRuntimeServicesStartedByRun($context, 'service-verify failed');
            foreach (($cleanup['warnings'] ?? []) as $warning) {
                $warnings[] = (string) $warning;
            }
        }

        return [
            'schema_version' => 1,
            'kit_setup_version' => self::VERSION,
            'action' => 'service-verify',
            'status' => count($errors) > 0 ? 'failed' : (count($warnings) > 0 ? 'warning' : 'success'),
            'started_at' => $context['started_at'],
            'finished_at' => date(DATE_ATOM),
            'run_id' => $context['run_id'],
            'run_dir' => $context['run_dir'],
            'service_wrapper' => self::SERVICE_WRAPPER,
            'service_root' => $this->winSwServiceRoot(),
            'service_count' => count($services),
            'runtime_services' => $checks,
            'cleanup' => $cleanup,
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    private function runRuntimeServiceStop(array $config, array $context): array
    {
        $declared = $this->stopRuntimeServicesDeclaredByConfig($config, 'service-stop requested');
        $started = $this->stopRuntimeServicesStartedByRun($context, 'service-stop requested');
        $seenPids = [];
        $seenServices = [];
        $runtimeServices = [];
        foreach (array_merge($declared['runtime_services'] ?? [], $started['runtime_services'] ?? []) as $service) {
            $pid = (string) ($service['process_id'] ?? '');
            if ($pid !== '' && isset($seenPids[$pid])) {
                continue;
            }
            if ($pid !== '') {
                $seenPids[$pid] = true;
            }
            $serviceId = (string) ($service['service_id'] ?? '');
            if ($serviceId !== '') {
                if (isset($seenServices[$serviceId])) {
                    continue;
                }
                $seenServices[$serviceId] = true;
            }
            $runtimeServices[] = $service;
        }
        $warnings = array_values(array_merge($declared['warnings'] ?? [], $started['warnings'] ?? []));
        $errors = array_values(array_merge($declared['errors'] ?? [], $started['errors'] ?? []));
        return [
            'schema_version' => 1,
            'kit_setup_version' => self::VERSION,
            'action' => 'service-stop',
            'status' => count($errors) > 0 ? 'failed' : (count($warnings) > 0 ? 'warning' : 'success'),
            'started_at' => $context['started_at'],
            'finished_at' => date(DATE_ATOM),
            'run_id' => $context['run_id'],
            'run_dir' => $context['run_dir'],
            'service_wrapper' => self::SERVICE_WRAPPER,
            'service_root' => $this->winSwServiceRoot(),
            'service_count' => count($runtimeServices),
            'runtime_services' => $runtimeServices,
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    private function startRuntimeService(array $service, string $serviceRunDir): array
    {
        $id = (string) ($service['id'] ?? 'runtime-service');
        $type = strtolower((string) ($service['type'] ?? 'background_process'));
        $spec = $this->winSwServiceSpec($service);
        $result = [
            'app_id' => $service['app_id'] ?? null,
            'id' => $id,
            'name' => $service['name'] ?? null,
            'type' => $service['type'] ?? null,
            'required' => ($service['required'] ?? true) !== false,
            'required_for_smoke' => ($service['required_for_smoke'] ?? false) === true,
            'manager' => $service['manager'] ?? null,
            'service_wrapper' => self::SERVICE_WRAPPER,
            'service_id' => $spec['service_id'],
            'service_dir' => $spec['service_dir'],
            'service_exe' => $spec['service_exe'],
            'service_config' => $spec['service_config'],
            'service_log_dir' => $spec['log_dir'],
            'working_directory' => $service['working_directory'] ?? null,
            'command' => $service['command'] ?? null,
            'args' => $service['args'] ?? [],
            'env' => $service['env'] ?? [],
            'health_check' => is_array($service['health_check'] ?? null) ? $service['health_check'] : [],
            'status' => 'warning',
            'message' => 'Runtime service was not started.',
        ];

        $existing = $this->inspectRuntimeService($service);
        if (($existing['status'] ?? '') === 'success') {
            $result['status'] = 'success';
            $result['message'] = 'WinSW service is already running and health check passed.';
            $result['health'] = $existing;
            return $result;
        }

        if ($type !== 'background_process') {
            $result['message'] = 'Runtime service type is not supported for WinSW service registration.';
            return $result;
        }

        $command = (string) ($service['command'] ?? '');
        $cwd = (string) ($service['working_directory'] ?? '');
        if ($command === '' || $cwd === '' || !is_dir($cwd)) {
            $result['status'] = (($service['required'] ?? true) !== false) ? 'failed' : 'warning';
            $result['message'] = 'Runtime service command or working directory is not valid.';
            return $result;
        }

        $prepare = $this->prepareWinSwService($service);
        $result['prepare'] = $prepare;
        if (($prepare['status'] ?? '') !== 'success') {
            $result['status'] = (($service['required'] ?? true) !== false) ? 'failed' : 'warning';
            $result['message'] = 'WinSW service preparation failed: ' . (string) ($prepare['message'] ?? 'unknown');
            return $result;
        }

        $serviceStatus = $this->queryWindowsService($spec['service_id']);
        if (($serviceStatus['status'] ?? '') === 'not-found') {
            $install = $this->runWinSwCommand($spec, 'install');
            $result['install'] = $install;
            if ((int) ($install['exit_code'] ?? 1) !== 0) {
                $result['status'] = (($service['required'] ?? true) !== false) ? 'failed' : 'warning';
                $result['message'] = 'WinSW service install failed.';
                return $result;
            }
            $result['installed_by_current_run'] = true;
        } else {
            $result['install'] = [
                'status' => 'skipped',
                'message' => 'Windows service already exists.',
                'service' => $serviceStatus,
            ];
        }

        $start = $this->runWinSwCommand($spec, 'start');
        $result['start'] = $start;
        $result['started_by_current_run'] = true;
        if ((int) ($start['exit_code'] ?? 1) !== 0 && !$this->winSwOutputIndicatesAlreadyRunning($start)) {
            $result['status'] = (($service['required'] ?? true) !== false) ? 'failed' : 'warning';
            $result['message'] = 'WinSW service start failed.';
            return $result;
        }

        $health = $this->waitForRuntimeServiceHealth($service);
        $result['health'] = $health;
        if (($health['status'] ?? '') === 'success') {
            $result['status'] = 'success';
            $result['message'] = 'WinSW service started and health check passed.';
            return $result;
        }
        if (($health['status'] ?? '') === 'warning') {
            $result['status'] = 'warning';
            $result['message'] = 'WinSW service was started, but health could not be fully verified.';
            return $result;
        }

        $result['status'] = (($service['required'] ?? true) !== false) ? 'failed' : 'warning';
        $result['message'] = 'WinSW service was started, but health check did not pass: ' . (string) ($health['message'] ?? 'unknown');
        $result['cleanup'] = $this->stopWinSwService($service, 'service-start health check failed');
        return $result;
    }

    private function stopRuntimeServicesStartedByRun(array $context, string $reason): array
    {
        $startPath = $this->joinPath((string) $context['run_dir'], 'service-start.json');
        $startReport = $this->readOptionalJson($startPath);
        $services = is_array($startReport) && is_array($startReport['runtime_services'] ?? null)
            ? $startReport['runtime_services']
            : [];
        $stops = [];
        $warnings = [];
        $errors = [];

        foreach ($services as $service) {
            if (!is_array($service) || ($service['started_by_current_run'] ?? false) !== true) {
                continue;
            }
            $stop = $this->stopWinSwService($service, $reason);
            $stops[] = [
                'app_id' => $service['app_id'] ?? null,
                'id' => $service['id'] ?? null,
                'name' => $service['name'] ?? null,
                ...$stop,
            ];
            if (($stop['status'] ?? '') === 'failed') {
                $errors[] = (string) ($stop['message'] ?? ('Failed to stop service ' . (string) ($service['id'] ?? 'runtime-service')));
            } elseif (($stop['status'] ?? '') === 'warning') {
                $warnings[] = (string) ($stop['message'] ?? ('Unable to confirm service stop ' . (string) ($service['id'] ?? 'runtime-service')));
            }
        }

        return [
            'status' => count($errors) > 0 ? 'failed' : (count($warnings) > 0 ? 'warning' : 'success'),
            'reason' => $reason,
            'runtime_services' => $stops,
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    private function stopRuntimeServicesDeclaredByConfig(array $config, string $reason): array
    {
        $stops = [];
        $warnings = [];
        $errors = [];

        foreach ($this->collectPreDeployRuntimeServices($config) as $service) {
            if (!is_array($service)) {
                continue;
            }
            $status = $this->queryWindowsService($this->winSwServiceSpec($service)['service_id']);
            if (($status['status'] ?? '') === 'not-found') {
                continue;
            }
            $stop = $this->stopWinSwService($service, $reason);
            $stops[] = [
                'app_id' => $service['app_id'] ?? null,
                'id' => $service['id'] ?? null,
                'name' => $service['name'] ?? null,
                ...$stop,
            ];
            if (($stop['status'] ?? '') === 'failed') {
                $errors[] = (string) ($stop['message'] ?? ('Failed to stop service ' . (string) ($service['id'] ?? 'runtime-service')));
            } elseif (($stop['status'] ?? '') === 'warning') {
                $warnings[] = (string) ($stop['message'] ?? ('Unable to confirm service stop ' . (string) ($service['id'] ?? 'runtime-service')));
            }
        }

        return [
            'status' => count($errors) > 0 ? 'failed' : (count($warnings) > 0 ? 'warning' : 'success'),
            'reason' => $reason,
            'runtime_services' => $stops,
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    private function collectPreDeployRuntimeServices(array $config): array
    {
        $services = [];
        foreach ($config['apps'] ?? [] as $app) {
            if (!is_array($app) || ($app['enabled'] ?? true) === false || (string) ($app['install_scope'] ?? 'local') !== 'local') {
                continue;
            }
            if (!$this->appNeedsPreDeployRuntimeStop($app)) {
                continue;
            }
            $appId = (string) ($app['id'] ?? '');
            foreach ($this->appRuntimeServices($appId, $app, $config) as $service) {
                $services[] = $service;
            }
        }
        return $services;
    }

    private function appNeedsPreDeployRuntimeStop(array $app): bool
    {
        $decision = strtolower((string) ($app['install_decision'] ?? 'install'));
        if (in_array($decision, ['repair', 'overwrite'], true)) {
            return true;
        }

        foreach (['install_manifest', 'install_report'] as $artifactKey) {
            $artifact = $this->readAppArtifact($app, $artifactKey);
            if (is_array($artifact) && ($artifact['exists'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    private function withWinSwServiceMetadata(array $service): array
    {
        $spec = $this->winSwServiceSpec($service);
        return [
            ...$service,
            'service_wrapper' => self::SERVICE_WRAPPER,
            'service_id' => $spec['service_id'],
            'service_dir' => $spec['service_dir'],
            'service_exe' => $spec['service_exe'],
            'service_config' => $spec['service_config'],
            'service_log_dir' => $spec['log_dir'],
            'wrapper_source' => $this->winSwSourceBinaryPath(),
        ];
    }

    private function prepareWinSwService(array $service): array
    {
        $spec = $this->winSwServiceSpec($service);
        $source = $this->winSwSourceBinaryPath();
        $result = [
            'service_wrapper' => self::SERVICE_WRAPPER,
            'source_binary' => $source,
            'service_id' => $spec['service_id'],
            'service_dir' => $spec['service_dir'],
            'service_exe' => $spec['service_exe'],
            'service_config' => $spec['service_config'],
            'service_log_dir' => $spec['log_dir'],
            'status' => 'failed',
            'message' => '',
        ];

        if (PHP_OS_FAMILY !== 'Windows') {
            $result['message'] = 'WinSW service registration requires Windows.';
            return $result;
        }
        if (!is_file($source)) {
            $result['message'] = 'WinSW binary is missing: ' . $source;
            return $result;
        }

        try {
            $this->ensureDirectory($spec['service_dir']);
            $this->ensureDirectory($spec['log_dir']);
            if (!is_file($spec['service_exe']) || hash_file('sha256', $spec['service_exe']) !== hash_file('sha256', $source)) {
                if (!copy($source, $spec['service_exe'])) {
                    throw new RuntimeException('Unable to copy WinSW binary to service directory.');
                }
            }
            file_put_contents($spec['service_config'], $this->buildWinSwXml($service, $spec));
            $result['status'] = 'success';
            $result['message'] = 'WinSW service files are ready.';
        } catch (Throwable $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    private function stopWinSwService(array $service, string $reason): array
    {
        $spec = $this->winSwServiceSpec($service);
        $result = [
            'service_wrapper' => self::SERVICE_WRAPPER,
            'service_id' => $spec['service_id'],
            'service_dir' => $spec['service_dir'],
            'service_exe' => $spec['service_exe'],
            'service_config' => $spec['service_config'],
            'service_log_dir' => $spec['log_dir'],
            'reason' => $reason,
            'status' => 'skipped',
            'message' => 'Windows service is not installed.',
        ];

        $query = $this->queryWindowsService($spec['service_id']);
        $result['service'] = $query;
        if (($query['status'] ?? '') === 'not-found') {
            return $result;
        }
        if (!is_file($spec['service_exe'])) {
            $result['status'] = 'failed';
            $result['message'] = 'WinSW service executable is missing: ' . $spec['service_exe'];
            return $result;
        }
        if (($query['state'] ?? '') === 'STOPPED') {
            $result['status'] = 'success';
            $result['message'] = 'Windows service is already stopped.';
            return $result;
        }

        $stop = $this->runWinSwCommand($spec, 'stop', 45);
        $after = $this->queryWindowsService($spec['service_id']);
        $result['stop'] = $stop;
        $result['service_after_stop'] = $after;
        if (($after['state'] ?? '') === 'STOPPED' || (($after['status'] ?? '') === 'not-found')) {
            $result['status'] = 'success';
            $result['message'] = 'WinSW service was stopped.';
            return $result;
        }

        $result['status'] = 'failed';
        $result['message'] = 'WinSW service stop could not be confirmed.';
        return $result;
    }

    private function runWinSwCommand(array $spec, string $command, int $timeoutSeconds = 30): array
    {
        if (!is_file($spec['service_exe'])) {
            return [
                'command' => $spec['service_exe'] . ' ' . $command,
                'exit_code' => 1,
                'stdout' => '',
                'stderr' => 'WinSW service executable is missing.',
            ];
        }
        return $this->runProcess([$spec['service_exe'], $command], $spec['service_dir'], null, $timeoutSeconds);
    }

    private function queryWindowsService(string $serviceId): array
    {
        $result = [
            'service_id' => $serviceId,
            'status' => 'not-found',
            'state' => null,
        ];
        if ($serviceId === '' || PHP_OS_FAMILY !== 'Windows') {
            $result['status'] = 'not-supported';
            return $result;
        }

        $process = $this->runProcess(['sc.exe', 'query', $serviceId], (string) getcwd(), null, 15);
        $output = trim((string) ($process['stdout'] ?? '') . PHP_EOL . (string) ($process['stderr'] ?? ''));
        $result['exit_code'] = $process['exit_code'] ?? null;
        $result['output'] = $output;
        if ((int) ($process['exit_code'] ?? 1) !== 0) {
            return $result;
        }
        if (preg_match('/STATE\s*:\s*\d+\s+([A-Z_]+)/i', $output, $matches) === 1) {
            $state = strtoupper($matches[1]);
            $result['state'] = $state;
            $result['status'] = $state === 'RUNNING' ? 'running' : 'stopped';
            return $result;
        }
        $result['status'] = 'unknown';
        return $result;
    }

    private function winSwOutputIndicatesAlreadyRunning(array $process): bool
    {
        $output = strtolower(trim((string) ($process['stdout'] ?? '') . ' ' . (string) ($process['stderr'] ?? '')));
        return str_contains($output, 'already running') || str_contains($output, 'service is running');
    }

    private function winSwServiceSpec(array $service): array
    {
        $serviceId = $this->sanitizeWindowsServiceId((string) ($service['id'] ?? 'pbb-runtime-service'));
        $root = $this->winSwServiceRoot();
        $serviceDir = $this->joinPath($root, $serviceId);
        return [
            'service_id' => $serviceId,
            'service_dir' => $serviceDir,
            'service_exe' => $this->joinPath($serviceDir, $serviceId . '.exe'),
            'service_config' => $this->joinPath($serviceDir, $serviceId . '.xml'),
            'log_dir' => $this->joinPath($serviceDir, 'logs'),
        ];
    }

    private function sanitizeWindowsServiceId(string $id): string
    {
        $id = trim($id) !== '' ? trim($id) : 'pbb-runtime-service';
        $id = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $id) ?: 'pbb-runtime-service';
        return substr($id, 0, 180);
    }

    private function winSwServiceRoot(): string
    {
        $programData = getenv('ProgramData');
        if (!is_string($programData) || trim($programData) === '') {
            $programData = 'C:\\ProgramData';
        }
        return $this->joinPath($programData, 'PBB\\Services');
    }

    private function winSwSourceBinaryPath(): string
    {
        return $this->joinPath(dirname(__DIR__), 'assets\\winsw\\WinSW-x64.exe');
    }

    private function buildWinSwXml(array $service, array $spec): string
    {
        $args = [];
        foreach (($service['args'] ?? []) as $arg) {
            $args[] = $this->windowsCommandLineArg((string) $arg);
        }
        $env = is_array($service['env'] ?? null) ? $service['env'] : [];
        $envLines = [];
        foreach ($env as $key => $value) {
            $name = preg_replace('/[^A-Za-z0-9_]/', '', (string) $key);
            if ($name === '') {
                continue;
            }
            $envLines[] = '  <env name="' . $this->xmlEscape($name) . '" value="' . $this->xmlEscape((string) $value) . '"/>';
        }

        $description = trim((string) ($service['notes'] ?? ''));
        if ($description === '') {
            $description = 'Project Bantay Bayan runtime service managed by Kit Setup.';
        }

        $lines = [
            '<service>',
            '  <id>' . $this->xmlEscape($spec['service_id']) . '</id>',
            '  <name>' . $this->xmlEscape((string) ($service['name'] ?? $spec['service_id'])) . '</name>',
            '  <description>' . $this->xmlEscape($description) . '</description>',
            '  <executable>' . $this->xmlEscape((string) ($service['command'] ?? '')) . '</executable>',
            '  <arguments>' . $this->xmlEscape(implode(' ', $args)) . '</arguments>',
            '  <workingdirectory>' . $this->xmlEscape((string) ($service['working_directory'] ?? '')) . '</workingdirectory>',
            '  <startmode>Automatic</startmode>',
            '  <stoptimeout>15 sec</stoptimeout>',
            '  <logpath>' . $this->xmlEscape($spec['log_dir']) . '</logpath>',
            '  <log mode="roll-by-size">',
            '    <sizeThreshold>10485760</sizeThreshold>',
            '    <keepFiles>8</keepFiles>',
            '  </log>',
            '  <onfailure action="restart" delay="10 sec"/>',
        ];
        foreach ($envLines as $line) {
            $lines[] = $line;
        }
        $lines[] = '</service>';
        return implode("\r\n", $lines) . "\r\n";
    }

    private function windowsCommandLineArg(string $value): string
    {
        if ($value === '') {
            return '""';
        }
        if (!preg_match('/[\s"]/', $value)) {
            return $value;
        }
        return '"' . str_replace('"', '\"', $value) . '"';
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function terminateRuntimeProcess(int $pid, string $serviceId, string $reason): array
    {
        if ($pid <= 0) {
            return [
                'status' => 'skipped',
                'message' => 'Runtime process did not have a valid PID.',
            ];
        }
        if (PHP_OS_FAMILY === 'Windows') {
            $process = $this->runProcess(['taskkill.exe', '/PID', (string) $pid, '/T', '/F'], (string) getcwd());
        } else {
            $process = $this->runProcess(['sh', '-c', 'kill -TERM ' . escapeshellarg((string) $pid)], (string) getcwd());
        }
        $status = (int) ($process['exit_code'] ?? 1) === 0 ? 'success' : 'warning';
        $output = trim((string) ($process['stdout'] ?? '') . ' ' . (string) ($process['stderr'] ?? ''));
        if ($status === 'warning' && PHP_OS_FAMILY === 'Windows' && preg_match('/process\s+"?' . preg_quote((string) $pid, '/') . '"?\s+not\s+found/i', $output)) {
            $status = 'success';
        }
        return [
            'status' => $status,
            'service_id' => $serviceId,
            'reason' => $reason,
            'command' => $process['command'] ?? '',
            'exit_code' => $process['exit_code'] ?? null,
            'message' => $status === 'success'
                ? (((int) ($process['exit_code'] ?? 1) === 0) ? 'Runtime service process was stopped.' : 'Runtime service process was already stopped.')
                : ('Runtime service process stop could not be confirmed' . ($output !== '' ? ': ' . $output : '.')),
        ];
    }

    private function writeRuntimeServiceCommandScript(string $scriptPath, array $service, string $stdoutPath, string $stderrPath): void
    {
        $lines = ['@echo off'];
        $lines[] = 'cd /d ' . $this->cmdQuote((string) ($service['working_directory'] ?? ''));
        $env = is_array($service['env'] ?? null) ? $service['env'] : [];
        foreach ($env as $key => $value) {
            $name = preg_replace('/[^A-Za-z0-9_]/', '', (string) $key);
            if ($name === '') {
                continue;
            }
            $lines[] = 'set "' . $name . '=' . str_replace('"', '\"', (string) $value) . '"';
        }
        $command = $this->cmdQuote((string) ($service['command'] ?? ''));
        $args = [];
        foreach (($service['args'] ?? []) as $arg) {
            $args[] = $this->cmdQuote((string) $arg);
        }
        $lines[] = trim($command . ' ' . implode(' ', $args)) . ' >> ' . $this->cmdQuote($stdoutPath) . ' 2>> ' . $this->cmdQuote($stderrPath);
        file_put_contents($scriptPath, implode("\r\n", $lines) . "\r\n");
    }

    private function waitForRuntimeServiceHealth(array $service): array
    {
        $health = is_array($service['health_check'] ?? null) ? $service['health_check'] : [];
        $timeoutSeconds = max(1, (float) ($health['timeout_seconds'] ?? 5));
        $deadline = microtime(true) + max(3, $timeoutSeconds);
        if (strtolower((string) ($health['type'] ?? '')) === 'process') {
            $last = $this->inspectRuntimeService($service);
            $sawSuccess = false;
            while (microtime(true) < $deadline) {
                $last = $this->inspectRuntimeService($service);
                if (($last['status'] ?? '') === 'success') {
                    $sawSuccess = true;
                } elseif ($sawSuccess) {
                    $last['status'] = (($service['required'] ?? true) !== false) ? 'failed' : 'warning';
                    $last['message'] = 'Runtime process service exited before the health check stability window completed.';
                    return $last;
                }
                usleep(250000);
            }
            return $last;
        }
        $last = $this->inspectRuntimeService($service);
        while (($last['status'] ?? '') !== 'success' && microtime(true) < $deadline) {
            usleep(250000);
            $last = $this->inspectRuntimeService($service);
        }
        return $last;
    }

    private function cmdQuote(string $value): string
    {
        return '"' . str_replace('"', '\"', $value) . '"';
    }

    private function collectRuntimeServices(array $config): array
    {
        $services = [];
        foreach ($config['apps'] ?? [] as $app) {
            if (!is_array($app) || ($app['enabled'] ?? true) === false || (string) ($app['install_scope'] ?? 'local') !== 'local') {
                continue;
            }
            $appId = (string) ($app['id'] ?? '');
            foreach ($this->appRuntimeServices($appId, $app, $config) as $service) {
                $services[] = $service;
            }
        }
        return $services;
    }

    private function appRuntimeServices(string $appId, array $app, array $config): array
    {
        $sources = [
            ['name' => 'app.runtime_services', 'items' => $app['runtime_services'] ?? null],
            ['name' => 'app.config.runtime_services', 'items' => $app['config']['runtime_services'] ?? null],
            ['name' => 'release.runtime_services', 'items' => $this->releaseRuntimeServices($app)],
            ['name' => 'installed.runtime_services', 'items' => $this->installedRuntimeServices($app)],
        ];

        $services = [];
        foreach ($sources as $source) {
            if (!is_array($source['items'])) {
                continue;
            }
            foreach ($source['items'] as $service) {
                if (is_array($service)) {
                    $services[] = $this->normalizeRuntimeService($service, $appId, $app, $config, (string) $source['name']);
                }
            }
        }

        $byId = [];
        $anonymous = [];
        foreach ($services as $service) {
            $id = (string) ($service['id'] ?? '');
            if ($id !== '') {
                $byId[$id] = $service;
            } else {
                $anonymous[] = $service;
            }
        }
        return array_values(array_merge($byId, $anonymous));
    }

    private function normalizeRuntimeService(array $service, string $appId, array $app, array $config, string $source): array
    {
        $id = trim((string) ($service['id'] ?? ''));
        $healthCheck = is_array($service['health_check'] ?? null)
            ? $service['health_check']
            : (is_array($service['healthcheck'] ?? null) ? $service['healthcheck'] : []);

        $normalized = [
            'app_id' => $appId,
            'id' => $id !== '' ? $id : trim($appId . '-runtime-service'),
            'name' => (string) ($service['name'] ?? $id),
            'type' => (string) ($service['type'] ?? $service['kind'] ?? 'background_process'),
            'required' => ($service['required'] ?? true) !== false,
            'required_for_smoke' => ($service['required_for_smoke'] ?? false) === true,
            'manager' => (string) ($service['manager'] ?? 'kit'),
            'working_directory' => $this->resolveRuntimeServiceValue((string) ($service['working_directory'] ?? $service['cwd'] ?? '{app.install_path}'), $app, $config),
            'command' => $this->resolveRuntimeServiceValue((string) ($service['command'] ?? ''), $app, $config),
            'args' => [],
            'env' => $this->resolveRuntimeServiceValue(is_array($service['env'] ?? null) ? $service['env'] : [], $app, $config),
            'health_check' => $this->resolveRuntimeServiceValue($healthCheck, $app, $config),
            'logs' => $this->resolveRuntimeServiceValue(is_array($service['logs'] ?? null) ? $service['logs'] : [], $app, $config),
            'notes' => (string) ($service['notes'] ?? ''),
            'source' => $source,
        ];

        $args = is_array($service['args'] ?? null) ? $service['args'] : [];
        foreach ($args as $arg) {
            $normalized['args'][] = $this->resolveRuntimeServiceValue((string) $arg, $app, $config);
        }

        return $normalized;
    }

    private function resolveRuntimeServiceValue($value, array $app, array $config)
    {
        if (is_array($value)) {
            $resolved = [];
            foreach ($value as $key => $item) {
                $resolved[$key] = $this->resolveRuntimeServiceValue($item, $app, $config);
            }
            return $resolved;
        }
        if (!is_string($value)) {
            return $value;
        }

        $appConfig = is_array($app['config'] ?? null) ? $app['config'] : [];
        $appUrl = (string) ($app['app_url'] ?? $appConfig['app_url'] ?? '');
        $appHost = $appUrl !== '' ? (string) (parse_url($appUrl, PHP_URL_HOST) ?: '') : '';
        $replacements = [
            '{app.install_path}' => (string) ($app['install_path'] ?? $appConfig['install_path'] ?? ''),
            '{app.public_path}' => (string) ($app['public_path'] ?? $appConfig['public_path'] ?? ''),
            '{app.app_url}' => $appUrl,
            '{app.host}' => $appHost,
            '{runtime.php_binary}' => (string) ($config['runtime']['php_binary'] ?? 'php'),
        ];
        return strtr($value, $replacements);
    }

    private function releaseRuntimeServices(array $app): array
    {
        $release = is_array($app['release'] ?? null) ? $app['release'] : null;
        if (!is_array($release)) {
            $releasePath = (string) ($app['release_path'] ?? $app['config']['release_path'] ?? '');
            if ($releasePath !== '') {
                $release = $this->readOptionalJson($this->joinPath($releasePath, 'release.json'));
            }
        }
        if (!is_array($release)) {
            return [];
        }

        $services = $release['runtime_services'] ?? $release['installer']['runtime_services'] ?? null;
        return is_array($services) ? $services : [];
    }

    private function installedRuntimeServices(array $app): array
    {
        $services = [];
        foreach (['install_manifest', 'install_report'] as $artifactKey) {
            $artifact = $this->readAppArtifact($app, $artifactKey);
            $json = is_array($artifact) && ($artifact['exists'] ?? false) === true && is_array($artifact['json'] ?? null)
                ? $artifact['json']
                : null;
            if (!is_array($json) || !is_array($json['runtime_services'] ?? null)) {
                continue;
            }
            foreach ($json['runtime_services'] as $service) {
                if (is_array($service)) {
                    $services[] = $service;
                }
            }
        }
        return $services;
    }

    private function inspectRuntimeService(array $service): array
    {
        $health = is_array($service['health_check'] ?? null) ? $service['health_check'] : [];
        $required = ($service['required'] ?? true) !== false;
        $requiredForSmoke = ($service['required_for_smoke'] ?? false) === true;
        $check = [
            'app_id' => $service['app_id'] ?? null,
            'id' => $service['id'] ?? null,
            'name' => $service['name'] ?? null,
            'type' => $service['type'] ?? null,
            'required' => $required,
            'required_for_smoke' => $requiredForSmoke,
            'manager' => $service['manager'] ?? null,
            'service_wrapper' => self::SERVICE_WRAPPER,
            'service_id' => $this->winSwServiceSpec($service)['service_id'],
            'working_directory' => $service['working_directory'] ?? null,
            'command' => $service['command'] ?? null,
            'args' => $service['args'] ?? [],
            'env' => $service['env'] ?? [],
            'health_check' => $health,
            'status' => 'warning',
            'message' => 'Runtime service does not declare a supported health_check.',
        ];
        $windowsService = $this->queryWindowsService($check['service_id']);
        $check['windows_service'] = $windowsService;

        $type = strtolower((string) ($health['type'] ?? ''));
        if ($type === 'tcp') {
            $host = (string) ($health['host'] ?? '127.0.0.1');
            $port = (int) ($health['port'] ?? 0);
            $timeoutSeconds = (float) ($health['timeout_seconds'] ?? 3);
            $tcp = $this->inspectTcpPort($host, $port, $timeoutSeconds);
            $check['tcp'] = $tcp;
            $serviceRunning = ($windowsService['status'] ?? '') === 'running';
            $check['status'] = ($tcp['status'] ?? '') === 'passed' && $serviceRunning ? 'success' : ($required ? 'failed' : 'warning');
            $check['message'] = ($tcp['status'] ?? '') === 'passed' && $serviceRunning
                ? 'WinSW service is running and runtime service health check passed.'
                : 'Runtime service health check failed: ' . (string) ($tcp['message'] ?? 'TCP port is not reachable.');
            if (!$serviceRunning) {
                $check['message'] = 'WinSW service is not running.';
            }
            return $check;
        }

        if ($type === 'process') {
            $check['status'] = ($windowsService['status'] ?? '') === 'running' ? 'success' : ($required ? 'failed' : 'warning');
            $check['message'] = ($windowsService['status'] ?? '') === 'running'
                ? 'WinSW service is running.'
                : 'WinSW service is not running.';
            return $check;
        }

        if ($required && $requiredForSmoke) {
            $check['status'] = 'failed';
            $check['message'] = 'Runtime service required for smoke is missing a supported health_check.';
        }
        return $check;
    }

    private function inspectRuntimeServiceProcess(array $service): array
    {
        $command = strtolower(basename(str_replace('\\', '/', (string) ($service['command'] ?? ''))));
        $needles = [];
        if ($command !== '') {
            $needles[] = $command;
        }
        foreach (($service['args'] ?? []) as $arg) {
            $arg = trim((string) $arg);
            if ($arg !== '') {
                $needles[] = strtolower($arg);
            }
        }
        if (count($needles) === 0) {
            return [
                'status' => 'failed',
                'message' => 'No command or args are available for process matching.',
            ];
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $conditions = [];
            foreach ($needles as $needle) {
                $conditions[] = '$_.CommandLine.ToLower().Contains(' . $this->powershellSingleQuoted($needle) . ')';
            }
            $script = '$p = Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -and ' . implode(' -and ', $conditions) . ' } | Select-Object -First 1; if ($p) { Write-Output $p.ProcessId; exit 0 } exit 1';
            $process = $this->runProcess(['powershell.exe', '-NoProfile', '-ExecutionPolicy', 'Bypass', '-Command', $script], (string) getcwd());
            $pid = trim((string) ($process['stdout'] ?? ''));
            if ((int) ($process['exit_code'] ?? 1) === 0 && $pid !== '') {
                return [
                    'status' => 'passed',
                    'pid' => $pid,
                    'message' => 'Matching process is running.',
                ];
            }
        } else {
            $process = $this->runProcess(['ps', '-eo', 'pid,args'], (string) getcwd());
            $stdout = strtolower((string) ($process['stdout'] ?? ''));
            $matched = true;
            foreach ($needles as $needle) {
                if (!str_contains($stdout, $needle)) {
                    $matched = false;
                    break;
                }
            }
            if ($matched) {
                return [
                    'status' => 'passed',
                    'message' => 'Matching process is running.',
                ];
            }
        }

        return [
            'status' => 'failed',
            'message' => 'No matching process was found.',
        ];
    }

    private function powershellSingleQuoted(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    private function inspectTcpPort(string $host, int $port, float $timeoutSeconds): array
    {
        if ($host === '' || $port <= 0 || $port > 65535) {
            return [
                'host' => $host,
                'port' => $port,
                'status' => 'failed',
                'message' => 'Invalid TCP health check host or port.',
            ];
        }

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, max(0.1, $timeoutSeconds));
        if (is_resource($socket)) {
            fclose($socket);
            return [
                'host' => $host,
                'port' => $port,
                'status' => 'passed',
                'message' => 'TCP port is reachable.',
            ];
        }

        return [
            'host' => $host,
            'port' => $port,
            'status' => 'failed',
            'message' => $errstr !== '' ? $errstr : 'TCP port is not reachable.',
        ];
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
        foreach (($vhosts['warnings'] ?? []) as $warning) {
            $warnings[] = (string) $warning;
        }
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
                'web_server_requirements' => $vhosts['requirements'] ?? [],
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
            'pbb-stub' => 'stub',
        ];
        $domains = is_array($config['domains'] ?? null) ? $config['domains'] : [];
        $entries = [];
        $requirements = [];
        $warnings = [];
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
            $appRequirements = $this->appWebServerRequirements($appId, $app);

            $entries[] = [
                'app_id' => $appId,
                'server_name' => $host,
                'server_aliases' => $aliases,
                'document_root' => $documentRoot,
                'web_server_requirements' => $appRequirements,
            ];
            foreach ($appRequirements as $requirement) {
                $requirements[] = [
                    'app_id' => $appId,
                    'server_name' => $host,
                    'requirement' => $requirement,
                ];
            }
            $blocks[] = $this->renderApacheVhostBlock($host, $aliases, $documentRoot, $certificateFile, $privateKeyFile, $chainFile, $appRequirements, $warnings, $appId);
        }

        return [
            'entries' => $entries,
            'requirements' => $requirements,
            'warnings' => $warnings,
            'content' => implode(PHP_EOL, $blocks) . PHP_EOL,
        ];
    }

    private function renderApacheVhostBlock(string $host, array $aliases, string $documentRoot, string $certificateFile, string $privateKeyFile, string $chainFile, array $webServerRequirements = [], array &$warnings = [], string $appId = ''): string
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
        foreach ($webServerRequirements as $requirement) {
            foreach ($this->renderApacheRequirementLines($requirement, $warnings, $appId) as $line) {
                $lines[] = $line;
            }
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

    private function renderApacheRequirementLines(array $requirement, array &$warnings, string $appId): array
    {
        if (!$this->isWebsocketProxyRequirement($requirement)) {
            return [];
        }

        $path = (string) ($requirement['server_path'] ?? $requirement['path_prefix'] ?? '');
        $upstream = (string) ($requirement['upstream_url'] ?? $requirement['upstream'] ?? '');
        $id = (string) ($requirement['id'] ?? 'websocket_proxy');
        if (!$this->isSafeApacheLocationPath($path) || !$this->isSafeApacheProxyTarget($upstream)) {
            $warnings[] = trim($appId . ' ' . $id . ': skipped unsafe websocket proxy requirement.');
            return [];
        }

        $lines = [
            '    # App web-server requirement: ' . $id,
            '    ProxyPreserveHost On',
        ];
        $directives = is_array($requirement['directives'] ?? null) ? $requirement['directives'] : [];
        if (isset($directives['ProxyWebsocketFallbackToProxyHttp'])) {
            $value = (string) $directives['ProxyWebsocketFallbackToProxyHttp'];
            if (in_array($value, ['On', 'Off'], true)) {
                $lines[] = '    ProxyWebsocketFallbackToProxyHttp ' . $value;
            }
        }
        $lines[] = '    ProxyPass "' . $path . '" "' . $upstream . '"';
        $lines[] = '    ProxyPassReverse "' . $path . '" "' . $upstream . '"';
        return $lines;
    }

    private function isWebsocketProxyRequirement(array $requirement): bool
    {
        $type = strtolower((string) ($requirement['type'] ?? $requirement['kind'] ?? ''));
        return $type === 'websocket_proxy';
    }

    private function isSafeApacheLocationPath(string $path): bool
    {
        return $path !== ''
            && $path[0] === '/'
            && strpos($path, '"') === false
            && strpos($path, "\n") === false
            && strpos($path, "\r") === false;
    }

    private function isSafeApacheProxyTarget(string $target): bool
    {
        if ($target === '' || strpos($target, '"') !== false || strpos($target, "\n") !== false || strpos($target, "\r") !== false) {
            return false;
        }
        $parts = parse_url($target);
        if (!is_array($parts)) {
            return false;
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        return in_array($scheme, ['ws', 'wss', 'http', 'https'], true) && isset($parts['host']);
    }

    private function appWebServerRequirements(string $appId, array $app): array
    {
        $requirements = [];
        foreach ([
            $app['web_server']['requirements'] ?? null,
            $app['config']['web_server']['requirements'] ?? null,
            $this->releaseWebServerRequirements($app),
            $this->installedWebServerRequirements($app),
        ] as $source) {
            if (!is_array($source)) {
                continue;
            }
            foreach ($source as $requirement) {
                if (is_array($requirement)) {
                    $requirements[] = $this->normalizeWebServerRequirement($requirement, $app);
                }
            }
        }

        $byId = [];
        $anonymous = [];
        foreach ($requirements as $requirement) {
            $id = (string) ($requirement['id'] ?? '');
            if ($id !== '') {
                $byId[$id] = $requirement;
            } else {
                $anonymous[] = $requirement;
            }
        }
        return array_values(array_merge($byId, $anonymous));
    }

    private function normalizeWebServerRequirement(array $requirement, array $app): array
    {
        return $this->resolveAppPlaceholders($requirement, $app);
    }

    private function resolveAppPlaceholders($value, array $app)
    {
        if (is_array($value)) {
            $resolved = [];
            foreach ($value as $key => $item) {
                $resolved[$key] = $this->resolveAppPlaceholders($item, $app);
            }
            return $resolved;
        }
        if (!is_string($value)) {
            return $value;
        }

        $appConfig = is_array($app['config'] ?? null) ? $app['config'] : [];
        $appUrl = (string) ($app['app_url'] ?? $appConfig['app_url'] ?? '');
        $appHost = $appUrl !== '' ? (string) (parse_url($appUrl, PHP_URL_HOST) ?: '') : '';
        return strtr($value, [
            '{app.url}' => $appUrl,
            '{app.app_url}' => $appUrl,
            '{app.host}' => $appHost,
            '{app.install_path}' => (string) ($app['install_path'] ?? $appConfig['install_path'] ?? ''),
            '{app.public_path}' => (string) ($app['public_path'] ?? $appConfig['public_path'] ?? ''),
        ]);
    }

    private function releaseWebServerRequirements(array $app): array
    {
        $release = is_array($app['release'] ?? null) ? $app['release'] : null;
        if (!is_array($release)) {
            $releasePath = (string) ($app['release_path'] ?? $app['config']['release_path'] ?? '');
            if ($releasePath !== '') {
                $release = $this->readOptionalJson($this->joinPath($releasePath, 'release.json'));
            }
        }
        if (!is_array($release)) {
            return [];
        }

        $requirements = $release['web_server']['requirements'] ?? $release['installer']['web_server']['requirements'] ?? null;
        return is_array($requirements) ? $requirements : [];
    }

    private function installedWebServerRequirements(array $app): array
    {
        $requirements = [];
        foreach (['install_manifest', 'install_report'] as $artifactKey) {
            $artifact = $this->readAppArtifact($app, $artifactKey);
            $json = is_array($artifact) && ($artifact['exists'] ?? false) === true && is_array($artifact['json'] ?? null)
                ? $artifact['json']
                : null;
            if (!is_array($json)) {
                continue;
            }

            foreach ([
                $json['web_server']['requirements'] ?? null,
                $json['web_server_requirements'] ?? null,
            ] as $source) {
                if (!is_array($source)) {
                    continue;
                }
                foreach ($source as $requirement) {
                    if (is_array($requirement)) {
                        $requirements[] = $requirement;
                    }
                }
            }
        }
        return $requirements;
    }

    private function apachePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    private function deployStagedPackage(string $stagingPath, string $targetPath, string $runDir, string $appId, array $allowedTargetRoots, ?callable $progress = null): array
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

        $this->copyDirectory($stagingPath, $targetPath, $progress);

        return [
            'backup_path' => $backupPath,
        ];
    }

    private function extractPackageArchive(string $archivePath, string $runDir, string $appId, ?callable $progress = null): array
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
            $files = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                $normalized = str_replace('\\', '/', $name);
                if ($normalized === '' || str_starts_with($normalized, '/') || preg_match('/(^|\/)\.\.(\/|$)/', $normalized) === 1) {
                    throw new RuntimeException('Unsafe archive path: ' . $name);
                }
                if (!str_ends_with($normalized, '/')) {
                    $files[] = ['name' => $name, 'normalized' => $normalized];
                }
            }

            $total = count($files);
            $interval = max(1, (int) floor(max(1, $total) / 25));
            $current = 0;
            foreach ($files as $file) {
                $destination = $this->joinPath($stagingPath, str_replace('/', DIRECTORY_SEPARATOR, $file['normalized']));
                $this->ensureDirectory(dirname($destination));
                $stream = $zip->getStream($file['name']);
                if (!is_resource($stream)) {
                    throw new RuntimeException('Unable to read package archive entry: ' . $file['name']);
                }
                $output = fopen($destination, 'wb');
                if (!is_resource($output)) {
                    fclose($stream);
                    throw new RuntimeException('Unable to create extracted package file: ' . $destination);
                }
                try {
                    stream_copy_to_stream($stream, $output);
                } finally {
                    fclose($output);
                    fclose($stream);
                }
                $current++;
                if ($progress !== null && ($current === 1 || $current === $total || $current % $interval === 0)) {
                    $progress($current, $total);
                }
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
        $this->writeLine('Usage: php bin/kit-setup.php --config <path> [--action detect|hub-resolve|prepare-packages|dns-plan|dns-apply|dns-client-apply|dns-verify|firewall-apply|service-plan|service-start|service-stop|service-verify|ssl-plan|ssl-apply|remote-check|smoke-check|stage-report|finish-report|plan|preflight|install|populate] [--run-dir <path>] [--run-id <id>] [--app <app-id>]');
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
        $apacheBinary = (string) ($platform['apache_binary'] ?? '');
        if ($apacheBinary === '') {
            $apacheBinary = $defaultApache;
        }
        $mysqlBinary = (string) ($platform['mysql_binary'] ?? '');
        if ($mysqlBinary === '') {
            $mysqlBinary = $defaultMysql;
        }
        $checks = [
            $this->inspectExecutableTool('apache', $apacheBinary, ['-v']),
            $this->inspectExecutableTool('mysql', $mysqlBinary, ['--version']),
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
        $appFilter = (string) ($context['app_filter'] ?? '');
        $readinessOnly = (bool) ($config['data_prep']['readiness_check'] ?? false);
        foreach ($config['apps'] as $app) {
            if (!is_array($app) || ($app['enabled'] ?? true) === false) {
                continue;
            }
            if ($appFilter !== '' && (string) ($app['id'] ?? '') !== $appFilter) {
                continue;
            }

            $check = $this->inspectAppSmokeEndpoint($app);
            $checks[] = $check;
            if (($check['status'] ?? '') === 'failed') {
                $errors[] = (string) ($check['message'] ?? ('Smoke check failed: ' . ($app['id'] ?? 'unknown')));
            } elseif (($check['status'] ?? '') === 'warning') {
                $warnings[] = (string) ($check['message'] ?? ('Smoke check warning: ' . ($app['id'] ?? 'unknown')));
            }

            foreach ($this->appRuntimeServices((string) ($app['id'] ?? ''), $app, $config) as $service) {
                if (($service['required_for_smoke'] ?? false) !== true) {
                    continue;
                }
                $serviceCheck = $this->inspectRuntimeService($service);
                $serviceCheck['check_type'] = 'runtime_service';
                $checks[] = $serviceCheck;
                if (($serviceCheck['status'] ?? '') === 'failed') {
                    $errors[] = (string) ($serviceCheck['message'] ?? ('Runtime service smoke prerequisite failed: ' . ($service['id'] ?? 'unknown')));
                } elseif (($serviceCheck['status'] ?? '') === 'warning') {
                    $warnings[] = (string) ($serviceCheck['message'] ?? ('Runtime service smoke prerequisite warning: ' . ($service['id'] ?? 'unknown')));
                }
            }

            if (!$readinessOnly) {
                foreach ($this->appWebServerRequirements((string) ($app['id'] ?? ''), $app) as $requirement) {
                    if (!$this->isWebsocketProxyRequirement($requirement)) {
                        continue;
                    }
                    $websocketCheck = $this->inspectWebsocketSmokeRequirement($app, $requirement);
                    $checks[] = $websocketCheck;
                    if (($websocketCheck['status'] ?? '') === 'failed') {
                        $errors[] = (string) ($websocketCheck['message'] ?? ('Websocket smoke check failed: ' . ($app['id'] ?? 'unknown')));
                    } elseif (($websocketCheck['status'] ?? '') === 'warning') {
                        $warnings[] = (string) ($websocketCheck['message'] ?? ('Websocket smoke check warning: ' . ($app['id'] ?? 'unknown')));
                    }
                }
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

    private function inspectWebsocketSmokeRequirement(array $app, array $requirement): array
    {
        $appId = (string) ($app['id'] ?? 'unknown');
        $requirementId = (string) ($requirement['id'] ?? 'websocket_proxy');
        $url = $this->websocketSmokeUrl($app, $requirement);
        $smokeTest = is_array($requirement['smoke_test'] ?? null) ? $requirement['smoke_test'] : [];
        $timeoutSeconds = (float) ($smokeTest['timeout_seconds'] ?? $requirement['timeout_seconds'] ?? ($app['smoke']['timeout_seconds'] ?? 5));
        $host = $this->hostFromUrlOrHost($url);
        $dns = $this->inspectDnsHost($host);
        $websocket = $this->inspectWebsocketEndpoint($url, $timeoutSeconds, $smokeTest);

        $status = 'success';
        $message = 'Websocket route is reachable.';
        if ($url === '' || $host === '') {
            $status = 'failed';
            $message = 'Websocket requirement is missing a valid public websocket URL.';
        } elseif (($dns['status'] ?? '') !== 'passed') {
            $status = 'failed';
            $message = 'Websocket host cannot be resolved: ' . $host;
        } elseif (($websocket['status'] ?? '') !== 'passed') {
            $status = 'failed';
            $message = 'Websocket route is not reachable: ' . $url;
        }

        return [
            'app_id' => $appId,
            'check_type' => 'websocket',
            'requirement_id' => $requirementId,
            'install_scope' => (string) ($app['install_scope'] ?? 'local'),
            'url' => $url,
            'host' => $host,
            'phase' => (string) ($requirement['smoke_test_phase'] ?? $smokeTest['phase'] ?? 'post-vhost'),
            'install_blocking' => ($requirement['install_blocking'] ?? false) === true,
            'smoke_test' => $smokeTest,
            'dns' => $dns,
            'websocket' => $websocket,
            'status' => $status,
            'message' => $message,
        ];
    }

    private function websocketSmokeUrl(array $app, array $requirement): string
    {
        $explicit = (string) ($requirement['public_websocket_url'] ?? $requirement['websocket_url'] ?? '');
        if ($explicit !== '') {
            return $explicit;
        }

        $smokeTest = is_array($requirement['smoke_test'] ?? null) ? $requirement['smoke_test'] : [];
        $path = (string) ($smokeTest['path'] ?? $requirement['server_path'] ?? $requirement['path_prefix'] ?? '/');
        if ($path === '') {
            $path = '/';
        }
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }
        $query = is_array($smokeTest['query'] ?? null) ? http_build_query($smokeTest['query']) : '';
        if ($query !== '') {
            $path .= (str_contains($path, '?') ? '&' : '?') . $query;
        }

        $base = (string) ($app['app_url'] ?? $app['config']['app_url'] ?? '');
        if ($base === '') {
            return '';
        }

        $parts = parse_url($base);
        if (!is_array($parts) || !isset($parts['host'])) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https')) === 'http' ? 'ws' : 'wss';
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        return $scheme . '://' . $parts['host'] . $port . $path;
    }

    private function inspectWebsocketEndpoint(string $url, float $timeoutSeconds, array $smokeTest = []): array
    {
        $result = [
            'url' => $url,
            'status' => 'failed',
            'handshake_status' => null,
            'expected_status' => (int) ($smokeTest['expect_status'] ?? 101),
            'expected_first_message_type' => (string) ($smokeTest['expect_first_message_type'] ?? ''),
            'phase' => 'parse',
            'message' => '',
        ];

        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['host'])) {
            $result['message'] = 'Invalid websocket URL.';
            return $result;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'ws'));
        if (!in_array($scheme, ['ws', 'wss'], true)) {
            $result['message'] = 'Unsupported websocket URL scheme.';
            return $result;
        }

        $host = (string) $parts['host'];
        $port = (int) ($parts['port'] ?? ($scheme === 'wss' ? 443 : 80));
        $path = (string) ($parts['path'] ?? '/');
        if ($path === '') {
            $path = '/';
        }
        if (isset($parts['query'])) {
            $path .= '?' . $parts['query'];
        }

        $target = ($scheme === 'wss' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $result['target'] = $target;
        $result['request_path'] = $path;
        $result['phase'] = $scheme === 'wss' ? 'tls_connect' : 'tcp_connect';
        $errno = 0;
        $errstr = '';
        $timeout = max(1.0, $timeoutSeconds);
        $context = stream_context_create(['ssl' => $this->tlsOptions()]);
        $socket = @stream_socket_client($target, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        if (!is_resource($socket)) {
            $result['connect_errno'] = $errno;
            $result['connect_error'] = $errstr;
            $result['message'] = trim($errstr) !== '' ? $errstr : 'Unable to connect to websocket endpoint.';
            return $result;
        }

        stream_set_timeout($socket, (int) ceil($timeout));
        $key = base64_encode(random_bytes(16));
        $hostHeader = $host . ((isset($parts['port']) && !in_array($port, [80, 443], true)) ? ':' . $port : '');
        $origin = ($scheme === 'wss' ? 'https://' : 'http://') . $hostHeader;
        $headers = [
            'Host' => $hostHeader,
            'Upgrade' => 'websocket',
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Key' => $key,
            'Sec-WebSocket-Version' => '13',
            'Origin' => $origin,
        ];
        if (is_array($smokeTest['headers'] ?? null)) {
            foreach ($smokeTest['headers'] as $name => $value) {
                $name = trim((string) $name);
                if ($name !== '') {
                    $headers[$name] = (string) $value;
                }
            }
        }
        $request = "GET {$path} HTTP/1.1\r\n"
            . implode('', array_map(static fn($name, $value) => $name . ': ' . $value . "\r\n", array_keys($headers), $headers))
            . "\r\n";
        $result['phase'] = 'handshake';
        $result['request_headers'] = $this->redactWebsocketSmokeHeaders($headers);
        $result['origin'] = (string) ($headers['Origin'] ?? $origin);
        fwrite($socket, $request);
        $response = '';
        while (!feof($socket) && strpos($response, "\r\n\r\n") === false && strlen($response) < 8192) {
            $chunk = fgets($socket, 1024);
            if ($chunk === false) {
                break;
            }
            $response .= $chunk;
        }

        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $response, $matches) === 1) {
            $result['handshake_status'] = (int) $matches[1];
        }
        $result['response_preview'] = trim(substr(str_replace("\r", '', $response), 0, 700));
        $expectedStatus = (int) ($smokeTest['expect_status'] ?? 101);
        $result['status'] = ((int) ($result['handshake_status'] ?? 0)) === $expectedStatus ? 'passed' : 'failed';
        $result['phase'] = $result['status'] === 'passed' ? 'post-handshake' : 'handshake';
        $result['message'] = $result['status'] === 'passed'
            ? 'Websocket handshake succeeded.'
            : 'Websocket handshake did not return HTTP ' . $expectedStatus . '.';
        if ($result['status'] === 'passed' && (string) ($smokeTest['expect_first_message_type'] ?? '') !== '') {
            $messageCheck = $this->inspectWebsocketFirstMessage($socket, (string) $smokeTest['expect_first_message_type'], $timeout);
            $result['first_message'] = $messageCheck;
            $result['status'] = ($messageCheck['status'] ?? '') === 'passed' ? 'passed' : 'failed';
            $result['phase'] = $result['status'] === 'passed' ? 'complete' : 'first-message';
            $result['message'] = $result['status'] === 'passed'
                ? 'Websocket handshake and first message check succeeded.'
                : 'Websocket first message did not match expected type.';
        } else {
            $result['phase'] = $result['status'] === 'passed' ? 'complete' : 'handshake';
        }
        fclose($socket);
        return $result;
    }

    private function redactWebsocketSmokeHeaders(array $headers): array
    {
        $redacted = [];
        foreach ($headers as $name => $value) {
            $key = (string) $name;
            if (preg_match('/authorization|token|secret|key/i', $key) === 1 && !in_array(strtolower($key), ['sec-websocket-key'], true)) {
                $redacted[$key] = '[redacted]';
            } else {
                $redacted[$key] = (string) $value;
            }
        }
        if (isset($redacted['Sec-WebSocket-Key'])) {
            $redacted['Sec-WebSocket-Key'] = '[generated]';
        }
        return $redacted;
    }

    private function inspectWebsocketFirstMessage($socket, string $expectedType, float $timeoutSeconds): array
    {
        $frame = $this->readWebsocketFrame($socket, $timeoutSeconds);
        $result = [
            'expected_type' => $expectedType,
            'status' => 'failed',
            'message' => 'No websocket message was received after handshake.',
        ];
        if (($frame['status'] ?? '') !== 'passed') {
            return array_merge($result, $frame);
        }
        $payload = (string) ($frame['payload'] ?? '');
        $decoded = json_decode($payload, true);
        $type = is_array($decoded) ? (string) ($decoded['type'] ?? $decoded['event_type'] ?? $decoded['event'] ?? '') : '';
        $result['payload_preview'] = substr($payload, 0, 700);
        $result['type'] = $type;
        $result['status'] = $type === $expectedType ? 'passed' : 'failed';
        $result['message'] = $result['status'] === 'passed'
            ? 'Expected websocket first message type was received.'
            : 'Unexpected websocket first message type.';
        return $result;
    }

    private function readWebsocketFrame($socket, float $timeoutSeconds): array
    {
        stream_set_timeout($socket, (int) ceil(max(1.0, $timeoutSeconds)));
        $header = fread($socket, 2);
        if ($header === false || strlen($header) < 2) {
            return ['status' => 'failed', 'message' => 'Unable to read websocket frame header.'];
        }
        $bytes = array_values(unpack('C2', $header));
        $opcode = $bytes[0] & 0x0f;
        $masked = ($bytes[1] & 0x80) === 0x80;
        $length = $bytes[1] & 0x7f;
        if ($length === 126) {
            $extended = fread($socket, 2);
            if ($extended === false || strlen($extended) < 2) {
                return ['status' => 'failed', 'message' => 'Unable to read websocket extended frame length.'];
            }
            $length = unpack('n', $extended)[1];
        } elseif ($length === 127) {
            $extended = fread($socket, 8);
            if ($extended === false || strlen($extended) < 8) {
                return ['status' => 'failed', 'message' => 'Unable to read websocket extended frame length.'];
            }
            $parts = unpack('N2', $extended);
            $length = ($parts[1] * 4294967296) + $parts[2];
            if ($length > 65536) {
                return ['status' => 'failed', 'message' => 'Websocket first frame is too large for smoke diagnostics.'];
            }
        }
        $mask = $masked ? fread($socket, 4) : '';
        $payload = '';
        while (strlen($payload) < $length) {
            $chunk = fread($socket, min(8192, $length - strlen($payload)));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $payload .= $chunk;
        }
        if (strlen($payload) < $length) {
            return ['status' => 'failed', 'message' => 'Unable to read complete websocket first frame.'];
        }
        if ($masked && strlen($mask) === 4) {
            $unmasked = '';
            for ($i = 0; $i < strlen($payload); $i += 1) {
                $unmasked .= $payload[$i] ^ $mask[$i % 4];
            }
            $payload = $unmasked;
        }
        if ($opcode !== 1) {
            return [
                'status' => 'failed',
                'opcode' => $opcode,
                'message' => 'Websocket first frame was not a text frame.',
            ];
        }
        return [
            'status' => 'passed',
            'opcode' => $opcode,
            'payload' => $payload,
            'message' => 'Websocket first text frame was received.',
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

    private function applyResolvedHubConfigFromRunDir(array $config, string $runDir): array
    {
        $path = $this->joinPath($runDir, 'hub-resolved-config.json');
        $resolved = $this->readOptionalJson($path);
        if (!is_array($resolved)) {
            return $config;
        }

        if (isset($resolved['kit']) && is_array($resolved['kit'])) {
            if (!isset($config['kit']) || !is_array($config['kit'])) {
                $config['kit'] = [];
            }
            foreach (['hub_record_id', 'node_id', 'node_name', 'deployment', 'domain', 'location_codes'] as $key) {
                if (array_key_exists($key, $resolved['kit'])) {
                    $config['kit'][$key] = $resolved['kit'][$key];
                }
            }
        }

        if (isset($resolved['shared']['hub']) && is_array($resolved['shared']['hub'])) {
            if (!isset($config['shared']) || !is_array($config['shared'])) {
                $config['shared'] = [];
            }
            $config['shared']['hub'] = $resolved['shared']['hub'];
        }

        if (isset($resolved['hub']) && is_array($resolved['hub'])) {
            if (!isset($config['hub']) || !is_array($config['hub'])) {
                $config['hub'] = [];
            }
            foreach (['base_url', 'hub_id', 'token_env', 'auto_resolve'] as $key) {
                if (array_key_exists($key, $resolved['hub'])) {
                    $config['hub'][$key] = $resolved['hub'][$key];
                }
            }
        }

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

    private function discoverApps(array $config, bool $requireInstaller = true): array
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
            $requiredFields = $requireInstaller ? ['app', 'version', 'installer'] : ['app', 'version'];
            foreach ($requiredFields as $field) {
                if (!array_key_exists($field, $release)) {
                    throw new RuntimeException("Release {$releaseJsonPath} is missing {$field}.");
                }
            }
            if ((string) $release['app'] !== $id) {
                throw new RuntimeException("Release app id mismatch for {$id}; release.json says {$release['app']}.");
            }

            $unattendedPath = null;
            if ($requireInstaller) {
                $unattended = (string) ($release['installer']['unattended'] ?? '');
                if ($unattended === '') {
                    throw new RuntimeException("Release {$id} must declare installer.unattended.");
                }
                $candidateUnattendedPath = $this->joinPath($releasePath, $unattended);
                if (!is_file($candidateUnattendedPath)) {
                    throw new RuntimeException("Release {$id} unattended installer does not exist: {$candidateUnattendedPath}");
                }
                $unattendedPath = $this->absolutePath($candidateUnattendedPath);
            }

            $status = (string) ($release['installer']['status'] ?? '');
            $statusPath = $status !== '' ? $this->joinPath($releasePath, $status) : '';

            $apps[$id] = [
                'id' => $id,
                'config' => $appConfig,
                'release_path' => $this->absolutePath($releasePath),
                'release' => $release,
                'unattended_path' => $unattendedPath,
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

    private function planApp(array $app, array $kitConfig, string $runDir, string $runId, ?string $modeOverride = null, bool $includeChecksum = true): array
    {
        $appConfigPath = $this->joinPath($runDir, 'apps' . DIRECTORY_SEPARATOR . $app['id'] . '.config.json');
        $appReportPath = $this->joinPath($runDir, 'apps' . DIRECTORY_SEPARATOR . $app['id'] . '.report.json');
        $generatedConfig = $this->buildAppConfig($app, $kitConfig, $runId, $modeOverride);
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
            'checksum' => $includeChecksum ? $this->verifyChecksums($app) : [
                'status' => 'skipped',
                'message' => 'Checksum verification is skipped for post-install Data Prep.',
            ],
            'runtime_services' => $this->appRuntimeServices((string) $app['id'], $app, $kitConfig),
        ];
    }

    private function provisionAppDatabases(array $orderedApps, array $kitConfig, string $action): array
    {
        $databases = [];
        $failed = false;

        foreach ($orderedApps as $app) {
            $database = $app['config']['database'] ?? ($kitConfig['shared']['database'] ?? null);
            if (!is_array($database)) {
                continue;
            }
            $database = $this->resolvePasswordEnvConfig($database);
            $driver = strtolower((string) ($database['driver'] ?? 'mysql'));
            $name = (string) ($database['database'] ?? '');
            if (!in_array($driver, ['mysql', 'mariadb'], true) || $name === '') {
                continue;
            }
            if (!isset($databases[$name])) {
                $databases[$name] = [
                    'database' => $database,
                    'app_ids' => [],
                    'reset' => false,
                ];
            }
            $appId = (string) ($app['id'] ?? 'app');
            $databases[$name]['app_ids'][] = $appId;
            if ($action === 'install' && $this->resolveAppInstallerMode($app, $action) === 'fresh') {
                $databases[$name]['reset'] = true;
            }
        }

        $results = [];
        foreach ($databases as $databasePlan) {
            $appIds = array_values(array_unique($databasePlan['app_ids']));
            $result = $this->provisionMysqlDatabase(
                $databasePlan['database'],
                implode(',', $appIds),
                (bool) $databasePlan['reset']
            );
            $result['app_ids'] = $appIds;
            $results[] = $result;
            if (($result['status'] ?? '') !== 'success') {
                $failed = true;
            }
        }

        return [
            'status' => $failed ? 'failed' : 'success',
            'mode' => $action === 'install' ? 'fresh-reset-or-create' : 'create-if-missing',
            'databases' => $results,
        ];
    }

    private function provisionMysqlDatabase(array $database, string $appId, bool $reset): array
    {
        $name = (string) ($database['database'] ?? '');
        $host = (string) ($database['host'] ?? '127.0.0.1');
        $port = (int) ($database['port'] ?? 3306);
        $username = (string) ($database['username'] ?? '');
        $password = (string) ($database['password'] ?? '');

        $result = [
            'app_id' => $appId,
            'driver' => strtolower((string) ($database['driver'] ?? 'mysql')),
            'database' => $name,
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'status' => 'failed',
            'message' => '',
            'reset' => $reset,
            'reset_mode' => $reset ? 'drop-and-create' : 'none',
        ];

        if ($username === '') {
            $result['message'] = 'Database username is required.';
            return $result;
        }
        if (!$this->isSafeMysqlIdentifier($name)) {
            $result['message'] = 'Database name contains unsupported characters.';
            return $result;
        }

        try {
            $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port);
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $existsBefore = $this->mysqlDatabaseExists($pdo, $name);
            $result['existed_before'] = $existsBefore;
            if ($existsBefore) {
                $result['tables_before'] = $this->mysqlDatabaseTableCount($pdo, $name);
            }
            if ($reset && $existsBefore) {
                $pdo->exec(sprintf('DROP DATABASE `%s`', str_replace('`', '``', $name)));
                $result['reset_performed'] = true;
            } else {
                $result['reset_performed'] = false;
            }
            $pdo->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', str_replace('`', '``', $name)));
            $result['tables_after'] = $this->mysqlDatabaseTableCount($pdo, $name);
            $result['status'] = 'success';
            $result['message'] = ($result['reset_performed'] ?? false)
                ? 'Fresh database was reset and recreated.'
                : 'Database is ready.';
        } catch (Throwable $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    private function mysqlDatabaseExists(PDO $pdo, string $database): bool
    {
        $statement = $pdo->prepare('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
        $statement->execute([$database]);

        return $statement->fetchColumn() !== false;
    }

    private function mysqlDatabaseTableCount(PDO $pdo, string $database): int
    {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ?');
        $statement->execute([$database]);

        return (int) $statement->fetchColumn();
    }

    private function isSafeMysqlIdentifier(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9_]+$/', $value) === 1;
    }

    private function runAppInstaller(array $app, array $kitConfig, string $runDir, string $runId, string $action): array
    {
        $phpBinary = (string) $kitConfig['runtime']['php_binary'];
        $mode = $this->resolveAppInstallerMode($app, $action);
        $plan = $this->planApp($app, $kitConfig, $runDir, $runId, $mode);

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

        $process = $this->runProcess($command, $app['release_path'], $this->buildAppInstallerEnvironment($kitConfig));
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
            'app_report_summary' => is_array($appReport) ? $this->summarizeAppReport($appReport) : null,
            'status_command' => $statusResult,
            'manifest' => $manifest,
            'services' => is_array($appReport) ? ($appReport['services'] ?? []) : [],
            'runtime_services' => is_array($appReport) && is_array($appReport['runtime_services'] ?? null)
                ? $appReport['runtime_services']
                : $this->appRuntimeServices((string) $app['id'], $app, $kitConfig),
        ]);
    }

    private function cleanupAppInstallerArtifacts(array $app): array
    {
        $releasePath = (string) ($app['release_path'] ?? '');
        $releaseReal = realpath($releasePath);
        $removed = [];
        $skipped = [];
        $warnings = [];

        if ($releaseReal === false || !is_dir($releaseReal)) {
            return [
                'status' => 'warning',
                'removed' => [],
                'skipped' => [],
                'warnings' => ['App release path was not available for installer cleanup.'],
            ];
        }

        $targets = [
            $this->joinPath($releaseReal, 'installer'),
            $this->joinPath($releaseReal, 'public' . DIRECTORY_SEPARATOR . 'installer'),
        ];

        $installer = is_array($app['release']['installer'] ?? null) ? $app['release']['installer'] : [];
        foreach (['interactive', 'unattended', 'status', 'schema'] as $key) {
            $relative = (string) ($installer[$key] ?? '');
            if ($relative === '') {
                continue;
            }
            if (strpos(str_replace('\\', '/', $relative), 'storage/') === 0) {
                $skipped[] = [
                    'path' => $relative,
                    'reason' => 'storage installer reports/manifests are retained for support diagnostics',
                ];
                continue;
            }
            if (strpos(strtolower(str_replace('\\', '/', $relative)), 'installer') !== false) {
                $targets[] = $this->joinPath($releaseReal, $relative);
            }
        }
        $cleanupArtifacts = $installer['cleanup_artifacts'] ?? [];
        if (is_array($cleanupArtifacts)) {
            foreach ($cleanupArtifacts as $relative) {
                if (!is_string($relative) || trim($relative) === '') {
                    continue;
                }
                $normalized = str_replace('\\', '/', trim($relative));
                if (
                    str_starts_with($normalized, '/')
                    || preg_match('/^[A-Za-z]:\//', $normalized) === 1
                    || in_array('..', explode('/', $normalized), true)
                ) {
                    $skipped[] = [
                        'path' => $relative,
                        'reason' => 'declared cleanup artifact is not a safe relative path',
                    ];
                    continue;
                }
                if (strpos(strtolower($normalized), 'storage/') === 0) {
                    $skipped[] = [
                        'path' => $relative,
                        'reason' => 'storage installer reports/manifests are retained for support diagnostics',
                    ];
                    continue;
                }
                $targets[] = $this->joinPath($releaseReal, str_replace('/', DIRECTORY_SEPARATOR, $normalized));
            }
        }

        $targets = array_values(array_unique($targets));
        usort($targets, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));

        foreach ($targets as $target) {
            $real = realpath($target);
            if ($real === false) {
                continue;
            }
            if (!$this->isPathInside($real, $releaseReal)) {
                $skipped[] = [
                    'path' => $target,
                    'reason' => 'path is outside the app release boundary',
                ];
                continue;
            }
            if (strpos(strtolower(str_replace('\\', '/', $this->relativePath($releaseReal, $real))), 'storage/') === 0) {
                $skipped[] = [
                    'path' => $real,
                    'reason' => 'storage installer reports/manifests are retained for support diagnostics',
                ];
                continue;
            }

            try {
                if (is_dir($real)) {
                    $this->removeDirectory($real);
                    $removed[] = ['path' => $real, 'type' => 'directory'];
                } elseif (is_file($real)) {
                    if (!unlink($real)) {
                        throw new RuntimeException('Unable to remove file: ' . $real);
                    }
                    $removed[] = ['path' => $real, 'type' => 'file'];
                }
            } catch (Throwable $e) {
                $warnings[] = $e->getMessage();
            }
        }

        return [
            'status' => count($warnings) > 0 ? 'warning' : 'success',
            'removed' => $removed,
            'skipped' => $skipped,
            'warnings' => $warnings,
        ];
    }

    private function resolveAppInstallerMode(array $app, string $action): string
    {
        if ($action === 'preflight') {
            return 'preflight';
        }

        $configuredMode = (string) ($app['config']['mode'] ?? 'fresh');
        if ($action !== 'install' || $configuredMode !== 'repair') {
            return $configuredMode;
        }

        $manifest = $this->readAppArtifact($app, 'install_manifest');
        if (is_array($manifest) && ($manifest['exists'] ?? false) === true) {
            return 'repair';
        }

        return 'fresh';
    }

    private function summarizeAppReport(array $appReport): array
    {
        $summary = [
            'status' => $appReport['status'] ?? null,
            'summary' => $appReport['summary'] ?? null,
            'warnings' => [],
            'errors' => [],
        ];

        foreach (['warnings', 'errors'] as $field) {
            $items = $appReport[$field] ?? [];
            if (!is_array($items)) {
                continue;
            }
            foreach ($items as $item) {
                if (is_array($item)) {
                    if (($item['status'] ?? null) !== null && (string) $item['status'] !== 'failed') {
                        continue;
                    }
                    $label = (string) ($item['label'] ?? $item['id'] ?? $field);
                    $message = (string) ($item['message'] ?? '');
                    $summary[$field][] = trim($label . ($message !== '' ? ': ' . $message : ''));
                } elseif (is_string($item) && $item !== '') {
                    $summary[$field][] = $item;
                }
                if (count($summary[$field]) >= 6) {
                    break;
                }
            }
        }

        return $summary;
    }

    private function runAppPopulationTools(array $app, array $kitConfig, string $runDir, string $runId): array
    {
        if (isset($app['release']['data_prep']) && is_array($app['release']['data_prep'])) {
            return $this->runAppDataPrepTools($app, $kitConfig, $runDir, $runId);
        }

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

    private function runAppDataPrepTools(array $app, array $kitConfig, string $runDir, string $runId): array
    {
        $plan = $this->planApp($app, $kitConfig, $runDir, $runId, 'initial', false);
        $dataPrep = $app['release']['data_prep'];
        $tools = is_array($dataPrep['tools'] ?? null) ? $dataPrep['tools'] : [];
        $capabilities = is_array($dataPrep['capabilities'] ?? null) ? $dataPrep['capabilities'] : [];
        $orderedSteps = [
            'prepare_data' => 'Prepare Data',
            'apply_settings' => 'Apply Settings',
            'verify' => 'Verify',
        ];
        $selectedStep = (string) ($kitConfig['data_prep']['step'] ?? '');
        if ($selectedStep !== '') {
            if (!array_key_exists($selectedStep, $orderedSteps)) {
                throw new RuntimeException('Unsupported Data Prep step: ' . $selectedStep);
            }
            $orderedSteps = [$selectedStep => $orderedSteps[$selectedStep]];
        }

        $config = $this->readJsonFile($plan['config_path']);
        $config = $this->prepareDataPrepAppConfig($config, $app, $kitConfig, $tools);
        $this->writeJsonFile($plan['config_path'], $config);

        $stepResults = [];
        $blockedBy = null;
        foreach ($orderedSteps as $step => $label) {
            $enabled = (bool) ($capabilities[$step] ?? isset($tools[$step]));
            if (!$enabled) {
                $stepResults[] = [
                    'name' => $step,
                    'step' => $step,
                    'label' => $label,
                    'status' => 'skipped',
                    'message' => $label . ' is not required for this app.',
                ];
                continue;
            }

            if ($blockedBy !== null) {
                $stepResults[] = [
                    'name' => $step,
                    'step' => $step,
                    'label' => $label,
                    'status' => 'blocked',
                    'message' => $label . ' was blocked by failed ' . $blockedBy . '.',
                ];
                continue;
            }

            if (!isset($tools[$step]) || !is_array($tools[$step])) {
                $stepResults[] = [
                    'name' => $step,
                    'step' => $step,
                    'label' => $label,
                    'status' => 'failed',
                    'message' => $label . ' is enabled but no tool is declared.',
                ];
                $blockedBy = $label;
                continue;
            }

            $stepResult = $this->runDataPrepTool(
                $app,
                $kitConfig,
                $runDir,
                (string) $step,
                $label,
                $tools[$step],
                $plan['config_path'],
                $config
            );
            $stepResults[] = $stepResult;
            if (($stepResult['status'] ?? '') === 'failed') {
                $blockedBy = $label;
            }
        }

        $statuses = array_map(static fn (array $result): string => (string) ($result['status'] ?? 'pending'), $stepResults);
        $failed = in_array('failed', $statuses, true);
        $success = in_array('success', $statuses, true);
        $status = $failed ? 'failed' : ($success ? 'success' : 'skipped');

        return array_merge($plan, [
            'status' => $status,
            'mode' => 'populate',
            'data_prep' => [
                'version' => $dataPrep['version'] ?? 1,
                'capabilities' => $capabilities,
                'steps' => $stepResults,
            ],
            'population_tools' => $stepResults,
            'message' => $status === 'skipped'
                ? 'No Data Prep tools are required for this app.'
                : ($status === 'failed' ? 'One or more Data Prep tools failed.' : 'Data Prep tools completed.'),
        ]);
    }

    private function runDataPrepPostApplyVerification(array $orderedApps, array $kitConfig, string $runDir, string $runId, array $context): array
    {
        $warnings = [];
        $errors = [];
        $serviceRestart = $this->restartDataPrepHeartbeatServices($orderedApps, $kitConfig, $runDir);
        foreach (($serviceRestart['warnings'] ?? []) as $warning) {
            $warnings[] = (string) $warning;
        }
        foreach (($serviceRestart['errors'] ?? []) as $error) {
            $errors[] = (string) $error;
        }

        $heartbeatVerify = $this->runMaestroHeartbeatDataPrepVerify($orderedApps, $kitConfig, $runDir, $runId, $context);
        foreach (($heartbeatVerify['warnings'] ?? []) as $warning) {
            $warnings[] = (string) $warning;
        }
        foreach (($heartbeatVerify['errors'] ?? []) as $error) {
            $errors[] = (string) $error;
        }

        return [
            'schema_version' => 1,
            'kit_setup_version' => self::VERSION,
            'action' => 'data-prep-post-apply',
            'status' => count($errors) > 0 ? 'failed' : (count($warnings) > 0 ? 'warning' : 'success'),
            'started_at' => $context['started_at'] ?? null,
            'finished_at' => date(DATE_ATOM),
            'service_restart' => $serviceRestart,
            'heartbeat_verify' => $heartbeatVerify,
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    private function restartDataPrepHeartbeatServices(array $orderedApps, array $kitConfig, string $runDir): array
    {
        $realtimeTargetServiceIds = [
            'pbb-realtime-websocket' => true,
            'pbb-realtime-media-dispatcher' => true,
        ];
        $services = [];
        foreach ($orderedApps as $app) {
            $appId = (string) ($app['id'] ?? '');
            if (!in_array($appId, ['pbb-relay', 'pbb-realtime'], true)) {
                continue;
            }
            foreach ($this->appRuntimeServices($appId, $app, $kitConfig) as $service) {
                $serviceId = (string) ($service['id'] ?? '');
                if ($appId === 'pbb-relay' || isset($realtimeTargetServiceIds[$serviceId])) {
                    $services[$serviceId] = $service;
                }
            }
        }

        $serviceRunDir = $this->joinPath($runDir, 'runtime-services');
        $this->ensureDirectory($serviceRunDir);
        $results = [];
        $warnings = [];
        $errors = [];
        foreach ($services as $service) {
            $stop = $this->stopWinSwService($service, 'Data Prep Apply Settings completed; restart for Maestro heartbeat verification');
            $start = $this->startRuntimeService($service, $serviceRunDir);
            $result = [
                'app_id' => $service['app_id'] ?? null,
                'id' => $service['id'] ?? null,
                'name' => $service['name'] ?? null,
                'stop' => $stop,
                'start' => $start,
                'status' => ($start['status'] ?? '') === 'success' ? 'success' : (($start['status'] ?? '') === 'warning' ? 'warning' : 'failed'),
                'message' => (string) ($start['message'] ?? 'Runtime service restart completed.'),
            ];
            $results[] = $result;
            if (($result['status'] ?? '') === 'failed') {
                $errors[] = 'Data Prep service restart failed for ' . (string) ($service['id'] ?? 'runtime-service') . ': ' . (string) ($result['message'] ?? 'unknown');
            } elseif (($result['status'] ?? '') === 'warning') {
                $warnings[] = 'Data Prep service restart warning for ' . (string) ($service['id'] ?? 'runtime-service') . ': ' . (string) ($result['message'] ?? 'unknown');
            }
        }

        return [
            'status' => count($errors) > 0 ? 'failed' : (count($warnings) > 0 ? 'warning' : 'success'),
            'reason' => 'Refresh runtime services that consume Data Prep .env/config changes.',
            'runtime_services' => $results,
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    private function runMaestroHeartbeatDataPrepVerify(array $orderedApps, array $kitConfig, string $runDir, string $runId, array $context): array
    {
        $maestro = null;
        foreach ($orderedApps as $app) {
            if ((string) ($app['id'] ?? '') === 'pbb-maestro') {
                $maestro = $app;
                break;
            }
        }
        if (!is_array($maestro) || !isset($maestro['release']['data_prep']) || !is_array($maestro['release']['data_prep'])) {
            return [
                'status' => 'skipped',
                'message' => 'Maestro Data Prep verify tool is not available.',
                'warnings' => [],
                'errors' => [],
            ];
        }

        $verifyConfig = $kitConfig;
        if (!isset($verifyConfig['data_prep']) || !is_array($verifyConfig['data_prep'])) {
            $verifyConfig['data_prep'] = [];
        }
        $verifyConfig['data_prep']['step'] = 'verify';
        $verifyConfig['data_prep']['require_fresh_heartbeat'] = true;
        if (!isset($verifyConfig['data_prep']['freshness_threshold_seconds'])) {
            $verifyConfig['data_prep']['freshness_threshold_seconds'] = 60;
        }

        $timeoutSeconds = max(30, (int) ($verifyConfig['data_prep']['heartbeat_verify_timeout_seconds'] ?? 75));
        $intervalSeconds = max(2, (int) ($verifyConfig['data_prep']['heartbeat_verify_interval_seconds'] ?? 5));
        $deadline = time() + $timeoutSeconds;
        $attempts = [];
        $last = null;
        $attempt = 0;
        do {
            $attempt++;
            $last = $this->runAppDataPrepTools($maestro, $verifyConfig, $runDir, $runId);
            $attempts[] = [
                'attempt' => $attempt,
                'status' => $last['status'] ?? 'unknown',
                'message' => $last['message'] ?? '',
                'finished_at' => date(DATE_ATOM),
                'steps' => $last['data_prep']['steps'] ?? [],
            ];
            if (($last['status'] ?? '') === 'success') {
                break;
            }
            if (time() >= $deadline) {
                break;
            }
            sleep(min($intervalSeconds, max(1, $deadline - time())));
        } while (true);

        $status = (string) ($last['status'] ?? 'failed');
        $warnings = [];
        $errors = [];
        if ($status !== 'success') {
            $errors[] = 'Maestro heartbeat verification did not receive fresh Relay/Realtime heartbeats within ' . $timeoutSeconds . ' seconds.';
        }

        return [
            'status' => $status === 'success' ? 'success' : 'failed',
            'message' => $status === 'success'
                ? 'Maestro received fresh Relay/Realtime heartbeats.'
                : 'Maestro heartbeat verification failed.',
            'timeout_seconds' => $timeoutSeconds,
            'interval_seconds' => $intervalSeconds,
            'attempt_count' => count($attempts),
            'attempts' => $attempts,
            'final_result' => $last,
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    private function prepareDataPrepAppConfig(array $config, array $app, array $kitConfig, array $tools): array
    {
        $apply = (bool) ($kitConfig['data_prep']['apply'] ?? ($config['data_prep']['apply'] ?? false));
        if (!isset($config['data_prep']) || !is_array($config['data_prep'])) {
            $config['data_prep'] = [];
        }
        $config['data_prep']['apply'] = $apply;
        foreach ($tools as $tool) {
            if (!is_array($tool)) {
                continue;
            }
            $sectionPath = (string) ($tool['config_section'] ?? '');
            if ($sectionPath === '') {
                continue;
            }
            $section = $this->getNestedValue($config, $sectionPath);
            if (!is_array($section)) {
                $section = [];
            }
            $section['enabled'] = true;
            $section['dry_run'] = !$apply;
            if (isset($section['options']) && is_array($section['options'])) {
                $section['options']['dry_run'] = !$apply;
            }
            $this->setNestedValue($config, $sectionPath, $section);
        }

        $shortId = preg_replace('/^pbb[-_]/', '', strtolower((string) ($app['id'] ?? '')));
        if (isset($config[$shortId]['populate']) && is_array($config[$shortId]['populate'])) {
            $config[$shortId]['populate']['enabled'] = true;
            $config[$shortId]['populate']['dry_run'] = !$apply;
            if (isset($config[$shortId]['populate']['options']) && is_array($config[$shortId]['populate']['options'])) {
                $config[$shortId]['populate']['options']['dry_run'] = !$apply;
            }
        }

        $secrets = is_array($kitConfig['shared']['secrets']['values'] ?? null) ? $kitConfig['shared']['secrets']['values'] : [];
        $relayTelemetryToken = (string) ($secrets['maestro_relay_telemetry_token'] ?? $secrets['maestro_telemetry_token'] ?? '');
        $realtimeTelemetryToken = (string) ($secrets['maestro_realtime_telemetry_token'] ?? $secrets['maestro_telemetry_token'] ?? '');

        if (($app['id'] ?? '') === 'pbb-maestro') {
            if (!isset($config['maestro']['populate']) || !is_array($config['maestro']['populate'])) {
                $config['maestro']['populate'] = [];
            }
            $config['maestro']['populate']['enabled'] = true;
            $config['maestro']['populate']['applications'] = $this->maestroDataPrepApplications($kitConfig, $config);
            $config['maestro']['populate']['generated_telemetry_tokens'] = [
                'relay' => [
                    [
                        'label' => 'Primary',
                        'plain_text_token' => $relayTelemetryToken,
                    ],
                ],
                'realtime' => [
                    [
                        'label' => 'Primary',
                        'plain_text_token' => $realtimeTelemetryToken,
                    ],
                ],
            ];
            if (!isset($config['maestro']['data_prep']['verify']) || !is_array($config['maestro']['data_prep']['verify'])) {
                $config['maestro']['data_prep']['verify'] = [];
            }
            $config['maestro']['data_prep']['verify']['enabled'] = true;
            $config['maestro']['data_prep']['verify']['require_fresh_heartbeat'] = (bool) ($kitConfig['data_prep']['require_fresh_heartbeat'] ?? false);
            $config['maestro']['data_prep']['verify']['freshness_threshold_seconds'] = max(1, (int) ($kitConfig['data_prep']['freshness_threshold_seconds'] ?? 60));
        }

        if (($app['id'] ?? '') === 'pbb-mapserver') {
            $config = $this->prepareMapServerDataPrepConfig($config, $app, $kitConfig, $apply);
        }

        if (($app['id'] ?? '') === 'pbb-relay') {
            if (!isset($config['relay']['data_prep']['apply_settings']) || !is_array($config['relay']['data_prep']['apply_settings'])) {
                $config['relay']['data_prep']['apply_settings'] = [];
            }
            $config['relay']['data_prep']['apply_settings']['enabled'] = true;
            $config['relay']['data_prep']['apply_settings']['maestro'] = [
                'base_url' => $this->appBaseUrlForDataPrep($kitConfig, $config, 'pbb-maestro', 'maestro', 'https://maestro.pbb.ph'),
                'app_code' => 'relay',
                'telemetry_token' => $relayTelemetryToken,
                'tls_verify' => true,
            ];
            $caFile = $this->bundledCaFile();
            if ($caFile !== '') {
                $config['relay']['data_prep']['apply_settings']['maestro']['ca_bundle'] = $caFile;
                $config['relay']['data_prep']['apply_settings']['maestro']['curl_ca_bundle'] = $caFile;
            }
        }

        if (($app['id'] ?? '') === 'pbb-realtime') {
            $config = $this->prepareRealtimeHotlineClientSecretConfig($config, $secrets);

            if (!isset($config['realtime']['data_prep']['apply_settings']) || !is_array($config['realtime']['data_prep']['apply_settings'])) {
                $config['realtime']['data_prep']['apply_settings'] = [];
            }
            $maestroTelemetrySettings = [
                'enabled' => true,
                'base_url' => $this->appBaseUrlForDataPrep($kitConfig, $config, 'pbb-maestro', 'maestro', 'https://maestro.pbb.ph'),
                'app_code' => 'realtime',
                'telemetry_token' => $realtimeTelemetryToken,
                'tls_verify' => true,
            ];
            $caFile = $this->bundledCaFile();
            if ($caFile !== '') {
                $maestroTelemetrySettings['ca_bundle'] = $caFile;
                $maestroTelemetrySettings['curl_ca_bundle'] = $caFile;
            }
            $mediaIngestSettings = [
                'enabled' => true,
                'project_codes' => [
                    'prj_HOTLINE_SERVER',
                    'prj_HOTLINE_CITIZEN',
                    'prj_HOTLINE_OPERATOR',
                ],
                'base_url' => $this->appBaseUrlForDataPrep($kitConfig, $config, 'pbb-hotline', 'hotline', 'https://hotline.pbb.ph'),
                'auth_header' => 'X-Realtime-Media-Ingest-Secret',
                'tls_verify' => true,
            ];
            $mediaIngestSecret = trim((string) ($secrets['realtime_media_ingest_secret'] ?? ''));
            if ($mediaIngestSecret !== '') {
                $mediaIngestSettings['auth_token'] = $mediaIngestSecret;
            }
            if ($caFile !== '') {
                $mediaIngestSettings['ca_bundle'] = $caFile;
                $mediaIngestSettings['curl_ca_bundle'] = $caFile;
            }
            $config['realtime']['data_prep']['apply_settings']['enabled'] = true;
            $config['realtime']['data_prep']['apply_settings']['maestro'] = $maestroTelemetrySettings;
            $config['realtime']['data_prep']['apply_settings']['media_ingest'] = $mediaIngestSettings;

            if (!isset($config['realtime']['data_prep']['verify']) || !is_array($config['realtime']['data_prep']['verify'])) {
                $config['realtime']['data_prep']['verify'] = [];
            }
            $config['realtime']['data_prep']['verify']['enabled'] = true;
            $config['realtime']['data_prep']['verify']['maestro'] = $maestroTelemetrySettings;
            $config['realtime']['data_prep']['verify']['media_ingest'] = $mediaIngestSettings;
        }

        if (($app['id'] ?? '') === 'pbb-hotline') {
            $realtimeSettings = $this->hotlineRealtimeDataPrepSettings($kitConfig, $config, $secrets);
            if (!isset($config['hotline']['data_prep']['apply_settings']) || !is_array($config['hotline']['data_prep']['apply_settings'])) {
                $config['hotline']['data_prep']['apply_settings'] = [];
            }
            $config['hotline']['data_prep']['apply_settings'] = array_replace(
                $config['hotline']['data_prep']['apply_settings'],
                $realtimeSettings,
                [
                    'enabled' => true,
                    'dry_run' => !$apply,
                    'realtime' => $realtimeSettings,
                ]
            );

            if (!isset($config['hotline']['data_prep']['verify']) || !is_array($config['hotline']['data_prep']['verify'])) {
                $config['hotline']['data_prep']['verify'] = [];
            }
            $config['hotline']['data_prep']['verify']['enabled'] = true;
            $config['hotline']['data_prep']['verify']['dry_run'] = !$apply;
            $config['hotline']['data_prep']['verify']['require_realtime_settings'] = true;
        }

        return $config;
    }

    private function prepareRealtimeHotlineClientSecretConfig(array $config, array $secrets): array
    {
        $backendSecret = trim((string) ($secrets['realtime_backend_ingress_secret'] ?? ''));
        if ($backendSecret === '') {
            return $config;
        }

        if (!isset($config['realtime']) || !is_array($config['realtime'])) {
            $config['realtime'] = [];
        }
        if (!isset($config['realtime']['populate']) || !is_array($config['realtime']['populate'])) {
            $config['realtime']['populate'] = [];
        }
        if (!isset($config['realtime']['populate']['source']) || trim((string) $config['realtime']['populate']['source']) === '') {
            $config['realtime']['populate']['source'] = 'resources/data/realtime/hotline-client-data.json';
        }
        if (!isset($config['realtime']['populate']['clients']) || !is_array($config['realtime']['populate']['clients'])) {
            $config['realtime']['populate']['clients'] = [];
        }

        $config['realtime']['populate']['clients'][] = [
            'client_code' => 'clt_PBB_HOTLINE',
            'name' => 'PBB Hotline',
            'backend_ingress_secret' => $backendSecret,
        ];

        if (!isset($config['realtime']['populate']['options']) || !is_array($config['realtime']['populate']['options'])) {
            $config['realtime']['populate']['options'] = [];
        }
        if (!array_key_exists('overwrite_secrets', $config['realtime']['populate']['options'])) {
            $config['realtime']['populate']['options']['overwrite_secrets'] = false;
        }

        return $config;
    }

    private function hotlineRealtimeDataPrepSettings(array $kitConfig, array $config, array $secrets): array
    {
        return [
            'base_url' => $this->appBaseUrlForDataPrep($kitConfig, $config, 'pbb-realtime', 'realtime', 'https://realtime.pbb.ph'),
            'client_code' => 'clt_PBB_HOTLINE',
            'project_code_server' => 'prj_HOTLINE_SERVER',
            'project_code_caller' => 'prj_HOTLINE_CITIZEN',
            'project_code_citizen' => 'prj_HOTLINE_CITIZEN',
            'project_code_operator' => 'prj_HOTLINE_OPERATOR',
            'project_code_command' => 'prj_HOTLINE_COMMAND',
            'project_code_media_ingest' => 'prj_HOTLINE_OPERATOR',
            'project_codes' => [
                'server' => 'prj_HOTLINE_SERVER',
                'caller' => 'prj_HOTLINE_CITIZEN',
                'citizen' => 'prj_HOTLINE_CITIZEN',
                'operator' => 'prj_HOTLINE_OPERATOR',
                'command' => 'prj_HOTLINE_COMMAND',
                'media_ingest' => 'prj_HOTLINE_OPERATOR',
            ],
            'backend_ingress_secret' => (string) ($secrets['realtime_backend_ingress_secret'] ?? ''),
            'media_ingest_secret' => (string) ($secrets['realtime_media_ingest_secret'] ?? ''),
            'token_signing_secret' => (string) ($secrets['realtime_token_secret'] ?? ''),
        ];
    }

    private function maestroDataPrepApplications(array $kitConfig, array $config): array
    {
        return [
            [
                'app_code' => 'relay',
                'display_name' => 'PBB Relay',
                'environment' => 'production',
                'base_url' => $this->appBaseUrlForDataPrep($kitConfig, $config, 'pbb-relay', 'relay', 'https://relay.pbb.ph'),
                'is_active' => true,
            ],
            [
                'app_code' => 'realtime',
                'display_name' => 'PBB Realtime',
                'environment' => 'production',
                'base_url' => $this->appBaseUrlForDataPrep($kitConfig, $config, 'pbb-realtime', 'realtime', 'https://realtime.pbb.ph'),
                'is_active' => true,
            ],
        ];
    }

    private function appBaseUrlForDataPrep(array $kitConfig, array $config, string $appId, string $dependencyKey, string $fallback): string
    {
        foreach (($kitConfig['apps'] ?? []) as $appConfig) {
            if (!is_array($appConfig) || (string) ($appConfig['id'] ?? '') !== $appId) {
                continue;
            }
            $url = trim((string) ($appConfig['app_url'] ?? ''));
            if ($url !== '') {
                return $url;
            }
        }

        $url = trim((string) ($config['dependencies'][$dependencyKey]['base_url'] ?? $kitConfig['shared']['dependencies'][$dependencyKey]['base_url'] ?? ''));
        return $url !== '' ? $url : $fallback;
    }

    private function prepareMapServerDataPrepConfig(array $config, array $app, array $kitConfig, bool $apply): array
    {
        $prepare = $this->getNestedValue($config, 'mapserver.data_prep.prepare');
        if (!is_array($prepare)) {
            $prepare = [];
        }

        $hub = is_array($kitConfig['shared']['hub'] ?? null) ? $kitConfig['shared']['hub'] : [];
        $locationCodes = is_array($kitConfig['kit']['location_codes'] ?? null) ? $kitConfig['kit']['location_codes'] : [];
        $code = static function (string $key) use ($hub, $locationCodes): string {
            return trim((string) ($hub[$key] ?? $locationCodes[$key] ?? ''));
        };

        $deployment = strtolower(trim((string) ($hub['deployment'] ?? $kitConfig['kit']['deployment'] ?? '')));
        $scope = 'barangay';
        if (in_array($deployment, ['city', 'citymun', 'municipality'], true)) {
            $scope = 'city';
        } elseif (in_array($deployment, ['province', 'prov'], true)) {
            $scope = 'province';
        } elseif (in_array($deployment, ['region', 'reg'], true)) {
            $scope = 'region';
        } elseif ($deployment === 'other') {
            $scope = 'other';
        }

        $codes = [
            'country_code' => $code('country_code'),
            'reg_code' => $code('reg_code'),
            'prov_code' => $code('prov_code'),
            'citymun_code' => $code('citymun_code'),
            'brgy_code' => $code('brgy_code'),
        ];
        $codes = array_filter($codes, static fn ($value): bool => $value !== '');

        $prepare['enabled'] = true;
        $prepare['dry_run'] = !$apply;
        $prepare['source'] = 'hub';
        $prepare['deployment_scope'] = $scope;
        if (!isset($prepare['base_url']) || trim((string) $prepare['base_url']) === '') {
            $baseUrl = (string) ($config['app']['app_url'] ?? $app['app_url'] ?? $app['config']['app_url'] ?? $config['app_url'] ?? '');
            if ($baseUrl !== '') {
                $prepare['base_url'] = $baseUrl;
            }
        }
        foreach ($codes as $key => $value) {
            $prepare[$key] = $value;
        }
        if (count($codes) > 0) {
            $prepare['codes'] = $codes;
        }

        if ($scope === 'city' && isset($codes['citymun_code'])) {
            $prepare['citymun_code'] = $codes['citymun_code'];
            $prepare['city_code'] = $codes['citymun_code'];
            $prepare['municipality_code'] = $codes['citymun_code'];
        } elseif ($scope === 'province' && isset($codes['prov_code'])) {
            $prepare['prov_code'] = $codes['prov_code'];
            $prepare['province_code'] = $codes['prov_code'];
        } elseif ($scope === 'region' && isset($codes['reg_code'])) {
            $prepare['reg_code'] = $codes['reg_code'];
            $prepare['region_code'] = $codes['reg_code'];
        } elseif (isset($codes['brgy_code'])) {
            $prepare['brgy_code'] = $codes['brgy_code'];
            $prepare['barangay_code'] = $codes['brgy_code'];
            $prepare['psgc_code'] = $codes['brgy_code'];
        }

        $caFile = $this->bundledCaFile();
        if ($caFile !== '') {
            if (!isset($prepare['curl_ca_bundle']) || trim((string) $prepare['curl_ca_bundle']) === '') {
                $prepare['curl_ca_bundle'] = $caFile;
            }
            if (!isset($prepare['ca_bundle']) || trim((string) $prepare['ca_bundle']) === '') {
                $prepare['ca_bundle'] = $caFile;
            }
        }

        $this->setNestedValue($config, 'mapserver.data_prep.prepare', $prepare);
        return $config;
    }

    private function applyMapServerTlsConfig(array $config): array
    {
        $caFile = $this->bundledCaFile();
        if ($caFile === '') {
            return $config;
        }

        if (!isset($config['mapserver']) || !is_array($config['mapserver'])) {
            $config['mapserver'] = [];
        }

        if (!isset($config['mapserver']['curl_ca_bundle']) || trim((string) $config['mapserver']['curl_ca_bundle']) === '') {
            $config['mapserver']['curl_ca_bundle'] = $caFile;
        }

        return $config;
    }

    private function applyHotlineTlsConfig(array $config): array
    {
        $caFile = $this->bundledCaFile();
        if ($caFile === '') {
            return $config;
        }

        if (!isset($config['hotline']) || !is_array($config['hotline'])) {
            $config['hotline'] = [];
        }

        if (!isset($config['hotline']['realtime_ca_bundle']) || trim((string) $config['hotline']['realtime_ca_bundle']) === '') {
            $config['hotline']['realtime_ca_bundle'] = $caFile;
        }

        return $config;
    }

    private function runDataPrepTool(array $app, array $kitConfig, string $runDir, string $step, string $label, array $tool, string $appConfigPath, array $config): array
    {
        $relativePath = (string) ($tool['path'] ?? '');
        $toolPath = $relativePath !== '' ? $this->joinPath($app['release_path'], $relativePath) : '';
        $reportPath = $this->joinPath($runDir, 'apps' . DIRECTORY_SEPARATOR . $app['id'] . '.data-prep.' . $step . '.report.json');
        if ($toolPath === '' || !is_file($toolPath)) {
            return [
                'name' => $step,
                'step' => $step,
                'label' => $label,
                'status' => 'failed',
                'message' => 'Data Prep tool not found: ' . $toolPath,
            ];
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

        $timeoutSeconds = $this->populationToolProcessTimeout($tool, $config);
        $process = $this->runProcess($command, $app['release_path'], null, $timeoutSeconds);
        $report = is_file($reportPath) ? $this->readJsonFile($reportPath) : null;
        $reportStatus = is_array($report) ? (string) ($report['status'] ?? '') : '';
        $status = $process['exit_code'] === 0 && !in_array($reportStatus, ['failed', 'error'], true) ? 'success' : 'failed';

        return [
            'name' => $step,
            'step' => $step,
            'label' => $label,
            'path' => $this->absolutePath($toolPath),
            'report_path' => $this->absolutePath($reportPath),
            'status' => $status,
            'message' => is_array($report) ? (string) ($report['summary'] ?? '') : '',
            'exit_code' => $process['exit_code'],
            'timed_out' => $process['timed_out'] ?? false,
            'timeout_seconds' => $process['timeout_seconds'] ?? $timeoutSeconds,
            'stdout' => $process['stdout'],
            'stderr' => $process['stderr'],
            'report_status' => $reportStatus !== '' ? $reportStatus : null,
        ];
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

        $timeoutSeconds = $this->populationToolProcessTimeout($tool, $config);
        $process = $this->runProcess($command, $app['release_path'], null, $timeoutSeconds);
        $report = is_file($reportPath) ? $this->readJsonFile($reportPath) : null;

        return [
            'name' => $name,
            'path' => $this->absolutePath($toolPath),
            'report_path' => $this->absolutePath($reportPath),
            'status' => $process['exit_code'] === 0 ? 'success' : 'failed',
            'exit_code' => $process['exit_code'],
            'timed_out' => $process['timed_out'] ?? false,
            'timeout_seconds' => $process['timeout_seconds'] ?? $timeoutSeconds,
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

        $timeoutSeconds = $this->populationToolProcessTimeout($tool, $config);
        $process = $this->runProcess($command, $app['release_path'], null, $timeoutSeconds);
        $report = is_file($reportPath) ? $this->readJsonFile($reportPath) : null;

        return [
            'name' => $name,
            'path' => $this->absolutePath($toolPath),
            'contract' => 'compatibility',
            'report_path' => $this->absolutePath($reportPath),
            'status' => $process['exit_code'] === 0 ? 'success' : 'failed',
            'exit_code' => $process['exit_code'],
            'timed_out' => $process['timed_out'] ?? false,
            'timeout_seconds' => $process['timeout_seconds'] ?? $timeoutSeconds,
            'stdout' => $process['stdout'],
            'stderr' => $process['stderr'],
            'report_status' => is_array($report) ? ($report['status'] ?? null) : null,
        ];
    }

    private function populationToolProcessTimeout(array $tool, array $config): int
    {
        $sectionPath = (string) ($tool['config_section'] ?? '');
        $section = $sectionPath !== '' ? $this->getNestedValue($config, $sectionPath) : null;
        if (!is_array($section)) {
            $section = [];
        }
        $populate = is_array($section['populate'] ?? null) ? $section['populate'] : $section;

        $toolTimeout = $tool['timeout_seconds'] ?? $tool['timeout'] ?? null;
        $configuredTimeout = $populate['timeout_seconds'] ?? $populate['timeout'] ?? null;
        $timeout = $toolTimeout ?? $configuredTimeout ?? 1500;
        if (!is_numeric($timeout)) {
            $timeout = 1500;
        }

        return max(60, min(1700, (int) ceil((float) $timeout) + 30));
    }

    private function buildAppConfig(array $app, array $kitConfig, string $runId, ?string $modeOverride = null): array
    {
        $appConfig = $app['config'];
        $dependencies = $kitConfig['shared']['dependencies'] ?? [];
        $database = $appConfig['database'] ?? ($kitConfig['shared']['database'] ?? null);
        $mode = $modeOverride !== null ? $modeOverride : (string) ($appConfig['mode'] ?? 'fresh');

        $result = [
            'schema_version' => 1,
            'mode' => $mode,
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
            'platform' => $this->buildAppPlatformConfig($kitConfig),
            'dependencies' => $dependencies,
            'secrets' => $kitConfig['shared']['secrets'] ?? ['policy' => 'app-generated'],
            'options' => [
                'database_setup' => 'baseline_schema',
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
            $result['database'] = $this->resolvePasswordEnvConfig($database);
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

        $result = $this->applySharedInstallDefaults($result, $app);
        if (($app['id'] ?? '') === 'pbb-mapserver') {
            $result = $this->applyMapServerTlsConfig($result);
        }
        if (($app['id'] ?? '') === 'pbb-hotline') {
            $result = $this->applyHotlineTlsConfig($result);
        }
        if (($app['id'] ?? '') === 'pbb-relay') {
            $result = $this->applyRelayHubIdentityConfig($result, $kitConfig);
        }

        return $result;
    }

    private function applyRelayHubIdentityConfig(array $config, array $kitConfig): array
    {
        if (!isset($config['relay']) || !is_array($config['relay'])) {
            $config['relay'] = [];
        }

        $hub = is_array($kitConfig['shared']['hub'] ?? null) ? $kitConfig['shared']['hub'] : [];
        $hubConfig = is_array($kitConfig['hub'] ?? null) ? $kitConfig['hub'] : [];
        $relayHubId = trim((string) ($hub['relay_hub_id'] ?? $kitConfig['kit']['node_id'] ?? ''));
        $hqHubId = $hub['hub_id'] ?? $kitConfig['kit']['hub_record_id'] ?? $hubConfig['hub_id'] ?? null;
        $hqBaseUrl = rtrim((string) ($hub['base_url'] ?? $hubConfig['base_url'] ?? $config['relay']['hq_api_base_url'] ?? 'https://hub.pbb.ph'), '/');

        if ($relayHubId !== '') {
            $config['relay']['hub_id'] = $relayHubId;
        }
        if ($hqHubId !== null && $hqHubId !== '') {
            $config['relay']['hq_hub_id'] = $hqHubId;
        }
        if ($hqBaseUrl !== '') {
            $config['relay']['hq_api_base_url'] = $hqBaseUrl;
        }

        $hubSnapshot = $this->publicHubSnapshot($hub, $hubConfig, $kitConfig);
        if (count($hubSnapshot) > 0) {
            $config['relay']['hub'] = $hubSnapshot;
        }

        $token = $this->getHubToken($hubConfig);
        if ($token !== '') {
            $config['relay']['hq_api_token'] = $token;
        }

        return $config;
    }

    private function publicHubSnapshot(array $hub, array $hubConfig, array $kitConfig): array
    {
        $baseUrl = rtrim((string) ($hub['base_url'] ?? $hubConfig['base_url'] ?? ''), '/');
        $hubId = $hub['hub_id'] ?? $hub['id'] ?? $kitConfig['kit']['hub_record_id'] ?? $hubConfig['hub_id'] ?? null;
        $relayHubId = trim((string) ($hub['relay_hub_id'] ?? $kitConfig['kit']['node_id'] ?? ''));
        if ($hubId === null && $relayHubId === '' && $baseUrl === '') {
            return [];
        }

        $locationCodes = is_array($kitConfig['kit']['location_codes'] ?? null) ? $kitConfig['kit']['location_codes'] : [];
        $snapshot = [
            'base_url' => $baseUrl !== '' ? $baseUrl : null,
            'hub_id' => $hubId,
            'relay_hub_id' => $relayHubId !== '' ? $relayHubId : null,
            'name' => $hub['name'] ?? ($kitConfig['kit']['node_name'] ?? null),
            'code' => $hub['code'] ?? null,
            'deployment' => $hub['deployment'] ?? ($kitConfig['kit']['deployment'] ?? null),
            'domain' => $hub['domain'] ?? ($kitConfig['kit']['domain'] ?? null),
            'status' => $hub['status'] ?? null,
            'country_code' => $hub['country_code'] ?? ($locationCodes['country_code'] ?? null),
            'reg_code' => $hub['reg_code'] ?? ($locationCodes['reg_code'] ?? null),
            'prov_code' => $hub['prov_code'] ?? ($locationCodes['prov_code'] ?? null),
            'citymun_code' => $hub['citymun_code'] ?? ($locationCodes['citymun_code'] ?? null),
            'brgy_code' => $hub['brgy_code'] ?? ($locationCodes['brgy_code'] ?? null),
            'uplinks' => $this->redactInstallStateHubList($hub['uplinks'] ?? []),
            'sources' => $this->redactInstallStateHubList($hub['sources'] ?? []),
        ];

        return array_filter($snapshot, static function ($value): bool {
            return $value !== null && $value !== '';
        });
    }

    private function applySharedInstallDefaults(array $config, array $app): array
    {
        $appId = (string) ($app['id'] ?? '');
        $releasePath = (string) ($app['release_path'] ?? '');
        if ($appId === '' || $releasePath === '') {
            return $config;
        }

        $defaultsPath = $this->joinPath($releasePath, 'resources' . DIRECTORY_SEPARATOR . 'kit-setup' . DIRECTORY_SEPARATOR . 'shared-install-defaults.json');
        $defaults = $this->readOptionalJson($defaultsPath);
        if (!is_array($defaults)) {
            return $config;
        }

        if ((string) ($defaults['app_id'] ?? '') !== $appId) {
            return $config;
        }

        $values = is_array($defaults['values'] ?? null) ? $defaults['values'] : [];
        if ($appId === 'pbb-mapserver') {
            $config = $this->applyMapServerSharedInstallDefaults($config, $values);
        }

        return $config;
    }

    private function applySharedInstallDefaultsToKitConfig(array $config): array
    {
        $apps = is_array($config['apps'] ?? null) ? $config['apps'] : [];
        foreach ($apps as $index => $appConfig) {
            if (!is_array($appConfig)) {
                continue;
            }

            $appId = (string) ($appConfig['id'] ?? '');
            $releasePath = (string) ($appConfig['release_path'] ?? $appConfig['install_path'] ?? '');
            if ($appId === '' || $releasePath === '') {
                continue;
            }

            $defaultsPath = $this->joinPath($releasePath, 'resources' . DIRECTORY_SEPARATOR . 'kit-setup' . DIRECTORY_SEPARATOR . 'shared-install-defaults.json');
            $defaults = $this->readOptionalJson($defaultsPath);
            if (!is_array($defaults) || (string) ($defaults['app_id'] ?? '') !== $appId) {
                continue;
            }

            $values = is_array($defaults['values'] ?? null) ? $defaults['values'] : [];
            if ($appId === 'pbb-mapserver') {
                [$config, $appConfig] = $this->applyMapServerSharedDefaultsToKitConfig($config, $appConfig, $values);
                $apps[$index] = $appConfig;
            }
        }

        $config['apps'] = $apps;
        return $config;
    }

    private function applyMapServerSharedDefaultsToKitConfig(array $config, array $appConfig, array $values): array
    {
        $mapserver = is_array($values['mapserver'] ?? null) ? $values['mapserver'] : [];
        $sharedSecrets = $this->getNestedValue($values, 'shared.secrets.values');
        if (!is_array($sharedSecrets)) {
            $sharedSecrets = [];
        }

        foreach (['stadiamaps_api_key', 'maptiler_api_key'] as $key) {
            $value = trim((string) ($mapserver[$key] ?? $sharedSecrets[$key] ?? ''));
            if ($value === '' || $this->isPlaceholder($value)) {
                continue;
            }

            if (!isset($config['shared']) || !is_array($config['shared'])) {
                $config['shared'] = [];
            }
            if (!isset($config['shared']['secrets']) || !is_array($config['shared']['secrets'])) {
                $config['shared']['secrets'] = ['policy' => 'kit-provided'];
            }
            if (!isset($config['shared']['secrets']['values']) || !is_array($config['shared']['secrets']['values'])) {
                $config['shared']['secrets']['values'] = [];
            }
            $config['shared']['secrets']['values'][$key] = $value;

            if (!isset($appConfig['config']) || !is_array($appConfig['config'])) {
                $appConfig['config'] = [];
            }
            if (!isset($appConfig['config']['mapserver']) || !is_array($appConfig['config']['mapserver'])) {
                $appConfig['config']['mapserver'] = [];
            }
            $appConfig['config']['mapserver'][$key] = $value;
        }

        return [$config, $appConfig];
    }

    private function applyMapServerSharedInstallDefaults(array $config, array $values): array
    {
        $mapserver = is_array($values['mapserver'] ?? null) ? $values['mapserver'] : [];
        $sharedSecrets = $this->getNestedValue($values, 'shared.secrets.values');
        if (!is_array($sharedSecrets)) {
            $sharedSecrets = [];
        }

        foreach (['stadiamaps_api_key', 'maptiler_api_key'] as $key) {
            $value = trim((string) ($mapserver[$key] ?? $sharedSecrets[$key] ?? ''));
            if ($value === '' || $this->isPlaceholder($value)) {
                continue;
            }

            if (!isset($config['mapserver']) || !is_array($config['mapserver'])) {
                $config['mapserver'] = [];
            }
            $config['mapserver'][$key] = $value;

            if (!isset($config['secrets']) || !is_array($config['secrets'])) {
                $config['secrets'] = ['policy' => 'kit-provided'];
            }
            if (!isset($config['secrets']['values']) || !is_array($config['secrets']['values'])) {
                $config['secrets']['values'] = [];
            }
            $config['secrets']['values'][$key] = $value;
        }

        return $config;
    }

    private function buildAppPlatformConfig(array $kitConfig): array
    {
        $platform = is_array($kitConfig['platform'] ?? null) ? $kitConfig['platform'] : [];

        return array_filter([
            'os' => $platform['os'] ?? (PHP_OS_FAMILY === 'Windows' ? 'windows' : 'linux'),
            'web_server' => $platform['web_server'] ?? null,
            'stack' => $platform['stack'] ?? null,
            'apache_binary' => $platform['apache_binary'] ?? null,
            'mysql_binary' => $platform['mysql_binary'] ?? null,
            'ffmpeg_binary' => $platform['ffmpeg_binary'] ?? null,
            'ffprobe_binary' => $platform['ffprobe_binary'] ?? null,
        ], static fn ($value): bool => is_string($value) ? $value !== '' : $value !== null);
    }

    private function buildAppInstallerEnvironment(array $kitConfig): array
    {
        $environment = getenv();
        if (!is_array($environment)) {
            $environment = $_ENV;
        }

        $platform = is_array($kitConfig['platform'] ?? null) ? $kitConfig['platform'] : [];
        $mysqlBinary = (string) ($platform['mysql_binary'] ?? '');
        if ($mysqlBinary !== '' && is_file($mysqlBinary)) {
            $mysqlBin = dirname($mysqlBinary);
            $path = (string) ($environment['PATH'] ?? $environment['Path'] ?? getenv('PATH') ?: getenv('Path') ?: '');
            if ($path === '' || stripos($path, $mysqlBin) === false) {
                $path = $mysqlBin . PATH_SEPARATOR . $path;
            }
            $environment['PATH'] = $path;
            $environment['Path'] = $path;
            $environment['PBB_MYSQL_BINARY'] = $mysqlBinary;
        }

        return $environment;
    }

    private function resolveAdminConfig(array $admin): array
    {
        return $this->resolvePasswordEnvConfig($admin);
    }

    private function resolvePasswordEnvConfig(array $config): array
    {
        $passwordEnv = (string) ($config['password_env'] ?? '');
        if ($passwordEnv !== '') {
            $password = getenv($passwordEnv);
            if (is_string($password) && $password !== '') {
                $config['password'] = $password;
            }
        }

        unset($config['password_env']);
        return $config;
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

    private function setNestedValue(array &$data, string $path, $value): void
    {
        $current = &$data;
        foreach (explode('.', $path) as $part) {
            if (!is_array($current)) {
                $current = [];
            }
            if (!array_key_exists($part, $current) || !is_array($current[$part])) {
                $current[$part] = [];
            }
            $current = &$current[$part];
        }
        $current = $value;
    }

    private function runProcess(array $command, string $cwd, ?array $environment = null, ?int $timeoutSeconds = null): array
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $commandLine = implode(' ', array_map([$this, 'escapeArg'], $command));
        $process = proc_open($commandLine, $descriptorSpec, $pipes, $cwd, $environment);
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start process: ' . $commandLine);
        }

        fclose($pipes[0]);
        if ($timeoutSeconds !== null) {
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
        }

        $stdout = '';
        $stderr = '';
        $timedOut = false;
        $exitCode = null;

        if ($timeoutSeconds === null) {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
        } else {
            $deadline = microtime(true) + max(1, $timeoutSeconds);
            while (true) {
                $stdout .= (string) stream_get_contents($pipes[1]);
                $stderr .= (string) stream_get_contents($pipes[2]);

                $status = proc_get_status($process);
                if (($status['running'] ?? false) !== true) {
                    $exitCode = isset($status['exitcode']) ? (int) $status['exitcode'] : null;
                    break;
                }

                if (microtime(true) >= $deadline) {
                    $timedOut = true;
                    $pid = isset($status['pid']) ? (int) $status['pid'] : 0;
                    proc_terminate($process);
                    if (PHP_OS_FAMILY === 'Windows' && $pid > 0) {
                        @exec('taskkill.exe /PID ' . $pid . ' /T /F');
                    }
                    $stderr = trim($stderr . PHP_EOL . 'Process timed out after ' . $timeoutSeconds . ' seconds.');
                    $exitCode = 124;
                    break;
                }

                usleep(100000);
            }

            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        $closedExitCode = proc_close($process);
        if ($exitCode === null && is_int($closedExitCode)) {
            $exitCode = $closedExitCode;
        }
        if ($timedOut) {
            $exitCode = 124;
        }

        return [
            'command' => $commandLine,
            'exit_code' => (int) $exitCode,
            'timed_out' => $timedOut,
            'timeout_seconds' => $timeoutSeconds,
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

    private function installStatePath(): string
    {
        $programData = (string) (getenv('ProgramData') ?: getenv('PROGRAMDATA') ?: 'C:\\ProgramData');
        return $this->joinPath($programData, 'PBB' . DIRECTORY_SEPARATOR . 'KitSetup' . DIRECTORY_SEPARATOR . 'install-state.json');
    }

    private function assertDataPrepAllowed(): array
    {
        $path = $this->installStatePath();
        if (!is_file($path)) {
            throw new RuntimeException('Data Prep is locked because Kit Setup has not completed on this machine.');
        }
        $state = $this->readJsonFile($path);
        if (($state['status'] ?? '') !== 'success') {
            throw new RuntimeException('Data Prep is locked because the Kit Setup completion marker is not successful.');
        }
        if (!isset($state['apps']) || !is_array($state['apps']) || count($state['apps']) === 0) {
            throw new RuntimeException('Data Prep is locked because the Kit Setup completion marker has no app topology.');
        }
        return $state;
    }

    private function applyInstallStateToDataPrepConfig(array $config, array $state): array
    {
        $installedApps = [];
        foreach (($state['apps'] ?? []) as $app) {
            if (!is_array($app)) {
                continue;
            }
            $appId = (string) ($app['app_id'] ?? $app['id'] ?? '');
            if ($appId === '') {
                continue;
            }
            $installedApps[$appId] = $app;
        }

        if (count($installedApps) === 0) {
            throw new RuntimeException('Data Prep is locked because the Kit Setup completion marker has no app topology.');
        }

        $configApps = [];
        foreach (($config['apps'] ?? []) as $appConfig) {
            if (!is_array($appConfig)) {
                continue;
            }
            $appId = (string) ($appConfig['id'] ?? '');
            if ($appId === '' || !isset($installedApps[$appId])) {
                $appConfig['enabled'] = false;
                $configApps[] = $appConfig;
                continue;
            }

            $installed = $installedApps[$appId];
            $scope = (string) ($installed['scope'] ?? 'local');
            $installPath = (string) ($installed['install_path'] ?? '');
            $baseUrl = (string) ($installed['base_url'] ?? '');
            $healthUrl = (string) ($installed['health_url'] ?? '');
            $healthUrl = $this->preferReleaseHealthUrl($installPath, $baseUrl, $healthUrl);

            $appConfig['enabled'] = $scope === 'local' && $installPath !== '';
            $appConfig['install_scope'] = $scope;
            if ($installPath !== '') {
                $appConfig['install_path'] = $installPath;
                $appConfig['release_path'] = $installPath;
                $appConfig['public_path'] = $appId === 'pbb-mapserver'
                    ? $installPath
                    : $this->joinPath($installPath, 'public');
            }
            if ($baseUrl !== '') {
                $appConfig['app_url'] = $baseUrl;
            }
            if ($healthUrl !== '') {
                if (!isset($appConfig['smoke']) || !is_array($appConfig['smoke'])) {
                    $appConfig['smoke'] = [];
                }
                $appConfig['smoke']['url'] = $healthUrl;
            }
            $configApps[] = $appConfig;
        }

        $knownConfigIds = [];
        foreach ($configApps as $appConfig) {
            if (is_array($appConfig) && isset($appConfig['id'])) {
                $knownConfigIds[(string) $appConfig['id']] = true;
            }
        }
        foreach ($installedApps as $appId => $installed) {
            if (isset($knownConfigIds[$appId])) {
                continue;
            }
            $scope = (string) ($installed['scope'] ?? 'local');
            $installPath = (string) ($installed['install_path'] ?? '');
            $baseUrl = (string) ($installed['base_url'] ?? '');
            $healthUrl = (string) ($installed['health_url'] ?? '');
            $healthUrl = $this->preferReleaseHealthUrl($installPath, $baseUrl, $healthUrl);
            $configApps[] = [
                'id' => $appId,
                'enabled' => $scope === 'local' && $installPath !== '',
                'install_scope' => $scope,
                'install_path' => $installPath,
                'release_path' => $installPath,
                'public_path' => $appId === 'pbb-mapserver' ? $installPath : $this->joinPath($installPath, 'public'),
                'app_url' => $baseUrl !== '' ? $baseUrl : null,
                'smoke' => $healthUrl !== '' ? ['url' => $healthUrl] : [],
            ];
        }

        $config['apps'] = $configApps;
        $config = $this->applyInstalledDependencyUrls($config, $installedApps);
        $config['data_prep']['install_state'] = [
            'source' => $this->installStatePath(),
            'completed_at' => $state['completed_at'] ?? null,
            'setup_run_id' => $state['kit_setup']['run_id'] ?? null,
        ];
        $config = $this->applyInstallStateSecretsToDataPrepConfig($config, $state);
        if (isset($state['hub']) && is_array($state['hub'])) {
            if (!isset($config['shared']) || !is_array($config['shared'])) {
                $config['shared'] = [];
            }
            $config['shared']['hub'] = $state['hub'];
            $config['kit']['hub_record_id'] = $state['hub']['hub_id'] ?? ($config['kit']['hub_record_id'] ?? null);
            $config['kit']['node_id'] = $state['hub']['relay_hub_id'] ?? ($config['kit']['node_id'] ?? null);
            $config['kit']['node_name'] = $state['hub']['name'] ?? ($config['kit']['node_name'] ?? null);
            $config['kit']['deployment'] = $state['hub']['deployment'] ?? ($config['kit']['deployment'] ?? null);
            $config['kit']['domain'] = $state['hub']['domain'] ?? ($config['kit']['domain'] ?? null);
            $config['kit']['location_codes'] = [
                'country_code' => $state['hub']['country_code'] ?? null,
                'reg_code' => $state['hub']['reg_code'] ?? null,
                'prov_code' => $state['hub']['prov_code'] ?? null,
                'citymun_code' => $state['hub']['citymun_code'] ?? null,
                'brgy_code' => $state['hub']['brgy_code'] ?? null,
            ];
        }
        return $config;
    }

    private function applyInstallStateSecretsToDataPrepConfig(array $config, array $state): array
    {
        $setupRunDir = trim((string) ($state['artifacts']['run_dir'] ?? ''));
        if ($setupRunDir === '') {
            return $config;
        }

        $secretPath = $this->joinPath($setupRunDir, 'secrets' . DIRECTORY_SEPARATOR . 'kit-secrets.json');
        $secretFile = $this->readOptionalJson($secretPath);
        $setupValues = is_array($secretFile['values'] ?? null) ? $secretFile['values'] : [];
        if (count($setupValues) === 0) {
            return $config;
        }

        if (!isset($config['shared']) || !is_array($config['shared'])) {
            $config['shared'] = [];
        }
        if (!isset($config['shared']['secrets']) || !is_array($config['shared']['secrets'])) {
            $config['shared']['secrets'] = ['policy' => 'kit-provided'];
        }
        if (!isset($config['shared']['secrets']['values']) || !is_array($config['shared']['secrets']['values'])) {
            $config['shared']['secrets']['values'] = [];
        }

        $copied = [];
        foreach ($setupValues as $name => $value) {
            if (!is_string($name) || $name === '' || !is_scalar($value)) {
                continue;
            }
            $current = (string) ($config['shared']['secrets']['values'][$name] ?? '');
            if ($current !== '' && !$this->isPlaceholder($current)) {
                continue;
            }
            $stringValue = (string) $value;
            if ($stringValue === '' || $this->isPlaceholder($stringValue)) {
                continue;
            }
            $config['shared']['secrets']['values'][$name] = $stringValue;
            $copied[] = $name;
        }

        if (count($copied) > 0) {
            if (!isset($config['data_prep']) || !is_array($config['data_prep'])) {
                $config['data_prep'] = [];
            }
            if (!isset($config['data_prep']['install_state']) || !is_array($config['data_prep']['install_state'])) {
                $config['data_prep']['install_state'] = [];
            }
            $config['data_prep']['install_state']['secrets_source'] = $this->absolutePath($secretPath);
            $config['data_prep']['install_state']['secrets_reused'] = array_values(array_unique($copied));
        }

        return $config;
    }

    private function applyInstalledDependencyUrls(array $config, array $installedApps): array
    {
        $dependencyMap = [
            'pbb-maestro' => 'maestro',
            'pbb-realtime' => 'realtime',
            'pbb-relay' => 'relay',
            'pbb-mapserver' => 'mapserver',
        ];

        foreach ($dependencyMap as $appId => $dependencyKey) {
            $baseUrl = trim((string) ($installedApps[$appId]['base_url'] ?? ''));
            if ($baseUrl === '') {
                continue;
            }
            if (!isset($config['dependencies']) || !is_array($config['dependencies'])) {
                $config['dependencies'] = [];
            }
            if (!isset($config['dependencies'][$dependencyKey]) || !is_array($config['dependencies'][$dependencyKey])) {
                $config['dependencies'][$dependencyKey] = [];
            }
            $config['dependencies'][$dependencyKey]['base_url'] = $baseUrl;

            if (!isset($config['shared']) || !is_array($config['shared'])) {
                $config['shared'] = [];
            }
            if (!isset($config['shared']['dependencies']) || !is_array($config['shared']['dependencies'])) {
                $config['shared']['dependencies'] = [];
            }
            if (!isset($config['shared']['dependencies'][$dependencyKey]) || !is_array($config['shared']['dependencies'][$dependencyKey])) {
                $config['shared']['dependencies'][$dependencyKey] = [];
            }
            $config['shared']['dependencies'][$dependencyKey]['base_url'] = $baseUrl;
        }

        return $config;
    }

    private function buildInstallState(array $config, array $finishReport, array $context, string $finishReportPath): array
    {
        $now = date(DATE_ATOM);
        $runDir = (string) ($context['run_dir'] ?? '');
        $hubReport = $runDir !== '' ? $this->readOptionalJson($this->joinPath($runDir, 'hub-report.json')) : null;
        return [
            'schema_version' => 1,
            'kind' => 'pbb-kit-setup-install-state',
            'status' => 'success',
            'completed_at' => $finishReport['finished_at'] ?? $now,
            'kit_setup' => [
                'milestone' => self::MILESTONE,
                'version' => self::VERSION,
                'display_version' => self::DISPLAY_VERSION,
                'run_id' => $context['run_id'] ?? null,
            ],
            'setup_operator' => $this->setupOperator(),
            'machine' => [
                'hostname' => gethostname() ?: null,
                'os' => $config['platform']['os'] ?? PHP_OS_FAMILY,
                'install_base' => $config['paths']['apps_base'] ?? ($config['layout']['base_path'] ?? null),
            ],
            'network' => [
                'machine_ip' => $config['machine']['ip_address'] ?? null,
                'dns_zone' => $config['dns']['zone'] ?? ($config['domains']['zone'] ?? null),
                'technitium_base_url' => $config['dns']['base_url'] ?? null,
            ],
            'runtime' => $this->runtimeState($config),
            'hub' => $this->installStateHub($config, $hubReport),
            'apps' => $this->installStateApps($config, $finishReport),
            'artifacts' => [
                'run_dir' => $context['run_dir'] ?? null,
                'finish_report' => $this->absolutePath($finishReportPath),
                'runtime_config' => $context['config_path'] ?? null,
            ],
            'data_prep' => [
                'allowed' => true,
                'reason' => 'setup_completed',
            ],
            'integrity' => [
                'written_by' => 'pbb-kit-setup',
                'written_at' => $now,
            ],
        ];
    }

    private function runtimeState(array $config): array
    {
        return [
            'php_binary' => (string) ($config['runtime']['php_binary'] ?? ''),
            'apache_binary' => (string) ($config['platform']['apache_binary'] ?? ''),
            'mysql_binary' => (string) ($config['platform']['mysql_binary'] ?? ''),
        ];
    }

    private function setupOperator(): array
    {
        $domain = (string) (getenv('USERDOMAIN') ?: getenv('COMPUTERNAME') ?: '');
        $username = (string) (getenv('USERNAME') ?: getenv('USER') ?: '');
        return [
            'windows_username' => $username !== '' ? $username : null,
            'windows_domain' => $domain !== '' ? $domain : null,
            'user_profile' => (string) (getenv('USERPROFILE') ?: '') ?: null,
            'sid' => (string) (getenv('USER_SID') ?: '') ?: null,
            'captured_at' => date(DATE_ATOM),
        ];
    }

    private function installStateHub(array $config, ?array $hubReport = null): array
    {
        $configHub = is_array($config['shared']['hub'] ?? null) ? $config['shared']['hub'] : [];
        $reportHub = [];
        if (is_array($hubReport) && ($hubReport['status'] ?? '') === 'success' && is_array($hubReport['hub'] ?? null)) {
            $reportHub = $hubReport['hub'];
        }
        $hub = array_merge($configHub, $reportHub);
        $locationCodes = is_array($config['kit']['location_codes'] ?? null) ? $config['kit']['location_codes'] : [];

        return [
            'base_url' => $hub['base_url'] ?? ($configHub['base_url'] ?? ($config['hub']['base_url'] ?? null)),
            'hub_id' => $reportHub['hub_id'] ?? ($reportHub['id'] ?? ($hub['hub_id'] ?? ($hub['id'] ?? ($config['kit']['hub_record_id'] ?? ($config['hub']['hub_id'] ?? null))))),
            'relay_hub_id' => $hub['relay_hub_id'] ?? ($config['kit']['node_id'] ?? null),
            'name' => $hub['name'] ?? ($config['kit']['node_name'] ?? null),
            'code' => $hub['code'] ?? null,
            'deployment' => $hub['deployment'] ?? ($config['kit']['deployment'] ?? null),
            'domain' => $hub['domain'] ?? ($config['kit']['domain'] ?? null),
            'status' => $hub['status'] ?? null,
            'country_code' => $hub['country_code'] ?? ($locationCodes['country_code'] ?? null),
            'reg_code' => $hub['reg_code'] ?? ($locationCodes['reg_code'] ?? null),
            'prov_code' => $hub['prov_code'] ?? ($locationCodes['prov_code'] ?? null),
            'citymun_code' => $hub['citymun_code'] ?? ($locationCodes['citymun_code'] ?? null),
            'brgy_code' => $hub['brgy_code'] ?? ($locationCodes['brgy_code'] ?? null),
            'uplinks' => $this->redactInstallStateHubList($hub['uplinks'] ?? []),
            'sources' => $this->redactInstallStateHubList($hub['sources'] ?? []),
        ];
    }

    private function redactInstallStateHubList($items): array
    {
        if (!is_array($items)) {
            return [];
        }
        $result = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $result[] = $this->redactSensitiveHubValue($item);
        }
        return $result;
    }

    private function redactSensitiveHubValue(array $value): array
    {
        $clean = [];
        foreach ($value as $key => $item) {
            if (preg_match('/token|secret|password|private[_-]?key/i', (string) $key) === 1) {
                continue;
            }
            $clean[$key] = is_array($item) ? $this->redactSensitiveHubValue($item) : $item;
        }
        return $clean;
    }

    private function installStateApps(array $config, array $finishReport): array
    {
        $finishApps = [];
        foreach (($finishReport['apps'] ?? []) as $app) {
            if (is_array($app)) {
                $finishApps[(string) ($app['id'] ?? $app['app_id'] ?? '')] = $app;
            }
        }
        $smokeStatuses = $this->finishSmokeStatusByApp($finishReport);
        $apps = [];
        foreach (($config['apps'] ?? []) as $appConfig) {
            if (!is_array($appConfig) || ($appConfig['enabled'] ?? true) === false) {
                continue;
            }
            $appId = (string) ($appConfig['id'] ?? '');
            if ($appId === '') {
                continue;
            }
            $finishApp = $finishApps[$appId] ?? [];
            $appUrl = (string) ($appConfig['app_url'] ?? ($finishApp['url'] ?? ''));
            $healthUrl = $this->installStateHealthUrl($appConfig, $finishApp, $appUrl);
            $apps[] = [
                'app_id' => $appId,
                'app_code' => preg_replace('/^pbb-/', '', $appId),
                'display_name' => $finishApp['name'] ?? $appId,
                'scope' => (string) ($appConfig['install_scope'] ?? 'local'),
                'version' => $finishApp['version'] ?? ($finishApp['manifest']['version'] ?? null),
                'install_path' => $appConfig['install_path'] ?? null,
                'base_url' => $appUrl !== '' ? $appUrl : null,
                'health_url' => $healthUrl !== '' ? $healthUrl : null,
                'smoke_status' => $smokeStatuses[$appId] ?? null,
            ];
        }
        return $apps;
    }

    private function installStateHealthUrl(array $appConfig, array $finishApp, string $appUrl): string
    {
        $healthUrl = (string) ($appConfig['smoke']['url'] ?? ($finishApp['health_url'] ?? ''));
        if ($healthUrl !== '') {
            return $healthUrl;
        }

        $releasePath = (string) ($appConfig['release_path'] ?? $appConfig['install_path'] ?? '');
        $release = $releasePath !== '' ? $this->readOptionalJson($this->joinPath($releasePath, 'release.json')) : null;
        $healthPath = '';
        if (is_array($release) && is_array($release['health'] ?? null)) {
            $healthPath = (string) ($release['health']['http'] ?? $release['health']['ready'] ?? $release['health']['status'] ?? '');
        }

        if ($appUrl !== '' && $healthPath !== '') {
            return $this->joinUrlPath($appUrl, $healthPath);
        }

        return $appUrl !== '' ? rtrim($appUrl, '/') . '/up' : '';
    }

    private function preferReleaseHealthUrl(string $installPath, string $baseUrl, string $currentHealthUrl): string
    {
        if ($installPath === '' || $baseUrl === '') {
            return $currentHealthUrl;
        }

        $isGenericUp = preg_match('#/up/?$#i', $currentHealthUrl) === 1;
        if ($currentHealthUrl !== '' && !$isGenericUp) {
            return $currentHealthUrl;
        }

        $release = $this->readOptionalJson($this->joinPath($installPath, 'release.json'));
        if (!is_array($release) || !is_array($release['health'] ?? null)) {
            return $currentHealthUrl;
        }

        $healthPath = (string) ($release['health']['http'] ?? $release['health']['ready'] ?? $release['health']['status'] ?? '');
        if ($healthPath === '') {
            return $currentHealthUrl;
        }

        return $this->joinUrlPath($baseUrl, $healthPath);
    }

    private function joinUrlPath(string $baseUrl, string $path): string
    {
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    private function finishSmokeStatusByApp(array $finishReport): array
    {
        $path = (string) ($finishReport['reports']['smoke_report'] ?? '');
        $report = $path !== '' ? $this->readOptionalJson($path) : null;
        $statuses = [];
        if (!is_array($report) || !is_array($report['apps'] ?? null)) {
            return $statuses;
        }
        foreach ($report['apps'] as $app) {
            if (!is_array($app)) {
                continue;
            }
            $appId = (string) ($app['app_id'] ?? $app['id'] ?? '');
            if ($appId !== '') {
                $statuses[$appId] = $app['status'] ?? null;
            }
        }
        return $statuses;
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

    private function isPathInside(string $path, string $root): bool
    {
        $path = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        $root = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $root), DIRECTORY_SEPARATOR);
        if (PHP_OS_FAMILY === 'Windows') {
            $path = strtolower($path);
            $root = strtolower($root);
        }
        return $path === $root || strpos($path, $root . DIRECTORY_SEPARATOR) === 0;
    }

    private function relativePath(string $root, string $path): string
    {
        $root = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $root), DIRECTORY_SEPARATOR);
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $compareRoot = PHP_OS_FAMILY === 'Windows' ? strtolower($root) : $root;
        $comparePath = PHP_OS_FAMILY === 'Windows' ? strtolower($path) : $path;
        if ($comparePath === $compareRoot) {
            return '';
        }
        if (strpos($comparePath, $compareRoot . DIRECTORY_SEPARATOR) !== 0) {
            return $path;
        }
        return substr($path, strlen($root) + 1);
    }

    private function copyDirectory(string $source, string $target, ?callable $progress = null): void
    {
        if (!is_dir($source)) {
            throw new RuntimeException('Copy source is not a directory: ' . $source);
        }
        $this->ensureDirectory($target);
        $totalFiles = $this->countDirectoryFiles($source);
        $copiedFiles = 0;
        $interval = max(1, (int) floor(max(1, $totalFiles) / 25));

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
                $copiedFiles++;
                if ($progress !== null && ($copiedFiles === 1 || $copiedFiles === $totalFiles || $copiedFiles % $interval === 0)) {
                    $progress($copiedFiles, $totalFiles);
                }
            }
        }
    }

    private function countDirectoryFiles(string $source): int
    {
        if (!is_dir($source)) {
            return 0;
        }

        $count = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $count++;
            }
        }
        return $count;
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
