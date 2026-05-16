const path = require('path');

function buildRuntimeConfig(template, form) {
  const config = JSON.parse(JSON.stringify(template));
  const repoRoot = cleanString(form.repoRoot) || path.resolve(__dirname, '..');
  const userDataPath = cleanString(form.userDataPath) || path.join(repoRoot, 'storage');
  const basePath = cleanString(form.basePath) || getNestedValue(config, ['layout', 'base_path']) || getNestedValue(config, ['paths', 'apps_base']);
  const machineIp = cleanString(form.machineIp) || getNestedValue(config, ['machine', 'ip_address']) || '127.0.0.1';
  const phpPath = cleanString(form.phpPath) || getNestedValue(config, ['runtime', 'php_binary']) || 'php';
  const selectedApps = normalizeAppScopes(form.appScopes);

  applyKitOwnedPaths(config, repoRoot, userDataPath, basePath);
  setNestedValue(config, ['runtime', 'php_binary'], phpPath);
  setNestedValue(config, ['hub', 'hub_id'], toNumberOrExisting(form.hubId, getNestedValue(config, ['hub', 'hub_id'])));
  setNestedValue(config, ['hub', 'token_env'], 'PBB_HUB_TOKEN');
  setNestedValue(config, ['machine', 'ip_address'], machineIp);
  setNestedValue(config, ['machine', 'selected_apps'], Object.entries(selectedApps)
    .filter(([, scope]) => scope === 'local')
    .map(([appId]) => appId));
  setNestedValue(config, ['paths', 'apps_base'], basePath);
  setNestedValue(config, ['layout', 'base_path'], basePath);

  setNestedValue(config, ['dns', 'base_url'], cleanString(form.technitiumBaseUrl) || getNestedValue(config, ['dns', 'base_url']) || 'http://localhost:5380');
  setNestedValue(config, ['dns', 'zone'], cleanString(form.dnsZone) || getNestedValue(config, ['dns', 'zone']) || 'pbb.ph');
  setNestedValue(config, ['dns', 'token_env'], 'PBB_TECHNITIUM_TOKEN');
  setNestedValue(config, ['dns', 'update_mode'], form.applyDns === true ? 'apply' : 'plan-only');
  setNestedValue(config, ['dns', 'client_nameserver'], cleanString(form.dnsClientNameserver) || '');
  setNestedValue(config, ['dns', 'client_interface_alias'], cleanString(form.dnsClientInterfaceAlias) || '');
  setNestedValue(config, ['dns', 'client_update_mode'], form.applyDnsClient === true ? 'apply' : 'plan-only');

  setNestedValue(config, ['ssl', 'certificate_root'], cleanString(form.certRoot) || getNestedValue(config, ['ssl', 'certificate_root']) || '');
  setNestedValue(config, ['ssl', 'pem_upload_path'], cleanString(form.pemUploadPath) || '');
  setNestedValue(config, ['ssl', 'write_extracted_files'], form.writeExtractedFiles === true);
  setNestedValue(config, ['ssl', 'web_server_update_mode'], form.applyWebServer === true ? 'apply' : 'plan-only');
  setNestedValue(config, ['paths', 'apache_include_output'], cleanString(form.apacheIncludeOutput) || getNestedValue(config, ['paths', 'apache_include_output']) || '');
  applySslDefaultFiles(config);

  setNestedValue(config, ['shared', 'admin', 'name'], 'PBB Administrator');
  setNestedValue(config, ['shared', 'admin', 'email'], 'admin@pbb.local');
  if (config.shared && config.shared.admin) {
    delete config.shared.admin.password;
  }
  setNestedValue(config, ['shared', 'admin', 'password_env'], 'PBB_FIRST_ADMIN_PASSWORD');

  applyAppScopesAndPaths(config, selectedApps, basePath);
  return config;
}

function applyKitOwnedPaths(config, repoRoot, userDataPath, basePath) {
  const runRoot = path.join(userDataPath, 'runs');
  const packageCache = path.join(userDataPath, 'package-cache');
  const packagesRoot = path.join(repoRoot, 'packages');
  const packageManifest = path.join(packagesRoot, 'packages.bundled.json');

  setNestedValue(config, ['kit', 'run_root'], runRoot);
  setNestedValue(config, ['kit', 'install_root'], basePath || getNestedValue(config, ['kit', 'install_root']) || '');
  setNestedValue(config, ['paths', 'package_cache'], packageCache);
  setNestedValue(config, ['packages', 'base_path'], packagesRoot);
  setNestedValue(config, ['packages', 'manifest_path'], packageManifest);
  setNestedValue(config, ['packages', 'dry_run'], false);
  setNestedValue(config, ['packages', 'max_parallel'], 3);
}

function normalizeAppScopes(appScopes) {
  const knownApps = ['pbb-mapserver', 'pbb-maestro', 'pbb-realtime', 'pbb-relay', 'pbb-hotline'];
  const scopes = {};
  for (const appId of knownApps) {
    const scope = appScopes && ['local', 'remote', 'disabled'].includes(appScopes[appId])
      ? appScopes[appId]
      : 'local';
    scopes[appId] = scope;
  }
  return scopes;
}

function applyAppScopesAndPaths(config, selectedApps, basePath) {
  const layoutNames = getNestedValue(config, ['layout', 'apps']) || {};
  const domains = getNestedValue(config, ['domains']) || {};
  const appDomainKeys = {
    'pbb-mapserver': 'mapserver',
    'pbb-maestro': 'maestro',
    'pbb-realtime': 'realtime',
    'pbb-relay': 'relay',
    'pbb-hotline': 'hotline'
  };

  if (!Array.isArray(config.apps)) {
    return;
  }

  for (const appConfig of config.apps) {
    const appId = appConfig.id;
    const scope = selectedApps[appId] || 'disabled';
    const folder = layoutNames[appId] || appId.replace(/^pbb-/, '');
    const installPath = basePath ? path.join(basePath, folder) : appConfig.install_path;
    appConfig.enabled = scope !== 'disabled';
    appConfig.install_scope = scope;
    if (scope === 'local') {
      appConfig.install_path = installPath;
      appConfig.release_path = installPath;
      appConfig.public_path = appId === 'pbb-mapserver' ? installPath : path.join(installPath, 'public');
    }

    const domainKey = appDomainKeys[appId];
    if (domainKey && domains[domainKey]) {
      appConfig.app_url = domains[domainKey];
    }
  }
}

function applySslDefaultFiles(config) {
  const certRoot = getNestedValue(config, ['ssl', 'certificate_root']);
  if (!certRoot) {
    return;
  }
  setNestedValue(config, ['ssl', 'certificate_file'], path.join(certRoot, 'pbb.ph.crt'));
  setNestedValue(config, ['ssl', 'private_key_file'], path.join(certRoot, 'pbb.ph.key'));
  setNestedValue(config, ['ssl', 'chain_file'], path.join(certRoot, 'pbb.ph.fullchain.crt'));
}

function cleanString(value) {
  return String(value || '').trim();
}

function toNumberOrExisting(value, existing) {
  const number = Number(value);
  return Number.isFinite(number) && number > 0 ? number : existing;
}

function getNestedValue(data, pathParts) {
  let current = data;
  for (const part of pathParts) {
    if (!current || typeof current !== 'object' || !(part in current)) {
      return undefined;
    }
    current = current[part];
  }
  return current;
}

function setNestedValue(data, pathParts, value) {
  let current = data;
  for (let index = 0; index < pathParts.length - 1; index += 1) {
    const part = pathParts[index];
    if (!current[part] || typeof current[part] !== 'object') {
      current[part] = {};
    }
    current = current[part];
  }
  current[pathParts[pathParts.length - 1]] = value;
}

module.exports = {
  buildRuntimeConfig
};
