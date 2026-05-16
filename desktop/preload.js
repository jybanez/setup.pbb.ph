const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('kitSetup', {
  getDefaults: () => ipcRenderer.invoke('kit:get-defaults'),
  selectConfig: () => ipcRenderer.invoke('kit:select-config'),
  selectFolder: (request) => ipcRenderer.invoke('kit:select-folder', request),
  selectFile: (request) => ipcRenderer.invoke('kit:select-file', request),
  buildConfig: (request) => ipcRenderer.invoke('kit:build-config', request),
  describeAction: (request) => ipcRenderer.invoke('kit:describe-action', request),
  runAction: (request) => ipcRenderer.invoke('kit:run-action', request),
  onRunnerOutput: (callback) => {
    const listener = (_event, payload) => callback(payload);
    ipcRenderer.on('kit:runner-output', listener);
    return () => ipcRenderer.removeListener('kit:runner-output', listener);
  }
});
