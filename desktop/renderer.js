const state = {
  stages: [],
  selectedStageIndex: 0,
  busy: false,
  templateConfigPath: '',
  pendingConfirmedAction: null
};

const elements = {
  stageNav: document.getElementById('stageNav'),
  stageTitle: document.getElementById('stageTitle'),
  chooseConfigButton: document.getElementById('chooseConfigButton'),
  refreshButton: document.getElementById('refreshButton'),
  phpPathInput: document.getElementById('phpPathInput'),
  configPathInput: document.getElementById('configPathInput'),
  hubTokenInput: document.getElementById('hubTokenInput'),
  technitiumTokenInput: document.getElementById('technitiumTokenInput'),
  adminPasswordInput: document.getElementById('adminPasswordInput'),
  hubIdInput: document.getElementById('hubIdInput'),
  basePathInput: document.getElementById('basePathInput'),
  basePathButton: document.getElementById('basePathButton'),
  machineIpInput: document.getElementById('machineIpInput'),
  technitiumBaseUrlInput: document.getElementById('technitiumBaseUrlInput'),
  dnsZoneInput: document.getElementById('dnsZoneInput'),
  applyDnsInput: document.getElementById('applyDnsInput'),
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
  'Prepare Trusted App Packages',
  'Network & Local DNS',
  'SSL & Web Server',
  'Remote Dependency Check',
  'Admin Account',
  'Installation Plan',
  'Stage-By-Stage Install',
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

const guardedActions = new Set(['prepare-packages', 'dns-apply', 'ssl-apply', 'install', 'populate']);

window.addEventListener('DOMContentLoaded', async () => {
  const defaults = await window.kitSetup.getDefaults();
  state.templateConfigPath = defaults.configPath;
  elements.phpPathInput.value = defaults.phpPath;
  elements.configPathInput.value = defaults.configPath;
  elements.basePathInput.value = 'C:\\wamp64\\www\\pbb-node';
  elements.certRootInput.value = 'C:\\wamp64\\certs\\pbb.ph';
  elements.apacheIncludeOutputInput.value = 'C:\\wamp64\\apache-vhosts\\pbb-vhosts.conf';
  renderAppScopeControls();
  setStages(fallbackStages);
  bindEvents();
});

function bindEvents() {
  elements.chooseConfigButton.addEventListener('click', async () => {
    const selected = await window.kitSetup.selectConfig();
    if (selected) {
      state.templateConfigPath = selected;
      elements.configPathInput.value = selected;
    }
  });

  elements.basePathButton.addEventListener('click', () => chooseFolder(elements.basePathInput, 'Choose App Base Path'));
  elements.certRootButton.addEventListener('click', () => chooseFolder(elements.certRootInput, 'Choose Certificate Folder'));
  elements.pemUploadButton.addEventListener('click', () => choosePemFile());
  elements.buildConfigButton.addEventListener('click', () => buildRuntimeConfig());
  elements.refreshButton.addEventListener('click', () => runAction('stage-report'));
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
    button.addEventListener('click', () => runAction(button.dataset.action));
  });

  document.querySelectorAll('input').forEach((input) => {
    input.addEventListener('input', () => renderActiveStageValidation());
    input.addEventListener('change', () => renderActiveStageValidation());
  });
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
    hubId: elements.hubIdInput.value,
    basePath: elements.basePathInput.value,
    machineIp: elements.machineIpInput.value,
    technitiumBaseUrl: elements.technitiumBaseUrlInput.value,
    dnsZone: elements.dnsZoneInput.value,
    applyDns: elements.applyDnsInput.checked,
    certRoot: elements.certRootInput.value,
    pemUploadPath: elements.pemUploadPathInput.value,
    apacheIncludeOutput: elements.apacheIncludeOutputInput.value,
    writeExtractedFiles: elements.writeExtractedFilesInput.checked,
    applyWebServer: elements.applyWebServerInput.checked,
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
  if (state.busy) {
    return;
  }

  const validation = validateAction(action);
  if (validation.blocking.length > 0) {
    focusFirstInvalidStage(validation.blocking[0]);
    appendOutput(`Cannot run ${action}: ${validation.blocking[0].issues[0]}`);
    return;
  }

  if (guardedActions.has(action) && options.confirmed !== true) {
    await requestActionConfirmation(action, options);
    return;
  }

  state.busy = true;
  setBusy(true);
  appendOutput(`Running ${action}...`);

  try {
    const result = await window.kitSetup.runAction({
      action,
      phpPath: elements.phpPathInput.value,
      configPath: elements.configPathInput.value,
      runId: `${action.replace(/[^a-z0-9]+/gi, '_')}_${Date.now()}`,
      appId: options.appId || '',
      secrets: collectSecrets()
    });
    renderRunResult(result);
  } catch (error) {
    appendOutput(`ERROR: ${error.message}`);
  } finally {
    state.busy = false;
    setBusy(false);
  }
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
    adminPassword: elements.adminPasswordInput.value
  };
}

function renderRunResult(result) {
  const output = [
    result.stdout,
    result.stderr ? `ERR: ${result.stderr}` : ''
  ].filter(Boolean).join('\n');
  appendOutput(output || `${result.action} finished with exit code ${result.exitCode}`);
  elements.reportPath.textContent = result.reportPath || '';
  renderCheckpoints(result.checkpoints, result.checkpointPath);

  if (result.action === 'stage-report' && result.report && Array.isArray(result.report.stages)) {
    setStages(result.report.stages);
    renderSummary(result.report);
    renderCheckpoints(result.report.checkpoints || result.checkpoints, result.checkpointPath);
    return;
  }

  if (result.report) {
    renderAppRetry(result.report);
    if (result.action === 'finish-report') {
      renderFinishSummary(result.report);
    }
    renderGenericReport(result.action, result.report);
  }
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
        <button type="button" data-app-action="populate" data-app-id="${escapeHtml(appId)}">Populate</button>
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
  const orderedActions = ['detect', 'hub-resolve', 'stage-report', 'plan', 'prepare-packages', 'dns-plan', 'dns-apply', 'dns-verify', 'ssl-plan', 'ssl-apply', 'remote-check', 'preflight', 'install', 'populate', 'smoke-check', 'finish-report'];
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
    'stage-report': [1, 2, 3, 4, 6, 7, 9],
    'finish-report': [1],
    plan: [1, 2, 3, 4, 6, 7, 9],
    'prepare-packages': [3, 4],
    'dns-plan': [6],
    'dns-apply': [6],
    'dns-verify': [6],
    'ssl-plan': [7],
    'ssl-apply': [7],
    'remote-check': [3, 8],
    preflight: [1, 3, 4, 9],
    install: [1, 3, 4, 9],
    populate: [3, 9],
    'smoke-check': [3, 6, 7]
  };
  return collectValidation(actionStages[action] || []);
}

function validateAllStages() {
  return collectValidation([1, 2, 3, 4, 6, 7, 9]);
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
  if (step === 6) {
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
  }
  if (step === 7) {
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
  if (step === 9) {
    const password = elements.adminPasswordInput.value;
    if (password.length === 0) {
      warn('Enter the first administrator password before preflight, install, or population.');
    } else if (password.length < 8) {
      fail('Use an administrator password with at least 8 characters.');
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
  document.body.classList.toggle('busy', isBusy);
  elements.refreshButton.disabled = isBusy;
  document.querySelectorAll('[data-action]').forEach((button) => {
    button.disabled = isBusy;
  });
  elements.buildConfigButton.disabled = isBusy;
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
