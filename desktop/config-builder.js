const path = require('path');

function buildRuntimeConfig(template, form) {
  const config = JSON.parse(JSON.stringify(template));
  const repoRoot = cleanString(form.repoRoot) || path.resolve(__dirname, '..');
  const userDataPath = cleanString(form.userDataPath) || path.join(repoRoot, 'storage');
  const basePath = cleanString(form.basePath) || getNestedValue(config, ['layout', 'base_path']) || getNestedValue(config, ['paths', 'apps_base']);
  const machineIp = cleanString(form.machineIp) || getNestedValue(config, ['machine', 'ip_address']) || '127.0.0.1';
  const phpPath = cleanString(form.phpPath) || getNestedValue(config, ['runtime', 'php_binary']) || 'php';
  const apachePath = cleanString(form.apachePath);
  const mysqlPath = cleanString(form.mysqlPath);
  const selectedApps = normalizeAppScopes(form.appScopes);
  const appInstallDecisions = normalizeAppInstallDecisions(form.appInstallDecisions, selectedApps);

  applyKitOwnedPaths(config, repoRoot, userDataPath, basePath);
  setNestedValue(config, ['runtime', 'php_binary'], phpPath);
  if (apachePath !== '') {
    setNestedValue(config, ['platform', 'apache_binary'], apachePath);
  }
  if (mysqlPath !== '') {
    setNestedValue(config, ['platform', 'mysql_binary'], mysqlPath);
  }
  setNestedValue(config, ['hub', 'hub_id'], toNumberOrExisting(form.hubId, getNestedValue(config, ['hub', 'hub_id'])));
  setNestedValue(config, ['hub', 'token_env'], 'PBB_HUB_TOKEN');
  setNestedValue(config, ['machine', 'ip_address'], machineIp);
  setNestedValue(config, ['machine', 'selected_apps'], Object.entries(selectedApps)
    .filter(([, scope]) => scope === 'local')
    .map(([appId]) => appId));
  setNestedValue(config, ['machine', 'app_install_decisions'], appInstallDecisions);
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
  applyFirewallInputs(config, form);

  setNestedValue(config, ['shared', 'admin', 'name'], cleanString(form.adminName) || 'PBB Administrator');
  setNestedValue(config, ['shared', 'admin', 'email'], cleanString(form.adminEmail) || 'admin@pbb.local');
  if (config.shared && config.shared.admin) {
    delete config.shared.admin.password;
  }
  setNestedValue(config, ['shared', 'admin', 'password_env'], 'PBB_FIRST_ADMIN_PASSWORD');

  applyDatabaseInputs(config, form);
  applyMapProviderInputs(config);
  applyAppScopesAndPaths(config, selectedApps, basePath, appInstallDecisions);
  if (form.dataPrepApply === true) {
    setNestedValue(config, ['data_prep', 'apply'], true);
    const dataPrepStep = cleanString(form.dataPrepStep);
    if (dataPrepStep !== '') {
      setNestedValue(config, ['data_prep', 'step'], dataPrepStep);
    }
    applyDataPrepInputs(config);
  }
  if (form.dataPrepReadiness === true) {
    setNestedValue(config, ['data_prep', 'readiness_check'], true);
  }
  return config;
}

function applyDataPrepInputs(config) {
  const sections = {
    'pbb-mapserver': ['mapserver'],
    'pbb-maestro': ['maestro'],
    'pbb-realtime': ['realtime'],
    'pbb-relay': ['relay'],
    'pbb-hotline': ['hotline']
  };

  for (const app of Array.isArray(config.apps) ? config.apps : []) {
    const sectionNames = sections[app.id] || [];
    if (!app.config || typeof app.config !== 'object') {
      app.config = {};
    }
    for (const sectionName of sectionNames) {
      if (!app.config[sectionName] || typeof app.config[sectionName] !== 'object') {
        app.config[sectionName] = {};
      }
      if (app.config[sectionName].populate && typeof app.config[sectionName].populate === 'object') {
        app.config[sectionName].populate.enabled = true;
        app.config[sectionName].populate.dry_run = false;
        if (app.config[sectionName].populate.options && typeof app.config[sectionName].populate.options === 'object') {
          app.config[sectionName].populate.options.dry_run = false;
        }
      }
      if (!app.config[sectionName].data_prep || typeof app.config[sectionName].data_prep !== 'object') {
        app.config[sectionName].data_prep = {};
      }
      for (const key of ['prepare', 'prepare_data', 'apply_settings', 'verify']) {
        if (!app.config[sectionName].data_prep[key] || typeof app.config[sectionName].data_prep[key] !== 'object') {
          app.config[sectionName].data_prep[key] = {};
        }
        app.config[sectionName].data_prep[key].enabled = true;
        app.config[sectionName].data_prep[key].dry_run = false;
      }
    }
  }
}

function applyFirewallInputs(config, form) {
  const updateMode = form.applyFirewall === true ? 'apply' : 'plan-only';
  setNestedValue(config, ['platform', 'firewall', 'update_mode'], updateMode);
}

function applyMapProviderInputs(config) {
  setNestedValue(config, ['shared', 'secrets', 'values', 'stadiamaps_api_key'], 'REPLACE_WITH_STADIAMAPS_API_KEY');
  setNestedValue(config, ['shared', 'secrets', 'values', 'maptiler_api_key'], 'REPLACE_WITH_MAPTILER_API_KEY');

  if (!Array.isArray(config.apps)) {
    return;
  }

  for (const appConfig of config.apps) {
    if (appConfig.id !== 'pbb-mapserver') {
      continue;
    }
    if (!appConfig.config || typeof appConfig.config !== 'object') {
      appConfig.config = {};
    }
    if (!appConfig.config.mapserver || typeof appConfig.config.mapserver !== 'object') {
      appConfig.config.mapserver = {};
    }
    appConfig.config.mapserver.stadiamaps_api_key = 'REPLACE_WITH_STADIAMAPS_API_KEY';
    appConfig.config.mapserver.maptiler_api_key = 'REPLACE_WITH_MAPTILER_API_KEY';
  }
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
  setNestedValue(config, ['packages', 'max_parallel'], 5);
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

function normalizeAppInstallDecisions(decisions, selectedApps) {
  const allowed = new Set(['install', 'repair', 'overwrite', 'skip']);
  const normalized = {};
  for (const [appId, scope] of Object.entries(selectedApps)) {
    if (scope !== 'local') {
      normalized[appId] = 'skip';
      continue;
    }
    const decision = decisions && allowed.has(decisions[appId]) ? decisions[appId] : 'install';
    normalized[appId] = decision;
  }
  return normalized;
}

function applyAppScopesAndPaths(config, selectedApps, basePath, appInstallDecisions = {}) {
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
    const decision = appInstallDecisions[appId] || (scope === 'local' ? 'install' : 'skip');
    const effectiveScope = decision === 'skip' ? 'disabled' : scope;
    appConfig.enabled = effectiveScope !== 'disabled';
    appConfig.install_scope = effectiveScope;
    appConfig.install_decision = decision;
    if (effectiveScope === 'local') {
      appConfig.install_path = installPath;
      appConfig.release_path = installPath;
      appConfig.public_path = appId === 'pbb-mapserver' ? installPath : path.join(installPath, 'public');
      applyAppOwnedRuntimePaths(appConfig, appId, installPath);
    }

    const domainKey = appDomainKeys[appId];
    if (domainKey && domains[domainKey]) {
      appConfig.app_url = domains[domainKey];
    }
  }
}

function applyAppOwnedRuntimePaths(appConfig, appId, installPath) {
  if (appId !== 'pbb-mapserver') {
    return;
  }
  if (!appConfig.config || typeof appConfig.config !== 'object') {
    appConfig.config = {};
  }
  if (!appConfig.config.mapserver || typeof appConfig.config.mapserver !== 'object') {
    appConfig.config.mapserver = {};
  }
  appConfig.config.mapserver.cache_root = path.join(installPath, 'storage', 'tiles');
  appConfig.config.mapserver.log_file = path.join(installPath, 'storage', 'logs', 'tiles.log');
}

function applyDatabaseInputs(config, form) {
  const sharedDatabase = getNestedValue(config, ['shared', 'database']) || {};
  const databaseHost = cleanString(form.databaseHost) || sharedDatabase.host || '127.0.0.1';
  const databasePort = toNumberOrExisting(form.databasePort, sharedDatabase.port || 3306);
  const databaseUsername = cleanString(form.databaseUsername) || sharedDatabase.username || 'root';
  const databasePasswordEnv = 'PBB_MYSQL_PASSWORD';

  setNestedValue(config, ['shared', 'database', 'driver'], sharedDatabase.driver || 'mysql');
  setNestedValue(config, ['shared', 'database', 'host'], databaseHost);
  setNestedValue(config, ['shared', 'database', 'port'], databasePort);
  setNestedValue(config, ['shared', 'database', 'username'], databaseUsername);
  delete config.shared.database.password;
  setNestedValue(config, ['shared', 'database', 'password_env'], databasePasswordEnv);

  if (!Array.isArray(config.apps)) {
    return;
  }

  for (const appConfig of config.apps) {
    if (!appConfig.database || typeof appConfig.database !== 'object') {
      continue;
    }
    appConfig.database.host = databaseHost;
    appConfig.database.port = databasePort;
    appConfig.database.username = databaseUsername;
    delete appConfig.database.password;
    appConfig.database.password_env = databasePasswordEnv;
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
