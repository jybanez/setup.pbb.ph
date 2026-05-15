<?php

declare(strict_types=1);

$options = parseArgs($argv);
$mode = (string) ($options['mode'] ?? 'fresh');
$configPath = (string) ($options['config'] ?? '');
$reportPath = (string) ($options['report'] ?? '');

if ($configPath === '' || $reportPath === '') {
    fwrite(STDERR, "Usage: php install-run.php --mode <mode> --config <path> --report <path>\n");
    exit(2);
}

$config = readJsonFile($configPath);
$startedAt = date(DATE_ATOM);
$steps = [];
$warnings = [];
$errors = [];
$status = 'success';

$steps[] = step('load-config', 'success', 'Stub installer loaded unattended config.');

if (!in_array($mode, ['preflight', 'fresh', 'upgrade', 'repair'], true)) {
    $status = 'failed';
    $errors[] = ['id' => 'mode.unsupported', 'message' => 'Unsupported mode: ' . $mode];
} else {
    $steps[] = step('validate-mode', 'success', 'Mode is supported: ' . $mode);
}

$installPath = (string) ($config['app']['install_path'] ?? '');
if ($installPath === '') {
    $status = 'failed';
    $errors[] = ['id' => 'app.install_path', 'message' => 'app.install_path is required.'];
} else {
    $steps[] = step('validate-install-path', 'success', 'Install path is present.');
}

if (($config['stub']['simulate_warning'] ?? false) === true) {
    $status = $status === 'failed' ? 'failed' : 'warning';
    $warnings[] = ['id' => 'stub.warning', 'message' => 'Simulated warning requested by config.'];
}

if ($status !== 'failed' && $mode !== 'preflight') {
    if (!is_dir($installPath) && !mkdir($installPath, 0775, true) && !is_dir($installPath)) {
        $status = 'failed';
        $errors[] = ['id' => 'filesystem.install_path', 'message' => 'Unable to create install path.'];
    } else {
        file_put_contents($installPath . DIRECTORY_SEPARATOR . 'stub-installed.txt', 'Installed by PBB Kit Setup stub at ' . date(DATE_ATOM) . PHP_EOL);
        $steps[] = step('write-stub-artifact', 'success', 'Stub artifact written.');
    }
}

$report = [
    'schema_version' => 1,
    'app' => 'pbb-stub',
    'version' => '0.1.0',
    'run_id' => (string) ($config['kit']['run_id'] ?? ''),
    'mode' => $mode,
    'status' => $status,
    'started_at' => $startedAt,
    'finished_at' => date(DATE_ATOM),
    'summary' => $status === 'failed' ? 'Stub installer failed.' : 'Stub installer completed.',
    'steps' => $steps,
    'urls' => [
        'app' => (string) ($config['app']['app_url'] ?? ''),
    ],
    'services' => [],
    'warnings' => $warnings,
    'errors' => $errors,
];

writeJsonFile($reportPath, $report);
fwrite(STDOUT, "Stub installer {$mode}: {$status}\n");
exit($status === 'failed' ? 1 : 0);

function parseArgs(array $argv): array
{
    $options = [];
    $count = count($argv);
    for ($i = 1; $i < $count; $i++) {
        $arg = (string) $argv[$i];
        if (strpos($arg, '--') !== 0) {
            continue;
        }
        $name = substr($arg, 2);
        $value = true;
        $next = $argv[$i + 1] ?? null;
        if (is_string($next) && strpos($next, '--') !== 0) {
            $value = $next;
            $i++;
        }
        $options[$name] = $value;
    }
    return $options;
}

function readJsonFile(string $path): array
{
    $json = file_get_contents($path);
    if ($json === false) {
        throw new RuntimeException('Unable to read JSON file: ' . $path);
    }
    $data = json_decode($json, true);
    if (!is_array($data)) {
        throw new RuntimeException('Invalid JSON file: ' . $path);
    }
    return $data;
}

function writeJsonFile(string $path, array $data): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
}

function step(string $id, string $status, string $message): array
{
    return [
        'id' => $id,
        'status' => $status,
        'message' => $message,
    ];
}
