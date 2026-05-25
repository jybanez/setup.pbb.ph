const state = {
  stages: [],
  selectedStageIndex: 0,
  busy: false,
  templateConfigPath: '',
  pendingConfirmedAction: null,
  activeAction: null,
  sessionRunId: '',
  runReports: {},
  packageProgress: new Map(),
  packageProgressWidget: null,
  appInstallGridWidget: null,
  dataPrepGridWidget: null,
  appInstallRows: new Map(),
  dataPrepRows: new Map(),
  overallProgressWidget: null,
  automatedElapsedWidget: null,
  automatedElapsedTimer: null,
  helperFactories: {},
  helperLoad: null,
  setupStepperWidget: null,
  setupStepperSignature: '',
  adminPropertyEditor: null,
  pathPickerWidgets: new Map(),
  existingInstallReport: null,
  windowsInstallerReport: null,
  technitiumDiscovery: null,
  prerequisiteGate: null,
  diskSpaceReport: null,
  enforceTechnitiumRequirement: false,
  enforceDiskSpaceRequirement: false,
  showAdvancedDnsClient: false,
  installStateGate: null,
  automatedProgress: null,
  appInstallDecisions: {},
  appInstallDecisionTouched: {},
  appScopeControls: new Map(),
  appScopes: {
    'pbb-mapserver': 'local',
    'pbb-maestro': 'local',
    'pbb-realtime': 'local',
    'pbb-relay': 'local',
    'pbb-hotline': 'local'
  },
  form: {
    phpPath: '',
    apachePath: '',
    mysqlPath: '',
    configPath: '',
    hubToken: '',
    technitiumToken: '',
    adminPassword: '',
    databaseHost: '127.0.0.1',
    databasePort: '3306',
    databaseUsername: 'root',
    databasePassword: '',
    adminEmail: 'admin@pbb.local',
    adminName: 'PBB Administrator',
    hubId: '11',
    basePath: 'C:\\wamp64\\www\\pbb-node',
    machineIp: '127.0.0.1',
    technitiumBaseUrl: 'http://localhost:5380',
    dnsZone: 'pbb.ph',
    applyDns: false,
    dnsClientNameserver: '',
    dnsClientInterface: '',
    applyDnsClient: false,
    certRoot: 'C:\\wamp64\\certs\\pbb.ph',
    pemUploadPath: '',
    apacheIncludeOutput: 'C:\\wamp64\\apache-vhosts\\pbb-vhosts.conf',
    writeExtractedFiles: false,
    applyWebServer: false,
    applyFirewall: true
  },
  syncingPropertyEditor: false,
  debug: false
};

function formField(name) {
  return {
    get value() {
      return state.form[name] ?? '';
    },
    set value(value) {
      state.form[name] = String(value ?? '');
    }
  };
}

function checkedField(name) {
  return {
    get checked() {
      return Boolean(state.form[name]);
    },
    set checked(value) {
      state.form[name] = Boolean(value);
    }
  };
}

const elements = {
  stageNav: document.getElementById('stageNav'),
  appModeTitle: document.getElementById('appModeTitle'),
  appModeSubtitle: document.getElementById('appModeSubtitle'),
  kitSetupVersion: document.getElementById('kitSetupVersion'),
  stageTitle: document.getElementById('stageTitle'),
  chooseConfigButton: document.getElementById('chooseConfigButton'),
  refreshButton: document.getElementById('refreshButton'),
  runAutomatedInstallButton: document.getElementById('runAutomatedInstallButton'),
  overallInstallProgressPanel: document.getElementById('overallInstallProgressPanel'),
  overallInstallProgressTitle: document.getElementById('overallInstallProgressTitle'),
  overallInstallProgressElapsed: document.getElementById('overallInstallProgressElapsed'),
  overallInstallProgressPercent: document.getElementById('overallInstallProgressPercent'),
  overallInstallProgressBar: document.getElementById('overallInstallProgressBar'),
  setupWorkflowStepper: document.getElementById('setupWorkflowStepper'),
  prerequisiteGatePanel: document.getElementById('prerequisiteGatePanel'),
  prerequisiteGateStatus: document.getElementById('prerequisiteGateStatus'),
  prerequisiteGateGrid: document.getElementById('prerequisiteGateGrid'),
  prerequisiteGateMessage: document.getElementById('prerequisiteGateMessage'),
  rerunPrerequisiteGateButton: document.getElementById('rerunPrerequisiteGateButton'),
  adminPropertyEditor: document.getElementById('adminPropertyEditor'),
  existingInstallPanel: document.getElementById('existingInstallPanel'),
  existingInstallSummary: document.getElementById('existingInstallSummary'),
  existingInstallGrid: document.getElementById('existingInstallGrid'),
  phpPathInput: formField('phpPath'),
  apachePathInput: formField('apachePath'),
  mysqlPathInput: formField('mysqlPath'),
  configPathInput: formField('configPath'),
  hubTokenInput: formField('hubToken'),
  technitiumTokenInput: formField('technitiumToken'),
  adminPasswordInput: formField('adminPassword'),
  databaseHostInput: formField('databaseHost'),
  databasePortInput: formField('databasePort'),
  databaseUsernameInput: formField('databaseUsername'),
  databasePasswordInput: formField('databasePassword'),
  adminEmailInput: formField('adminEmail'),
  adminNameInput: formField('adminName'),
  hubIdInput: formField('hubId'),
  basePathInput: formField('basePath'),
  machineIpInput: formField('machineIp'),
  technitiumBaseUrlInput: formField('technitiumBaseUrl'),
  dnsZoneInput: formField('dnsZone'),
  applyDnsInput: checkedField('applyDns'),
  dnsClientNameserverInput: formField('dnsClientNameserver'),
  dnsClientInterfaceInput: formField('dnsClientInterface'),
  applyDnsClientInput: checkedField('applyDnsClient'),
  certRootInput: formField('certRoot'),
  pemUploadPathInput: formField('pemUploadPath'),
  apacheIncludeOutputInput: formField('apacheIncludeOutput'),
  writeExtractedFilesInput: checkedField('writeExtractedFiles'),
  applyWebServerInput: checkedField('applyWebServer'),
  applyFirewallInput: checkedField('applyFirewall'),
  overallStatus: document.getElementById('overallStatus'),
  successCount: document.getElementById('successCount'),
  warningCount: document.getElementById('warningCount'),
  pendingCount: document.getElementById('pendingCount'),
  checkpointPath: document.getElementById('checkpointPath'),
  checkpointGrid: document.getElementById('checkpointGrid'),
  packageProgressPanel: document.getElementById('packageProgressPanel'),
  packageProgressSummary: document.getElementById('packageProgressSummary'),
  packageOverallProgress: document.getElementById('packageOverallProgress'),
  packageProgressGrid: document.getElementById('packageProgressGrid'),
  dataPrepPanel: document.getElementById('dataPrepPanel'),
  dataPrepSummary: document.getElementById('dataPrepSummary'),
  dataPrepGrid: document.getElementById('dataPrepGrid'),
  startDataPrepButton: document.getElementById('startDataPrepButton'),
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
  'Admin Inputs',
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

const guardedActions = new Set(['prepare-packages', 'dns-apply', 'dns-client-apply', 'firewall-apply', 'ssl-apply', 'service-stop', 'install', 'populate']);
const adminInputSteps = new Set([1, 2, 3, 4, 5, 9, 10]);

window.addEventListener('DOMContentLoaded', async () => {
  debugLog('dom:loaded:start');
  const defaults = await window.kitSetup.getDefaults();
  state.debug = Boolean(defaults.devToolsEnabled);
  document.body.classList.toggle('dev-tools-open', state.debug);
  debugLog('defaults', defaults);
  state.templateConfigPath = defaults.configPath;
  state.launchMode = defaults.launchMode || 'setup';
  elements.kitSetupVersion.textContent = defaults.kitSetupVersion || '';
  applyLaunchMode(state.launchMode);
  elements.phpPathInput.value = defaults.phpPath;
  elements.apachePathInput.value = defaults.apachePath || '';
  elements.mysqlPathInput.value = defaults.mysqlPath || '';
  elements.configPathInput.value = defaults.configPath;
  elements.machineIpInput.value = defaults.localIpAddress || '127.0.0.1';
  state.sessionRunId = makeSessionRunId();
  await loadHelperUiFactories();
  if (state.launchMode === 'data-prep') {
    await initializeDataPrepStartup();
    bindEvents();
    if (window.kitSetup.onRunnerOutput) {
      window.kitSetup.onRunnerOutput(handleRunnerOutput);
    }
    debugLog('dom:loaded:done');
    return;
  }
  renderAdminPropertyEditor();
  await refreshPrerequisiteGate();
  await refreshTechnitiumDiscovery({ prefill: true });
  await refreshExistingInstallDiscovery();
  await refreshWindowsInstallerDiscovery();
  await refreshDiskSpaceEstimate();
  renderAdminPropertyEditor();
  setStages(fallbackStages);
  bindEvents();
  if (window.kitSetup.onRunnerOutput) {
    window.kitSetup.onRunnerOutput(handleRunnerOutput);
  }
  debugLog('dom:loaded:done');
});

function applyLaunchMode(mode) {
  if (mode === 'data-prep') {
    document.body.classList.add('data-prep-mode');
    document.title = 'Project Bantay Bayan Data Prep';
    elements.appModeTitle.textContent = 'Data Prep';
    elements.appModeSubtitle.textContent = 'Post-install tools';
    if (elements.stageTitle) {
      elements.stageTitle.textContent = 'Data Prep';
    }
    state.selectedStageIndex = 10;
    if (elements.prerequisiteGatePanel) {
      elements.prerequisiteGatePanel.hidden = true;
    }
    if (elements.dataPrepPanel) {
      elements.dataPrepPanel.hidden = false;
    }
    appendOutput('Data Prep mode: checking Kit Setup completion before loading app discovery.');
    return;
  }
  document.body.classList.remove('data-prep-mode');
  if (elements.prerequisiteGatePanel) {
    elements.prerequisiteGatePanel.hidden = false;
  }
  document.title = 'Project Bantay Bayan Setup';
  elements.appModeTitle.textContent = 'Setup';
  elements.appModeSubtitle.textContent = 'Node installer';
}

function makeSessionRunId() {
  return `setup_session_${Date.now()}`;
}

function getSessionRunId() {
  if (!state.sessionRunId) {
    state.sessionRunId = makeSessionRunId();
  }
  return state.sessionRunId;
}

function bindEvents() {
  debugLog('bind-events:start');
  elements.chooseConfigButton?.addEventListener('click', async () => {
    debugLog('config:choose:click');
    const selected = await window.kitSetup.selectConfig();
    if (selected) {
      state.templateConfigPath = selected;
      elements.configPathInput.value = selected;
      renderAdminPropertyEditor();
      scheduleExistingInstallDiscovery({ rerender: true });
    }
  });

  elements.refreshButton?.addEventListener('click', () => {
    debugLog('run-checks:click');
    runAction('stage-report');
  });
  elements.runAutomatedInstallButton?.addEventListener('click', () => runAutomatedInstall());
  elements.rerunPrerequisiteGateButton?.addEventListener('click', async () => {
    await refreshPrerequisiteGate({ force: true });
  });
  elements.startDataPrepButton?.addEventListener('click', () => runAutomatedDataPrep());
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

  debugLog('bind-events:done');
}

async function initializeDataPrepStartup() {
  if (!elements.dataPrepPanel) {
    return;
  }
  elements.dataPrepPanel.hidden = false;
  elements.dataPrepSummary.textContent = 'Checking setup completion';
  elements.startDataPrepButton.disabled = true;
  elements.dataPrepGrid.innerHTML = '';

  const gate = await window.kitSetup.getInstallState();
  state.installStateGate = gate;
  if (!gate || gate.allowed !== true) {
    renderDataPrepLocked(gate);
    appendOutput(gate?.message || 'Data Prep is locked because Kit Setup has not completed.');
    return;
  }

  applyDataPrepRuntimeState(gate.state || gate);
  initializeDataPrepRows(gate.apps || gate.state?.apps || []);
  elements.dataPrepSummary.textContent = gate.source === 'roaming'
    ? 'Ready from completed setup session'
    : 'Ready from machine setup state';
  elements.startDataPrepButton.disabled = false;
  renderDataPrepGrid();
  appendOutput(`Data Prep unlocked by ${gate.source || 'setup'} completion marker: ${gate.path || ''}`);
}

function applyDataPrepRuntimeState(stateValue) {
  const runtime = stateValue && typeof stateValue.runtime === 'object' ? stateValue.runtime : {};
  const platform = stateValue && typeof stateValue.platform === 'object' ? stateValue.platform : {};
  const phpBinary = cleanValue(runtime.php_binary || platform.php_binary);
  const apacheBinary = cleanValue(runtime.apache_binary || platform.apache_binary);
  const mysqlBinary = cleanValue(runtime.mysql_binary || platform.mysql_binary);
  if (phpBinary !== '') {
    elements.phpPathInput.value = phpBinary;
  }
  if (apacheBinary !== '') {
    elements.apachePathInput.value = apacheBinary;
  }
  if (mysqlBinary !== '') {
    elements.mysqlPathInput.value = mysqlBinary;
  }
}

function renderDataPrepLocked(gate) {
  elements.dataPrepSummary.textContent = 'Setup required';
  elements.startDataPrepButton.disabled = true;
  state.dataPrepGridWidget?.destroy?.();
  state.dataPrepGridWidget = null;
  state.dataPrepRows = new Map();
  elements.dataPrepGrid.innerHTML = `
    <div class="data-prep-locked">
      <strong>Data Prep unavailable</strong>
      <span>${escapeHtml(gate?.message || 'Kit Setup has not completed on this machine. Run Project Bantay Bayan Setup first.')}</span>
    </div>
  `;
}

function renderAdminPropertyEditor() {
  if (!elements.adminPropertyEditor || !state.helperFactories.propertyEditor) {
    return;
  }
  const scrollHost = document.scrollingElement || document.documentElement;
  const priorScrollTop = scrollHost ? scrollHost.scrollTop : 0;
  destroyPathPickers();
  state.adminPropertyEditor?.destroy?.();
  elements.adminPropertyEditor.innerHTML = '';
  state.adminPropertyEditor = state.helperFactories.propertyEditor(elements.adminPropertyEditor, buildAdminPropertyEditorData(), {
    className: 'kit-admin-property-editor',
    showSelectionLabel: false,
    dense: true,
    showSectionDescriptions: true,
    showPropertyHelp: true,
    labelWidth: 172,
    onPropertyChange(change) {
      applyAdminPropertyChange(change);
    },
    onAction(property, action) {
      handleAdminPropertyAction(property, action);
    }
  });
  renderAdminPathPickers();
  document.body.classList.add('has-admin-property-editor');
  if (scrollHost && priorScrollTop > 0) {
    requestAnimationFrame(() => {
      scrollHost.scrollTop = priorScrollTop;
    });
  }
}

function buildAdminPropertyEditorData() {
  return {
    selectionLabel: 'Administrator setup values',
    sections: [
      {
        id: 'platform',
        title: 'Platform',
        description: 'Local runtime executables used by the installer.',
        properties: [
          editorPath('phpPath', 'PHP', elements.phpPathInput.value),
          editorPath('apachePath', 'Apache httpd.exe', elements.apachePathInput.value),
          editorPath('mysqlPath', 'MySQL/MariaDB mysql.exe', elements.mysqlPathInput.value)
        ]
      },
      {
        id: 'hub',
        title: 'Hub Pairing',
        description: 'Official hub identifier and token for this machine.',
        properties: [
          editorNumber('hubId', 'Hub ID', elements.hubIdInput.value),
          editorPassword('hubToken', 'Hub Token', elements.hubTokenInput.value)
        ]
      },
      {
        id: 'apps',
        title: 'Apps On This Machine',
        description: 'Choose which apps are installed locally, remote-only, or disabled.',
        properties: appOptions.map(([appId, label]) => editorSelect(`scope:${appId}`, label, collectAppScopes()[appId] || 'local', [
          { value: 'local', label: 'Local' },
          { value: 'remote', label: 'Remote' },
          { value: 'disabled', label: 'Off' }
        ]))
      },
      {
        id: 'paths',
        title: 'Install Base',
        description: installBaseDescription(),
        properties: [
          editorPath('basePath', 'Install Base', elements.basePathInput.value)
        ]
      },
      {
        id: 'admin',
        title: 'Admin & Database',
        description: 'Database connection and first administrator account.',
        properties: [
          editorText('databaseHost', 'Database Host', elements.databaseHostInput.value),
          editorNumber('databasePort', 'Database Port', elements.databasePortInput.value),
          editorText('databaseUsername', 'Database Username', elements.databaseUsernameInput.value),
          editorPassword('databasePassword', 'Database Password', elements.databasePasswordInput.value),
          editorText('adminEmail', 'Admin Email', elements.adminEmailInput.value),
          editorText('adminName', 'Admin Name', elements.adminNameInput.value),
          editorPassword('adminPassword', 'Admin Password', elements.adminPasswordInput.value)
        ]
      },
      {
        id: 'dns',
        title: 'DNS',
        description: 'Local DNS records and optional Windows DNS client update.',
        properties: [
          editorText('machineIp', 'Machine IP', elements.machineIpInput.value, '', [
            { id: 'detectMachineIp', label: 'Detect' }
          ]),
          editorText('technitiumBaseUrl', 'Technitium URL', elements.technitiumBaseUrlInput.value),
          editorText('dnsZone', 'Zone', elements.dnsZoneInput.value),
          editorPassword('technitiumToken', 'Technitium Token', elements.technitiumTokenInput.value),
          editorToggle('applyDns', 'Apply DNS records', elements.applyDnsInput.checked),
          editorToggle('applyDnsClient', 'Set this machine to use local DNS', elements.applyDnsClientInput.checked),
          ...advancedDnsClientProperties()
        ]
      },
      {
        id: 'ssl',
        title: 'SSL & Apache',
        description: 'Certificate material, generated vhost include, and firewall policy.',
        properties: [
          editorPath('certRoot', 'Certificate Folder', elements.certRootInput.value),
          editorPath('pemUploadPath', 'PEM Bundle', elements.pemUploadPathInput.value),
          editorPath('apacheIncludeOutput', 'Apache Include', elements.apacheIncludeOutputInput.value),
          editorToggle('writeExtractedFiles', 'Write extracted cert files', elements.writeExtractedFilesInput.checked),
          editorToggle('applyWebServer', 'Apply Apache include', elements.applyWebServerInput.checked),
          editorToggle('applyFirewall', 'Apply Windows Firewall HTTP/HTTPS rules', elements.applyFirewallInput.checked)
        ]
      }
    ]
  };
}

function advancedDnsClientProperties() {
  if (!state.showAdvancedDnsClient) {
    return [];
  }
  return [
    editorText('dnsClientNameserver', 'Windows DNS Server', elements.dnsClientNameserverInput.value, 'Defaults to Technitium host'),
    editorText('dnsClientInterface', 'Network Adapter', elements.dnsClientInterfaceInput.value, 'Auto-select active adapter')
  ];
}

function installBaseDescription() {
  const base = 'Root folder where selected app folders are deployed.';
  const target = diskSpaceCheck('target');
  if (!target) {
    return `${base} Space requirement will appear after the install base is checked.`;
  }
  const required = formatBytes(target.required_bytes);
  const free = formatBytes(target.free_bytes);
  const drive = target.drive || 'target drive';
  const status = target.status === 'failed' ? 'Not enough space' : 'Space OK';
  return `${base} ${status}: requires about ${required} on ${drive}; ${free} free.`;
}

function diskSpaceCheck(id) {
  const checks = Array.isArray(state.diskSpaceReport?.checks) ? state.diskSpaceReport.checks : [];
  return checks.find((check) => check.id === id) || null;
}

function editorText(id, label, value, placeholder = '', actions = []) {
  return { id, label, kind: 'text', value: value || '', placeholder, actions };
}

function editorPath(id, label, value) {
  return { id, label, kind: 'display', value: value || '', className: 'kit-path-property' };
}

function editorPassword(id, label, value) {
  return { id, label, kind: 'password', value: value || '' };
}

function editorNumber(id, label, value) {
  return { id, label, kind: 'number', value: value || '' };
}

function editorToggle(id, label, checked) {
  return { id, label, kind: 'toggle', value: Boolean(checked), offLabel: 'Off', onLabel: 'On' };
}

function editorSelect(id, label, value, options) {
  return { id, label, kind: 'select', value, options };
}

function editorAction(id, label) {
  return { id, label, kind: 'action', action: id };
}

function destroyPathPickers() {
  for (const picker of state.pathPickerWidgets.values()) {
    picker?.destroy?.();
  }
  state.pathPickerWidgets.clear();
}

function renderAdminPathPickers() {
  if (!state.helperFactories.pathPicker || !elements.adminPropertyEditor) {
    return;
  }
  const configs = getPathPickerConfigs();
  for (const config of configs) {
    const row = elements.adminPropertyEditor.querySelector(`[data-property-id="${config.id}"]`);
    const valueCell = row?.querySelector('.ui-property-editor-value');
    if (!valueCell) {
      continue;
    }
    valueCell.innerHTML = '';
    const host = document.createElement('div');
    host.className = 'kit-path-picker-host';
    valueCell.appendChild(host);
    const picker = state.helperFactories.pathPicker(host, {
      id: `kit-path-${config.id}`,
      mode: config.mode,
      value: config.input.value,
      placeholder: config.placeholder || '',
      extensions: config.extensions || [],
      required: Boolean(config.required),
      showClear: config.showClear !== false,
      browseLabel: config.browseLabel,
      ariaLabel: config.label,
      pickFile: (context) => pickFileForPath(config, context),
      pickFolder: (context) => pickFolderForPath(config, context),
      validatePath: (value, context) => validateAdminPath(config, value, context),
      onChange(value) {
        config.input.value = value;
        if (config.id === 'configPath') {
          state.templateConfigPath = value;
        }
        renderActiveStageValidation();
        if (config.id === 'basePath') {
          scheduleExistingInstallDiscovery({ rerender: true });
        }
      }
    });
    const browse = host.querySelector('.ui-path-picker-browse');
    if (browse) {
      browse.setAttribute('title', config.label || 'Browse');
      browse.setAttribute('aria-label', config.label ? `Browse ${config.label}` : 'Browse');
    }
    state.pathPickerWidgets.set(config.id, picker);
  }
}

function getPathPickerConfigs() {
  return [
    {
      id: 'phpPath',
      label: 'PHP',
      mode: 'file',
      input: elements.phpPathInput,
      extensions: ['.exe'],
      required: true,
      browseLabel: 'Browse...',
      filters: [{ name: 'PHP executable', extensions: ['exe'] }]
    },
    {
      id: 'apachePath',
      label: 'Apache httpd.exe',
      mode: 'file',
      input: elements.apachePathInput,
      extensions: ['.exe'],
      browseLabel: 'Browse...',
      filters: [{ name: 'Apache executable', extensions: ['exe'] }]
    },
    {
      id: 'mysqlPath',
      label: 'MySQL/MariaDB mysql.exe',
      mode: 'file',
      input: elements.mysqlPathInput,
      extensions: ['.exe'],
      browseLabel: 'Browse...',
      filters: [{ name: 'MySQL executable', extensions: ['exe'] }]
    },
    {
      id: 'basePath',
      label: 'Install Base',
      mode: 'folder',
      input: elements.basePathInput,
      required: true,
      browseLabel: 'Browse...'
    },
    {
      id: 'certRoot',
      label: 'Certificate Folder',
      mode: 'folder',
      input: elements.certRootInput,
      browseLabel: 'Browse...'
    },
    {
      id: 'pemUploadPath',
      label: 'PEM Bundle',
      mode: 'file',
      input: elements.pemUploadPathInput,
      extensions: ['.pem'],
      browseLabel: 'Browse...',
      filters: [{ name: 'PEM bundle', extensions: ['pem'] }]
    },
    {
      id: 'apacheIncludeOutput',
      label: 'Apache Include',
      mode: 'save-file',
      input: elements.apacheIncludeOutputInput,
      extensions: ['.conf'],
      required: true,
      browseLabel: 'Browse...',
      filters: [{ name: 'Apache config include', extensions: ['conf'] }]
    }
  ];
}

async function pickFileForPath(config) {
  const picker = config.mode === 'save-file' ? window.kitSetup.selectSaveFile : window.kitSetup.selectFile;
  return picker({
    title: config.browseLabel,
    defaultPath: config.input.value,
    filters: config.filters
  });
}

async function pickFolderForPath(config) {
  return window.kitSetup.selectFolder({
    title: config.browseLabel,
    defaultPath: config.input.value
  });
}

async function validateAdminPath(config, value, context = {}) {
  return window.kitSetup.validatePath({
    path: value,
    mode: config.mode || context.mode || 'file',
    required: Boolean(config.required)
  });
}

let existingInstallDiscoveryTimer = null;

function scheduleExistingInstallDiscovery(options = {}) {
  window.clearTimeout(existingInstallDiscoveryTimer);
  existingInstallDiscoveryTimer = window.setTimeout(async () => {
    await Promise.all([
      refreshExistingInstallDiscovery(),
      refreshTechnitiumDiscovery({ prefill: true }),
      refreshPrerequisiteGate(),
      refreshDiskSpaceEstimate()
    ]);
    if (options.rerender) {
      renderAdminPropertyEditor();
    }
  }, 350);
}

function applyAdminPropertyChange(change) {
  if (!change || state.syncingPropertyEditor) {
    return;
  }
  const id = String(change.propertyId || '');
  const value = change.value;
  state.syncingPropertyEditor = true;
  try {
    setAdminInputValue(id, value);
  } finally {
    state.syncingPropertyEditor = false;
  }
  renderActiveStageValidation();
  if (['basePath', 'machineIp', 'technitiumBaseUrl', 'dnsZone'].includes(id) || id.startsWith('scope:')) {
    scheduleExistingInstallDiscovery({ rerender: id === 'basePath' || id.startsWith('scope:') });
  }
}

function setAdminInputValue(id, value) {
  const inputMap = {
    phpPath: elements.phpPathInput,
    apachePath: elements.apachePathInput,
    mysqlPath: elements.mysqlPathInput,
    hubId: elements.hubIdInput,
    hubToken: elements.hubTokenInput,
    basePath: elements.basePathInput,
    databaseHost: elements.databaseHostInput,
    databasePort: elements.databasePortInput,
    databaseUsername: elements.databaseUsernameInput,
    databasePassword: elements.databasePasswordInput,
    adminEmail: elements.adminEmailInput,
    adminName: elements.adminNameInput,
    adminPassword: elements.adminPasswordInput,
    machineIp: elements.machineIpInput,
    technitiumBaseUrl: elements.technitiumBaseUrlInput,
    dnsZone: elements.dnsZoneInput,
    technitiumToken: elements.technitiumTokenInput,
    dnsClientNameserver: elements.dnsClientNameserverInput,
    dnsClientInterface: elements.dnsClientInterfaceInput,
    certRoot: elements.certRootInput,
    pemUploadPath: elements.pemUploadPathInput,
    apacheIncludeOutput: elements.apacheIncludeOutputInput
  };
  const checkboxMap = {
    applyDns: elements.applyDnsInput,
    applyDnsClient: elements.applyDnsClientInput,
    writeExtractedFiles: elements.writeExtractedFilesInput,
    applyWebServer: elements.applyWebServerInput,
    applyFirewall: elements.applyFirewallInput
  };
  if (id.startsWith('scope:')) {
    setAppScope(id.slice('scope:'.length), String(value || 'local'));
    return;
  }
  if (inputMap[id]) {
    inputMap[id].value = value == null ? '' : String(value);
    if (id === 'configPath') {
      state.templateConfigPath = inputMap[id].value;
    }
    return;
  }
  if (checkboxMap[id]) {
    checkboxMap[id].checked = Boolean(value);
  }
}

function setAppScope(appId, scope) {
  const nextScope = ['local', 'remote', 'disabled'].includes(scope) ? scope : 'local';
  state.appScopes[appId] = nextScope;
  const control = state.appScopeControls.get(appId);
  if (control?.setValue) {
    control.setValue(nextScope);
    return;
  }
  const radio = document.querySelector(`input[name="scope-${appId}"][value="${nextScope}"]`);
  if (radio) {
    radio.checked = true;
  }
}

async function handleAdminPropertyAction(property, action) {
  const actionId = String(action?.id || property?.action || property?.id || '');
  if (actionId === 'browsePhp') {
    await choosePhpBinary();
  } else if (actionId === 'browseApache') {
    await chooseExecutable(elements.apachePathInput, 'Choose Apache httpd.exe');
  } else if (actionId === 'browseMysql') {
    await chooseExecutable(elements.mysqlPathInput, 'Choose MySQL/MariaDB mysql.exe');
  } else if (actionId === 'browseBasePath') {
    await chooseFolder(elements.basePathInput, 'Choose App Base Path');
  } else if (actionId === 'browseCertRoot') {
    await chooseFolder(elements.certRootInput, 'Choose Certificate Folder');
  } else if (actionId === 'browsePem') {
    await choosePemFile();
  } else if (actionId === 'detectMachineIp') {
    const detected = await window.kitSetup.detectLocalIp();
    if (detected) {
      elements.machineIpInput.value = detected;
      scheduleExistingInstallDiscovery();
    }
  }
  renderAdminPropertyEditor();
}

async function chooseFolder(input, title) {
  const selected = await window.kitSetup.selectFolder({
    title,
    defaultPath: input.value
  });
  if (selected) {
    input.value = selected;
    renderAdminPropertyEditor();
    renderActiveStageValidation();
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
    renderAdminPropertyEditor();
    renderActiveStageValidation();
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
    renderAdminPropertyEditor();
    renderActiveStageValidation();
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
    technitiumResolvedIp: technitiumResolvedIp(),
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
    adminEmail: elements.adminEmailInput.value,
    adminName: elements.adminNameInput.value,
    applyFirewall: elements.applyFirewallInput.checked,
    appScopes: collectAppScopes(),
    appInstallDecisions: collectAppInstallDecisions()
  };
}

function technitiumResolvedIp() {
  return firstTechnitiumIpv4Candidate();
}

function collectAppInstallDecisions() {
  const scopes = collectAppScopes();
  const decisions = {};
  for (const [appId] of appOptions) {
    if (scopes[appId] !== 'local') {
      decisions[appId] = 'skip';
      continue;
    }
    decisions[appId] = state.appInstallDecisions[appId] || defaultAppInstallDecision(appId);
  }
  return decisions;
}

function defaultAppInstallDecision(appId) {
  const app = existingInstallForApp(appId);
  if (!app || (!app.manifest_exists && !app.path_exists)) {
    return 'install';
  }
  return app.manifest_exists ? 'repair' : 'overwrite';
}

function existingInstallForApp(appId) {
  const apps = Array.isArray(state.existingInstallReport?.apps) ? state.existingInstallReport.apps : [];
  return apps.find((app) => app.app_id === appId) || null;
}

function collectAppScopes() {
  const scopes = {};
  for (const [appId] of appOptions) {
    const control = state.appScopeControls.get(appId);
    if (control) {
      scopes[appId] = control.getValue() || 'local';
      continue;
    }
    const selected = document.querySelector(`input[name="scope-${appId}"]:checked`);
    scopes[appId] = selected ? selected.value : (state.appScopes[appId] || 'local');
  }
  return scopes;
}

async function refreshExistingInstallDiscovery() {
  if (!elements.existingInstallPanel || !window.kitSetup.inspectExistingInstalls) {
    return;
  }
  try {
    const report = await window.kitSetup.inspectExistingInstalls({
      form: collectSetupForm()
    });
    state.existingInstallReport = report;
    renderExistingInstallDiscovery(report);
  } catch (error) {
    elements.existingInstallPanel.hidden = false;
    elements.existingInstallSummary.textContent = 'Check failed';
    elements.existingInstallGrid.innerHTML = `<p class="panel-copy">${escapeHtml(error.message || 'Unable to inspect existing installs.')}</p>`;
  }
}

async function refreshWindowsInstallerDiscovery() {
  if (!window.kitSetup.inspectWindowsInstaller) {
    return null;
  }
  try {
    const report = await window.kitSetup.inspectWindowsInstaller();
    state.windowsInstallerReport = report;
    return report;
  } catch (error) {
    state.windowsInstallerReport = { status: 'failed', message: error.message, matches: [] };
    return state.windowsInstallerReport;
  }
}

async function refreshDiskSpaceEstimate() {
  if (!window.kitSetup.estimateDiskSpace) {
    return null;
  }
  try {
    const report = await window.kitSetup.estimateDiskSpace({
      form: collectSetupForm()
    });
    state.diskSpaceReport = report;
    return report;
  } catch (error) {
    state.diskSpaceReport = { status: 'failed', message: error.message, errors: [error.message] };
    return state.diskSpaceReport;
  }
}

async function refreshTechnitiumDiscovery(options = {}) {
  if (!window.kitSetup.detectTechnitium) {
    return null;
  }
  try {
    const report = await window.kitSetup.detectTechnitium({ form: collectSetupForm() });
    state.technitiumDiscovery = report;
    if (options.prefill && report?.status === 'success' && shouldPrefillTechnitiumUrl() && elements.technitiumBaseUrlInput.value !== report.url) {
      elements.technitiumBaseUrlInput.value = report.url;
      renderAdminPropertyEditor();
    }
    renderActiveStageValidation();
    return report;
  } catch (error) {
    state.technitiumDiscovery = { status: 'failed', message: error.message, candidates: [] };
    renderActiveStageValidation();
    return state.technitiumDiscovery;
  }
}

async function refreshPrerequisiteGate(options = {}) {
  if (!window.kitSetup.inspectPrerequisites) {
    state.prerequisiteGate = {
      status: 'failed',
      errors: ['This setup build does not expose prerequisite inspection.']
    };
    renderPrerequisiteGate();
    return state.prerequisiteGate;
  }
  state.prerequisiteGate = state.prerequisiteGate && !options.force
    ? { ...state.prerequisiteGate, checking: true }
    : { status: 'checking', checking: true };
  renderPrerequisiteGate();
  try {
    const report = await window.kitSetup.inspectPrerequisites({ form: collectSetupForm() });
    state.prerequisiteGate = report;
    if (
      report?.status === 'success'
      && report.technitium?.url
      && shouldPrefillTechnitiumUrl()
      && elements.technitiumBaseUrlInput.value !== report.technitium.url
    ) {
      elements.technitiumBaseUrlInput.value = report.technitium.url;
      renderAdminPropertyEditor();
    }
  } catch (error) {
    state.prerequisiteGate = {
      status: 'failed',
      errors: [error.message],
      message: error.message
    };
  }
  renderPrerequisiteGate();
  renderActiveStageValidation();
  return state.prerequisiteGate;
}

function renderPrerequisiteGate() {
  if (!elements.prerequisiteGatePanel) {
    return;
  }
  if (state.launchMode === 'data-prep') {
    elements.prerequisiteGatePanel.hidden = true;
    return;
  }
  const report = state.prerequisiteGate || { status: 'checking', checking: true };
  const status = report.checking ? 'pending' : (report.status === 'success' ? 'success' : 'failed');
  const ready = report.status === 'success' && !report.checking;
  elements.prerequisiteGateStatus.textContent = report.checking ? 'Checking' : (ready ? 'Ready' : 'Blocked');
  elements.prerequisiteGateStatus.className = `status-pill ${status}`;
  const setupForm = document.querySelector('.setup-form');
  if (setupForm) {
    setupForm.hidden = !ready;
  }
  elements.prerequisiteGatePanel.classList.toggle('success', ready);
  elements.prerequisiteGatePanel.classList.toggle('failed', !ready && !report.checking);
  elements.prerequisiteGateMessage.textContent = ready
    ? 'Startup requirements passed. Admin inputs are now available.'
    : (report.errors && report.errors.length > 0
      ? report.errors[0]
      : 'WAMPServer on this machine and Technitium at dns.pbb.ph are required for automated setup.');
  elements.prerequisiteGateGrid.innerHTML = '';
  for (const item of prerequisiteGateItems(report)) {
    const row = document.createElement('div');
    row.className = `prerequisite-gate-item ${item.status || 'pending'}`;
    row.innerHTML = `
      <span>${escapeHtml(item.label)}</span>
      <strong>${escapeHtml(item.value || 'Checking')}</strong>
      <small>${escapeHtml(item.detail || '')}</small>
    `;
    elements.prerequisiteGateGrid.appendChild(row);
  }
}

function prerequisiteGateItems(report) {
  if (!report || report.checking || report.status === 'checking') {
    return [
      { label: 'WAMP root', value: 'C:\\wamp64', detail: 'checking', status: 'pending' },
      { label: 'Apache', value: 'httpd.exe', detail: 'checking', status: 'pending' },
      { label: 'MySQL/MariaDB', value: 'mysql.exe', detail: 'checking', status: 'pending' },
      { label: 'Technitium DNS', value: 'http://dns.pbb.ph:5380', detail: 'checking', status: 'pending' }
    ];
  }
  const wamp = report?.wamp || {};
  const technitium = report?.technitium || {};
  const wampChecks = Array.isArray(wamp.checks) ? wamp.checks : [];
  const checkById = new Map(wampChecks.map((check) => [check.id, check]));
  const serviceText = (check) => [check?.service_name, check?.state].filter(Boolean).join(' ');
  return [
    {
      label: 'WAMP root',
      value: checkById.get('wamp_root')?.path || wamp.root || 'C:\\wamp64',
      detail: checkById.get('wamp_root')?.status === 'success' ? 'found' : 'missing',
      status: checkById.get('wamp_root')?.status || 'pending'
    },
    {
      label: 'Apache',
      value: checkById.get('apache_binary')?.path || wamp.apache_binary || '',
      detail: serviceText(checkById.get('apache_service')),
      status: checkById.get('apache_binary')?.status === 'success' && checkById.get('apache_service')?.status === 'success' ? 'success' : 'failed'
    },
    {
      label: 'MySQL/MariaDB',
      value: checkById.get('mysql_binary')?.path || wamp.mysql_binary || '',
      detail: serviceText(checkById.get('database_service')),
      status: checkById.get('mysql_binary')?.status === 'success' && checkById.get('database_service')?.status === 'success' ? 'success' : 'failed'
    },
    {
      label: 'Technitium DNS',
      value: technitium.url || 'http://dns.pbb.ph:5380',
      detail: technitium.resolved_ips?.length
        ? `resolved ${technitium.resolved_ips.join(', ')}`
        : (technitium.status === 'success' ? 'HTTP reachable; resolver did not return an IP' : 'dns.pbb.ph not resolved'),
      status: technitium.status || 'pending'
    }
  ];
}

function shouldPrefillTechnitiumUrl() {
  const current = cleanValue(elements.technitiumBaseUrlInput.value).toLowerCase();
  return current === '' || current === 'http://localhost:5380' || current === 'http://127.0.0.1:5380';
}

function renderExistingInstallDiscovery(report) {
  const apps = Array.isArray(report?.apps) ? report.apps : [];
  const existing = apps.filter((app) => app.manifest_exists || app.path_exists);
  elements.existingInstallPanel.hidden = false;
  elements.existingInstallSummary.textContent = existing.length > 0
    ? `${existing.length} possible existing install${existing.length === 1 ? '' : 's'}`
    : 'No existing installs found';
  elements.existingInstallGrid.innerHTML = '';
  if (apps.length === 0) {
    const empty = document.createElement('p');
    empty.className = 'panel-copy';
    empty.textContent = 'No apps are available for discovery.';
    elements.existingInstallGrid.appendChild(empty);
    return;
  }
  for (const app of apps) {
    const item = document.createElement('div');
    item.className = `existing-install-item ${app.status || 'not-found'}`;
    const label = (app.app_id || '').replace(/^pbb-/, '') || 'app';
    const meta = app.manifest_exists
      ? `${app.version || 'installed'}${app.installed_at ? ` / ${app.installed_at}` : ''}`
      : app.path_exists
        ? 'folder exists, no manifest'
        : 'not found';
    item.innerHTML = `
      <strong>${escapeHtml(label)}</strong>
      <span>${escapeHtml(app.host || '')}</span>
      <small>${escapeHtml(meta)}</small>
    `;
    item.title = app.install_path || '';
    elements.existingInstallGrid.appendChild(item);
  }
  renderAppInstallGrid();
}

async function runAction(action, options = {}) {
  debugLog('run-action:enter', { action, options, busy: state.busy });
  if (state.busy) {
    debugLog('run-action:ignored-busy', { action });
    return null;
  }

  const validation = validateAction(action);
  debugLog('run-action:validation', validation);
  if (validation.blocking.length > 0) {
    await handleValidationBlock(`Cannot run ${action}`, validation.blocking[0]);
    return null;
  }

  if (guardedActions.has(action) && options.confirmed !== true) {
    debugLog('run-action:confirmation-required', { action });
    await requestActionConfirmation(action, options);
    return null;
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
    await buildRuntimeConfigForAction(options);
    debugLog('run-action:build-config:done', { action, configPath: elements.configPathInput.value });
    const request = {
      action,
      phpPath: elements.phpPathInput.value,
      configPath: elements.configPathInput.value,
      runId: getSessionRunId(),
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
    return result;
  } catch (error) {
    debugLog('run-action:error', { action, message: error.message, stack: error.stack });
    appendOutput(`ERROR: ${error.message}`);
    return null;
  } finally {
    state.busy = false;
    state.activeAction = null;
    setBusy(false);
    debugLog('run-action:finally', { action, busy: state.busy });
  }
}

async function runAutomatedInstall() {
  debugLog('run-automated-install:enter', { busy: state.busy });
  if (state.busy) {
    return;
  }
  resetAutomatedProgress();
  updateAutomatedProgress('inputs', 0, 'current', 'Confirm administrator inputs');
  if (state.prerequisiteGate?.status !== 'success') {
    await handleValidationBlock(
      'Cannot run automated install',
      validationIssue(
        'Startup requirements must pass before setup can continue.',
        '',
        'Start WAMPServer on this machine and make sure dns.pbb.ph reaches Technitium, then click Check Again.'
      )
    );
    updateAutomatedProgress('inputs', 0, 'failed', 'Startup requirements blocked setup');
    return;
  }
  await refreshTechnitiumDiscovery({ prefill: true });
  ensureDnsClientNameserverDefault();
  await refreshExistingInstallDiscovery();
  await refreshWindowsInstallerDiscovery();
  await refreshDiskSpaceEstimate();
  state.enforceTechnitiumRequirement = true;
  state.enforceDiskSpaceRequirement = true;
  const validation = validateAllStages();
  state.enforceTechnitiumRequirement = false;
  state.enforceDiskSpaceRequirement = false;
  if (validation.blocking.length > 0) {
    await handleValidationBlock('Cannot run automated install', validation.blocking[0]);
    updateAutomatedProgress('inputs', 0, 'failed', 'Administrator inputs need attention');
    return;
  }
  const confirmed = await confirmAutomatedInstall(validation);
  if (!confirmed) {
    updateAutomatedProgress('inputs', 0, 'pending', 'Automated install cancelled');
    return;
  }
  const sequence = buildAutomatedInstallSequence();
  state.automatedProgress.sequence = sequence;
  document.body.classList.remove('troubleshooting-visible');
  document.body.classList.add('admin-inputs-collapsed');
  document.body.classList.add('automation-running');
  startAutomatedElapsedTimer();
  appendOutput(`Automated install started with session ${getSessionRunId()}.`);
  appendOutput('Administrator inputs confirmed and hidden for the automated run.');
  for (let index = 0; index < sequence.length; index += 1) {
    const action = sequence[index];
    updateAutomatedProgress(phaseForAction(action), index, 'current', `Running ${action}`);
    markAppActionPhaseRunning(action);
    appendOutput(`Automated install step: ${action}`);
    const result = await runAction(action, { confirmed: true });
    const status = result?.report?.status || '';
    if (!result || Number(result.exitCode || 0) !== 0 || status === 'failed') {
      appendOutput(`Automated install stopped at ${action}.`);
      if (result && !result.report) {
        appendOutput(`${action} did not produce a report. Check the runner output above for stderr or timeout details.`);
      } else if (result?.report?.errors?.length) {
        appendOutput(`${action} errors: ${result.report.errors.slice(0, 3).join('; ')}`);
      }
      updateAutomatedProgress(phaseForAction(action), index, 'failed', `Stopped at ${action}`);
      stopAutomatedElapsedTimer();
      document.body.classList.remove('automation-running');
      document.body.classList.add('troubleshooting-visible');
      await showAutomatedActionAlert(action, result, 'failed');
      return;
    }
    updateAutomatedProgress(phaseForAction(action), index + 1, status === 'warning' ? 'warning' : 'current', `${action} ${status || 'complete'}`);
    if (status === 'warning') {
      await showAutomatedActionAlert(action, result, 'warning');
    }
  }
  updateAutomatedProgress('finish', sequence.length, 'success', 'Automated install sequence completed');
  appendOutput('Automated install sequence completed.');
  stopAutomatedElapsedTimer();
  document.body.classList.remove('automation-running');
  await showSuccessfulInstallAndExit();
}

async function showSuccessfulInstallAndExit() {
  if (state.helperFactories.alert && window.kitSetup.quitInstaller) {
    await state.helperFactories.alert('Project Bantay Bayan installation completed successfully.', {
      title: 'Installation Successful',
      description: `Session ${getSessionRunId()} completed successfully. The installer will now close.`,
      variant: 'success',
      okText: 'Close Installer',
      renderTarget: 'local',
      size: 'sm'
    });
    await window.kitSetup.quitInstaller();
    return;
  }
  if (!window.kitSetup.showSuccessAndQuit) {
    window.alert('Project Bantay Bayan installation completed successfully. The installer will now close.');
    window.close();
    return;
  }
  await window.kitSetup.showSuccessAndQuit({
    detail: `Session ${getSessionRunId()} completed successfully. The installer will now close.`
  });
}

async function confirmAutomatedInstall(validation) {
  const summary = automatedInstallSummary(validation);
  if (state.helperFactories.actionModal) {
    const confirmed = await confirmAutomatedInstallWithActionModal(summary, validation);
    if (confirmed !== null) {
      return confirmed;
    }
  }
  updateAutomatedProgress('validate', 0, 'current', 'Resolving hub identity');
  appendOutput('Resolving hub identity before confirmation...');
  delete state.runReports['hub-resolve'];
  const hubResult = await runAction('hub-resolve', { confirmed: true });
  if (!hasValidHubResolveResult(hubResult)) {
    await showInvalidHubInformationAlert();
    updateAutomatedProgress('validate', 0, 'failed', 'Hub pairing failed');
    return false;
  }
  if (state.helperFactories.confirm) {
    const refreshedSummary = automatedInstallSummary(validation);
    return state.helperFactories.confirm(refreshedSummary.message, {
      title: 'Confirm Administrator Inputs',
      description: refreshedSummary.description,
      variant: validation.warnings.length > 0 ? 'warning' : 'info',
      confirmText: 'Start Automated Install',
      cancelText: 'Review Inputs',
      renderTarget: 'local',
      size: 'md'
    });
  }
  const refreshedSummary = automatedInstallSummary(validation);
  return window.confirm(`${refreshedSummary.message}\n\n${refreshedSummary.description}`);
}

function confirmAutomatedInstallWithActionModal(summary, validation) {
  return new Promise((resolve) => {
    let settled = false;
    let modal = null;
    let contentHost = null;
    const finish = (value) => {
      if (settled) {
        return;
      }
      settled = true;
      modal?.destroy?.();
      resolve(value);
    };
    try {
      const body = document.createElement('div');
      body.className = 'automated-confirm-body';
      const description = document.createElement('p');
      description.textContent = summary.description;
      body.appendChild(description);
      contentHost = document.createElement('div');
      body.appendChild(contentHost);
      renderAutomatedInstallConfirmationContent(contentHost);
      const actions = (canStart) => [
        { id: 'review', label: 'Review Inputs', variant: 'ghost', onClick: () => finish(false) },
        {
          id: 'start',
          label: 'Start Installation',
          variant: validation.warnings.length > 0 ? 'warning' : 'primary',
          autoFocus: canStart,
          disabled: !canStart,
          onClick: () => finish(true)
        }
      ];
      modal = state.helperFactories.actionModal({
        title: 'Confirm Administrator Inputs',
        content: body,
        body,
        size: 'xl',
        renderTarget: 'local',
        onClose(event = {}) {
          if (!settled) {
            finish(event.actionId === 'start');
          }
        },
        actions: actions(false)
      });
      const opened = modal.open();
      if (!opened) {
        finish(null);
        return;
      }
      modal.setBusy?.(true, { message: 'Verifying hub, Technitium, and existing installs...' });
      updateAutomatedProgress('validate', 0, 'current', 'Resolving hub identity');
      appendOutput('Resolving hub identity before confirmation...');
      delete state.runReports['hub-resolve'];
      Promise.all([
        runAction('hub-resolve', { confirmed: true }),
        refreshTechnitiumDiscovery({ prefill: true }),
        refreshExistingInstallDiscovery(),
        refreshWindowsInstallerDiscovery(),
        refreshDiskSpaceEstimate()
      ]).then(async ([hubResult]) => {
        if (settled) {
          return;
        }
        if (!hasValidHubResolveResult(hubResult)) {
          modal.setBusy?.(false);
          await showInvalidHubInformationAlert();
          updateAutomatedProgress('validate', 0, 'failed', 'Hub pairing failed');
          finish(false);
          return;
        }
        renderAutomatedInstallConfirmationContent(contentHost);
        modal.setActions?.(actions(true));
        modal.setBusy?.(false);
      }).catch(async (error) => {
        debugLog('automated-install:hub-resolve-error', { message: error.message });
        if (settled) {
          return;
        }
        modal.setBusy?.(false);
        await showInvalidHubInformationAlert();
        updateAutomatedProgress('validate', 0, 'failed', 'Hub pairing failed');
        finish(false);
      });
    } catch (error) {
      debugLog('automated-install:action-modal-error', { message: error.message });
      finish(null);
    }
  });
}

function automatedInstallConfirmationItems() {
  const scopes = collectAppScopes();
  const localApps = Object.entries(scopes)
    .filter(([, scope]) => scope === 'local')
    .map(([appId]) => appId.replace(/^pbb-/, ''))
    .join(', ') || 'none';
  const remoteApps = Object.entries(scopes)
    .filter(([, scope]) => scope === 'remote')
    .map(([appId]) => appId.replace(/^pbb-/, ''))
    .join(', ') || 'none';
  return [
    { label: 'Admin', value: `${cleanValue(elements.adminNameInput.value)} <${cleanValue(elements.adminEmailInput.value)}>` },
    { id: 'hub', label: 'Hub', value: 'Checking hub identity...' },
    { label: 'Local Apps', value: localApps },
    { label: 'Remote Apps', value: remoteApps },
    { label: 'Install Base', value: cleanValue(elements.basePathInput.value) },
    { label: 'Database', value: `${cleanValue(elements.databaseUsernameInput.value)}@${cleanValue(elements.databaseHostInput.value)}:${cleanValue(elements.databasePortInput.value)}` },
    { label: 'Machine IP', value: cleanValue(elements.machineIpInput.value) },
    { label: 'DNS', value: `${elements.applyDnsInput.checked ? 'apply records' : 'plan only'} / ${elements.applyDnsClientInput.checked ? 'set local DNS' : 'client unchanged'}` },
    { label: 'SSL & Apache', value: `${elements.writeExtractedFilesInput.checked ? 'write certs' : 'use existing certs'} / ${elements.applyWebServerInput.checked ? 'apply include' : 'plan only'}` },
    { label: 'Firewall', value: elements.applyFirewallInput.checked ? 'apply HTTP/HTTPS rules' : 'skip firewall rules' }
  ];
}

function renderAutomatedInstallConfirmationContent(host) {
  if (!host) {
    return;
  }
  host.innerHTML = '';
  const layout = document.createElement('div');
  layout.className = 'automated-confirm-columns';
  layout.append(
    confirmationColumn('Hub', hubConfirmationRows()),
    confirmationAppsColumn(),
    confirmationColumn('Admin Inputs', adminConfirmationRows())
  );
  host.appendChild(layout);
}

function confirmationColumn(title, rows) {
  const section = document.createElement('section');
  section.className = 'automated-confirm-column';
  const heading = document.createElement('h4');
  heading.textContent = title;
  section.appendChild(heading);
  const list = document.createElement('dl');
  list.className = 'automated-confirm-list';
  rows.forEach((row) => {
    const term = document.createElement('dt');
    term.textContent = row.label;
    const detail = document.createElement('dd');
    detail.textContent = row.value;
    if (row.tone) {
      detail.className = `tone-${row.tone}`;
    }
    list.append(term, detail);
  });
  section.appendChild(list);
  return section;
}

function hubConfirmationRows() {
  const hub = state.runReports['hub-resolve']?.hub || {};
  const technitium = state.technitiumDiscovery || {};
  const installer = state.windowsInstallerReport || {};
  return [
    { label: 'Hub', value: hasResolvedHubInformation(state.runReports['hub-resolve']) ? hubConfirmationValue() : 'Not verified', tone: hasResolvedHubInformation(state.runReports['hub-resolve']) ? 'success' : 'warning' },
    { label: 'Technitium', value: technitium.status === 'success' ? technitium.url : 'Not detected', tone: technitium.status === 'success' ? 'success' : 'danger' },
    { label: 'Kit Setup', value: installer.status === 'installed' ? windowsInstallerSummary(installer) : 'No existing Windows installer found', tone: installer.status === 'installed' ? 'warning' : 'muted' },
    { label: 'Machine IP', value: cleanValue(elements.machineIpInput.value) || '-' },
    { label: 'Zone', value: cleanValue(elements.dnsZoneInput.value) || '-' }
  ];
}

function windowsInstallerSummary(report) {
  const first = Array.isArray(report?.matches) ? report.matches[0] : null;
  if (!first) {
    return 'Installed';
  }
  return [first.display_name, first.version, first.install_location].filter(Boolean).join(' / ') || 'Installed';
}

function confirmationAppsColumn() {
  const section = document.createElement('section');
  section.className = 'automated-confirm-column automated-confirm-apps';
  const heading = document.createElement('h4');
  heading.textContent = 'Apps';
  section.appendChild(heading);
  const list = document.createElement('div');
  list.className = 'automated-confirm-app-list';
  const scopes = collectAppScopes();
  appOptions.forEach(([appId, label]) => {
    const app = existingInstallForApp(appId);
    const scope = scopes[appId] || 'local';
    const row = document.createElement('div');
    row.className = 'automated-confirm-app-row';
    const copy = document.createElement('div');
    const title = document.createElement('strong');
    title.textContent = label;
    const meta = document.createElement('span');
    meta.textContent = appDiscoverySummary(app, scope);
    copy.append(title, meta);
    const select = document.createElement('select');
    select.className = 'ui-input automated-confirm-app-decision';
    appDecisionOptions(app, scope).forEach((option) => {
      const item = document.createElement('option');
      item.value = option.value;
      item.textContent = option.label;
      select.appendChild(item);
    });
    select.value = collectAppInstallDecisions()[appId];
    select.disabled = scope !== 'local';
    select.addEventListener('change', () => {
      state.appInstallDecisions[appId] = select.value;
    });
    row.append(copy, select);
    list.appendChild(row);
  });
  section.appendChild(list);
  return section;
}

function appDiscoverySummary(app, scope) {
  if (scope !== 'local') {
    return `${scope} / will not deploy locally`;
  }
  if (!app) {
    return 'discovery pending';
  }
  const risk = appDiscoveryRiskSummary(app);
  if (app.manifest_exists) {
    return `existing ${app.version || 'install'}${risk ? ` / ${risk}` : ''} / ${app.install_path || ''}`;
  }
  if (app.path_exists) {
    return `folder exists without manifest${risk ? ` / ${risk}` : ''} / ${app.install_path || ''}`;
  }
  if (risk) {
    return `new install / ${risk} / ${app.install_path || ''}`;
  }
  return `new install / ${app.install_path || ''}`;
}

function appDiscoveryRiskSummary(app) {
  const parts = [];
  const processes = Number(app?.runtime?.process_count || 0);
  if (processes > 0) {
    parts.push(`${processes} running process${processes === 1 ? '' : 'es'}`);
  }
  const addresses = Array.isArray(app?.dns?.addresses) ? app.dns.addresses : [];
  if (addresses.length > 0) {
    parts.push(`DNS ${addresses.slice(0, 2).join(', ')}`);
  }
  if (app?.http?.http_status) {
    parts.push(`HTTP ${app.http.http_status}`);
  }
  return parts.join(' / ');
}

function appDecisionOptions(app, scope) {
  if (scope !== 'local') {
    return [{ value: 'skip', label: 'Skip' }];
  }
  if (!app || (!app.manifest_exists && !app.path_exists)) {
    return [{ value: 'install', label: 'Install' }];
  }
  return [
    { value: 'repair', label: 'Repair / Reinstall' },
    { value: 'overwrite', label: 'Overwrite' },
    { value: 'skip', label: 'Skip' }
  ];
}

function adminConfirmationRows() {
  return [
    { label: 'Admin', value: `${cleanValue(elements.adminNameInput.value)} <${cleanValue(elements.adminEmailInput.value)}>` },
    { label: 'Install Base', value: cleanValue(elements.basePathInput.value) },
    { label: 'Database', value: `${cleanValue(elements.databaseUsernameInput.value)}@${cleanValue(elements.databaseHostInput.value)}:${cleanValue(elements.databasePortInput.value)}` },
    { label: 'DNS', value: `${elements.applyDnsInput.checked ? 'apply records' : 'plan only'} / ${elements.applyDnsClientInput.checked ? 'set local DNS' : 'client unchanged'}` },
    { label: 'SSL & Apache', value: `${elements.writeExtractedFilesInput.checked ? 'write certs' : 'use existing certs'} / ${elements.applyWebServerInput.checked ? 'apply include' : 'plan only'}` },
    { label: 'Firewall', value: elements.applyFirewallInput.checked ? 'apply HTTP/HTTPS rules' : 'skip firewall rules' }
  ];
}

function hasResolvedHubInformation(report) {
  const hub = report?.hub;
  return Boolean(hub && typeof hub === 'object' && hub.id && hub.name && hub.domain);
}

function hasValidHubResolveResult(result) {
  const status = result?.report?.status || '';
  return Boolean(result && Number(result.exitCode || 0) === 0 && status === 'success' && hasResolvedHubInformation(result.report));
}

async function showInvalidHubInformationAlert() {
  const message = 'Hub information is incorrect. Please verify that the Hub ID and Hub Token are correct before starting installation.';
  if (state.helperFactories.alert) {
    await state.helperFactories.alert(message, {
      title: 'Verify Hub Information',
      variant: 'error',
      okText: 'Review Inputs',
      renderTarget: 'local',
      size: 'sm'
    });
    return;
  }
  window.alert(message);
}

async function showAutomatedActionAlert(action, result, severity = 'failed') {
  const report = result?.report || {};
  const title = severity === 'warning' ? 'Installation Warning' : 'Installation Stopped';
  const message = automatedActionMessage(action, result, severity);
  const description = [
    reportPathLine(result),
    severity === 'warning'
      ? 'The installer can continue, but the admin should review this message.'
      : 'The automated install stopped before continuing. Review the message and rerun after fixing the issue.'
  ].filter(Boolean).join('\n');

  if (state.helperFactories.alert) {
    await state.helperFactories.alert(message, {
      title,
      description,
      variant: severity === 'warning' ? 'warning' : 'error',
      okText: severity === 'warning' ? 'Continue' : 'Review',
      renderTarget: 'local',
      size: 'md'
    });
    return;
  }
  window.alert(`${title}\n\n${message}${description ? `\n\n${description}` : ''}`);
}

function automatedActionMessage(action, result, severity) {
  const report = result?.report || {};
  const errors = Array.isArray(report.errors) ? report.errors.filter(Boolean) : [];
  const warnings = Array.isArray(report.warnings) ? report.warnings.filter(Boolean) : [];
  const details = severity === 'warning' ? warnings : errors;
  if (details.length > 0) {
    return `${action}: ${details.slice(0, 3).join(' ')}`;
  }
  if (report.message) {
    return `${action}: ${report.message}`;
  }
  if (result && !result.report) {
    return `${action} did not produce a report.`;
  }
  return `${action} reported ${severity}.`;
}

function reportPathLine(result) {
  const reportPath = result?.reportPath || '';
  if (!reportPath) {
    return `Session: ${getSessionRunId()}`;
  }
  return `Report: ${reportPath}`;
}

async function handleValidationBlock(prefix, result) {
  const issue = firstValidationIssue(result);
  const message = issueText(issue);
  focusFirstInvalidStage(result, issue);
  appendOutput(`${prefix}: ${message}`);
  await showValidationIssueAlert(prefix, issue);
  focusInvalidAdminField(issue?.field || '');
}

async function showValidationIssueAlert(prefix, issue) {
  const message = issueText(issue);
  const fieldLabel = issue?.field ? validationFieldLabel(issue.field) : '';
  const description = [
    fieldLabel ? `Field: ${fieldLabel}` : '',
    issue?.help || 'Review the highlighted administrator input and update it before starting installation.'
  ].filter(Boolean).join('\n');

  if (state.helperFactories.alert) {
    await state.helperFactories.alert(message, {
      title: prefix,
      description,
      variant: 'warning',
      okText: 'Review Input',
      renderTarget: 'local',
      size: 'sm'
    });
    return;
  }
  window.alert(`${prefix}\n\n${message}${description ? `\n\n${description}` : ''}`);
}

function hubConfirmationValue() {
  const hub = state.runReports['hub-resolve']?.hub;
  if (!hub || typeof hub !== 'object') {
    return `Hub ${cleanValue(elements.hubIdInput.value)} / token ${maskSecret(elements.hubTokenInput.value)}`;
  }

  return [
    `Hub ${hub.id}`,
    hub.name,
    hub.relay_hub_id ? `relay ${hub.relay_hub_id}` : '',
    hub.deployment,
    hub.domain,
    hub.status ? `status ${hub.status}` : ''
  ].filter(Boolean).join(' / ');
}

function maskSecret(value) {
  return cleanValue(value) === '' ? 'not set' : 'set';
}

function automatedInstallSummary(validation) {
  const localApps = Object.entries(collectAppScopes())
    .filter(([, scope]) => scope === 'local')
    .map(([appId]) => appId.replace(/^pbb-/, ''))
    .join(', ') || 'none';
  const warnings = validation.warnings.flatMap((item) => item.issues || []).map(issueText);
  const warningText = warnings.length > 0
    ? ` Warnings: ${warnings.slice(0, 4).join(' ')}${warnings.length > 4 ? ' ...' : ''}`
    : '';
  return {
    message: [
      `Admin: ${cleanValue(elements.adminNameInput.value)} <${cleanValue(elements.adminEmailInput.value)}>`,
      `Hub: ${hubConfirmationValue()}`,
      `Apps: ${localApps}`,
      `Base: ${cleanValue(elements.basePathInput.value)}`,
      `Machine IP: ${cleanValue(elements.machineIpInput.value)}`,
      `DNS apply: ${elements.applyDnsInput.checked ? 'yes' : 'no'}`,
      `Apache apply: ${elements.applyWebServerInput.checked ? 'yes' : 'no'}`,
      `Firewall apply: ${elements.applyFirewallInput.checked ? 'yes' : 'no'}`
    ].join('\n'),
    description: `The automated installer will use these administrator-owned inputs, hide the input panels, and execute guarded steps without separate prompts.${warningText}`
  };
}

function buildAutomatedInstallSequence() {
  const sequence = [
    'stage-report',
    'service-stop',
    'prepare-packages',
    'plan',
    'preflight',
    'install',
    'dns-plan'
  ];
  if (elements.applyDnsInput.checked) {
    sequence.push('dns-apply');
  }
  if (elements.applyDnsClientInput.checked) {
    sequence.push('dns-client-apply');
  }
  sequence.push('dns-verify');
  if (elements.applyFirewallInput.checked) {
    sequence.push('firewall-apply');
  }
  sequence.push('ssl-plan');
  if (elements.writeExtractedFilesInput.checked || elements.applyWebServerInput.checked) {
    sequence.push('ssl-apply');
  }
  sequence.push('service-plan', 'service-start', 'service-verify', 'remote-check', 'smoke-check', 'finish-report');
  return sequence;
}

function resetAutomatedProgress() {
  stopAutomatedElapsedTimer();
  state.automatedProgress = {
    sequence: [],
    completed: 0,
    currentPhase: 'inputs',
    status: 'current',
    message: 'Confirm administrator inputs',
    startedAt: null,
    finishedAt: null
  };
  state.overallProgressWidget?.destroy?.();
  state.overallProgressWidget = null;
  state.automatedElapsedWidget?.destroy?.();
  state.automatedElapsedWidget = null;
  state.setupStepperSignature = '';
  renderAutomatedProgress();
  renderSetupStepper(state.stages);
}

function startAutomatedElapsedTimer() {
  if (!state.automatedProgress) {
    resetAutomatedProgress();
  }
  state.automatedProgress.startedAt = Date.now();
  state.automatedProgress.finishedAt = null;
  state.automatedElapsedWidget?.destroy?.();
  state.automatedElapsedWidget = null;
  renderAutomatedElapsed();
}

function stopAutomatedElapsedTimer() {
  if (state.automatedProgress && state.automatedProgress.startedAt && !state.automatedProgress.finishedAt) {
    state.automatedProgress.finishedAt = Date.now();
  }
  state.automatedElapsedWidget?.stop?.(state.automatedProgress?.finishedAt || Date.now());
  if (state.automatedElapsedTimer) {
    window.clearInterval(state.automatedElapsedTimer);
    state.automatedElapsedTimer = null;
  }
  renderAutomatedElapsed();
}

function updateAutomatedProgress(phaseId, completed, status, message) {
  if (!state.automatedProgress) {
    resetAutomatedProgress();
  }
  state.automatedProgress = {
    ...state.automatedProgress,
    currentPhase: phaseId,
    completed,
    status,
    message
  };
  renderAutomatedProgress();
  renderSetupStepper(state.stages);
}

function renderAutomatedProgress() {
  if (!elements.overallInstallProgressPanel || !state.automatedProgress) {
    return;
  }
  elements.overallInstallProgressPanel.hidden = false;
  const total = Math.max(1, state.automatedProgress.sequence.length || buildAutomatedInstallSequence().length);
  const percent = Math.max(0, Math.min(100, Math.round((Number(state.automatedProgress.completed || 0) / total) * 100)));
  elements.overallInstallProgressTitle.textContent = state.automatedProgress.message || 'Automated install';
  elements.overallInstallProgressPercent.textContent = `${percent}%`;
  renderAutomatedElapsed();
  if (!state.helperFactories.progress) {
    elements.overallInstallProgressBar.innerHTML = `
      <div class="package-progress-fallback" role="progressbar" aria-label="Automated install progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${percent}">
        <span style="width: ${percent}%"></span>
      </div>
    `;
    return;
  }
  const data = { label: 'Overall progress', value: percent };
  const options = {
    style: 'bar',
    size: 'md',
    showLabel: false,
    showPercent: false,
    ariaLabel: 'Automated install progress'
  };
  if (!state.overallProgressWidget) {
    elements.overallInstallProgressBar.innerHTML = '';
    state.overallProgressWidget = state.helperFactories.progress(elements.overallInstallProgressBar, data, options);
    return;
  }
  state.overallProgressWidget.update(data, options);
}

function renderAutomatedElapsed() {
  if (!elements.overallInstallProgressElapsed) {
    return;
  }
  const startedAt = state.automatedProgress?.startedAt || null;
  if (!startedAt) {
    elements.overallInstallProgressElapsed.textContent = 'Elapsed 00:00:00';
    return;
  }
  if (state.helperFactories.elapsedTime) {
    if (!state.automatedElapsedWidget) {
      elements.overallInstallProgressElapsed.innerHTML = '';
      state.automatedElapsedWidget = state.helperFactories.elapsedTime(elements.overallInstallProgressElapsed, {
        startTime: startedAt,
        endTime: state.automatedProgress?.finishedAt || null,
        showLabel: true,
        label: 'Elapsed',
        chrome: false,
        ariaLabel: 'Automated install elapsed time'
      });
      return;
    }
    state.automatedElapsedWidget.update?.({
      startTime: startedAt,
      endTime: state.automatedProgress?.finishedAt || null
    });
    return;
  }
  const renderFallback = () => {
    const end = state.automatedProgress?.finishedAt || Date.now();
    elements.overallInstallProgressElapsed.textContent = `Elapsed ${formatElapsed(end - startedAt)}`;
  };
  renderFallback();
  if (!state.automatedProgress?.finishedAt && !state.automatedElapsedTimer) {
    state.automatedElapsedTimer = window.setInterval(renderFallback, 1000);
  }
}

function formatElapsed(ms) {
  const totalSeconds = Math.max(0, Math.floor(Number(ms || 0) / 1000));
  const hours = Math.floor(totalSeconds / 3600);
  const minutes = Math.floor((totalSeconds % 3600) / 60);
  const seconds = totalSeconds % 60;
  return hours > 0
    ? `${hours}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
    : `${minutes}:${String(seconds).padStart(2, '0')}`;
}

function phaseForAction(action) {
  if (['stage-report', 'hub-resolve'].includes(action)) {
    return 'validate';
  }
  if (['plan', 'preflight', 'dns-plan', 'ssl-plan', 'service-plan'].includes(action)) {
    return 'plan';
  }
  if (['finish-report'].includes(action)) {
    return 'finish';
  }
  return 'install';
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
        if (state.automatedProgress) {
          updateAutomatedProgress(
            'install',
            Number(state.automatedProgress.completed || 0),
            'current',
            packageProgressMessage(payload)
          );
        }
        if (payload.step === 'summary') {
          updatePackageProgressSummary(payload);
        } else {
          updatePackageProgress(payload);
        }
      }
    } catch (_error) {
      // Ignore malformed progress lines; final reports still carry authoritative state.
    }
  }
}

function packageProgressMessage(payload) {
  if (payload.step === 'summary') {
    const complete = Number(payload.complete || 0);
    const running = Number(payload.running || 0);
    return `Preparing app bundles: ${complete} complete, ${running} running`;
  }
  const appId = String(payload.app_id || '').replace(/^pbb-/, '');
  const message = String(payload.message || 'Preparing app bundle.');
  return appId !== '' ? `${appId}: ${message}` : message;
}

function resetPackageProgress() {
  state.packageProgressWidget?.destroy?.();
  state.packageProgressWidget = null;
  state.packageProgress = new Map();
  state.appInstallRows = new Map();
  for (const [appId, label] of appOptions) {
    state.packageProgress.set(appId, {
      app_id: appId,
      label,
      step: 'pending',
      status: 'pending',
      message: 'Waiting for package preparation.',
      extract_percent: 0,
      deploy_percent: 0
    });
    state.appInstallRows.set(appId, makeAppInstallRow(appId, label));
  }
  renderPackageProgress();
}

function updatePackageProgress(payload) {
  const appId = String(payload.app_id || '');
  if (!appId) {
    return;
  }
  const current = state.packageProgress.get(appId) || { app_id: appId, label: appId };
  const nextPercent = Number.isFinite(Number(payload.percent))
    ? Math.max(Number(current.percent || 0), Number(payload.percent))
    : Number(current.percent || 0);
  const step = String(payload.step || current.step || '');
  const status = payload.status || progressStatusForStep(step);
  const extractPercent = progressPhasePercent('extract', current, payload, status);
  const deployPercent = progressPhasePercent('deploy', current, payload, status);
  state.packageProgress.set(appId, {
    ...current,
    ...payload,
    status,
    message: payload.message || progressLabelForStep(step),
    percent: nextPercent,
    extract_percent: extractPercent,
    deploy_percent: deployPercent
  });
  mergePackageIntoAppInstall(appId, current.label || appId, step, status, payload.message || progressLabelForStep(step), extractPercent, deployPercent);
  renderPackageProgress();
}

function mergePackageIntoAppInstall(appId, label, step, status, message, extractPercent, deployPercent) {
  const row = ensureAppInstallRow(appId, label);
  const normalized = normalizeAppStatus(status);
  if (step === 'failed' || normalized === 'failed') {
    if (extractPercent >= 100) {
      row.unpack = mergePhaseState(row.unpack, { status: 'success', message, percent: 100, forceStatus: true }, true);
      row.copy = mergePhaseState(row.copy, { status: 'failed', message, percent: deployPercent, forceStatus: true }, true);
    } else {
      row.unpack = mergePhaseState(row.unpack, { status: 'failed', message, percent: extractPercent, forceStatus: true }, true);
      row.copy = mergePhaseState(row.copy, { status: 'blocked', message: 'Blocked by package preparation failure.', percent: deployPercent, forceStatus: true }, true);
    }
    blockAppInstallPhasesAfter(row, 'copy', 'Blocked by package preparation failure.');
    return;
  }
  if (step === 'complete' || normalized === 'success') {
    row.unpack = mergePhaseState(row.unpack, { status: 'success', message, percent: 100 }, true);
    row.copy = mergePhaseState(row.copy, { status: 'success', message, percent: 100 }, true);
    return;
  }
  row.unpack = mergePhaseState(row.unpack, {
    status: extractPercent >= 100 ? 'success' : normalized,
    message,
    percent: extractPercent
  }, true);
  row.copy = mergePhaseState(row.copy, {
    status: deployPercent > 0 ? normalized : row.copy.status,
    message: deployPercent > 0 ? message : row.copy.message,
    percent: deployPercent
  }, true);
}

function progressPhasePercent(phase, current, payload, status) {
  if (status === 'success') {
    return 100;
  }
  const currentValue = Number(current[`${phase}_percent`] || 0);
  const step = String(payload.step || '');
  const payloadPercent = Number.isFinite(Number(payload.percent)) ? Number(payload.percent) : null;
  if (phase === 'extract') {
    if (['verify', 'deploy', 'complete'].includes(step)) {
      return 100;
    }
    if (step === 'extract' && payloadPercent !== null) {
      return Math.max(currentValue, extractPhasePercent(payloadPercent));
    }
  }
  if (phase === 'deploy') {
    if (step === 'complete') {
      return 100;
    }
    if (step === 'deploy' && payloadPercent !== null) {
      return Math.max(currentValue, deployPhasePercent(payloadPercent));
    }
  }
  return Math.max(0, Math.min(100, currentValue));
}

function extractPhasePercent(percent) {
  if (percent >= 55) {
    return 100;
  }
  return Math.max(0, Math.min(100, Math.round(((percent - 10) / 45) * 100)));
}

function deployPhasePercent(percent) {
  if (percent >= 95) {
    return 100;
  }
  return Math.max(0, Math.min(100, Math.round(((percent - 60) / 35) * 100)));
}

function updatePackageProgressSummary(payload) {
  const apps = Array.isArray(payload.apps)
    ? payload.apps
    : Object.values(payload.apps || {});
  if (apps.length === 0) {
    return;
  }
  for (const item of apps) {
    updatePackageProgress({
      ...item,
      status: item.status || progressStatusForStep(item.step),
      message: item.message || progressLabelForStep(item.step)
    });
  }
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

function makeAppInstallRow(appId, label = appId) {
  return {
    id: appId,
    app: appId,
    label,
    unpack: { status: 'pending', percent: 0, message: 'Waiting' },
    copy: { status: 'pending', percent: 0, message: 'Waiting' },
    preflight: { status: 'pending', message: 'Waiting' },
    install: { status: 'pending', message: 'Waiting' },
    dataPrep: { status: 'pending', message: 'Waiting' },
    smoke: { status: 'pending', message: 'Waiting' }
  };
}

function ensureAppInstallRow(appId, label = '') {
  const option = appOptions.find(([id]) => id === appId);
  const resolvedLabel = label || option?.[1] || appId;
  const existing = state.appInstallRows.get(appId);
  if (existing) {
    existing.label = existing.label || resolvedLabel;
    return existing;
  }
  const row = makeAppInstallRow(appId, resolvedLabel);
  state.appInstallRows.set(appId, row);
  return row;
}

function mergeAppActionPhase(appId, phase, update = {}) {
  if (!appId || !phase) {
    return;
  }
  const row = ensureAppInstallRow(appId, update.label || '');
  row[phase] = mergePhaseState(row[phase], update, false);
  if (normalizeAppStatus(update.status) === 'failed') {
    blockAppInstallPhasesAfter(row, phase, `Blocked by ${phaseLabel(phase)} failure.`);
  }
}

function blockAppInstallPhasesAfter(row, phase, message) {
  const phases = ['unpack', 'copy', 'preflight', 'install', 'smoke', 'dataPrep'];
  const start = phases.indexOf(phase);
  if (start < 0) {
    return;
  }
  phases.slice(start + 1).forEach((nextPhase) => {
    row[nextPhase] = mergePhaseState(row[nextPhase], {
      status: 'blocked',
      message,
      forceStatus: true
    }, nextPhase === 'unpack' || nextPhase === 'copy');
  });
}

function phaseLabel(phase) {
  const labels = {
    unpack: 'unpack',
    copy: 'copy',
    preflight: 'preflight',
    install: 'install',
    smoke: 'smoke check',
    dataPrep: 'data preparation'
  };
  return labels[phase] || phase;
}

function mergePhaseState(current = {}, update = {}, isProgress = false) {
  const next = { ...current };
  if (update.status) {
    const incomingStatus = normalizeAppStatus(update.status);
    next.status = update.forceStatus ? incomingStatus : strongerStatus(next.status, incomingStatus);
  }
  if (update.message) {
    next.message = update.message;
  }
  if (isProgress && Object.prototype.hasOwnProperty.call(update, 'percent')) {
    const currentPercent = Number(next.percent || 0);
    const incomingPercent = Number(update.percent || 0);
    next.percent = Math.max(currentPercent, Math.max(0, Math.min(100, incomingPercent)));
    if (next.percent >= 100 && !['failed', 'warning', 'blocked'].includes(next.status)) {
      next.status = 'success';
    }
  }
  return next;
}

function strongerStatus(current = 'pending', incoming = 'pending') {
  const rank = {
    failed: 7,
    warning: 6,
    blocked: 5,
    success: 4,
    running: 3,
    skipped: 2,
    pending: 1
  };
  return (rank[incoming] || 0) >= (rank[current] || 0) ? incoming : current;
}

function normalizeAppStatus(status) {
  const value = String(status || 'pending').toLowerCase();
  if (['success', 'passed', 'complete', 'completed', 'planned'].includes(value)) {
    return 'success';
  }
  if (['failed', 'error'].includes(value)) {
    return 'failed';
  }
  if (['blocked', 'cancelled', 'canceled'].includes(value)) {
    return 'blocked';
  }
  if (['warning', 'warn'].includes(value)) {
    return 'warning';
  }
  if (['running', 'working', 'current'].includes(value)) {
    return 'running';
  }
  if (value === 'skipped') {
    return 'skipped';
  }
  return 'pending';
}

function loadHelperUiFactories() {
  if (state.helperLoad) {
    return state.helperLoad;
  }

  const helperBundleUrl = new URL('../assets/helpers.pbb.ph/dist/helpers.ui.bundle.min.js', window.location.href).href;
  state.helperLoad = import(helperBundleUrl)
    .then((module) => {
      const modules = module?.helperUiBundleModules || {};
      state.helperFactories = {
        progress: modules['./ui.progress.js']?.createProgress || null,
        stepper: modules['./ui.stepper.js']?.createStepper || null,
        toggleGroup: modules['./ui.toggle.group.js']?.createToggleGroup || null,
        alert: modules['./ui.dialog.js']?.uiAlert || null,
        confirm: modules['./ui.dialog.js']?.uiConfirm || null,
        modal: modules['./ui.modal.js']?.createModal || null,
        actionModal: modules['./ui.modal.js']?.createActionModal || null,
        propertyEditor: modules['./ui.property.editor.js']?.createPropertyEditor || null,
        password: modules['./ui.password.js']?.createPasswordField || null,
        pathPicker: modules['./ui.path.picker.js']?.createPathPicker || null,
        grid: modules['./ui.grid.js']?.createGrid || null,
        icon: modules['./ui.icons.js']?.createIcon || null,
        elapsedTime: modules['./ui.elapsed.time.js']?.createElapsedTime || null
      };
      return state.helperFactories;
    })
    .catch(() => {
      state.helperFactories = {};
      return state.helperFactories;
    });
  return state.helperLoad;
}

function renderPackageProgress() {
  elements.packageProgressPanel.hidden = false;
  const packages = Array.from(state.packageProgress.values());
  const done = packages.filter((item) => item.status === 'success' || item.status === 'failed').length;
  const failed = packages.filter((item) => item.status === 'failed').length;
  const running = packages.filter((item) => item.status === 'running').length;
  const total = packages.length;
  const aggregatePercent = total > 0
    ? Math.round(packages.reduce((sum, item) => {
      if (item.status === 'success' || item.status === 'failed') {
        return sum + 100;
      }
      return sum + Math.max(0, Math.min(100, Number(item.percent || 0)));
    }, 0) / total)
    : 0;
  const progressLabel = failed > 0
    ? `${done} of ${total} complete, ${failed} failed`
    : running > 0
      ? `${done} of ${total} complete, ${running} running`
      : `${done} of ${total} complete`;
  elements.packageProgressSummary.textContent = progressLabel;
  renderHelperPackageProgress(aggregatePercent, total, progressLabel);
  renderAppInstallGrid();
}

function renderAppInstallGrid() {
  if (!elements.packageProgressGrid) {
    return;
  }
  const rows = appOptions.map(([appId, label]) => {
    const row = ensureAppInstallRow(appId, label);
    return { ...row };
  });
  const columns = [
    {
      key: 'app',
      label: 'App',
      width: '170px',
      sortable: false,
      renderCell({ row }) {
        return renderAppCell(row);
      }
    },
    {
      key: 'discovery',
      label: 'Discovery',
      width: '150px',
      sortable: false,
      renderCell({ row }) {
        return renderDiscoveryCell(row);
      }
    },
    {
      key: 'unpack',
      label: 'Unpack',
      width: '120px',
      sortable: false,
      renderCell({ value }) {
        return renderProgressPhaseCell(value);
      }
    },
    {
      key: 'copy',
      label: 'Copy',
      width: '120px',
      sortable: false,
      renderCell({ value }) {
        return renderProgressPhaseCell(value);
      }
    },
    {
      key: 'preflight',
      label: 'Preflight',
      width: '110px',
      sortable: false,
      renderCell({ value }) {
        return renderStatusPhaseCell(value);
      }
    },
    {
      key: 'install',
      label: 'Install',
      width: '100px',
      sortable: false,
      renderCell({ value }) {
        return renderStatusPhaseCell(value);
      }
    },
    {
      key: 'smoke',
      label: 'Smoke',
      width: '100px',
      sortable: false,
      renderCell({ value }) {
        return renderStatusPhaseCell(value);
      }
    },
    {
      key: 'dataPrep',
      label: 'Data Prep',
      width: '110px',
      sortable: false,
      renderCell({ value }) {
        return renderStatusPhaseCell(value);
      }
    }
  ];
  const options = {
    columns,
    rowKey: 'id',
    chrome: false,
    wrapCellContent: false,
    enableSearch: false,
    enableSort: false,
    enablePagination: false,
    className: 'kit-app-install-grid',
    emptyText: 'No app progress is available yet.'
  };
  if (state.helperFactories.grid) {
    if (!state.appInstallGridWidget) {
      elements.packageProgressGrid.innerHTML = '';
      state.appInstallGridWidget = state.helperFactories.grid(elements.packageProgressGrid, rows, options);
    } else {
      state.appInstallGridWidget.update(rows, options);
    }
    return;
  }
  renderAppInstallGridFallback(rows, columns);
}

function renderAppCell(row) {
  const host = document.createElement('div');
  const rowStatus = overallAppRowStatus(row);
  host.className = `app-install-app-cell ${rowStatus}`;
  host.title = row.id || '';
  const dot = document.createElement('span');
  dot.className = `app-install-dot ${rowStatus}`;
  dot.setAttribute('aria-hidden', 'true');
  const copy = document.createElement('div');
  const title = document.createElement('strong');
  title.textContent = row.label || row.id;
  const meta = document.createElement('span');
  meta.textContent = appDomainForId(row.id) || row.id || '';
  copy.append(title, meta);
  if (rowStatus === 'failed') {
    const status = document.createElement('em');
    status.className = 'app-install-app-status failed';
    status.textContent = 'Failed';
    copy.appendChild(status);
  }
  host.append(dot, copy);
  return host;
}

function appDomainForId(appId) {
  const app = discoveryAppForId(appId);
  if (app?.host) {
    return app.host;
  }

  const hostLabels = {
    'pbb-mapserver': 'mapserver',
    'pbb-maestro': 'maestro',
    'pbb-realtime': 'realtime',
    'pbb-relay': 'relay',
    'pbb-hotline': 'hotline'
  };
  const zone = cleanValue(elements.dnsZoneInput.value) || 'pbb.ph';
  const hostLabel = hostLabels[appId] || String(appId || '').replace(/^pbb-/, '');
  if (!hostLabel || !zone) {
    return '';
  }
  return `${hostLabel}.${zone}`;
}

function renderDiscoveryCell(row) {
  const app = discoveryAppForId(row.id);
  const status = discoveryStatus(app);
  const host = document.createElement('div');
  host.className = `app-install-discovery-cell ${status}`;
  const label = document.createElement('strong');
  label.textContent = discoveryLabel(app, row.id);
  const meta = document.createElement('span');
  meta.textContent = appDiscoverySummary(app, state.appScopes[row.id] || 'local');
  host.append(label, meta);
  return host;
}

function discoveryAppForId(appId) {
  const apps = Array.isArray(state.existingInstallReport?.apps) ? state.existingInstallReport.apps : [];
  return apps.find((app) => String(app.app_id || app.id || '') === appId) || null;
}

function discoveryStatus(app) {
  if (!app) {
    return 'pending';
  }
  if (app.manifest_exists) {
    return 'installed';
  }
  if (app.path_exists) {
    return 'path-found';
  }
  return 'fresh';
}

function discoveryLabel(app, appId) {
  const decision = state.appInstallDecisions?.[appId] || '';
  if (decision) {
    return decision;
  }
  const status = discoveryStatus(app);
  if (status === 'installed') {
    return 'installed';
  }
  if (status === 'path-found') {
    return 'folder found';
  }
  if (status === 'fresh') {
    return 'fresh';
  }
  return 'checking';
}

function renderProgressPhaseCell(phase = {}) {
  const value = Math.max(0, Math.min(100, Math.round(Number(phase.percent || 0))));
  const status = normalizeAppStatus(phase.status);
  const host = document.createElement('div');
  host.className = `app-install-progress-cell ${status}`;
  host.title = phase.message || '';
  const bar = document.createElement('span');
  bar.className = 'app-install-progress-bar';
  const fill = document.createElement('i');
  fill.style.width = `${['failed', 'blocked'].includes(status) ? Math.max(value, 8) : value}%`;
  bar.appendChild(fill);
  const text = document.createElement('b');
  text.textContent = status === 'failed' ? 'Failed' : status === 'blocked' ? 'Blocked' : `${value}%`;
  host.append(bar, text);
  return host;
}

function renderStatusPhaseCell(phase = {}) {
  const status = normalizeAppStatus(phase.status);
  const host = document.createElement('div');
  host.className = `app-install-action-cell ${status}`;
  host.title = phase.message || status;

  if (status === 'running') {
    const progressHost = document.createElement('div');
    progressHost.className = 'app-install-action-progress';
    host.appendChild(progressHost);
    if (state.helperFactories.progress) {
      state.helperFactories.progress(progressHost, {
        label: phase.message || 'Running',
        value: 0
      }, {
        style: 'indeterminate',
        indeterminate: true,
        size: 'sm',
        showLabel: false,
        showPercent: false,
        ariaLabel: phase.message || 'Running app step'
      });
    } else {
      const fallback = document.createElement('span');
      fallback.className = 'app-install-action-indeterminate-fallback';
      progressHost.appendChild(fallback);
    }
    return host;
  }

  if (status === 'success') {
    const icon = document.createElement('span');
    icon.className = 'app-install-action-icon success';
    icon.setAttribute('aria-label', 'Success');
    if (state.helperFactories.icon) {
      icon.appendChild(state.helperFactories.icon('status.success', {
        size: 16,
        ariaLabel: 'Success',
        className: 'app-install-helper-icon'
      }));
    } else {
      icon.textContent = 'OK';
    }
    host.appendChild(icon);
    return host;
  }

  const placeholder = document.createElement('span');
  placeholder.className = status === 'pending'
    ? 'app-install-action-placeholder'
    : `app-install-action-label ${status}`;
  placeholder.textContent = status === 'pending' ? '' : status;
  host.appendChild(placeholder);
  return host;
}

function renderAppInstallGridFallback(rows, columns) {
  elements.packageProgressGrid.innerHTML = '';
  const table = document.createElement('table');
  table.className = 'kit-app-install-grid-fallback';
  const thead = document.createElement('thead');
  const headRow = document.createElement('tr');
  columns.forEach((column) => {
    const th = document.createElement('th');
    th.textContent = column.label;
    headRow.appendChild(th);
  });
  thead.appendChild(headRow);
  table.appendChild(thead);
  const tbody = document.createElement('tbody');
  rows.forEach((row) => {
    const tr = document.createElement('tr');
    columns.forEach((column) => {
      const td = document.createElement('td');
      const rendered = column.renderCell({ row, value: row[column.key], key: column.key, column });
      if (rendered instanceof Node) {
        td.appendChild(rendered);
      } else {
        td.textContent = String(rendered || '');
      }
      tr.appendChild(td);
    });
    tbody.appendChild(tr);
  });
  table.appendChild(tbody);
  elements.packageProgressGrid.appendChild(table);
}

function overallAppRowStatus(row) {
  const statuses = [
    row.unpack?.status,
    row.copy?.status,
    row.preflight?.status,
    row.install?.status,
    row.smoke?.status,
    row.dataPrep?.status
  ].map(normalizeAppStatus);
  if (statuses.includes('failed')) {
    return 'failed';
  }
  if (statuses.includes('warning')) {
    return 'warning';
  }
  if (statuses.includes('running')) {
    return 'running';
  }
  if (statuses.includes('blocked')) {
    return 'blocked';
  }
  if (statuses.includes('pending')) {
    return 'pending';
  }
  return 'success';
}

function renderHelperPackageProgress(percent, total, label) {
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

  if (!state.helperFactories.progress) {
    elements.packageOverallProgress.innerHTML = `
      <div class="package-progress-fallback" role="progressbar" aria-label="${escapeHtml(label)}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${percent}">
        <span style="width: ${percent}%"></span>
      </div>
    `;
    return;
  }

  if (!state.packageProgressWidget) {
    elements.packageOverallProgress.innerHTML = '';
    state.packageProgressWidget = state.helperFactories.progress(elements.packageOverallProgress, data, options);
    return;
  }

  state.packageProgressWidget.update(data, options);
}

async function buildRuntimeConfigForAction(options = {}) {
  debugLog('build-config-for-action:start', {
    templatePath: state.templateConfigPath || elements.configPathInput.value
  });
  const result = await window.kitSetup.buildConfig({
    templatePath: state.templateConfigPath || elements.configPathInput.value,
    form: {
      ...collectSetupForm(),
      ...(options.formOverrides || {})
    }
  });
  elements.configPathInput.value = result.configPath;
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

  if (result.report) {
    state.runReports[result.action] = result.report;
  }

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
    renderAppRetry(result.report, result.action);
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
  state.appInstallRows = new Map();
  for (const item of report.packages) {
    const appId = item.app_id || '';
    const option = appOptions.find(([id]) => id === appId);
    state.packageProgress.set(appId, {
      app_id: appId,
      label: option ? option[1] : appId,
      step: item.extraction || item.source_type || 'complete',
      status: item.status || 'pending',
      message: packageReportMessage(item),
      percent: item.status === 'pending' ? 0 : 100,
      extract_percent: item.status === 'pending' ? 0 : 100,
      deploy_percent: item.status === 'pending' ? 0 : 100
    });
    const label = option ? option[1] : appId;
    mergePackageIntoAppInstall(
      appId,
      label,
      item.status === 'pending' ? 'pending' : 'complete',
      item.status || 'pending',
      packageReportMessage(item),
      item.status === 'pending' ? 0 : 100,
      item.status === 'pending' ? 0 : 100
    );
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
  renderSetupStepper(stages);
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
      renderSetupStepper(state.stages);
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

function renderSetupStepper(stages) {
  if (!elements.setupWorkflowStepper) {
    return;
  }
  const workflowSteps = [
    { id: 'inputs', title: 'Inputs', subtitle: 'Admin values' },
    { id: 'validate', title: 'Validate', subtitle: 'Readiness' },
    { id: 'plan', title: 'Plan', subtitle: 'Review' },
    { id: 'install', title: 'Install', subtitle: 'Automated run' },
    { id: 'finish', title: 'Finish', subtitle: 'Handoff' }
  ];
  const progress = state.automatedProgress;
  const selectedStep = Number((stages[state.selectedStageIndex] || {}).step || 1);
  const currentStepId = progress?.currentPhase || (selectedStep >= 12
    ? 'finish'
    : selectedStep >= 6
      ? 'install'
      : selectedStep >= 5
        ? 'plan'
        : selectedStep >= 1
          ? 'inputs'
          : 'validate');
  const currentIndex = workflowSteps.findIndex((step) => step.id === currentStepId);
  const materialized = workflowSteps.map((step, index) => {
    let status = '';
    if (progress) {
      if (progress.status === 'success') {
        status = 'complete';
      } else if (index < currentIndex) {
        status = 'complete';
      } else if (index === currentIndex) {
        status = normalizeStepperStatus(progress.status || 'current');
      } else {
        status = 'pending';
      }
    } else {
      status = step.id === currentStepId ? 'current' : 'pending';
    }
    return { ...step, status };
  });
  const stepperSignature = JSON.stringify({
    currentStepId,
    helperReady: Boolean(state.helperFactories.stepper),
    hasWidget: Boolean(state.setupStepperWidget),
    steps: materialized.map((step) => [step.id, step.status])
  });
  if (state.setupStepperSignature === stepperSignature) {
    return;
  }
  state.setupStepperSignature = stepperSignature;

  if (!state.helperFactories.stepper) {
    elements.setupWorkflowStepper.innerHTML = materialized.map((step, index) => `
      <span class="${escapeHtml(step.status || '')}">${index + 1}. ${escapeHtml(step.title)}</span>
    `).join('');
    return;
  }

  if (!state.setupStepperWidget) {
    state.setupStepperWidget = state.helperFactories.stepper(elements.setupWorkflowStepper, materialized, {
      currentStepId,
      clickable: true,
      className: 'kit-workflow-stepper',
      onStepClick(step) {
        jumpToWorkflowStep(step.id);
      }
    });
    state.setupStepperSignature = JSON.stringify({
      currentStepId,
      helperReady: true,
      hasWidget: true,
      steps: materialized.map((step) => [step.id, step.status])
    });
    return;
  }
  state.setupStepperWidget.update(materialized, {
    currentStepId,
    clickable: true,
    className: 'kit-workflow-stepper',
    onStepClick(step) {
      jumpToWorkflowStep(step.id);
    }
  });
}

function normalizeStepperStatus(status) {
  if (status === 'success' || status === 'complete' || status === 'completed') {
    return 'complete';
  }
  if (status === 'failed' || status === 'warning' || status === 'error') {
    return 'error';
  }
  if (status === 'current' || status === 'running') {
    return 'current';
  }
  return 'pending';
}

function jumpToWorkflowStep(stepId) {
  const stageByWorkflow = {
    inputs: 1,
    validate: 1,
    plan: 5,
    install: 8,
    finish: 12
  };
  const targetStep = stageByWorkflow[stepId] || 1;
  const index = state.stages.findIndex((stage) => Number(stage.step) === targetStep);
  if (index >= 0) {
    state.selectedStageIndex = index;
    renderStageDetail(state.stages[index]);
    showStagePanel(targetStep);
    renderActiveStageValidation();
    markSelectedStage();
  }
}

function renderSummary(report) {
  elements.overallStatus.textContent = report.status || 'unknown';
  renderStageCounts(report.stages || []);
  renderCheckpoints(report.checkpoints || null, '');
}

function renderAppRetry(report, action = '') {
  const phase = appPhaseForAction(action);
  if (!phase || !report || !Array.isArray(report.apps) || report.apps.length === 0) {
    return;
  }

  for (const app of report.apps) {
    const appId = reportAppId(app);
    if (!appId) {
      continue;
    }
    const status = normalizeAppStatus(app.status || app.check_status || 'pending');
    const summary = appFailureSummary(app) || app.message || status;
    mergeAppActionPhase(appId, phase, {
      label: app.name || appId,
      status,
      message: summary,
      forceStatus: true
    });
  }
  renderAppInstallGrid();
  renderDataPrepFromActionReport(report, action);
}

async function runAutomatedDataPrep() {
  if (state.busy) {
    return;
  }
  if (!state.installStateGate || state.installStateGate.allowed !== true) {
    await initializeDataPrepStartup();
    if (!state.installStateGate || state.installStateGate.allowed !== true) {
      return;
    }
  }
  elements.startDataPrepButton.hidden = true;
  initializeDataPrepRows(state.installStateGate.apps || state.installStateGate.state?.apps || []);
  elements.dataPrepPanel.hidden = false;
  elements.dataPrepSummary.textContent = 'Checking installed apps';
  await refreshExistingInstallDiscovery();
  updateDataPrepDiscoveryFromExistingInstalls();
  renderDataPrepGrid();

  const phases = dataPrepOperationalPhases();
  const localAppIds = dataPrepRunnableAppIds();
  const totalSteps = (localAppIds.length * (phases.length + 1)) + 1;
  let completedSteps = 0;
  resetAutomatedProgress();
  state.automatedProgress.sequence = Array.from({ length: Math.max(1, totalSteps) }, (_, index) => `data-prep-${index + 1}`);
  updateAutomatedProgress('dataPrep', 0, 'current', 'Checking Data Prep readiness');
  startAutomatedElapsedTimer();

  for (const appId of localAppIds) {
    updateAutomatedProgress('dataPrep', completedSteps, 'current', `Checking ${labelForAppId(appId)} readiness`);
    const readiness = await runDataPrepReadiness(appId);
    completedSteps += 1;
    updateAutomatedProgress('dataPrep', completedSteps, dataPrepAppPhaseFailed(appId, 'readiness') ? 'warning' : 'current', `${labelForAppId(appId)} readiness checked`);
    if (!readiness || dataPrepAppPhaseFailed(appId, 'readiness')) {
      blockDataPrepAppPhasesAfter(appId, 'readiness', 'Blocked because readiness failed.');
      continue;
    }

    for (const phase of phases) {
      updateAutomatedProgress('dataPrep', completedSteps, 'current', `${phase.label}: ${labelForAppId(appId)}`);
      const result = await runDataPrepPhase(appId, phase);
      completedSteps += 1;
      updateAutomatedProgress('dataPrep', completedSteps, dataPrepAppPhaseFailed(appId, phase.key) ? 'warning' : 'current', `${phase.label}: ${labelForAppId(appId)}`);
      if (!result || dataPrepAppPhaseFailed(appId, phase.key)) {
        blockDataPrepAppPhasesAfter(appId, phase.key, `Blocked because ${phase.label} failed.`);
        break;
      }
    }
  }

  if (!dataPrepHasAnyFailure()) {
    markDataPrepAppPhaseRunning('pbb-maestro', 'verify', 'Waiting for fresh Relay/Realtime heartbeats');
    updateAutomatedProgress('dataPrep', completedSteps, 'current', 'Verifying Maestro heartbeats');
    await runDataPrepPostApplyVerify();
    completedSteps += 1;
    updateAutomatedProgress('dataPrep', completedSteps, dataPrepAppPhaseFailed('pbb-maestro', 'verify') ? 'warning' : 'current', 'Maestro heartbeat verification checked');
  } else {
    completedSteps += 1;
  }

  const hasIssues = dataPrepHasAnyIssue();
  elements.dataPrepSummary.textContent = hasIssues
    ? 'Population completed with app-level issues'
    : 'Population completed';
  updateAutomatedProgress('dataPrep', totalSteps, hasIssues ? 'warning' : 'success', elements.dataPrepSummary.textContent);
  stopAutomatedElapsedTimer();
  await showDataPrepCompletionFeedback(hasIssues);
}

function dataPrepOperationalPhases() {
  return [
    { key: 'prepareData', step: 'prepare_data', label: 'Prepare Data', running: 'Preparing data' },
    { key: 'applySettings', step: 'apply_settings', label: 'Apply Settings', running: 'Applying settings' },
    { key: 'verify', step: 'verify', label: 'Verify', running: 'Verifying Data Prep output' }
  ];
}

async function runDataPrepReadiness(appId) {
  markDataPrepAppPhaseRunning(appId, 'readiness', 'Checking app URL readiness');
  return runAction('smoke-check', {
    confirmed: true,
    appId,
    formOverrides: {
      dataPrepReadiness: true
    }
  });
}

function dataPrepRunnableAppIds() {
  return Array.from(state.dataPrepRows.keys()).filter((appId) => state.appScopes[appId] !== 'remote');
}

async function runDataPrepPhase(appId, phase) {
  markDataPrepAppPhaseRunning(appId, phase.key, phase.running);
  return runAction('populate', {
    confirmed: true,
    appId,
    formOverrides: {
      dataPrepApply: true,
      dataPrepStep: phase.step
    }
  });
}

async function runDataPrepPostApplyVerify() {
  return runAction('populate', {
    confirmed: true,
    formOverrides: {
      dataPrepApply: true,
      dataPrepStep: 'post_apply_verify'
    }
  });
}

function dataPrepAppPhaseFailed(appId, phase) {
  const row = ensureDataPrepRow(appId);
  const status = normalizeDataPrepStatus(row[phase]?.status, row[phase]?.message);
  return status === 'failed' || status === 'blocked';
}

function dataPrepHasAnyFailure() {
  for (const row of state.dataPrepRows.values()) {
    const phases = [row.discovery, row.readiness, row.prepareData, row.applySettings, row.verify];
    if (phases.some((phase) => ['failed', 'blocked'].includes(normalizeDataPrepStatus(phase?.status, phase?.message)))) {
      return true;
    }
  }
  return false;
}

function dataPrepHasAnyIssue() {
  return dataPrepIssueItems().length > 0;
}

function dataPrepIssueItems() {
  const phaseLabels = [
    ['discovery', 'Discovery'],
    ['readiness', 'Readiness'],
    ['prepareData', 'Prepare Data'],
    ['applySettings', 'Apply Settings'],
    ['verify', 'Verify']
  ];
  const issues = [];
  for (const row of state.dataPrepRows.values()) {
    for (const [key, label] of phaseLabels) {
      const phase = row[key];
      const status = normalizeDataPrepStatus(phase?.status, phase?.message);
      if (!['warning', 'failed', 'blocked'].includes(status)) {
        continue;
      }
      issues.push({
        app: row.label || row.id,
        phase: label,
        status,
        message: phase?.message || dataPrepStatusLabel(status)
      });
    }
  }
  return issues;
}

async function showDataPrepCompletionFeedback(hasIssues) {
  if (!hasIssues) {
    if (state.helperFactories.alert) {
      await state.helperFactories.alert('Data Prep completed successfully.', {
        title: 'Data Prep Complete',
        description: `Session ${getSessionRunId()} finished with no app-level issues.`,
        variant: 'success',
        okText: 'Close Data Prep',
        renderTarget: 'local',
        size: 'sm'
      });
    } else {
      window.alert('Data Prep completed successfully.');
    }
    if (window.kitSetup.quitInstaller) {
      await window.kitSetup.quitInstaller();
    } else {
      window.close();
    }
    return;
  }

  await showDataPrepIssuesModal(dataPrepIssueItems());
}

function showDataPrepIssuesModal(issues) {
  if (!state.helperFactories.actionModal) {
    const lines = issues.map((item) => `${item.app} / ${item.phase}: ${item.message}`);
    window.alert(`Data Prep finished with app-level issues.\n\n${lines.join('\n')}`);
    return Promise.resolve();
  }

  return new Promise((resolve) => {
    let modal = null;
    const close = () => {
      modal?.destroy?.();
      resolve();
    };
    const body = document.createElement('div');
    body.className = 'data-prep-issues-modal';
    const summary = document.createElement('p');
    summary.textContent = `${issues.length} issue${issues.length === 1 ? '' : 's'} need review before considering this node fully prepared.`;
    body.appendChild(summary);

    const list = document.createElement('ul');
    list.className = 'data-prep-issues-list';
    for (const item of issues.slice(0, 10)) {
      const issue = document.createElement('li');
      issue.innerHTML = `
        <strong>${escapeHtml(item.app)} / ${escapeHtml(item.phase)}</strong>
        <span>${escapeHtml(item.message)}</span>
      `;
      list.appendChild(issue);
    }
    body.appendChild(list);

    const next = document.createElement('p');
    next.className = 'data-prep-next-steps';
    next.textContent = 'Next: review the run reports, repair the affected app or setup prerequisite, then rerun Data Prep for the affected app or full sequence.';
    body.appendChild(next);

    modal = state.helperFactories.actionModal({
      title: 'Data Prep Needs Attention',
      content: body,
      body,
      size: 'md',
      renderTarget: 'local',
      onClose: close,
      actions: [
        { id: 'close', label: 'Close', variant: 'primary', autoFocus: true, onClick: close }
      ]
    });
    if (!modal.open()) {
      close();
    }
  });
}

function initializeDataPrepRows(installedApps = null) {
  state.dataPrepGridWidget?.destroy?.();
  state.dataPrepGridWidget = null;
  state.dataPrepRows = new Map();
  const rows = Array.isArray(installedApps) && installedApps.length > 0
    ? installedApps.map((app) => {
      const appId = app.app_id || app.id;
      return [appId, app.display_name || app.name || labelForAppId(appId), app];
    }).filter(([appId]) => Boolean(appId))
    : appOptions.map(([appId, label]) => [appId, label, null]);
  for (const [appId, label, app] of rows) {
    state.dataPrepRows.set(appId, {
      id: appId,
      label,
      discovery: { status: 'pending', message: app?.base_url || app?.install_path || 'Waiting for discovery.' },
      readiness: { status: 'pending', message: 'Waiting for readiness check.' },
      prepareData: { status: 'pending', message: 'Waiting for Prepare Data.' },
      applySettings: { status: 'pending', message: 'Waiting for Apply Settings.' },
      verify: { status: 'pending', message: 'Waiting for Verify.' }
    });
  }
}

function ensureDataPrepRow(appId) {
  if (!state.dataPrepRows.has(appId)) {
    const option = appOptions.find(([id]) => id === appId);
    state.dataPrepRows.set(appId, {
      id: appId,
      label: option ? option[1] : appId,
      discovery: { status: 'pending', message: 'Waiting for discovery.' },
      readiness: { status: 'pending', message: 'Waiting for readiness check.' },
      prepareData: { status: 'pending', message: 'Waiting for Prepare Data.' },
      applySettings: { status: 'pending', message: 'Waiting for Apply Settings.' },
      verify: { status: 'pending', message: 'Waiting for Verify.' }
    });
  }
  return state.dataPrepRows.get(appId);
}

function updateDataPrepDiscoveryFromExistingInstalls() {
  for (const [appId] of appOptions) {
    const row = ensureDataPrepRow(appId);
    const app = discoveryAppForId(appId);
    const status = discoveryStatus(app);
    row.discovery = {
      status: status === 'installed' || status === 'path-found' ? 'success' : 'warning',
      message: appDiscoverySummary(app, state.appScopes[appId] || 'local') || discoveryLabel(app, appId)
    };
  }
  renderDataPrepGrid();
}

function labelForAppId(appId) {
  const found = appOptions.find(([id]) => id === appId);
  return found ? found[1] : String(appId || '').replace(/^pbb-/, '');
}

function markDataPrepPhaseRunning(phase, message) {
  for (const [appId] of appOptions) {
    if (state.appScopes[appId] === 'remote') {
      continue;
    }
    markDataPrepAppPhaseRunning(appId, phase, message, false);
  }
  renderDataPrepGrid();
}

function markDataPrepAppPhaseRunning(appId, phase, message, render = true) {
  if (!appId || state.appScopes[appId] === 'remote') {
    return;
  }
  const row = ensureDataPrepRow(appId);
  row[phase] = { status: 'running', message };
  if (render) {
    renderDataPrepGrid();
  }
}

function blockDataPrepPhasesAfter(phase, message) {
  const phases = ['discovery', 'readiness', 'prepareData', 'applySettings', 'verify'];
  const start = phases.indexOf(phase);
  if (start < 0) {
    return;
  }
  const blockedPhases = phases.slice(start + 1);
  for (const [appId] of appOptions) {
    if (state.appScopes[appId] === 'remote') {
      continue;
    }
    blockDataPrepAppPhases(appId, blockedPhases, message);
  }
  renderDataPrepGrid();
}

function blockDataPrepAppPhasesAfter(appId, phase, message) {
  const phases = ['discovery', 'readiness', 'prepareData', 'applySettings', 'verify'];
  const start = phases.indexOf(phase);
  if (start < 0) {
    return;
  }
  blockDataPrepAppPhases(appId, phases.slice(start + 1), message);
  renderDataPrepGrid();
}

function blockDataPrepAppPhases(appId, phases, message) {
  const row = ensureDataPrepRow(appId);
  for (const blockedPhase of phases) {
    const current = normalizeDataPrepStatus(row[blockedPhase]?.status || 'pending', row[blockedPhase]?.message || '');
    if (current === 'pending' || current === 'running') {
      row[blockedPhase] = { status: 'blocked', message };
    }
  }
}

function renderDataPrepFromActionReport(report, action) {
  if (!elements.dataPrepPanel || elements.dataPrepPanel.hidden || !report || !Array.isArray(report.apps)) {
    return;
  }
  if (action === 'smoke-check') {
    for (const app of report.apps) {
      const appId = reportAppId(app);
      if (!appId) {
        continue;
      }
      const row = ensureDataPrepRow(appId);
      row.readiness = {
        status: normalizeAppStatus(app.status || app.check_status || 'pending'),
        message: appFailureSummary(app) || app.message || app.status || 'Readiness checked.'
      };
    }
    renderDataPrepGrid();
    return;
  }
  if (action !== 'populate') {
    return;
  }
  for (const app of report.apps) {
    const appId = reportAppId(app);
    if (!appId) {
      continue;
    }
    const row = ensureDataPrepRow(appId);
    const steps = app.data_prep && Array.isArray(app.data_prep.steps)
      ? app.data_prep.steps
      : Array.isArray(app.population_tools) ? app.population_tools : [];
    for (const step of steps) {
      const key = dataPrepPhaseForStep(step.step || step.name);
      if (!key) {
        continue;
      }
      row[key] = {
        status: normalizeDataPrepStatus(step.status || step.report_status || 'pending', step.message || ''),
        message: step.message || step.report_status || step.status || ''
      };
    }
  }
  renderDataPrepGrid();
}

function dataPrepPhaseForStep(step) {
  const normalized = String(step || '').replace(/-/g, '_');
  return {
    prepare_data: 'prepareData',
    populate_initial_data: 'prepareData',
    apply_settings: 'applySettings',
    verify: 'verify'
  }[normalized] || '';
}

function renderDataPrepGrid() {
  if (!elements.dataPrepGrid) {
    return;
  }
  const rows = Array.from(state.dataPrepRows.values());
  const columns = [
    {
      key: 'app',
      label: 'App',
      width: '190px',
      sortable: false,
      renderCell({ row }) {
        return renderDataPrepAppCell(row);
      }
    },
    {
      key: 'discovery',
      label: 'Discovery',
      width: '150px',
      sortable: false,
      renderCell({ value }) {
        return renderDataPrepStatusCell(value);
      }
    },
    {
      key: 'readiness',
      label: 'Readiness',
      width: '150px',
      sortable: false,
      renderCell({ value }) {
        return renderDataPrepStatusCell(value);
      }
    },
    {
      key: 'prepareData',
      label: 'Prepare Data',
      width: '150px',
      sortable: false,
      renderCell({ value }) {
        return renderDataPrepStatusCell(value);
      }
    },
    {
      key: 'applySettings',
      label: 'Apply Settings',
      width: '150px',
      sortable: false,
      renderCell({ value }) {
        return renderDataPrepStatusCell(value);
      }
    },
    {
      key: 'verify',
      label: 'Verify',
      width: '130px',
      sortable: false,
      renderCell({ value }) {
        return renderDataPrepStatusCell(value);
      }
    }
  ];
  const options = {
    columns,
    rowKey: 'id',
    chrome: false,
    wrapCellContent: false,
    enableSearch: false,
    enableSort: false,
    enablePagination: false,
    className: 'kit-data-prep-grid',
    emptyText: 'No Data Prep apps are available.'
  };
  if (state.helperFactories.grid) {
    if (!state.dataPrepGridWidget) {
      elements.dataPrepGrid.innerHTML = '';
      state.dataPrepGridWidget = state.helperFactories.grid(elements.dataPrepGrid, rows, options);
    } else {
      state.dataPrepGridWidget.update(rows, options);
    }
    return;
  }
  renderDataPrepGridFallback(rows, columns);
}

function renderDataPrepAppCell(row) {
  const host = document.createElement('div');
  const rowStatus = overallDataPrepRowStatus(row);
  host.className = `app-install-app-cell ${rowStatus}`;
  host.title = row.id || '';
  const dot = document.createElement('span');
  dot.className = `app-install-dot ${rowStatus}`;
  dot.setAttribute('aria-hidden', 'true');
  const copy = document.createElement('div');
  const title = document.createElement('strong');
  title.textContent = row.label || row.id;
  const meta = document.createElement('span');
  meta.textContent = appDomainForId(row.id) || row.id || '';
  copy.append(title, meta);
  host.append(dot, copy);
  return host;
}

function overallDataPrepRowStatus(row) {
  const statuses = [
    row.discovery,
    row.readiness,
    row.prepareData,
    row.applySettings,
    row.verify
  ].map((phase) => normalizeDataPrepStatus(phase?.status, phase?.message));
  if (statuses.includes('failed')) {
    return 'failed';
  }
  if (statuses.includes('running')) {
    return 'running';
  }
  if (statuses.includes('blocked')) {
    return 'blocked';
  }
  if (statuses.includes('warning')) {
    return 'warning';
  }
  const required = statuses.filter((status) => status !== 'skipped');
  if (required.length > 0 && required.every((status) => status === 'success')) {
    return 'success';
  }
  return 'pending';
}

function renderDataPrepStatusCell(value = {}) {
  const status = normalizeDataPrepStatus(value.status || 'pending', value.message || '');
  const host = document.createElement('div');
  host.className = `data-prep-status-cell ${status}`;
  host.title = value.message || status;

  if (status === 'pending' || status === 'running') {
    renderDataPrepProgressState(host, status, value.message || dataPrepStatusLabel(status));
    return host;
  }

  host.appendChild(renderDataPrepIconState(status));
  return host;
}

function normalizeDataPrepStatus(status, message = '') {
  const normalized = normalizeAppStatus(status);
  if (normalized === 'running' && /\bwaiting\b/i.test(String(message || ''))) {
    return 'pending';
  }
  return normalized;
}

function renderDataPrepProgressState(host, status, label) {
  const progressHost = document.createElement('div');
  progressHost.className = `data-prep-action-progress ${status}`;
  host.appendChild(progressHost);

  if (state.helperFactories.progress) {
    state.helperFactories.progress(progressHost, {
      label,
      value: 0
    }, {
      style: status === 'running' ? 'indeterminate' : 'bar',
      indeterminate: status === 'running',
      size: 'sm',
      showLabel: false,
      showPercent: false,
      ariaLabel: label
    });
    return;
  }

  const fallback = document.createElement('span');
  fallback.className = status === 'running'
    ? 'data-prep-indeterminate-fallback'
    : 'data-prep-progress-placeholder';
  progressHost.appendChild(fallback);
}

function renderDataPrepIconState(status) {
  const icon = document.createElement('span');
  icon.className = `data-prep-state-icon ${status}`;
  icon.setAttribute('aria-label', dataPrepStatusLabel(status));

  const iconName = {
    success: 'status.success',
    skipped: 'status.success',
    warning: 'status.warning',
    blocked: 'status.warning',
    failed: 'status.error'
  }[status] || 'status.warning';

  if (state.helperFactories.icon) {
    icon.appendChild(state.helperFactories.icon(iconName, {
      size: 16,
      ariaLabel: dataPrepStatusLabel(status),
      className: 'data-prep-helper-icon'
    }));
  } else {
    icon.textContent = {
      success: 'OK',
      skipped: 'OK',
      warning: '!',
      blocked: '!',
      failed: 'X'
    }[status] || '!';
  }

  return icon;
}

function dataPrepStatusLabel(status) {
  return {
    success: 'Done',
    warning: 'Check',
    failed: 'Failed',
    running: 'Running',
    blocked: 'Blocked',
    skipped: 'Skipped',
    pending: 'Pending'
  }[status] || status;
}

function renderDataPrepGridFallback(rows, columns) {
  elements.dataPrepGrid.innerHTML = '';
  const table = document.createElement('table');
  table.className = 'kit-data-prep-grid-fallback';
  const thead = document.createElement('thead');
  const headRow = document.createElement('tr');
  for (const column of columns) {
    const th = document.createElement('th');
    th.textContent = column.label;
    headRow.appendChild(th);
  }
  thead.appendChild(headRow);
  table.appendChild(thead);
  const tbody = document.createElement('tbody');
  for (const row of rows) {
    const tr = document.createElement('tr');
    for (const column of columns) {
      const td = document.createElement('td');
      const rendered = column.renderCell({ row, value: row[column.key], key: column.key, column });
      if (rendered instanceof Node) {
        td.appendChild(rendered);
      } else {
        td.textContent = String(rendered || '');
      }
      tr.appendChild(td);
    }
    tbody.appendChild(tr);
  }
  table.appendChild(tbody);
  elements.dataPrepGrid.appendChild(table);
}

function markAppActionPhaseRunning(action) {
  const phase = appPhaseForAction(action);
  if (!phase) {
    return;
  }
  appOptions.forEach(([appId, label]) => {
    if (state.appScopes[appId] === 'remote') {
      return;
    }
    mergeAppActionPhase(appId, phase, {
      label,
      status: 'running',
      message: `Running ${action}`
    });
  });
  renderAppInstallGrid();
}

function appPhaseForAction(action) {
  const phases = {
    preflight: 'preflight',
    install: 'install',
    populate: 'dataPrep',
    'smoke-check': 'smoke'
  };
  return phases[action] || '';
}

function reportAppId(app) {
  const appId = String(app.app_id || '').trim();
  if (appOptions.some(([id]) => id === appId)) {
    return appId;
  }
  const id = String(app.id || '').trim();
  if (appOptions.some(([optionId]) => optionId === id)) {
    return id;
  }
  return appId || id;
}

function appFailureSummary(app) {
  const reportSummary = app.app_report_summary || {};
  const errors = Array.isArray(reportSummary.errors) ? reportSummary.errors.filter(Boolean) : [];
  if (errors.length > 0) {
    return errors.slice(0, 2).join(' ');
  }
  if (reportSummary.summary && app.status === 'failed') {
    return reportSummary.summary;
  }
  if (app.stderr) {
    return String(app.stderr).split(/\r?\n/).filter(Boolean).slice(0, 2).join(' ');
  }
  return '';
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
  const orderedActions = ['detect', 'hub-resolve', 'stage-report', 'plan', 'prepare-packages', 'preflight', 'install', 'dns-plan', 'dns-apply', 'dns-client-apply', 'dns-verify', 'firewall-apply', 'ssl-plan', 'ssl-apply', 'service-plan', 'service-start', 'service-stop', 'service-verify', 'remote-check', 'smoke-check', 'populate', 'finish-report'];
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
  if (elements.stageTitle) {
    elements.stageTitle.textContent = stage.name;
  }
  elements.detailStep.textContent = `Step ${stage.step}`;
  elements.detailName.textContent = stage.name;
  elements.detailStatus.textContent = stage.status || 'pending';
  elements.detailStatus.className = `status-pill ${stage.status || 'pending'}`;
  elements.detailMessage.textContent = stage.message || '';
  elements.detailJson.textContent = JSON.stringify(stage.details || {}, null, 2);
}

function showStagePanel(step) {
  void step;
}

function renderActiveStageValidation() {
  const stage = state.stages[state.selectedStageIndex] || { step: 1 };
  const step = Number(stage.step || 1);
  if (step === 1) {
    for (const adminStep of adminInputSteps) {
      renderStageValidation(adminStep);
    }
    return;
  }
  renderStageValidation(step);
}

function renderStageValidation(step) {
  void step;
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
    'firewall-apply': [10],
    'ssl-plan': [10],
    'ssl-apply': [10],
    'remote-check': [3, 11],
    preflight: [1, 3, 4, 5],
    install: [1, 3, 4, 5],
    populate: [3, 5],
    'service-plan': [3],
    'service-start': [3],
    'service-stop': [3],
    'service-verify': [3],
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
  const fail = (message, field = '', help = '') => {
    status = 'failed';
    issues.push(validationIssue(message, field, help));
  };
  const warn = (message, field = '', help = '') => {
    if (status !== 'failed') {
      status = 'warning';
    }
    issues.push(validationIssue(message, field, help));
  };

  if (step === 1) {
    if (state.prerequisiteGate?.status !== 'success') {
      fail(
        'Startup requirements must pass before Admin Inputs are available.',
        '',
        'Start WAMPServer on this machine and make sure dns.pbb.ph reaches Technitium, then click Check Again.'
      );
    }
    if (cleanValue(elements.phpPathInput.value) === '') {
      fail('Select the PHP runtime path.', 'phpPath');
    }
    if (cleanValue(elements.configPathInput.value) === '') {
      fail('The bundled installer config template was not found. Reinstall or repair Project Bantay Bayan Setup.', 'phpPath');
    }
    if (cleanValue(elements.apachePathInput.value) === '') {
      warn('Select Apache httpd.exe to avoid Apache detection warnings.', 'apachePath');
    }
    if (cleanValue(elements.mysqlPathInput.value) === '') {
      warn('Select MySQL/MariaDB mysql.exe to avoid database tool detection warnings.', 'mysqlPath');
    }
  }
  if (step === 2) {
    if (Number(elements.hubIdInput.value) <= 0) {
      fail('Enter the Hub ID from hub.pbb.ph.', 'hubId');
    }
    if (cleanValue(elements.hubTokenInput.value) === '') {
      warn('Enter the Hub token before resolving Hub pairing.', 'hubToken');
    }
  }
  if (step === 3) {
    const scopes = collectAppScopes();
    const localCount = Object.values(scopes).filter((scope) => scope === 'local').length;
    if (localCount === 0) {
      fail('Select at least one app to install locally on this machine.', 'scope:pbb-mapserver');
    }
  }
  if (step === 4) {
    if (cleanValue(elements.basePathInput.value) === '') {
      fail('Choose the base folder where local app folders will be created.', 'basePath');
    }
    if (state.enforceDiskSpaceRequirement && state.diskSpaceReport?.status === 'failed') {
      fail(
        diskSpaceFailureMessage(state.diskSpaceReport),
        'basePath',
        'Free disk space on the install and staging drives, or select a different install base path.'
      );
    }
  }
  if (step === 5) {
    if (!looksLikeIpOrHost(elements.databaseHostInput.value)) {
      fail('Enter the MySQL/MariaDB host, for example 127.0.0.1.', 'databaseHost');
    }
    const databasePort = Number(elements.databasePortInput.value);
    if (!Number.isInteger(databasePort) || databasePort <= 0 || databasePort > 65535) {
      fail('Enter a valid MySQL/MariaDB port.', 'databasePort');
    }
    if (cleanValue(elements.databaseUsernameInput.value) === '') {
      fail('Enter the MySQL/MariaDB username.', 'databaseUsername');
    }
    if (cleanValue(elements.adminEmailInput.value) === '' || !cleanValue(elements.adminEmailInput.value).includes('@')) {
      fail('Enter the first administrator email address.', 'adminEmail');
    }
    if (cleanValue(elements.adminNameInput.value) === '') {
      fail('Enter the first administrator display name.', 'adminName');
    }
    const password = elements.adminPasswordInput.value;
    if (password.length === 0) {
      warn('Enter the first administrator password before preflight, install, or data preparation.', 'adminPassword');
    } else if (!isStrongAdminPassword(password)) {
      fail('Use a first administrator password with at least 12 characters, including uppercase, lowercase, and a number.', 'adminPassword');
    }
  }
  if (step === 9) {
    if (!looksLikeIpOrHost(elements.machineIpInput.value)) {
      fail('Enter this machine IP address or resolvable hostname.', 'machineIp');
    }
    if (cleanValue(elements.technitiumBaseUrlInput.value) === '') {
      fail('Enter the Technitium base URL.', 'technitiumBaseUrl');
    }
    if (state.enforceTechnitiumRequirement && hasLocalAppsSelected() && state.technitiumDiscovery?.status !== 'success') {
      fail(
        'Technitium DNS is required for local installation and could not be detected.',
        'technitiumBaseUrl',
        'Start Technitium on this network or enter its URL manually, for example http://192.168.254.192:5380.'
      );
    }
    if (cleanValue(elements.dnsZoneInput.value) === '') {
      fail('Enter the DNS zone, for example pbb.ph.', 'dnsZone');
    }
    if (elements.applyDnsInput.checked && cleanValue(elements.technitiumTokenInput.value) === '') {
      fail('Enter the Technitium token before enabling DNS apply.', 'technitiumToken');
    }
    if (elements.applyDnsClientInput.checked) {
      const targetDns = dnsClientTargetNameserver();
      if (!looksLikeIpv4(targetDns)) {
        fail(
          'Enter the Windows DNS server IPv4 address, or use a Technitium URL with an IPv4 host.',
          'dnsClientNameserver',
          'Fill Windows DNS Server with an IPv4 address such as 192.168.254.192, or use a Technitium URL that the startup gate resolved to an IPv4 address.'
        );
      }
    }
  }
  if (step === 10) {
    if (cleanValue(elements.certRootInput.value) === '' && cleanValue(elements.pemUploadPathInput.value) === '') {
      fail('Select an existing certificate folder or a PEM bundle.', 'certRoot');
    }
    if ((elements.applyWebServerInput.checked || elements.writeExtractedFilesInput.checked) && cleanValue(elements.apacheIncludeOutputInput.value) === '') {
      fail('Enter the Apache include output path before applying web-server changes.', 'apacheIncludeOutput');
    }
    if (elements.writeExtractedFilesInput.checked && cleanValue(elements.pemUploadPathInput.value) === '') {
      fail('Select a PEM bundle before enabling certificate extraction.', 'pemUploadPath');
    }
  }
  return { step, status, issues };
}

function hasLocalAppsSelected() {
  return Object.values(collectAppScopes()).some((scope) => scope === 'local');
}

function validationIssue(message, field = '', help = '') {
  return { message, field, help };
}

function diskSpaceFailureMessage(report) {
  const errors = Array.isArray(report?.errors) ? report.errors.filter(Boolean) : [];
  if (errors.length > 0) {
    return errors.slice(0, 2).join(' ');
  }
  return 'There is not enough free disk space for package staging and installation.';
}

function formatBytes(bytes) {
  const value = Number(bytes || 0);
  if (!Number.isFinite(value) || value <= 0) {
    return 'unknown';
  }
  if (value >= 1024 * 1024 * 1024) {
    return `${(value / 1024 / 1024 / 1024).toFixed(1)} GB`;
  }
  if (value >= 1024 * 1024) {
    return `${Math.ceil(value / 1024 / 1024)} MB`;
  }
  return `${Math.ceil(value / 1024)} KB`;
}

function firstValidationIssue(result) {
  return result?.issues?.[0] || validationIssue('Review administrator inputs.');
}

function issueText(issue) {
  if (typeof issue === 'string') {
    return issue;
  }
  return String(issue?.message || 'Review administrator inputs.');
}

function validationFieldLabel(field) {
  if (!field) {
    return '';
  }
  const row = elements.adminPropertyEditor?.querySelector?.(`[data-property-id="${cssEscape(field)}"]`);
  const label = row?.querySelector?.('.ui-property-editor-label, .ui-label, label');
  const text = cleanValue(label?.textContent || '');
  if (text) {
    return text;
  }
  const labels = {
    phpPath: 'PHP',
    apachePath: 'Apache httpd.exe',
    mysqlPath: 'MySQL/MariaDB mysql.exe',
    hubId: 'Hub ID',
    hubToken: 'Hub Token',
    basePath: 'Install Base',
    databaseHost: 'Database Host',
    databasePort: 'Database Port',
    databaseUsername: 'Database Username',
    adminEmail: 'Admin Email',
    adminName: 'Admin Name',
    adminPassword: 'Admin Password',
    machineIp: 'Machine IP',
    technitiumBaseUrl: 'Technitium URL',
    dnsZone: 'Zone',
    technitiumToken: 'Technitium Token',
    dnsClientNameserver: 'Windows DNS Server',
    certRoot: 'Certificate Folder',
    apacheIncludeOutput: 'Apache Include',
    pemUploadPath: 'PEM Bundle'
  };
  if (field.startsWith('scope:')) {
    return appOptions.find(([id]) => id === field.slice('scope:'.length))?.[1] || 'App selection';
  }
  return labels[field] || field;
}

function isStrongAdminPassword(password) {
  return password.length >= 12
    && /[A-Z]/.test(password)
    && /[a-z]/.test(password)
    && /[0-9]/.test(password);
}

function focusFirstInvalidStage(result, issue = null) {
  const index = state.stages.findIndex((stage) => Number(stage.step) === Number(result.step));
  if (index >= 0) {
    state.selectedStageIndex = index;
    const stage = state.stages[index];
    renderStageDetail(stage);
    showStagePanel(stage.step);
    renderActiveStageValidation();
    renderSetupStepper(state.stages);
    markSelectedStage();
  }
  focusInvalidAdminField(issue?.field || '');
}

function focusInvalidAdminField(field) {
  if (!field || !elements.adminPropertyEditor) {
    return;
  }
  if (['dnsClientNameserver', 'dnsClientInterface'].includes(field) && !state.showAdvancedDnsClient) {
    state.showAdvancedDnsClient = true;
    renderAdminPropertyEditor();
  }
  const row = elements.adminPropertyEditor.querySelector(`[data-property-id="${cssEscape(field)}"]`);
  if (!row) {
    return;
  }
  row.classList.add('kit-validation-focus');
  row.scrollIntoView({ behavior: 'smooth', block: 'center' });
  const focusTarget = row.querySelector('input:not([type="hidden"]), textarea, select, button, [tabindex]:not([tabindex="-1"])');
  window.setTimeout(() => {
    focusTarget?.focus?.({ preventScroll: true });
  }, 120);
  window.setTimeout(() => {
    row.classList.remove('kit-validation-focus');
  }, 4200);
}

function cssEscape(value) {
  if (window.CSS && typeof window.CSS.escape === 'function') {
    return window.CSS.escape(String(value));
  }
  return String(value).replace(/["\\]/g, '\\$&');
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

function dnsClientTargetNameserver() {
  const explicit = cleanValue(elements.dnsClientNameserverInput.value);
  if (explicit !== '') {
    return explicit;
  }
  const discovered = firstTechnitiumIpv4Candidate();
  if (discovered) {
    return discovered;
  }
  return hostFromUrl(elements.technitiumBaseUrlInput.value);
}

function firstTechnitiumIpv4Candidate() {
  const resolved = Array.isArray(state.prerequisiteGate?.technitium?.resolved_ips)
    ? state.prerequisiteGate.technitium.resolved_ips.find(looksLikeIpv4)
    : '';
  if (resolved) {
    return resolved;
  }
  const remoteAddress = cleanValue(state.prerequisiteGate?.technitium?.probe?.remote_address);
  if (looksLikeIpv4(remoteAddress)) {
    return remoteAddress;
  }
  const discoveryRemoteAddress = cleanValue(state.technitiumDiscovery?.remote_address);
  if (looksLikeIpv4(discoveryRemoteAddress)) {
    return discoveryRemoteAddress;
  }
  const discoveryResolved = Array.isArray(state.technitiumDiscovery?.resolved_ips)
    ? state.technitiumDiscovery.resolved_ips.find(looksLikeIpv4)
    : '';
  if (discoveryResolved) {
    return discoveryResolved;
  }
  const discoveredFromCandidates = Array.isArray(state.technitiumDiscovery?.candidates)
    ? state.technitiumDiscovery.candidates
      .flatMap((candidate) => [
        cleanValue(candidate?.remote_address),
        ...(Array.isArray(candidate?.resolved_ips) ? candidate.resolved_ips : [])
      ])
      .find(looksLikeIpv4)
    : '';
  return discoveredFromCandidates || '';
}

function ensureDnsClientNameserverDefault() {
  if (!elements.applyDnsClientInput.checked || cleanValue(elements.dnsClientNameserverInput.value) !== '') {
    return;
  }
  const target = firstTechnitiumIpv4Candidate();
  if (target) {
    elements.dnsClientNameserverInput.value = target;
  }
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
  elements.detailMessage.textContent = report.message || reportFailureSummary(report);
  elements.detailJson.textContent = JSON.stringify(report, null, 2);
}

function reportFailureSummary(report) {
  if (!report || !Array.isArray(report.apps)) {
    return '';
  }
  const failed = report.apps.filter((app) => app.status === 'failed');
  if (failed.length === 0) {
    return '';
  }
  return failed.map((app) => {
    const appId = app.name || app.id || app.app_id || 'app';
    const reason = appFailureSummary(app);
    return reason ? `${appId}: ${reason}` : `${appId}: failed`;
  }).slice(0, 3).join(' ');
}

function markSelectedStage() {
  elements.stageNav.querySelectorAll('.stage-button').forEach((button, index) => {
    button.classList.toggle('selected', index === state.selectedStageIndex);
  });
}

function setBusy(isBusy) {
  debugLog('busy:set', { isBusy });
  document.body.classList.toggle('busy', isBusy);
  if (elements.refreshButton) {
    elements.refreshButton.disabled = isBusy;
  }
  if (elements.runAutomatedInstallButton) {
    elements.runAutomatedInstallButton.disabled = isBusy;
  }
  document.querySelectorAll('[data-action]').forEach((button) => {
    button.disabled = isBusy;
  });
  void isBusy;
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
