const state = {
  stages: [],
  selectedStageIndex: 0,
  busy: false,
  templateConfigPath: '',
  pendingConfirmedAction: null,
  activeAction: null,
  packageProgress: new Map(),
  packageProgressWidget: null,
  helperProgressFactory: null,
  helperProgressLoad: null,
  debug: false
};

const elements = {
  stageNav: document.getElementById('stageNav'),
  appModeTitle: document.getElementById('appModeTitle'),
  appModeSubtitle: document.getElementById('appModeSubtitle'),
  kitSetupVersion: document.getElementById('kitSetupVersion'),
  stageTitle: document.getElementById('stageTitle'),
  chooseConfigButton: document.getElementById('chooseConfigButton'),
  refreshButton: document.getElementById('refreshButton'),
  phpPathInput: document.getElementById('phpPathInput'),
  phpPathButton: document.getElementById('phpPathButton'),
  apachePathInput: document.getElementById('apachePathInput'),
  apachePathButton: document.getElementById('apachePathButton'),
  mysqlPathInput: document.getElementById('mysqlPathInput'),
  mysqlPathButton: document.getElementById('mysqlPathButton'),
  configPathInput: document.getElementById('configPathInput'),
  hubTokenInput: document.getElementById('hubTokenInput'),
  technitiumTokenInput: document.getElementById('technitiumTokenInput'),
  adminPasswordInput: document.getElementById('adminPasswordInput'),
  databaseHostInput: document.getElementById('databaseHostInput'),
  databasePortInput: document.getElementById('databasePortInput'),
  databaseUsernameInput: document.getElementById('databaseUsernameInput'),
  databasePasswordInput: document.getElementById('databasePasswordInput'),
  hubIdInput: document.getElementById('hubIdInput'),
  basePathInput: document.getElementById('basePathInput'),
  basePathButton: document.getElementById('basePathButton'),
  machineIpInput: document.getElementById('machineIpInput'),
  technitiumBaseUrlInput: document.getElementById('technitiumBaseUrlInput'),
  dnsZoneInput: document.getElementById('dnsZoneInput'),
  applyDnsInput: document.getElementById('applyDnsInput'),
  dnsClientNameserverInput: document.getElementById('dnsClientNameserverInput'),
  dnsClientInterfaceInput: document.getElementById('dnsClientInterfaceInput'),
  applyDnsClientInput: document.getElementById('applyDnsClientInput'),
  certRootInput: document.getElementById('certRootInput'),
  certRootButton: document.getElementById('certRootButton'),
  pemUploadPathInput: document.getElementById('pemUploadPathInput'),
  pemUploadButton: document.getElementById('pemUploadButton'),
  apacheIncludeOutputInput: document.getElementById('apacheIncludeOutputInput'),
  writeExtractedFilesInput: document.getElementById('writeExtractedFilesInput'),
  applyWebServerInput: document.getElementById('applyWebServerInput'),
  appScopeGrid: document.getElementById('appScopeGrid'),
  buildConfigButton: document.getElementById('buildConfigButton'),
  configBuildStatus: document.getElementById('configBuildStatus'),
  stagePanels: Array.from(document.querySelectorAll('[data-stage-panel]')),
  overallStatus: document.getElementById('overallStatus'),
  successCount: document.getElementById('successCount'),
  warningCount: document.getElementById('warningCount'),
  pendingCount: document.getElementById('pendingCount'),
  checkpointPath: document.getElementById('checkpointPath'),
  checkpointGrid: document.getElementById('checkpointGrid'),
  appRetryGrid: document.getElementById('appRetryGrid'),
  packageProgressPanel: document.getElementById('packageProgressPanel'),
  packageProgressSummary: document.getElementById('packageProgressSummary'),
  packageOverallProgress: document.getElementById('packageOverallProgress'),
  packageProgressGrid: document.getElementById('packageProgressGrid'),
  finishStatus: document.getElementById('finishStatus'),
  finishContent: document.getElementById('finishContent'),
  detailStep: document.getElementById('detailStep'),
  detailName: document.getElementById('detailName'),
  detailStatus: document.getElementById('detailStatus'),
  detailMessage: document.getElementById('detailMessage'),
  detailJson: document.getElementById('detailJson'),
  runnerOutput: document.getElementById('runnerOutput'),
  reportPath: document.getElementById('reportPath'),
  confirmModal: document.getElementById('confirmModal'),
  confirmTitle: document.getElementById('confirmTitle'),
  confirmRisk: document.getElementById('confirmRisk'),
  confirmSummary: document.getElementById('confirmSummary'),
  confirmDetails: document.getElementById('confirmDetails'),
  confirmCheckbox: document.getElementById('confirmCheckbox'),
  confirmCancelButton: document.getElementById('confirmCancelButton'),
  confirmRunButton: document.getElementById('confirmRunButton')
};

const fallbackStages = [
  'Platform Check',
  'Hub Pairing',
  'Select Apps',
  'Choose Base Path',
  'Admin & Database',
  'Prepare Trusted App Packages',
  'Preflight Apps',
  'Install Apps',
  'Network & Local DNS',
  'SSL & Web Server',
  'Remote & Smoke Checks',
  'Finish'
].map((name, index) => ({
  step: index + 1,
  name,
  status: 'pending',
  message: 'Waiting for checks.',
  details: {}
}));

const appOptions = [
  ['pbb-mapserver', 'MapServer'],
  ['pbb-maestro', 'Maestro'],
  ['pbb-realtime', 'Realtime'],
  ['pbb-relay', 'Relay'],
  ['pbb-hotline', 'Hotline']
];

const guardedActions = new Set(['prepare-packages', 'dns-apply', 'dns-client-apply', 'ssl-apply', 'install', 'populate']);

window.addEventListener('DOMContentLoaded', async () => {
  debugLog('dom:loaded:start');
  const defaults = await window.kitSetup.getDefaults();
  state.debug = Boolean(defaults.devToolsEnabled);
  debugLog('defaults', defaults);
  state.templateConfigPath = defaults.configPath;
  state.launchMode = defaults.launchMode || 'setup';
  elements.kitSetupVersion.textContent = defaults.kitSetupVersion || '';
  applyLaunchMode(state.launchMode);
  elements.phpPathInput.value = defaults.phpPath;
  elements.apachePathInput.value = defaults.apachePath || '';
  elements.mysqlPathInput.value = defaults.mysqlPath || '';
  elements.configPathInput.value = defaults.configPath;
  elements.basePathInput.value = 'C:\\wamp64\\www\\pbb-node';
  elements.certRootInput.value = 'C:\\wamp64\\certs\\pbb.ph';
  elements.apacheIncludeOutputInput.value = 'C:\\wamp64\\apache-vhosts\\pbb-vhosts.conf';
  renderAppScopeControls();
  setStages(fallbackStages);
  bindEvents();
  loadHelperProgressFactory().then(() => {
    if (!elements.packageProgressPanel.hidden) {
      renderPackageProgress();
    }
  });
  if (window.kitSetup.onRunnerOutput) {
    window.kitSetup.onRunnerOutput(handleRunnerOutput);
  }
  debugLog('dom:loaded:done');
});

function applyLaunchMode(mode) {
  if (mode === 'data-prep') {
    document.title = 'Project Bantay Bayan Data Prep';
    elements.appModeTitle.textContent = 'Data Prep';
    elements.appModeSubtitle.textContent = 'Post-install tools';
    state.selectedStageIndex = 10;
    appendOutput('Data Prep mode: use the Data Prep action after the node installation is complete.');
    return;
  }
  document.title = 'Project Bantay Bayan Setup';
  elements.appModeTitle.textContent = 'Setup';
  elements.appModeSubtitle.textContent = 'Node installer';
}

function bindEvents() {
  debugLog('bind-events:start');
  elements.chooseConfigButton.addEventListener('click', async () => {
    debugLog('config:choose:click');
    const selected = await window.kitSetup.selectConfig();
    if (selected) {
      state.templateConfigPath = selected;
      elements.configPathInput.value = selected;
    }
  });

  elements.phpPathButton.addEventListener('click', () => choosePhpBinary());
  elements.apachePathButton.addEventListener('click', () => chooseExecutable(elements.apachePathInput, 'Choose Apache httpd.exe'));
  elements.mysqlPathButton.addEventListener('click', () => chooseExecutable(elements.mysqlPathInput, 'Choose MySQL/MariaDB mysql.exe'));
  elements.basePathButton.addEventListener('click', () => chooseFolder(elements.basePathInput, 'Choose App Base Path'));
  elements.certRootButton.addEventListener('click', () => chooseFolder(elements.certRootInput, 'Choose Certificate Folder'));
  elements.pemUploadButton.addEventListener('click', () => choosePemFile());
  elements.buildConfigButton.addEventListener('click', () => buildRuntimeConfig());
  elements.refreshButton.addEventListener('click', () => {
    debugLog('run-checks:click');
    runAction('stage-report');
  });
  elements.confirmCancelButton.addEventListener('click', () => closeConfirmation());
  elements.confirmCheckbox.addEventListener('change', () => {
    elements.confirmRunButton.disabled = !elements.confirmCheckbox.checked;
  });
  elements.confirmRunButton.addEventListener('click', () => {
    const pending = state.pendingConfirmedAction;
    closeConfirmation();
    if (pending) {
      runAction(pending.action, { ...(pending.options || {}), confirmed: true });
    }
  });

  document.querySelectorAll('[data-action]').forEach((button) => {
    button.addEventListener('click', () => {
      debugLog('action-button:click', { action: button.dataset.action });
      runAction(button.dataset.action);
    });
  });

  document.querySelectorAll('input').forEach((input) => {
    input.addEventListener('input', () => renderActiveStageValidation());
    input.addEventListener('change', () => renderActiveStageValidation());
  });
  debugLog('bind-events:done');
}

function renderAppScopeControls() {
  elements.appScopeGrid.innerHTML = '';
  for (const [appId, label] of appOptions) {
    const row = document.createElement('div');
    row.className = 'app-scope-row';
    row.innerHTML = `
      <strong>${escapeHtml(label)}</strong>
      <div class="segmented" data-app-id="${escapeHtml(appId)}">
        <label><input type="radio" name="scope-${escapeHtml(appId)}" value="local" checked><span>Local</span></label>
        <label><input type="radio" name="scope-${escapeHtml(appId)}" value="remote"><span>Remote</span></label>
        <label><input type="radio" name="scope-${escapeHtml(appId)}" value="disabled"><span>Off</span></label>
      </div>
    `;
    elements.appScopeGrid.appendChild(row);
  }
}

async function chooseFolder(input, title) {
  const selected = await window.kitSetup.selectFolder({
    title,
    defaultPath: input.value
  });
  if (selected) {
    input.value = selected;
  }
}

async function choosePemFile() {
  const selected = await window.kitSetup.selectFile({
    title: 'Choose PEM Bundle',
    defaultPath: elements.certRootInput.value,
    filters: [
      { name: 'PEM files', extensions: ['pem', 'crt', 'cer', 'key'] },
      { name: 'All files', extensions: ['*'] }
    ]
  });
  if (selected) {
    elements.pemUploadPathInput.value = selected;
  }
}

async function choosePhpBinary() {
  await chooseExecutable(elements.phpPathInput, 'Choose PHP Executable');
}

async function chooseExecutable(input, title) {
  const selected = await window.kitSetup.selectFile({
    title,
    defaultPath: input.value,
    filters: [
      { name: 'Executable', extensions: ['exe'] },
      { name: 'All files', extensions: ['*'] }
    ]
  });
  if (selected) {
    input.value = selected;
    renderActiveStageValidation();
  }
}

async function buildRuntimeConfig() {
  if (state.busy) {
    return;
  }
  const validation = validateAllStages();
  if (validation.blocking.length > 0) {
    focusFirstInvalidStage(validation.blocking[0]);
    elements.configBuildStatus.textContent = 'Fix required inputs first';
    appendOutput(`Cannot build config: ${validation.blocking[0].issues[0]}`);
    return;
  }
  state.busy = true;
  setBusy(true);
  elements.configBuildStatus.textContent = 'Building config...';
  try {
    const result = await window.kitSetup.buildConfig({
      templatePath: state.templateConfigPath || elements.configPathInput.value,
      form: collectSetupForm()
    });
    elements.configPathInput.value = result.configPath;
    elements.configBuildStatus.textContent = 'Runtime config ready';
    appendOutput(`Runtime config built: ${result.configPath}`);
  } catch (error) {
    elements.configBuildStatus.textContent = 'Build failed';
    appendOutput(`ERROR: ${error.message}`);
  } finally {
    state.busy = false;
    setBusy(false);
  }
}

function collectSetupForm() {
  return {
    phpPath: elements.phpPathInput.value,
    apachePath: elements.apachePathInput.value,
    mysqlPath: elements.mysqlPathInput.value,
    hubId: elements.hubIdInput.value,
    basePath: elements.basePathInput.value,
    machineIp: elements.machineIpInput.value,
    technitiumBaseUrl: elements.technitiumBaseUrlInput.value,
    dnsZone: elements.dnsZoneInput.value,
    applyDns: elements.applyDnsInput.checked,
    dnsClientNameserver: elements.dnsClientNameserverInput.value,
    dnsClientInterfaceAlias: elements.dnsClientInterfaceInput.value,
    applyDnsClient: elements.applyDnsClientInput.checked,
    certRoot: elements.certRootInput.value,
    pemUploadPath: elements.pemUploadPathInput.value,
    apacheIncludeOutput: elements.apacheIncludeOutputInput.value,
    writeExtractedFiles: elements.writeExtractedFilesInput.checked,
    applyWebServer: elements.applyWebServerInput.checked,
    databaseHost: elements.databaseHostInput.value,
    databasePort: elements.databasePortInput.value,
    databaseUsername: elements.databaseUsernameInput.value,
    appScopes: collectAppScopes()
  };
}

function collectAppScopes() {
  const scopes = {};
  for (const [appId] of appOptions) {
    const selected = document.querySelector(`input[name="scope-${appId}"]:checked`);
    scopes[appId] = selected ? selected.value : 'local';
  }
  return scopes;
}

async function runAction(action, options = {}) {
  debugLog('run-action:enter', { action, options, busy: state.busy });
  if (state.busy) {
    debugLog('run-action:ignored-busy', { action });
    return;
  }

  const validation = validateAction(action);
  debugLog('run-action:validation', validation);
  if (validation.blocking.length > 0) {
    focusFirstInvalidStage(validation.blocking[0]);
    appendOutput(`Cannot run ${action}: ${validation.blocking[0].issues[0]}`);
    return;
  }

  if (guardedActions.has(action) && options.confirmed !== true) {
    debugLog('run-action:confirmation-required', { action });
    await requestActionConfirmation(action, options);
    return;
  }

  state.busy = true;
  state.activeAction = action;
  if (action === 'prepare-packages') {
    resetPackageProgress();
  }
  setBusy(true);
  debugLog('run-action:busy-set', { action });
  appendOutput(`Running ${action}...`);

  try {
    debugLog('run-action:build-config:start', { action });
    await buildRuntimeConfigForAction();
    debugLog('run-action:build-config:done', { action, configPath: elements.configPathInput.value });
    const request = {
      action,
      phpPath: elements.phpPathInput.value,
      configPath: elements.configPathInput.value,
      runId: `${action.replace(/[^a-z0-9]+/gi, '_')}_${Date.now()}`,
      appId: options.appId || '',
      secrets: collectSecrets()
    };
    debugLog('run-action:ipc:start', {
      ...request,
      secrets: Object.fromEntries(Object.entries(request.secrets).map(([key, value]) => [key, Boolean(value)]))
    });
    const result = await window.kitSetup.runAction(request);
    debugLog('run-action:ipc:done', {
      action,
      exitCode: result.exitCode,
      reportPath: result.reportPath,
      hasReport: Boolean(result.report),
      hasCheckpoints: Boolean(result.checkpoints)
    });
    renderRunResult(result);
  } catch (error) {
    debugLog('run-action:error', { action, message: error.message, stack: error.stack });
    appendOutput(`ERROR: ${error.message}`);
  } finally {
    state.busy = false;
    state.activeAction = null;
    setBusy(false);
    debugLog('run-action:finally', { action, busy: state.busy });
  }
}

function handleRunnerOutput(payload) {
  debugLog('runner-output:received', payload);
  if (!payload || payload.action !== state.activeAction) {
    debugLog('runner-output:ignored', { activeAction: state.activeAction, payloadAction: payload && payload.action });
    return;
  }
  const text = String(payload.text || '');
  if (text.trim() === '') {
    return;
  }
  appendOutput(text.trimEnd());
  parseProgressLines(text);
}

function parseProgressLines(text) {
  const lines = String(text || '').split(/\r?\n/);
  for (const line of lines) {
    const trimmed = line.trim();
    if (!trimmed.startsWith('PROGRESS:')) {
      continue;
    }
    try {
      const payload = JSON.parse(trimmed.slice('PROGRESS:'.length).trim());
      if (payload.scope === 'package') {
        updatePackageProgress(payload);
      }
    } catch (_error) {
      // Ignore malformed progress lines; final reports still carry authoritative state.
    }
  }
}

function resetPackageProgress() {
  state.packageProgressWidget?.destroy?.();
  state.packageProgressWidget = null;
  state.packageProgress = new Map();
  for (const [appId, label] of appOptions) {
    state.packageProgress.set(appId, {
      app_id: appId,
      label,
      step: 'pending',
      status: 'pending',
      message: 'Waiting for package preparation.'
    });
  }
  renderPackageProgress();
}

function updatePackageProgress(payload) {
  const appId = String(payload.app_id || '');
  if (!appId) {
    return;
  }
  const current = state.packageProgress.get(appId) || { app_id: appId, label: appId };
  state.packageProgress.set(appId, {
    ...current,
    ...payload,
    status: payload.status || progressStatusForStep(payload.step),
    message: payload.message || progressLabelForStep(payload.step)
  });
  renderPackageProgress();
}

function progressStatusForStep(step) {
  if (step === 'complete') {
    return 'success';
  }
  if (step === 'failed') {
    return 'failed';
  }
  if (['hash', 'extract', 'verify', 'deploy', 'validate', 'start', 'worker-started', 'working'].includes(step)) {
    return 'running';
  }
  return 'pending';
}

function progressLabelForStep(step) {
  const labels = {
    start: 'Preparing trusted package.',
    hash: 'Checking package SHA-256.',
    extract: 'Extracting package to staging.',
    verify: 'Verifying release metadata and checksums.',
    deploy: 'Copying package into selected base path.',
    'worker-started': 'Package worker started.',
    working: 'Still working on package deployment.',
    complete: 'Package is ready.',
    failed: 'Package preparation failed.'
  };
  return labels[step] || 'Waiting for package preparation.';
}

function loadHelperProgressFactory() {
  if (state.helperProgressFactory) {
    return Promise.resolve(state.helperProgressFactory);
  }
  if (state.helperProgressLoad) {
    return state.helperProgressLoad;
  }

  const helperBundleUrl = new URL('../assets/helpers.pbb.ph/dist/helpers.ui.bundle.min.js', window.location.href).href;
  state.helperProgressLoad = import(helperBundleUrl)
    .then((module) => {
      const factory = module?.helperUiBundleModules?.['./ui.progress.js']?.createProgress;
      state.helperProgressFactory = typeof factory === 'function' ? factory : null;
      return state.helperProgressFactory;
    })
    .catch(() => {
      state.helperProgressFactory = null;
      return null;
    });
  return state.helperProgressLoad;
}

function renderPackageProgress() {
  elements.packageProgressPanel.hidden = false;
  const packages = Array.from(state.packageProgress.values());
  const done = packages.filter((item) => item.status === 'success' || item.status === 'failed').length;
  const failed = packages.filter((item) => item.status === 'failed').length;
  const running = packages.filter((item) => item.status === 'running').length;
  const total = packages.length;
  const progressLabel = failed > 0
    ? `${done} of ${total} complete, ${failed} failed`
    : running > 0
      ? `${done} of ${total} complete, ${running} running`
      : `${done} of ${total} complete`;
  elements.packageProgressSummary.textContent = progressLabel;
  renderHelperPackageProgress(done, total, progressLabel);
  elements.packageProgressGrid.innerHTML = '';
  for (const item of packages) {
    const row = document.createElement('div');
    row.className = `package-progress-row ${item.status || 'pending'}`;
    row.innerHTML = `
      <span class="package-dot" aria-hidden="true"></span>
      <div class="package-progress-copy">
        <strong>${escapeHtml(item.label || item.app_id)}</strong>
        <span>${escapeHtml(item.message || '')}</span>
      </div>
      <small>${escapeHtml(item.step || 'pending')}</small>
    `;
    elements.packageProgressGrid.appendChild(row);
  }
}

function renderHelperPackageProgress(done, total, label) {
  const percent = total > 0 ? Math.round((done / total) * 100) : 0;
  const data = {
    label: 'Trusted package preparation',
    value: percent
  };
  const options = {
    style: 'segmented',
    size: 'md',
    showLabel: true,
    showPercent: true,
    segments: Math.max(5, total || 5),
    ariaLabel: label
  };

  if (!state.helperProgressFactory) {
    elements.packageOverallProgress.innerHTML = `
      <div class="package-progress-fallback" role="progressbar" aria-label="${escapeHtml(label)}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${percent}">
        <span style="width: ${percent}%"></span>
      </div>
    `;
    return;
  }

  if (!state.packageProgressWidget) {
    elements.packageOverallProgress.innerHTML = '';
    state.packageProgressWidget = state.helperProgressFactory(elements.packageOverallProgress, data, options);
    return;
  }

  state.packageProgressWidget.update(data, options);
}

async function buildRuntimeConfigForAction() {
  debugLog('build-config-for-action:start', {
    templatePath: state.templateConfigPath || elements.configPathInput.value
  });
  const result = await window.kitSetup.buildConfig({
    templatePath: state.templateConfigPath || elements.configPathInput.value,
    form: collectSetupForm()
  });
  elements.configPathInput.value = result.configPath;
  elements.configBuildStatus.textContent = 'Runtime config ready';
  debugLog('build-config-for-action:done', { configPath: result.configPath });
  return result;
}

async function requestActionConfirmation(action, options = {}) {
  try {
    const description = await window.kitSetup.describeAction({
      action,
      configPath: elements.configPathInput.value
    });
    openConfirmation(action, description, options);
  } catch (error) {
    appendOutput(`ERROR: ${error.message}`);
  }
}

function openConfirmation(action, description, options = {}) {
  state.pendingConfirmedAction = { action, options };
  elements.confirmTitle.textContent = description.title || action;
  elements.confirmRisk.textContent = description.risk || 'guarded';
  elements.confirmRisk.className = `status-pill ${description.risk === 'mutating' ? 'failed' : 'warning'}`;
  elements.confirmSummary.textContent = description.summary || '';
  elements.confirmDetails.innerHTML = '';
  for (const detail of description.details || []) {
    const item = document.createElement('li');
    item.textContent = detail;
    elements.confirmDetails.appendChild(item);
  }
  if (options.appId) {
    const item = document.createElement('li');
    item.textContent = `App filter: ${options.appId}`;
    elements.confirmDetails.appendChild(item);
  }
  elements.confirmCheckbox.checked = false;
  elements.confirmRunButton.disabled = true;
  elements.confirmModal.hidden = false;
}

function closeConfirmation() {
  state.pendingConfirmedAction = null;
  elements.confirmModal.hidden = true;
}

function collectSecrets() {
  return {
    hubToken: elements.hubTokenInput.value,
    technitiumToken: elements.technitiumTokenInput.value,
    adminPassword: elements.adminPasswordInput.value,
    mysqlPassword: elements.databasePasswordInput.value
  };
}

function renderRunResult(result) {
  debugLog('render-run-result:start', {
    action: result.action,
    exitCode: result.exitCode,
    reportPath: result.reportPath,
    hasReport: Boolean(result.report),
    hasCheckpoints: Boolean(result.checkpoints)
  });
  const output = [
    result.stdout,
    result.stderr ? `ERR: ${result.stderr}` : ''
  ].filter(Boolean).join('\n');
  if (!output) {
    appendOutput(`${result.action} finished with exit code ${result.exitCode}`);
  }
  elements.reportPath.textContent = result.reportPath || '';
  renderCheckpoints(result.checkpoints, result.checkpointPath);

  if (result.action === 'stage-report' && result.report && Array.isArray(result.report.stages)) {
    setStages(result.report.stages);
    renderSummary(result.report);
    renderCheckpoints(result.report.checkpoints || result.checkpoints, result.checkpointPath);
    debugLog('render-run-result:stage-report-done', { action: result.action });
    return;
  }

  if (result.report) {
    if (result.action === 'prepare-packages') {
      renderPackageReport(result.report);
    }
    renderAppRetry(result.report);
    if (result.action === 'finish-report') {
      renderFinishSummary(result.report);
    }
    renderGenericReport(result.action, result.report);
  }
  debugLog('render-run-result:done', { action: result.action });
}

function renderPackageReport(report) {
  if (!report || !Array.isArray(report.packages)) {
    return;
  }
  elements.packageProgressPanel.hidden = false;
  state.packageProgressWidget?.destroy?.();
  state.packageProgressWidget = null;
  state.packageProgress = new Map();
  for (const item of report.packages) {
    const appId = item.app_id || '';
    const option = appOptions.find(([id]) => id === appId);
    state.packageProgress.set(appId, {
      app_id: appId,
      label: option ? option[1] : appId,
      step: item.extraction || item.source_type || 'complete',
      status: item.status || 'pending',
      message: packageReportMessage(item)
    });
  }
  renderPackageProgress();
}

function packageReportMessage(item) {
  if ((item.errors || []).length > 0) {
    return item.errors.join(' ');
  }
  if ((item.warnings || []).length > 0) {
    return item.warnings.join(' ');
  }
  if (item.target_prepared) {
    return `Ready at ${item.target_path || 'target path'}.`;
  }
  return 'Package is ready.';
}

function setStages(stages) {
  state.stages = stages;
  elements.stageNav.innerHTML = '';
  stages.forEach((stage, index) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = `stage-button ${stage.status || 'pending'}`;
    button.innerHTML = `
      <span class="stage-number">${stage.step}</span>
      <span class="stage-copy">
        <strong>${escapeHtml(stage.name)}</strong>
        <small>${escapeHtml(stage.status || 'pending')}</small>
      </span>
    `;
    button.addEventListener('click', () => {
      state.selectedStageIndex = index;
      renderStageDetail(stage);
      showStagePanel(stage.step);
      renderActiveStageValidation();
      markSelectedStage();
    });
    elements.stageNav.appendChild(button);
  });
  state.selectedStageIndex = Math.min(state.selectedStageIndex, stages.length - 1);
  renderStageDetail(stages[state.selectedStageIndex]);
  showStagePanel(stages[state.selectedStageIndex] ? stages[state.selectedStageIndex].step : 1);
  renderActiveStageValidation();
  markSelectedStage();
  renderStageCounts(stages);
}

function renderSummary(report) {
  elements.overallStatus.textContent = report.status || 'unknown';
  renderStageCounts(report.stages || []);
  renderCheckpoints(report.checkpoints || null, '');
}

function renderAppRetry(report) {
  elements.appRetryGrid.innerHTML = '';
  if (!report || !Array.isArray(report.apps) || report.apps.length === 0) {
    const empty = document.createElement('p');
    empty.className = 'panel-copy';
    empty.textContent = 'No per-app report is available yet.';
    elements.appRetryGrid.appendChild(empty);
    return;
  }

  for (const app of report.apps) {
    const appId = app.id || app.app_id || '';
    if (!appId) {
      continue;
    }
    const row = document.createElement('div');
    row.className = `app-retry-row ${app.status || 'pending'}`;
    row.innerHTML = `
      <div class="app-retry-copy">
        <strong>${escapeHtml(app.name || appId)}</strong>
        <span>${escapeHtml(appId)} / ${escapeHtml(app.status || 'pending')}</span>
      </div>
      <div class="app-retry-actions">
        <button type="button" data-app-action="preflight" data-app-id="${escapeHtml(appId)}">Preflight</button>
        <button type="button" data-app-action="install" data-app-id="${escapeHtml(appId)}">Install</button>
        <button type="button" data-app-action="populate" data-app-id="${escapeHtml(appId)}">Data Prep</button>
      </div>
    `;
    row.querySelectorAll('[data-app-action]').forEach((button) => {
      button.addEventListener('click', () => runAction(button.dataset.appAction, { appId: button.dataset.appId }));
    });
    elements.appRetryGrid.appendChild(row);
  }
}

function renderFinishSummary(report) {
  elements.finishStatus.textContent = report.status || 'unknown';
  elements.finishContent.innerHTML = '';
  const apps = Array.isArray(report.apps) ? report.apps : [];
  const followUps = Array.isArray(report.follow_ups) ? report.follow_ups : [];
  const admin = report.admin || {};

  const adminBlock = document.createElement('div');
  adminBlock.className = 'finish-block';
  adminBlock.innerHTML = `
    <strong>Admin</strong>
    <span>${escapeHtml(admin.name || 'PBB Administrator')}</span>
    <span>${escapeHtml(admin.email || 'admin@pbb.local')}</span>
  `;
  elements.finishContent.appendChild(adminBlock);

  const appsBlock = document.createElement('div');
  appsBlock.className = 'finish-block';
  appsBlock.innerHTML = '<strong>Apps</strong>';
  for (const app of apps) {
    const row = document.createElement('span');
    row.textContent = `${app.id || 'app'}: ${app.status || 'unknown'}${app.url ? ` - ${app.url}` : ''}`;
    appsBlock.appendChild(row);
  }
  elements.finishContent.appendChild(appsBlock);

  const followBlock = document.createElement('div');
  followBlock.className = 'finish-block';
  followBlock.innerHTML = '<strong>Follow-ups</strong>';
  if (followUps.length === 0) {
    const row = document.createElement('span');
    row.textContent = 'No required follow-ups reported.';
    followBlock.appendChild(row);
  } else {
    for (const item of followUps) {
      const row = document.createElement('span');
      row.textContent = `${item.severity || 'note'}: ${item.message || ''}`;
      followBlock.appendChild(row);
    }
  }
  elements.finishContent.appendChild(followBlock);
}

function renderCheckpoints(checkpoints, checkpointPath) {
  elements.checkpointPath.textContent = checkpointPath || '';
  elements.checkpointGrid.innerHTML = '';
  const actions = checkpoints && checkpoints.actions ? checkpoints.actions : {};
  const orderedActions = ['detect', 'hub-resolve', 'stage-report', 'plan', 'prepare-packages', 'preflight', 'install', 'dns-plan', 'dns-apply', 'dns-client-apply', 'dns-verify', 'ssl-plan', 'ssl-apply', 'remote-check', 'smoke-check', 'populate', 'finish-report'];
  for (const action of orderedActions) {
    const entry = actions[action] || null;
    const item = document.createElement('button');
    item.type = 'button';
    item.className = `checkpoint-item ${entry ? entry.status : 'pending'}`;
    item.innerHTML = `
      <strong>${escapeHtml(action)}</strong>
      <span>${escapeHtml(entry ? entry.status : 'pending')}</span>
    `;
    if (entry && entry.report_path) {
      item.title = entry.report_path;
    }
    item.addEventListener('click', () => runAction(action));
    elements.checkpointGrid.appendChild(item);
  }
}

function renderStageCounts(stages) {
  elements.successCount.textContent = stages.filter((stage) => stage.status === 'success').length;
  elements.warningCount.textContent = stages.filter((stage) => stage.status === 'warning' || stage.status === 'failed').length;
  elements.pendingCount.textContent = stages.filter((stage) => stage.status === 'pending').length;
  if (elements.overallStatus.textContent === 'Not run') {
    elements.overallStatus.textContent = 'Pending';
  }
}

function renderStageDetail(stage) {
  if (!stage) {
    return;
  }
  elements.stageTitle.textContent = stage.name;
  elements.detailStep.textContent = `Step ${stage.step}`;
  elements.detailName.textContent = stage.name;
  elements.detailStatus.textContent = stage.status || 'pending';
  elements.detailStatus.className = `status-pill ${stage.status || 'pending'}`;
  elements.detailMessage.textContent = stage.message || '';
  elements.detailJson.textContent = JSON.stringify(stage.details || {}, null, 2);
}

function showStagePanel(step) {
  for (const panel of elements.stagePanels) {
    panel.hidden = Number(panel.dataset.stagePanel) !== Number(step);
  }
}

function renderActiveStageValidation() {
  const stage = state.stages[state.selectedStageIndex] || { step: 1 };
  const step = Number(stage.step || 1);
  const panel = elements.stagePanels.find((item) => Number(item.dataset.stagePanel) === step);
  if (!panel) {
    return;
  }
  let box = panel.querySelector('.validation-box');
  if (!box) {
    box = document.createElement('div');
    box.className = 'validation-box';
    panel.appendChild(box);
  }
  const result = validateStage(step);
  box.className = `validation-box ${result.status}`;
  box.innerHTML = '';
  const title = document.createElement('strong');
  title.textContent = result.status === 'success' ? 'Ready' : (result.status === 'warning' ? 'Needs attention' : 'Required before continuing');
  box.appendChild(title);
  if (result.issues.length === 0) {
    const message = document.createElement('p');
    message.textContent = 'No immediate input issues found for this stage.';
    box.appendChild(message);
    return;
  }
  const list = document.createElement('ul');
  for (const issue of result.issues) {
    const item = document.createElement('li');
    item.textContent = issue;
    list.appendChild(item);
  }
  box.appendChild(list);
}

function validateAction(action) {
  const actionStages = {
    detect: [1],
    'hub-resolve': [1, 2],
    'stage-report': [1, 2, 3, 4, 5, 9, 10],
    'finish-report': [1],
    plan: [1, 2, 3, 4, 5, 9, 10],
    'prepare-packages': [3, 4],
    'dns-plan': [9],
    'dns-apply': [9],
    'dns-client-apply': [9],
    'dns-verify': [9],
    'ssl-plan': [10],
    'ssl-apply': [10],
    'remote-check': [3, 11],
    preflight: [1, 3, 4, 5],
    install: [1, 3, 4, 5],
    populate: [3, 5],
    'smoke-check': [3, 9, 10]
  };
  return collectValidation(actionStages[action] || []);
}

function validateAllStages() {
  return collectValidation([1, 2, 3, 4, 5, 9, 10]);
}

function collectValidation(steps) {
  const results = steps.map((step) => validateStage(step)).filter((result) => result.issues.length > 0);
  return {
    blocking: results.filter((result) => result.status === 'failed'),
    warnings: results.filter((result) => result.status === 'warning')
  };
}

function validateStage(step) {
  const issues = [];
  let status = 'success';
  const fail = (message) => {
    status = 'failed';
    issues.push(message);
  };
  const warn = (message) => {
    if (status !== 'failed') {
      status = 'warning';
    }
    issues.push(message);
  };

  if (step === 1) {
    if (cleanValue(elements.phpPathInput.value) === '') {
      fail('Select the PHP runtime path.');
    }
    if (cleanValue(elements.configPathInput.value) === '') {
      fail('Select or build a runtime config file.');
    }
    if (cleanValue(elements.apachePathInput.value) === '') {
      warn('Select Apache httpd.exe to avoid Apache detection warnings.');
    }
    if (cleanValue(elements.mysqlPathInput.value) === '') {
      warn('Select MySQL/MariaDB mysql.exe to avoid database tool detection warnings.');
    }
  }
  if (step === 2) {
    if (Number(elements.hubIdInput.value) <= 0) {
      fail('Enter the Hub ID from hub.pbb.ph.');
    }
    if (cleanValue(elements.hubTokenInput.value) === '') {
      warn('Enter the Hub token before resolving Hub pairing.');
    }
  }
  if (step === 3) {
    const scopes = collectAppScopes();
    const localCount = Object.values(scopes).filter((scope) => scope === 'local').length;
    if (localCount === 0) {
      fail('Select at least one app to install locally on this machine.');
    }
  }
  if (step === 4) {
    if (cleanValue(elements.basePathInput.value) === '') {
      fail('Choose the base folder where local app folders will be created.');
    }
  }
  if (step === 5) {
    if (!looksLikeIpOrHost(elements.databaseHostInput.value)) {
      fail('Enter the MySQL/MariaDB host, for example 127.0.0.1.');
    }
    const databasePort = Number(elements.databasePortInput.value);
    if (!Number.isInteger(databasePort) || databasePort <= 0 || databasePort > 65535) {
      fail('Enter a valid MySQL/MariaDB port.');
    }
    if (cleanValue(elements.databaseUsernameInput.value) === '') {
      fail('Enter the MySQL/MariaDB username.');
    }
    const password = elements.adminPasswordInput.value;
    if (password.length === 0) {
      warn('Enter the first administrator password before preflight, install, or data preparation.');
    } else if (password.length < 8) {
      fail('Use an administrator password with at least 8 characters.');
    }
  }
  if (step === 9) {
    if (!looksLikeIpOrHost(elements.machineIpInput.value)) {
      fail('Enter this machine IP address or resolvable hostname.');
    }
    if (cleanValue(elements.technitiumBaseUrlInput.value) === '') {
      fail('Enter the Technitium base URL.');
    }
    if (cleanValue(elements.dnsZoneInput.value) === '') {
      fail('Enter the DNS zone, for example pbb.ph.');
    }
    if (elements.applyDnsInput.checked && cleanValue(elements.technitiumTokenInput.value) === '') {
      fail('Enter the Technitium token before enabling DNS apply.');
    }
    if (elements.applyDnsClientInput.checked) {
      const targetDns = cleanValue(elements.dnsClientNameserverInput.value) || hostFromUrl(elements.technitiumBaseUrlInput.value);
      if (!looksLikeIpv4(targetDns)) {
        fail('Enter the Windows DNS server IPv4 address, or use a Technitium URL with an IPv4 host.');
      }
    }
  }
  if (step === 10) {
    if (cleanValue(elements.certRootInput.value) === '' && cleanValue(elements.pemUploadPathInput.value) === '') {
      fail('Select an existing certificate folder or a PEM bundle.');
    }
    if ((elements.applyWebServerInput.checked || elements.writeExtractedFilesInput.checked) && cleanValue(elements.apacheIncludeOutputInput.value) === '') {
      fail('Enter the Apache include output path before applying web-server changes.');
    }
    if (elements.writeExtractedFilesInput.checked && cleanValue(elements.pemUploadPathInput.value) === '') {
      fail('Select a PEM bundle before enabling certificate extraction.');
    }
  }
  return { step, status, issues };
}

function focusFirstInvalidStage(result) {
  const index = state.stages.findIndex((stage) => Number(stage.step) === Number(result.step));
  if (index >= 0) {
    state.selectedStageIndex = index;
    const stage = state.stages[index];
    renderStageDetail(stage);
    showStagePanel(stage.step);
    renderActiveStageValidation();
    markSelectedStage();
  }
}

function cleanValue(value) {
  return String(value || '').trim();
}

function looksLikeIpOrHost(value) {
  const text = cleanValue(value);
  return /^[a-z0-9.-]+$/i.test(text) && text.includes('.');
}

function looksLikeIpv4(value) {
  const text = cleanValue(value);
  if (!/^\d{1,3}(?:\.\d{1,3}){3}$/.test(text)) {
    return false;
  }
  return text.split('.').every((part) => Number(part) >= 0 && Number(part) <= 255);
}

function hostFromUrl(value) {
  const text = cleanValue(value);
  if (text === '') {
    return '';
  }
  try {
    return new URL(text).hostname;
  } catch (error) {
    return text.split('/')[0].split(':')[0];
  }
}

function renderGenericReport(action, report) {
  elements.detailStep.textContent = action;
  elements.detailName.textContent = `${action} report`;
  elements.detailStatus.textContent = report.status || 'unknown';
  elements.detailStatus.className = `status-pill ${report.status || 'neutral'}`;
  elements.detailMessage.textContent = report.message || '';
  elements.detailJson.textContent = JSON.stringify(report, null, 2);
}

function markSelectedStage() {
  elements.stageNav.querySelectorAll('.stage-button').forEach((button, index) => {
    button.classList.toggle('selected', index === state.selectedStageIndex);
  });
}

function setBusy(isBusy) {
  debugLog('busy:set', { isBusy });
  document.body.classList.toggle('busy', isBusy);
  elements.refreshButton.disabled = isBusy;
  document.querySelectorAll('[data-action]').forEach((button) => {
    button.disabled = isBusy;
  });
  elements.buildConfigButton.disabled = isBusy;
}

function debugLog(message, data = undefined) {
  if (!state.debug && !String(window.location.search || '').includes('debug=1')) {
    return;
  }
  if (data === undefined) {
    console.log(`[KitSetup] ${message}`);
    return;
  }
  console.log(`[KitSetup] ${message}`, data);
}

function appendOutput(text) {
  const current = elements.runnerOutput.textContent.trim();
  elements.runnerOutput.textContent = [current, text].filter(Boolean).join('\n\n');
  elements.runnerOutput.scrollTop = elements.runnerOutput.scrollHeight;
}

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}
