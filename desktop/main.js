const { app, BrowserWindow, ipcMain, dialog } = require('electron');
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const configBuilder = require('./config-builder');

const repoRoot = path.resolve(__dirname, '..');
const defaultConfigPath = path.join(repoRoot, 'examples', 'kit-config.local-all.example.json');
const defaultPhpPath = 'C:\\wamp64\\bin\\php\\php8.2.29\\php.exe';
const runnerPath = path.join(repoRoot, 'bin', 'kit-setup.php');

function createWindow() {
  const win = new BrowserWindow({
    width: 1200,
    height: 760,
    minWidth: 980,
    minHeight: 640,
    title: 'PBB Kit Setup',
    backgroundColor: '#f5f7f9',
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: false
    }
  });

  win.loadFile(path.join(__dirname, 'index.html'));
}

app.whenReady().then(() => {
  createWindow();
  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
      createWindow();
    }
  });
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit();
  }
});

ipcMain.handle('kit:get-defaults', async () => ({
  repoRoot,
  configPath: defaultConfigPath,
  phpPath: fs.existsSync(defaultPhpPath) ? defaultPhpPath : 'php'
}));

ipcMain.handle('kit:select-config', async () => {
  const result = await dialog.showOpenDialog({
    title: 'Select Kit Setup Config',
    defaultPath: defaultConfigPath,
    properties: ['openFile'],
    filters: [{ name: 'JSON config', extensions: ['json'] }]
  });

  if (result.canceled || result.filePaths.length === 0) {
    return null;
  }
  return result.filePaths[0];
});

ipcMain.handle('kit:select-folder', async (_event, request) => {
  const result = await dialog.showOpenDialog({
    title: String(request && request.title ? request.title : 'Select Folder'),
    defaultPath: String(request && request.defaultPath ? request.defaultPath : repoRoot),
    properties: ['openDirectory', 'createDirectory']
  });

  if (result.canceled || result.filePaths.length === 0) {
    return null;
  }
  return result.filePaths[0];
});

ipcMain.handle('kit:select-file', async (_event, request) => {
  const result = await dialog.showOpenDialog({
    title: String(request && request.title ? request.title : 'Select File'),
    defaultPath: String(request && request.defaultPath ? request.defaultPath : repoRoot),
    properties: ['openFile'],
    filters: request && request.filters ? request.filters : undefined
  });

  if (result.canceled || result.filePaths.length === 0) {
    return null;
  }
  return result.filePaths[0];
});

ipcMain.handle('kit:build-config', async (_event, request) => {
  const templatePath = String(request.templatePath || defaultConfigPath);
  const template = readJsonIfExists(templatePath);
  if (!template) {
    throw new Error(`Unable to read template config: ${templatePath}`);
  }

  const form = request.form || {};
  const config = configBuilder.buildRuntimeConfig(template, form);
  const configDir = path.join(repoRoot, 'storage', 'desktop-configs');
  fs.mkdirSync(configDir, { recursive: true });
  const configPath = path.join(configDir, `kit-config-${Date.now()}.json`);
  fs.writeFileSync(configPath, JSON.stringify(config, null, 2) + '\n', 'utf8');
  return { configPath, config };
});

ipcMain.handle('kit:describe-action', async (_event, request) => {
  const action = String(request.action || '');
  const configPath = String(request.configPath || defaultConfigPath);
  const config = readJsonIfExists(configPath);
  if (!config) {
    throw new Error(`Unable to read config: ${configPath}`);
  }
  return describeAction(action, config);
});

ipcMain.handle('kit:run-action', async (_event, request) => {
  const action = String(request.action || '');
  const allowedActions = new Set([
    'detect',
    'hub-resolve',
    'stage-report',
    'finish-report',
    'plan',
    'prepare-packages',
    'dns-plan',
    'dns-apply',
    'dns-verify',
    'ssl-plan',
    'ssl-apply',
    'remote-check',
    'smoke-check',
    'preflight',
    'install',
    'populate'
  ]);
  if (!allowedActions.has(action)) {
    throw new Error(`Unsupported action: ${action}`);
  }

  const phpPath = String(request.phpPath || 'php');
  const configPath = String(request.configPath || defaultConfigPath);
  const runId = String(request.runId || `${action}_${Date.now()}`);
  const appId = String(request.appId || '').trim();
  const env = buildRuntimeEnv(request.secrets || {});
  const args = [
    runnerPath,
    '--config',
    configPath,
    '--action',
    action,
    '--run-id',
    runId
  ];
  if (appId !== '') {
    args.push('--app', appId);
  }

  const processResult = await runProcess(phpPath, args, env);
  const reportPath = getReportPath(configPath, runId, action);
  const report = readJsonIfExists(reportPath);
  const checkpointPath = getCheckpointPath(configPath, runId);
  const checkpoints = readJsonIfExists(checkpointPath);
  return {
    action,
    runId,
    exitCode: processResult.exitCode,
    stdout: processResult.stdout,
    stderr: processResult.stderr,
    reportPath,
    report,
    checkpointPath,
    checkpoints,
    appId: appId || null
  };
});

function buildRuntimeEnv(secrets) {
  const env = { ...process.env };
  const mappings = {
    hubToken: 'PBB_HUB_TOKEN',
    technitiumToken: 'PBB_TECHNITIUM_TOKEN',
    adminPassword: 'PBB_FIRST_ADMIN_PASSWORD'
  };

  for (const [field, envName] of Object.entries(mappings)) {
    const value = String(secrets[field] || '').trim();
    if (value !== '') {
      env[envName] = value;
    }
  }
  return env;
}

function describeAction(action, config) {
  const localApps = Array.isArray(config.apps)
    ? config.apps.filter((item) => item && item.enabled !== false && (item.install_scope || 'local') === 'local')
    : [];
  const descriptions = {
    'prepare-packages': {
      title: 'Prepare Trusted App Packages',
      risk: config.packages && config.packages.dry_run === false ? 'mutating' : 'guarded',
      summary: config.packages && config.packages.dry_run === false
        ? 'Verified packages may be copied into selected app folders.'
        : 'Package preparation is currently dry-run only.',
      details: [
        `Selected local apps: ${localApps.length}`,
        `packages.dry_run: ${String(config.packages ? config.packages.dry_run : true)}`,
        `manifest: ${config.packages && config.packages.manifest_path ? config.packages.manifest_path : 'not configured'}`
      ]
    },
    'dns-apply': {
      title: 'Apply DNS Records',
      risk: config.dns && config.dns.update_mode === 'apply' ? 'mutating' : 'guarded',
      summary: config.dns && config.dns.update_mode === 'apply'
        ? 'Technitium DNS records may be created or overwritten.'
        : 'DNS apply will skip API writes because update mode is not apply.',
      details: [
        `provider: ${config.dns && config.dns.provider ? config.dns.provider : 'not configured'}`,
        `base URL: ${config.dns && config.dns.base_url ? config.dns.base_url : 'not configured'}`,
        `zone: ${config.dns && config.dns.zone ? config.dns.zone : 'not configured'}`,
        `dns.update_mode: ${config.dns && config.dns.update_mode ? config.dns.update_mode : 'not configured'}`
      ]
    },
    'dns-verify': {
      title: 'Verify DNS Records',
      risk: 'safe',
      summary: 'Planned DNS records will be resolved from this machine and compared with the target IP.',
      details: [
        `provider: ${config.dns && config.dns.provider ? config.dns.provider : 'not configured'}`,
        `zone: ${config.dns && config.dns.zone ? config.dns.zone : 'not configured'}`,
        `verification mode: ${config.dns && config.dns.verification_mode ? config.dns.verification_mode : 'system'}`,
        `nameserver: ${config.dns && config.dns.verify_nameserver ? config.dns.verify_nameserver : 'system default'}`
      ]
    },
    'ssl-apply': {
      title: 'Apply SSL/Web Server Config',
      risk: config.ssl && config.ssl.web_server_update_mode === 'apply' ? 'mutating' : 'guarded',
      summary: config.ssl && config.ssl.web_server_update_mode === 'apply'
        ? 'The generated Apache include may be written to the configured target path.'
        : 'SSL apply will skip Apache include writes because update mode is not apply.',
      details: [
        `certificate root: ${config.ssl && config.ssl.certificate_root ? config.ssl.certificate_root : 'not configured'}`,
        `write extracted files: ${String(config.ssl ? config.ssl.write_extracted_files : false)}`,
        `web_server_update_mode: ${config.ssl && config.ssl.web_server_update_mode ? config.ssl.web_server_update_mode : 'not configured'}`,
        `Apache include: ${config.paths && config.paths.apache_include_output ? config.paths.apache_include_output : 'not configured'}`
      ]
    },
    install: {
      title: 'Install Selected Apps',
      risk: 'mutating',
      summary: 'Selected local app installers may write files, databases, manifests, and service artifacts.',
      details: [
        `Selected local apps: ${localApps.length}`,
        `Apps: ${localApps.map((item) => item.id).join(', ') || 'none'}`
      ]
    },
    populate: {
      title: 'Populate Initial Data',
      risk: 'mutating',
      summary: 'Enabled population tools may create initial records, clients, tiles, or seed data.',
      details: [
        `Selected local apps: ${localApps.length}`,
        'Only app-declared tools with enabled population config will run.'
      ]
    },
    'smoke-check': {
      title: 'Run Final Smoke Checks',
      risk: 'safe',
      summary: 'Selected app URLs will be resolved and checked for HTTP reachability.',
      details: [
        `Selected local apps: ${localApps.length}`,
        `Apps: ${localApps.map((item) => item.id).join(', ') || 'none'}`
      ]
    }
  };

  return descriptions[action] || {
    title: action,
    risk: 'safe',
    summary: 'This action is treated as non-mutating.',
    details: []
  };
}

function runProcess(command, args, env) {
  return new Promise((resolve) => {
    const child = spawn(command, args, {
      cwd: repoRoot,
      env,
      windowsHide: true
    });
    let stdout = '';
    let stderr = '';

    child.stdout.on('data', (chunk) => {
      stdout += chunk.toString();
    });
    child.stderr.on('data', (chunk) => {
      stderr += chunk.toString();
    });
    child.on('error', (error) => {
      resolve({
        exitCode: 1,
        stdout,
        stderr: stderr + error.message
      });
    });
    child.on('close', (exitCode) => {
      resolve({
        exitCode,
        stdout: stdout.trim(),
        stderr: stderr.trim()
      });
    });
  });
}

function getReportPath(configPath, runId, action) {
  const config = readJsonIfExists(configPath);
  const runRoot = config && config.kit && config.kit.run_root
    ? config.kit.run_root
    : path.join(repoRoot, 'storage', 'runs');
  const filenames = {
    detect: 'platform-report.json',
    'hub-resolve': 'hub-report.json',
    'stage-report': 'stage-report.json',
    'finish-report': 'finish-report.json',
    plan: 'kit-report.json',
    'prepare-packages': 'package-report.json',
    'dns-plan': 'dns-plan.json',
    'dns-apply': 'dns-apply.json',
    'dns-verify': 'dns-verify.json',
    'ssl-plan': 'ssl-plan.json',
    'ssl-apply': 'ssl-apply.json',
    'remote-check': 'remote-check.json',
    'smoke-check': 'smoke-check.json',
    preflight: 'kit-report.json',
    install: 'kit-report.json',
    populate: 'kit-report.json'
  };
  return path.join(runRoot, runId, filenames[action] || 'kit-report.json');
}

function getCheckpointPath(configPath, runId) {
  const config = readJsonIfExists(configPath);
  const runRoot = config && config.kit && config.kit.run_root
    ? config.kit.run_root
    : path.join(repoRoot, 'storage', 'runs');
  return path.join(runRoot, runId, 'checkpoints.json');
}

function readJsonIfExists(filePath) {
  try {
    if (!filePath || !fs.existsSync(filePath)) {
      return null;
    }
    return JSON.parse(fs.readFileSync(filePath, 'utf8'));
  } catch (_error) {
    return null;
  }
}
