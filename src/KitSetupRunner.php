<?php

declare(strict_types=1);

final class KitSetupRunner
{
    private const VERSION = '0.1.0';

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
            if (!in_array($action, ['plan', 'preflight', 'install', 'populate'], true)) {
                throw new InvalidArgumentException('Unsupported --action. Use plan, preflight, install, or populate.');
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

            $apps = $this->discoverApps($config);
            $orderedApps = $this->orderApps($apps);

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
                'apps' => [],
                'warnings' => [],
                'errors' => [],
            ];

            if ($action === 'plan') {
                foreach ($orderedApps as $app) {
                    $kitReport['apps'][] = $this->planApp($app, $config, $runDir, $runId);
                }
                $kitReport['status'] = 'success';
                $kitReport['finished_at'] = date(DATE_ATOM);
                $this->writeJsonFile($this->joinPath($runDir, 'kit-report.json'), $kitReport);
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
            $this->writeJsonFile($this->joinPath($runDir, 'kit-report.json'), $kitReport);
            $this->writeLine('Run report: ' . $this->joinPath($runDir, 'kit-report.json'));

            return $failed ? 1 : 0;
        } catch (Throwable $e) {
            $this->writeLine('ERROR: ' . $e->getMessage(), true);
            return 1;
        }
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
        $this->writeLine('Usage: php bin/kit-setup.php --config <path> [--action plan|preflight|install|populate] [--run-dir <path>] [--run-id <id>]');
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
                $visit((string) $dependencyId);
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
                    $result[$key] = $appConfig[$key];
                }
            }
        }

        if (!isset($result['admin']) && isset($kitConfig['shared']['admin']) && is_array($kitConfig['shared']['admin'])) {
            $result['admin'] = $kitConfig['shared']['admin'];
        }

        return $result;
    }

    private function verifyChecksums(array $app): array
    {
        $checksumPath = $this->joinPath($app['release_path'], 'checksums.sha256');
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
            $path = $this->joinPath($app['release_path'], $relativePath);
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

    private function writeJsonFile(string $path, array $data): void
    {
        $this->ensureDirectory(dirname($path));
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Unable to encode JSON for ' . $path);
        }
        if (file_put_contents($path, $json . PHP_EOL) === false) {
            throw new RuntimeException('Unable to write JSON file: ' . $path);
        }
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
    }
}
