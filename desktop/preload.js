const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('thai2d3dWindow', Object.freeze({
    minimize: () => ipcRenderer.send('window:minimize'),
    toggleMaximize: () => ipcRenderer.send('window:toggle-maximize'),
    close: () => ipcRenderer.send('window:close'),
}));
