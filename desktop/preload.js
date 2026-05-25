const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('kitSetup', {
  getDefaults: () => ipcRenderer.invoke('kit:get-defaults'),
  selectConfig: () => ipcRenderer.invoke('kit:select-config'),
  selectFolder: (request) => ipcRenderer.invoke('kit:select-folder', request),
  selectFile: (request) => ipcRenderer.invoke('kit:select-file', request),
  selectSaveFile: (request) => ipcRenderer.invoke('kit:select-save-file', request),
  validatePath: (request) => ipcRenderer.invoke('kit:validate-path', request),
  detectLocalIp: () => ipcRenderer.invoke('kit:detect-local-ip'),
  detectTechnitium: (request) => ipcRenderer.invoke('kit:detect-technitium', request),
  inspectPrerequisites: (request) => ipcRenderer.invoke('kit:inspect-prerequisites', request),
  inspectExistingInstalls: (request) => ipcRenderer.invoke('kit:inspect-existing-installs', request),
  inspectWindowsInstaller: () => ipcRenderer.invoke('kit:inspect-windows-installer'),
  getInstallState: () => ipcRenderer.invoke('kit:get-install-state'),
  estimateDiskSpace: (request) => ipcRenderer.invoke('kit:estimate-disk-space', request),
  showSuccessAndQuit: (request) => ipcRenderer.invoke('kit:show-success-and-quit', request),
  quitInstaller: () => ipcRenderer.invoke('kit:quit-installer'),
  buildConfig: (request) => ipcRenderer.invoke('kit:build-config', request),
  describeAction: (request) => ipcRenderer.invoke('kit:describe-action', request),
  runAction: (request) => ipcRenderer.invoke('kit:run-action', request),
  onRunnerOutput: (callback) => {
    const listener = (_event, payload) => callback(payload);
    ipcRenderer.on('kit:runner-output', listener);
    return () => ipcRenderer.removeListener('kit:runner-output', listener);
  }
});
