const { app, BrowserWindow, ipcMain, dialog, Menu, globalShortcut } = require('electron');
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const configBuilder = require('./config-builder');

const repoRoot = path.resolve(__dirname, '..');
const defaultConfigPath = path.join(repoRoot, 'examples', 'kit-config.local-all.example.json');
const runnerPath = path.join(repoRoot, 'bin', 'kit-setup.php');
const appIconPath = path.join(repoRoot, 'assets', 'branding', 'app-icon.ico');
const caCertPath = path.join(repoRoot, 'assets', 'certs', 'cacert.pem');
const launchMode = getLaunchMode();
const devToolsEnabled = shouldEnableDevTools();

function createWindow() {
  const win = new BrowserWindow({
    width: 1200,
    height: 760,
    minWidth: 980,
    minHeight: 640,
    title: launchMode === 'data-prep' ? 'Project Bantay Bayan Data Prep' : 'Project Bantay Bayan Setup',
    icon: appIconPath,
    backgroundColor: '#f5f7f9',
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: false
    }
  });

  win.loadFile(path.join(__dirname, 'index.html'));
  if (devToolsEnabled) {
    win.webContents.once('did-finish-load', () => {
      win.webContents.openDevTools({ mode: 'detach' });
    });
  }
}

app.whenReady().then(() => {
  Menu.setApplicationMenu(null);
  globalShortcut.register('CommandOrControl+Shift+I', () => {
    const win = BrowserWindow.getFocusedWindow() || BrowserWindow.getAllWindows()[0];
    if (win) {
      win.webContents.toggleDevTools();
    }
  });
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

app.on('will-quit', () => {
  globalShortcut.unregisterAll();
});

ipcMain.handle('kit:get-defaults', async () => ({
  repoRoot,
  configPath: defaultConfigPath,
  phpPath: findPhpBinary(),
  caCertPath,
  caCertExists: fs.existsSync(caCertPath),
  userDataPath: app.getPath('userData'),
  launchMode,
  devToolsEnabled
}));

function getLaunchMode() {
  const modeArg = process.argv.find((arg) => arg.startsWith('--mode='));
  if (modeArg) {
    return modeArg.split('=')[1] === 'data-prep' ? 'data-prep' : 'setup';
  }
  const modeIndex = process.argv.indexOf('--mode');
  if (modeIndex >= 0 && process.argv[modeIndex + 1] === 'data-prep') {
    return 'data-prep';
  }
  return 'setup';
}

function shouldEnableDevTools() {
  return process.argv.includes('--devtools') || process.env.PBB_KIT_SETUP_DEVTOOLS === '1';
}

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
  console.log('[KitSetup:main] build-config:start', {
    templatePath: String(request.templatePath || defaultConfigPath),
    userDataPath: app.getPath('userData')
  });
  const templatePath = String(request.templatePath || defaultConfigPath);
  const template = readJsonIfExists(templatePath);
  if (!template) {
    throw new Error(`Unable to read template config: ${templatePath}`);
  }

  const form = request.form || {};
  const config = configBuilder.buildRuntimeConfig(template, {
    ...form,
    repoRoot,
    userDataPath: app.getPath('userData')
  });
  const configDir = path.join(app.getPath('userData'), 'desktop-configs');
  fs.mkdirSync(configDir, { recursive: true });
  const configPath = path.join(configDir, `kit-config-${Date.now()}.json`);
  fs.writeFileSync(configPath, JSON.stringify(config, null, 2) + '\n', 'utf8');
  console.log('[KitSetup:main] build-config:done', { configPath });
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

ipcMain.handle('kit:run-action', async (event, request) => {
  const action = String(request.action || '');
  console.log('[KitSetup:main] run-action:start', {
    action,
    configPath: String(request.configPath || defaultConfigPath),
    runId: String(request.runId || '')
  });
  const allowedActions = new Set([
    'detect',
    'hub-resolve',
    'stage-report',
    'finish-report',
    'plan',
    'prepare-packages',
    'dns-plan',
    'dns-apply',
    'dns-client-apply',
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
  const args = withPhpTlsArgs([
    runnerPath,
    '--config',
    configPath,
    '--action',
    action,
    '--run-id',
    runId
  ]);
  if (appId !== '') {
    args.push('--app', appId);
  }

  const resolvedPhpPath = resolvePhpForRun(phpPath);
  const debugStart = {
    action,
    php: resolvedPhpPath,
    args,
    configPath,
    runId
  };
  console.log('[KitSetup:main] run-action:spawn', debugStart);
  if (event && event.sender && !event.sender.isDestroyed()) {
    event.sender.send('kit:runner-output', {
      action,
      stream: 'debug',
      text: `DEBUG main spawn ${JSON.stringify(debugStart)}\n`
    });
  }
  const processResult = await runProcess(resolvedPhpPath, args, env, {
    timeoutMs: runnerTimeoutForAction(action),
    onStdout: (text) => {
      if (!event || !event.sender || event.sender.isDestroyed()) {
        return;
      }
      event.sender.send('kit:runner-output', { action, stream: 'stdout', text });
    },
    onStderr: (text) => {
      if (!event || !event.sender || event.sender.isDestroyed()) {
        return;
      }
      event.sender.send('kit:runner-output', { action, stream: 'stderr', text });
    }
  });
  const reportPath = getReportPath(configPath, runId, action);
  const report = readJsonIfExists(reportPath);
  const checkpointPath = getCheckpointPath(configPath, runId);
  const checkpoints = readJsonIfExists(checkpointPath);
  console.log('[KitSetup:main] run-action:done', {
    action,
    runId,
    exitCode: processResult.exitCode,
    reportPath,
    hasReport: Boolean(report),
    checkpointPath,
    hasCheckpoints: Boolean(checkpoints)
  });
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

function findPhpBinary() {
  const candidates = [
    ...findWampPhpCandidates(),
    'C:\\xampp\\php\\php.exe',
    'C:\\tools\\php\\php.exe',
    'C:\\php\\php.exe',
    'C:\\Program Files\\PHP\\php.exe'
  ];
  for (const candidate of candidates) {
    if (candidate && fs.existsSync(candidate)) {
      return candidate;
    }
  }
  return 'php';
}

function findWampPhpCandidates() {
  const phpRoot = 'C:\\wamp64\\bin\\php';
  try {
    if (!fs.existsSync(phpRoot)) {
      return [];
    }
    return fs.readdirSync(phpRoot, { withFileTypes: true })
      .filter((entry) => entry.isDirectory() && entry.name.toLowerCase().startsWith('php'))
      .map((entry) => path.join(phpRoot, entry.name, 'php.exe'))
      .sort((left, right) => right.localeCompare(left, undefined, { numeric: true }));
  } catch (_error) {
    return [];
  }
}

function resolvePhpForRun(phpPath) {
  const trimmed = String(phpPath || '').trim();
  if (trimmed === '' || trimmed.toLowerCase() === 'php') {
    return findPhpBinary();
  }
  if (path.isAbsolute(trimmed) && !fs.existsSync(trimmed)) {
    const discovered = findPhpBinary();
    if (discovered !== 'php') {
      return discovered;
    }
  }
  return trimmed;
}

function runnerTimeoutForAction(action) {
  const quickActions = new Set([
    'detect',
    'hub-resolve',
    'stage-report',
    'finish-report',
    'plan',
    'dns-plan',
    'dns-apply',
    'dns-client-apply',
    'dns-verify',
    'ssl-plan',
    'ssl-apply',
    'remote-check',
    'smoke-check',
    'preflight'
  ]);
  const longActions = new Set([
    'prepare-packages',
    'install',
    'populate'
  ]);

  if (quickActions.has(action)) {
    return 120000;
  }
  if (longActions.has(action)) {
    return 1800000;
  }
  return 300000;
}

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
  if (fs.existsSync(caCertPath)) {
    env.SSL_CERT_FILE = caCertPath;
    env.CURL_CA_BUNDLE = caCertPath;
  }
  return env;
}

function withPhpTlsArgs(args) {
  if (!fs.existsSync(caCertPath)) {
    return args;
  }
  return [
    '-d',
    `openssl.cafile=${caCertPath}`,
    '-d',
    `curl.cainfo=${caCertPath}`,
    ...args
  ];
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
        `packages.max_parallel: ${String(config.packages && config.packages.max_parallel ? config.packages.max_parallel : 3)}`,
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
    'dns-client-apply': {
      title: 'Use Local DNS On This Machine',
      risk: config.dns && config.dns.client_update_mode === 'apply' ? 'mutating' : 'guarded',
      summary: config.dns && config.dns.client_update_mode === 'apply'
        ? 'Windows network adapter DNS settings may be changed to use the local Technitium DNS server.'
        : 'DNS client apply will skip adapter changes because client update mode is not apply.',
      details: [
        `target DNS: ${config.dns && config.dns.client_nameserver ? config.dns.client_nameserver : 'Technitium URL host'}`,
        `adapter: ${config.dns && config.dns.client_interface_alias ? config.dns.client_interface_alias : 'auto-select active adapter'}`,
        `dns.client_update_mode: ${config.dns && config.dns.client_update_mode ? config.dns.client_update_mode : 'not configured'}`
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
      title: 'Run Data Preparation',
      risk: 'mutating',
      summary: 'Enabled data preparation tools may create or refresh operational records, clients, tiles, or cache data after installation.',
      details: [
        `Selected local apps: ${localApps.length}`,
        'Only app-declared tools with enabled population config will run. This is separate from the required installer path.'
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

function runProcess(command, args, env, hooks = {}) {
  return new Promise((resolve) => {
    const timeoutMs = Number(hooks.timeoutMs || 300000);
    console.log('[KitSetup:main] process:start', { command, args, timeoutMs });
    const child = spawn(command, args, {
      cwd: repoRoot,
      env,
      windowsHide: true
    });
    let stdout = '';
    let stderr = '';
    let settled = false;

    const finish = (result) => {
      if (settled) {
        return;
      }
      settled = true;
      clearTimeout(timer);
      resolve(result);
    };

    const timer = setTimeout(() => {
      const text = `Runner process timed out after ${timeoutMs} ms: ${command} ${args.join(' ')}\n`;
      stderr += text;
      console.error('[KitSetup:main] process:timeout', { command, args, timeoutMs });
      if (typeof hooks.onStderr === 'function') {
        hooks.onStderr(text);
      }
      try {
        child.kill('SIGKILL');
      } catch (error) {
        stderr += `${error.message}\n`;
      }
      finish({
        exitCode: 124,
        stdout: stdout.trim(),
        stderr: stderr.trim()
      });
    }, timeoutMs);

    child.stdout.on('data', (chunk) => {
      const text = chunk.toString();
      stdout += text;
      console.log('[KitSetup:main] process:stdout', text.trimEnd());
      if (typeof hooks.onStdout === 'function') {
        hooks.onStdout(text);
      }
    });
    child.stderr.on('data', (chunk) => {
      const text = chunk.toString();
      stderr += text;
      console.warn('[KitSetup:main] process:stderr', text.trimEnd());
      if (typeof hooks.onStderr === 'function') {
        hooks.onStderr(text);
      }
    });
    child.on('error', (error) => {
      console.error('[KitSetup:main] process:error', error);
      finish({
        exitCode: 1,
        stdout: stdout.trim(),
        stderr: (stderr + error.message).trim()
      });
    });
    child.on('close', (exitCode) => {
      console.log('[KitSetup:main] process:close', { exitCode });
      finish({
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
    'dns-client-apply': 'dns-client-apply.json',
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
