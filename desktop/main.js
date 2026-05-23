const { app, BrowserWindow, ipcMain, dialog, Menu, globalShortcut } = require('electron');
const { spawn } = require('child_process');
const fs = require('fs');
const dns = require('dns');
const http = require('http');
const https = require('https');
const os = require('os');
const path = require('path');
const configBuilder = require('./config-builder');

const repoRoot = path.resolve(__dirname, '..');
const packageMeta = require(path.join(repoRoot, 'package.json'));
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
  apachePath: findApacheBinary(),
  mysqlPath: findMysqlBinary(),
  localIpAddress: detectLocalIpAddress(),
  caCertPath,
  caCertExists: fs.existsSync(caCertPath),
  userDataPath: app.getPath('userData'),
  launchMode,
  devToolsEnabled,
  kitSetupVersion: packageMeta?.pbb?.displayVersion || `v${packageMeta.version}`
}));

function detectLocalIpAddress() {
  const interfaces = os.networkInterfaces();
  const candidates = [];
  for (const items of Object.values(interfaces)) {
    for (const item of items || []) {
      if (!item || item.family !== 'IPv4' || item.internal) {
        continue;
      }
      const address = String(item.address || '').trim();
      if (!address || address.startsWith('169.254.')) {
        continue;
      }
      candidates.push({
        address,
        score: address.startsWith('192.168.') || address.startsWith('10.') || /^172\.(1[6-9]|2\d|3[0-1])\./.test(address) ? 0 : 1
      });
    }
  }
  candidates.sort((left, right) => left.score - right.score || left.address.localeCompare(right.address, undefined, { numeric: true }));
  return candidates[0]?.address || '127.0.0.1';
}

async function detectTechnitium(form = {}) {
  const candidates = await technitiumCandidates(form);
  const results = [];
  for (const candidate of candidates) {
    const result = await probeTechnitium(candidate.url, candidate.source);
    results.push(result);
    if (result.status === 'success') {
      return {
        status: 'success',
        url: result.url,
        source: result.source,
        message: 'Technitium DNS was detected.',
        candidates: results
      };
    }
  }
  return {
    status: 'failed',
    url: '',
    source: '',
    message: 'No reachable Technitium DNS instance was detected.',
    candidates: results
  };
}

async function technitiumCandidates(form = {}) {
  const seen = new Set();
  const candidates = [];
  const add = (url, source) => {
    const normalized = normalizeTechnitiumUrl(url);
    if (!normalized || seen.has(normalized)) {
      return;
    }
    seen.add(normalized);
    candidates.push({ url: normalized, source });
  };

  add(form.technitiumBaseUrl, 'configured-url');
  for (const server of await getWindowsDnsServers()) {
    add(`http://${server}:5380`, 'active-adapter-dns');
  }
  const zone = String(form.dnsZone || '').trim();
  if (zone) {
    add(`http://dns.${zone}:5380`, 'dns-zone-host');
  }
  const gateway = await getDefaultGateway();
  if (gateway) {
    add(`http://${gateway}:5380`, 'default-gateway');
  }
  return candidates;
}

function normalizeTechnitiumUrl(value) {
  const text = String(value || '').trim();
  if (!text) {
    return '';
  }
  try {
    const url = new URL(text.includes('://') ? text : `http://${text}`);
    if (!url.port) {
      url.port = '5380';
    }
    url.pathname = '/';
    url.search = '';
    url.hash = '';
    return url.toString().replace(/\/$/, '');
  } catch {
    return '';
  }
}

function probeTechnitium(url, source) {
  return new Promise((resolve) => {
    let parsed;
    try {
      parsed = new URL(url);
    } catch {
      resolve({ status: 'failed', url, source, message: 'Invalid URL.' });
      return;
    }
    const client = parsed.protocol === 'https:' ? https : http;
    const request = client.get(parsed, {
      timeout: 2500,
      rejectUnauthorized: false,
      headers: { 'User-Agent': 'PBB-Kit-Setup' }
    }, (response) => {
      let body = '';
      response.setEncoding('utf8');
      response.on('data', (chunk) => {
        body += chunk;
      });
      response.on('end', () => {
        const looksLikeTechnitium = /technitium/i.test(body)
          || /technitium/i.test(String(response.headers.server || ''))
          || /dns server/i.test(body);
        resolve({
          status: looksLikeTechnitium ? 'success' : 'failed',
          url,
          source,
          http_status: response.statusCode,
          message: looksLikeTechnitium ? 'Technitium response detected.' : 'HTTP response did not look like Technitium.'
        });
      });
    });
    request.on('timeout', () => {
      request.destroy(new Error('Connection timed out.'));
    });
    request.on('error', (error) => {
      resolve({ status: 'failed', url, source, message: error.message });
    });
  });
}

async function getWindowsDnsServers() {
  if (process.platform !== 'win32') {
    return dns.getServers().filter(looksLikeIpv4Address);
  }
  const command = [
    '$ErrorActionPreference="SilentlyContinue";',
    'Get-DnsClientServerAddress -AddressFamily IPv4 |',
    'Where-Object { $_.ServerAddresses -and $_.ServerAddresses.Count -gt 0 } |',
    'ForEach-Object { $_.ServerAddresses }'
  ].join(' ');
  const result = await runProcess('powershell.exe', ['-NoProfile', '-Command', command], process.env, { timeoutMs: 6000 });
  return result.stdout.split(/\r?\n/)
    .map((line) => line.trim())
    .filter(looksLikeIpv4Address);
}

async function getDefaultGateway() {
  if (process.platform !== 'win32') {
    return '';
  }
  const command = '(Get-NetRoute -DestinationPrefix "0.0.0.0/0" | Sort-Object RouteMetric,InterfaceMetric | Select-Object -First 1 -ExpandProperty NextHop)';
  const result = await runProcess('powershell.exe', ['-NoProfile', '-Command', command], process.env, { timeoutMs: 6000 });
  const value = result.stdout.split(/\r?\n/).map((line) => line.trim()).find(looksLikeIpv4Address);
  return value || '';
}

async function inspectWindowsInstaller() {
  if (process.platform !== 'win32') {
    return { status: 'unsupported', matches: [] };
  }
  const script = [
    '$paths = @(',
    '"HKLM:\\Software\\Microsoft\\Windows\\CurrentVersion\\Uninstall\\*",',
    '"HKLM:\\Software\\WOW6432Node\\Microsoft\\Windows\\CurrentVersion\\Uninstall\\*",',
    '"HKCU:\\Software\\Microsoft\\Windows\\CurrentVersion\\Uninstall\\*"',
    ');',
    '$items = foreach ($path in $paths) { Get-ItemProperty $path -ErrorAction SilentlyContinue };',
    '$items | Where-Object { $_.DisplayName -match "Project Bantay Bayan|PBB Kit Setup|Bantay Bayan" } |',
    'Select-Object DisplayName,DisplayVersion,InstallLocation,Publisher,UninstallString | ConvertTo-Json -Depth 3'
  ].join(' ');
  const result = await runProcess('powershell.exe', ['-NoProfile', '-Command', script], process.env, { timeoutMs: 8000 });
  let matches = [];
  try {
    const parsed = result.stdout ? JSON.parse(result.stdout) : [];
    matches = Array.isArray(parsed) ? parsed : parsed ? [parsed] : [];
  } catch {
    matches = [];
  }
  return {
    status: matches.length > 0 ? 'installed' : 'not-found',
    matches: matches.map((item) => ({
      display_name: item.DisplayName || '',
      version: item.DisplayVersion || '',
      install_location: item.InstallLocation || '',
      publisher: item.Publisher || '',
      uninstall_command: item.UninstallString || ''
    }))
  };
}

function looksLikeIpv4Address(value) {
  const text = String(value || '').trim();
  if (!/^\d{1,3}(?:\.\d{1,3}){3}$/.test(text)) {
    return false;
  }
  return text.split('.').every((part) => Number(part) >= 0 && Number(part) <= 255);
}

async function inspectAppRuntimeProcesses(installPath) {
  const target = String(installPath || '').trim();
  if (process.platform !== 'win32' || target === '') {
    return { status: 'skipped', process_count: 0, processes: [] };
  }
  const escaped = target.replace(/'/g, "''").toLowerCase();
  const script = [
    '$items = Get-CimInstance Win32_Process -ErrorAction SilentlyContinue |',
    `Where-Object { $_.CommandLine -and $_.CommandLine.ToLower().Contains('${escaped}') } |`,
    'Select-Object ProcessId,Name,CommandLine;',
    '$items | ConvertTo-Json -Depth 3'
  ].join(' ');
  const result = await runProcess('powershell.exe', ['-NoProfile', '-Command', script], process.env, { timeoutMs: 8000 });
  let parsed = [];
  try {
    parsed = result.stdout ? JSON.parse(result.stdout) : [];
  } catch {
    parsed = [];
  }
  const processes = (Array.isArray(parsed) ? parsed : parsed ? [parsed] : []).map((item) => ({
    pid: item.ProcessId || null,
    name: item.Name || '',
    command: item.CommandLine || ''
  }));
  return {
    status: processes.length > 0 ? 'running' : 'not-running',
    process_count: processes.length,
    processes: processes.slice(0, 5)
  };
}

function resolveHostAddresses(host) {
  return new Promise((resolve) => {
    const value = String(host || '').trim();
    if (!value) {
      resolve({ status: 'skipped', addresses: [] });
      return;
    }
    dns.lookup(value, { all: true }, (error, addresses) => {
      if (error) {
        resolve({ status: 'failed', addresses: [], message: error.message });
        return;
      }
      resolve({
        status: 'success',
        addresses: (addresses || []).map((item) => item.address).filter(Boolean)
      });
    });
  });
}

function probeAppUrl(url) {
  return new Promise((resolve) => {
    let parsed;
    try {
      parsed = new URL(url);
    } catch {
      resolve({ status: 'skipped', url, message: 'Invalid URL.' });
      return;
    }
    const client = parsed.protocol === 'https:' ? https : http;
    const request = client.request(parsed, {
      method: 'GET',
      timeout: 2500,
      rejectUnauthorized: false,
      headers: { 'User-Agent': 'PBB-Kit-Setup' }
    }, (response) => {
      response.resume();
      response.on('end', () => {
        resolve({
          status: response.statusCode && response.statusCode < 500 ? 'reachable' : 'warning',
          url,
          http_status: response.statusCode || null
        });
      });
    });
    request.on('timeout', () => {
      request.destroy(new Error('Connection timed out.'));
    });
    request.on('error', (error) => {
      resolve({ status: 'failed', url, message: error.message });
    });
    request.end();
  });
}

async function estimateDiskSpace(form = {}) {
  const selectedApps = form.appScopes && typeof form.appScopes === 'object' ? form.appScopes : {};
  const decisions = form.appInstallDecisions && typeof form.appInstallDecisions === 'object' ? form.appInstallDecisions : {};
  const packageManifest = readJsonIfExists(path.join(repoRoot, 'packages', 'packages.bundled.json'));
  const packages = Array.isArray(packageManifest?.packages) ? packageManifest.packages : [];
  const selectedPackages = packages.filter((item) => {
    const appId = String(item.app_id || '');
    return selectedApps[appId] === 'local' && decisions[appId] !== 'skip';
  }).map((item) => {
    const packagePath = path.resolve(repoRoot, 'packages', String(item.path || ''));
    const size = fs.existsSync(packagePath) ? fs.statSync(packagePath).size : 0;
    return {
      app_id: item.app_id || '',
      version: item.version || '',
      path: packagePath,
      archive_bytes: size
    };
  });
  const archiveBytes = selectedPackages.reduce((sum, item) => sum + Number(item.archive_bytes || 0), 0);
  const targetRequiredBytes = Math.ceil((archiveBytes * 2.5) + (512 * 1024 * 1024));
  const stagingRequiredBytes = Math.ceil((archiveBytes * 1.5) + (512 * 1024 * 1024));
  const basePath = String(form.basePath || '').trim() || 'C:\\wamp64\\www\\pbb-node';
  const targetDrive = await driveSpaceForPath(basePath);
  const stagingDrive = await driveSpaceForPath(app.getPath('userData'));
  const checks = [
    { id: 'target', label: 'Install drive', required_bytes: targetRequiredBytes, ...targetDrive },
    { id: 'staging', label: 'Staging drive', required_bytes: stagingRequiredBytes, ...stagingDrive }
  ].map((check) => ({
    ...check,
    status: Number(check.free_bytes || 0) > 0 && Number(check.free_bytes || 0) < Number(check.required_bytes || 0)
      ? 'failed'
      : 'success'
  }));
  const failures = checks.filter((check) => Number(check.free_bytes || 0) > 0 && Number(check.free_bytes || 0) < Number(check.required_bytes || 0));
  return {
    status: failures.length > 0 ? 'failed' : 'success',
    package_count: selectedPackages.length,
    archive_bytes: archiveBytes,
    required_bytes: targetRequiredBytes + stagingRequiredBytes,
    packages: selectedPackages,
    checks,
    errors: failures.map((check) => `${check.label} needs ${formatBytes(check.required_bytes)} but only ${formatBytes(check.free_bytes)} is free.`)
  };
}

async function driveSpaceForPath(targetPath) {
  const root = path.parse(path.resolve(String(targetPath || '.'))).root;
  const device = root.replace(/\\+$/, '');
  if (process.platform !== 'win32') {
    return { path: targetPath, drive: root, free_bytes: null, total_bytes: null };
  }
  const script = `Get-CimInstance Win32_LogicalDisk -Filter "DeviceID='${device.replace(/'/g, "''")}'" | Select-Object DeviceID,FreeSpace,Size | ConvertTo-Json -Depth 2`;
  const result = await runProcess('powershell.exe', ['-NoProfile', '-Command', script], process.env, { timeoutMs: 8000 });
  try {
    const parsed = result.stdout ? JSON.parse(result.stdout) : null;
    return {
      path: targetPath,
      drive: parsed?.DeviceID || device,
      free_bytes: Number(parsed?.FreeSpace || 0),
      total_bytes: Number(parsed?.Size || 0)
    };
  } catch {
    return { path: targetPath, drive: device, free_bytes: null, total_bytes: null };
  }
}

function formatBytes(bytes) {
  const value = Number(bytes || 0);
  if (value >= 1024 * 1024 * 1024) {
    return `${(value / 1024 / 1024 / 1024).toFixed(1)} GB`;
  }
  if (value >= 1024 * 1024) {
    return `${(value / 1024 / 1024).toFixed(0)} MB`;
  }
  return `${value} bytes`;
}

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

ipcMain.handle('kit:select-save-file', async (_event, request) => {
  const result = await dialog.showSaveDialog({
    title: String(request && request.title ? request.title : 'Select Output File'),
    defaultPath: String(request && request.defaultPath ? request.defaultPath : repoRoot),
    filters: request && request.filters ? request.filters : undefined
  });

  if (result.canceled || !result.filePath) {
    return null;
  }
  return result.filePath;
});

ipcMain.handle('kit:validate-path', async (_event, request) => {
  const targetPath = String(request && request.path ? request.path : '').trim();
  const mode = String(request && request.mode ? request.mode : 'file').toLowerCase();
  const required = Boolean(request && request.required);
  if (targetPath === '') {
    return required
      ? { valid: false, tone: 'warning', message: 'Path is required.' }
      : { valid: true, message: '' };
  }
  const exists = fs.existsSync(targetPath);
  if (mode === 'folder') {
    return exists && fs.statSync(targetPath).isDirectory()
      ? { valid: true, tone: 'success', message: 'Folder exists.' }
      : { valid: false, tone: 'warning', message: 'Folder does not exist yet.' };
  }
  if (mode === 'save-file') {
    const parent = path.dirname(targetPath);
    return fs.existsSync(parent) && fs.statSync(parent).isDirectory()
      ? { valid: true, tone: 'success', message: exists ? 'Output file exists.' : 'Output folder exists.' }
      : { valid: false, tone: 'warning', message: 'Output folder does not exist.' };
  }
  return exists && fs.statSync(targetPath).isFile()
    ? { valid: true, tone: 'success', message: 'File exists.' }
    : { valid: false, tone: 'warning', message: 'File does not exist.' };
});

ipcMain.handle('kit:detect-local-ip', async () => detectLocalIpAddress());

ipcMain.handle('kit:detect-technitium', async (_event, request) => {
  const form = request && request.form ? request.form : {};
  return detectTechnitium(form);
});

ipcMain.handle('kit:inspect-windows-installer', async () => inspectWindowsInstaller());

ipcMain.handle('kit:inspect-existing-installs', async (_event, request) => {
  const form = request && request.form ? request.form : {};
  const basePath = String(form.basePath || '').trim() || 'C:\\wamp64\\www\\pbb-node';
  const machineIp = String(form.machineIp || '').trim();
  const appScopes = form.appScopes && typeof form.appScopes === 'object' ? form.appScopes : {};
  const appFolders = {
    'pbb-mapserver': 'mapserver',
    'pbb-maestro': 'maestro',
    'pbb-realtime': 'realtime',
    'pbb-relay': 'relay',
    'pbb-hotline': 'hotline'
  };
  const hosts = {
    'pbb-mapserver': 'mapserver.pbb.ph',
    'pbb-maestro': 'maestro.pbb.ph',
    'pbb-realtime': 'realtime.pbb.ph',
    'pbb-relay': 'relay.pbb.ph',
    'pbb-hotline': 'hotline.pbb.ph'
  };
  const manifests = {
    'pbb-mapserver': path.join('storage', 'installer', 'install-manifest.json'),
    'pbb-maestro': path.join('storage', 'app', 'installer', 'install-manifest.json'),
    'pbb-realtime': path.join('storage', 'app', 'installer', 'install-manifest.json'),
    'pbb-relay': path.join('storage', 'app', 'installer', 'install-manifest.json'),
    'pbb-hotline': path.join('storage', 'app', 'installer', 'install-manifest.json')
  };

  const apps = await Promise.all(Object.entries(appFolders).map(async ([appId, folder]) => {
    const installPath = path.join(basePath, folder);
    const manifestPath = path.join(installPath, manifests[appId]);
    const manifest = readJsonIfExists(manifestPath);
    const pathExists = fs.existsSync(installPath);
    const manifestExists = Boolean(manifest);
    const scope = String(appScopes[appId] || 'local');
    const status = manifestExists ? 'installed' : pathExists ? 'path-found' : 'not-found';
    const host = hosts[appId];
    const [runtime, dnsResult, httpResult] = await Promise.all([
      inspectAppRuntimeProcesses(installPath),
      resolveHostAddresses(host),
      probeAppUrl(`https://${host}`)
    ]);
    return {
      app_id: appId,
      scope,
      host,
      expected_ip: machineIp,
      install_path: installPath,
      path_exists: pathExists,
      manifest_path: manifestPath,
      manifest_exists: manifestExists,
      installed_at: manifest?.installed_at || null,
      version: manifest?.display_version || manifest?.version || null,
      runtime,
      dns: dnsResult,
      http: httpResult,
      overwrite_risk: pathExists || runtime.process_count > 0,
      status
    };
  }));

  return {
    checked_at: new Date().toISOString(),
    machine_ip: machineIp,
    apps
  };
});

ipcMain.handle('kit:get-install-state', async () => inspectInstallState());

ipcMain.handle('kit:estimate-disk-space', async (_event, request) => {
  const form = request && request.form ? request.form : {};
  return estimateDiskSpace(form);
});

ipcMain.handle('kit:show-success-and-quit', async (event, request) => {
  const owner = BrowserWindow.fromWebContents(event.sender);
  await dialog.showMessageBox(owner || undefined, {
    type: 'info',
    buttons: ['Close Installer'],
    defaultId: 0,
    noLink: true,
    title: 'Installation Successful',
    message: 'Project Bantay Bayan installation completed successfully.',
    detail: String(request && request.detail ? request.detail : 'The installer will now close.')
  });
  setImmediate(() => {
    for (const win of BrowserWindow.getAllWindows()) {
      win.close();
    }
    app.quit();
  });
  return true;
});

ipcMain.handle('kit:quit-installer', async () => {
  setImmediate(() => {
    for (const win of BrowserWindow.getAllWindows()) {
      win.close();
    }
    app.quit();
  });
  return true;
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
    'firewall-apply',
    'service-plan',
    'service-start',
    'service-stop',
    'service-verify',
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

function findApacheBinary() {
  const candidates = [
    ...findWampToolCandidates('C:\\wamp64\\bin\\apache', 'apache', path.join('bin', 'httpd.exe')),
    'C:\\xampp\\apache\\bin\\httpd.exe',
    'C:\\Apache24\\bin\\httpd.exe'
  ];
  return firstExistingPath(candidates);
}

function findMysqlBinary() {
  const candidates = [
    ...findWampToolCandidates('C:\\wamp64\\bin\\mariadb', 'mariadb', path.join('bin', 'mysql.exe')),
    ...findWampToolCandidates('C:\\wamp64\\bin\\mysql', 'mysql', path.join('bin', 'mysql.exe')),
    'C:\\xampp\\mysql\\bin\\mysql.exe',
    'C:\\Program Files\\MariaDB 11.2\\bin\\mysql.exe',
    'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysql.exe'
  ];
  return firstExistingPath(candidates);
}

function findWampToolCandidates(root, prefix, executableRelativePath) {
  try {
    if (!fs.existsSync(root)) {
      return [];
    }
    return fs.readdirSync(root, { withFileTypes: true })
      .filter((entry) => entry.isDirectory() && entry.name.toLowerCase().startsWith(prefix))
      .map((entry) => path.join(root, entry.name, executableRelativePath))
      .sort((left, right) => right.localeCompare(left, undefined, { numeric: true }));
  } catch (_error) {
    return [];
  }
}

function firstExistingPath(candidates) {
  for (const candidate of candidates) {
    if (candidate && fs.existsSync(candidate)) {
      return candidate;
    }
  }
  return '';
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
    'finish-report',
    'dns-plan',
    'dns-apply',
    'dns-client-apply',
    'dns-verify',
    'service-plan',
    'service-start',
    'service-stop',
    'service-verify',
    'ssl-plan',
    'ssl-apply',
    'remote-check',
    'smoke-check',
    'preflight'
  ]);
  const longActions = new Set([
    'stage-report',
    'plan',
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
    stadiamapsApiKey: 'STADIAMAPS_API_KEY',
    maptilerApiKey: 'MAPTILER_API_KEY',
    adminPassword: 'PBB_FIRST_ADMIN_PASSWORD',
    mysqlPassword: 'PBB_MYSQL_PASSWORD'
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
        `packages.max_parallel: ${String(config.packages && config.packages.max_parallel ? config.packages.max_parallel : 5)}`,
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
    'firewall-apply': {
      title: 'Open Windows Firewall',
      risk: 'mutating',
      summary: 'Inbound Windows Firewall allow rules may be added for Project Bantay Bayan web access.',
      details: [
        'Default inbound ports: TCP 80 and TCP 443',
        'Existing Project Bantay Bayan firewall rules with the same names will be replaced.'
      ]
    },
    'service-plan': {
      title: 'Plan Runtime Services',
      risk: 'safe',
      summary: 'App-declared runtime service requirements will be collected for Kit orchestration.',
      details: [
        `Selected local apps: ${localApps.length}`,
        'Canonical app metadata key: runtime_services'
      ]
    },
    'service-start': {
      title: 'Start Runtime Services',
      risk: 'mutating',
      summary: 'Kit-managed app runtime services will be launched before service verification.',
      details: [
        `Selected local apps: ${localApps.length}`,
        'Only manager=kit background_process services are started by this action.'
      ]
    },
    'service-stop': {
      title: 'Stop Runtime Services',
      risk: 'mutating',
      summary: 'Kit-managed runtime service processes started by the current setup run will be stopped.',
      details: [
        'Only PIDs recorded by the current service-start report are targeted.',
        'Manually started or pre-existing services are left running.'
      ]
    },
    'service-verify': {
      title: 'Verify Runtime Services',
      risk: 'safe',
      summary: 'Required runtime service health checks will run before final smoke checks.',
      details: [
        `Selected local apps: ${localApps.length}`,
        'Services with required_for_smoke=true must pass health checks before handoff.'
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
      summary: 'Selected local app installers may write files, reset fresh app databases, write manifests, and generate service artifacts.',
      details: [
        `Selected local apps: ${localApps.length}`,
        `Apps: ${localApps.map((item) => item.id).join(', ') || 'none'}`,
        'Apps resolved to fresh install will have their app database cleared/recreated before baseline schema import.'
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
    plan: 'plan-report.json',
    'prepare-packages': 'package-report.json',
    'dns-plan': 'dns-plan.json',
    'dns-apply': 'dns-apply.json',
    'dns-client-apply': 'dns-client-apply.json',
    'dns-verify': 'dns-verify.json',
    'firewall-apply': 'firewall-apply.json',
    'service-plan': 'service-plan.json',
    'service-start': 'service-start.json',
    'service-stop': 'service-stop.json',
    'service-verify': 'service-verify.json',
    'ssl-plan': 'ssl-plan.json',
    'ssl-apply': 'ssl-apply.json',
    'remote-check': 'remote-check.json',
    'smoke-check': 'smoke-check.json',
    preflight: 'preflight-report.json',
    install: 'install-report.json',
    populate: 'populate-report.json'
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

function inspectInstallState() {
  const machinePath = getInstallStatePath();
  const machineState = readJsonIfExists(machinePath);
  const machineResult = validateInstallState(machineState, machinePath, 'machine');
  if (machineResult.allowed) {
    return machineResult;
  }

  const roamingResult = findLatestSuccessfulSetupSession();
  if (roamingResult.allowed) {
    return {
      ...roamingResult,
      machine_state_path: machinePath,
      machine_state_status: machineResult.status,
      warning: 'Using the latest completed Roaming setup session because the machine install-state marker is not available yet.'
    };
  }

  return {
    allowed: false,
    status: 'locked',
    reason: 'setup_not_completed',
    message: 'Kit Setup has not completed on this machine. Run Project Bantay Bayan Setup first.',
    path: machinePath,
    machine_state_status: machineResult.status,
    roaming_state_status: roamingResult.status
  };
}

function getInstallStatePath() {
  const programData = process.env.ProgramData || process.env.PROGRAMDATA || 'C:\\ProgramData';
  return path.join(programData, 'PBB', 'KitSetup', 'install-state.json');
}

function validateInstallState(state, statePath, source) {
  if (!state || typeof state !== 'object') {
    return {
      allowed: false,
      status: 'missing',
      reason: 'missing_marker',
      path: statePath,
      source
    };
  }
  if (state.status !== 'success') {
    return {
      allowed: false,
      status: 'invalid',
      reason: 'setup_not_successful',
      message: 'Setup completion marker is present but does not report success.',
      path: statePath,
      source,
      state
    };
  }
  const apps = Array.isArray(state.apps) ? state.apps : [];
  if (apps.length === 0) {
    return {
      allowed: false,
      status: 'invalid',
      reason: 'missing_app_topology',
      message: 'Setup completion marker does not include app topology.',
      path: statePath,
      source,
      state
    };
  }
  const runtime = installStateRuntime(state);
  if (runtime) {
    state.runtime = runtime;
  }
  return {
    allowed: true,
    status: 'success',
    reason: 'setup_completed',
    message: 'Kit Setup has completed. Data Prep can load the installed app topology.',
    path: statePath,
    source,
    state,
    apps
  };
}

function installStateRuntime(state) {
  if (state.runtime && typeof state.runtime === 'object') {
    return state.runtime;
  }
  const runtimeConfigPath = String(state.artifacts?.runtime_config || '').trim();
  const config = readJsonIfExists(runtimeConfigPath);
  if (!config || typeof config !== 'object') {
    return null;
  }
  return {
    php_binary: config.runtime?.php_binary || '',
    apache_binary: config.platform?.apache_binary || '',
    mysql_binary: config.platform?.mysql_binary || ''
  };
}

function findLatestSuccessfulSetupSession() {
  const runsRoot = path.join(app.getPath('userData'), 'runs');
  let entries = [];
  try {
    entries = fs.readdirSync(runsRoot, { withFileTypes: true })
      .filter((entry) => entry.isDirectory() && entry.name.startsWith('setup_session_'))
      .map((entry) => {
        const fullPath = path.join(runsRoot, entry.name);
        const finishPath = path.join(fullPath, 'finish-report.json');
        let mtimeMs = 0;
        try {
          mtimeMs = fs.statSync(finishPath).mtimeMs;
        } catch (_error) {
          try {
            mtimeMs = fs.statSync(fullPath).mtimeMs;
          } catch (_nestedError) {
            mtimeMs = 0;
          }
        }
        return { name: entry.name, fullPath, finishPath, mtimeMs };
      })
      .sort((left, right) => right.mtimeMs - left.mtimeMs);
  } catch (_error) {
    return {
      allowed: false,
      status: 'missing',
      reason: 'missing_roaming_runs',
      path: runsRoot,
      source: 'roaming'
    };
  }

  for (const entry of entries) {
    const finishReport = readJsonIfExists(entry.finishPath);
    const state = installStateFromFinishReport(finishReport, entry);
    const result = validateInstallState(state, entry.finishPath, 'roaming');
    if (result.allowed) {
      return result;
    }
  }

  return {
    allowed: false,
    status: 'missing',
    reason: 'no_successful_setup_session',
    path: runsRoot,
    source: 'roaming'
  };
}

function installStateFromFinishReport(report, entry) {
  if (!report || typeof report !== 'object') {
    return null;
  }
  const apps = Array.isArray(report.apps)
    ? report.apps.map((item) => ({
      app_id: item.id || item.app_id || null,
      app_code: String(item.id || item.app_id || '').replace(/^pbb-/, ''),
      display_name: item.name || item.id || item.app_id || null,
      scope: item.install_scope || 'local',
      version: item.version || item.manifest?.version || null,
      install_path: item.install_path || item.manifest?.install_path || null,
      base_url: item.url || item.app_url || null,
      health_url: item.health_url || item.smoke_url || null,
      smoke_status: item.smoke_status || item.status || null
    })).filter((item) => item.app_id)
    : [];
  return {
    schema_version: 1,
    kind: 'pbb-kit-setup-install-state',
    status: report.status,
    completed_at: report.finished_at || null,
    kit_setup: report.kit_setup || {
      version: report.kit_setup_version || null,
      display_version: report.kit_setup_version ? `v1-${report.kit_setup_version}` : null,
      run_id: report.run_id || entry.name
    },
    apps,
    runtime: report.runtime || null,
    artifacts: {
      run_dir: entry.fullPath,
      finish_report: entry.finishPath,
      runtime_config: null
    },
    data_prep: {
      allowed: report.status === 'success',
      reason: report.status === 'success' ? 'setup_completed' : 'setup_not_successful'
    }
  };
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
