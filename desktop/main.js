const { app, BrowserWindow, dialog, ipcMain, shell } = require('electron');
const { spawn } = require('node:child_process');
const crypto = require('node:crypto');
const fs = require('node:fs');
const net = require('node:net');
const path = require('node:path');

const APP_ROOT = app.isPackaged
    ? path.join(process.resourcesPath, 'laravel')
    : path.resolve(__dirname, '..');
const PHP_ROOT = app.isPackaged
    ? path.join(process.resourcesPath, 'php')
    : path.join(__dirname, 'runtime', process.platform === 'win32' ? 'php-win' : 'php-linux');
const PHP_BINARY = process.platform === 'win32'
    ? path.join(PHP_ROOT, 'php.exe')
    : path.join(PHP_ROOT, 'bin', 'php');
const PHP_INI = path.join(PHP_ROOT, 'php.ini');
const WEB_PARTITION = 'persist:thai2d3d-local';

let serverProcess = null;
let schedulerProcess = null;
let mainWindow = null;
let serverOrigin = null;
let shuttingDown = false;

function ensureDirectory(directory) {
    fs.mkdirSync(directory, { recursive: true });
}

function appendProcessLog(name, stream, directory) {
    const logPath = path.join(directory, `${name}.log`);
    stream.on('data', chunk => {
        const line = chunk.toString();
        fs.appendFile(logPath, line, error => {
            if (error) console.error(`Unable to write ${name} log:`, error.message);
        });
    });
}

function loadOrCreateAppKey(directory) {
    const keyPath = path.join(directory, 'app-key');
    if (fs.existsSync(keyPath)) {
        const key = fs.readFileSync(keyPath, 'utf8').trim();
        if (key.startsWith('base64:') && key.length > 40) return key;
        throw new Error('The local application key file is invalid. Keep a backup of the database before repairing this installation.');
    }

    const key = `base64:${crypto.randomBytes(32).toString('base64')}`;
    fs.writeFileSync(keyPath, `${key}\n`, { mode: 0o600, flag: 'wx' });
    return key;
}

function createRuntimeEnvironment(databasePath, storagePath, bootstrapPath, appKey, appUrl, phpIniScanPath) {
    const environment = {
        ...process.env,
        APP_NAME: 'Thai2D3D',
        APP_ENV: 'local',
        APP_KEY: appKey,
        APP_DEBUG: 'false',
        APP_URL: appUrl,
        APP_LOCALE: 'en',
        APP_FALLBACK_LOCALE: 'en',
        APP_TIMEZONE: 'Asia/Yangon',
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: databasePath,
        DB_URL: '',
        DB_FOREIGN_KEYS: 'true',
        SESSION_DRIVER: 'file',
        SESSION_SECURE_COOKIE: 'false',
        CACHE_STORE: 'file',
        QUEUE_CONNECTION: 'sync',
        FILESYSTEM_DISK: 'local',
        LOG_CHANNEL: 'single',
        LOG_LEVEL: 'warning',
        THAI2D3D_DESKTOP: 'true',
        LARAVEL_STORAGE_PATH: storagePath,
        LARAVEL_BOOTSTRAP_PATH: bootstrapPath,
        PHP_INI_SCAN_DIR: phpIniScanPath,
    };

    if (process.platform !== 'win32') {
        const bundledLibraries = path.join(PHP_ROOT, 'lib');
        environment.LD_LIBRARY_PATH = [
            bundledLibraries,
            process.env.LD_LIBRARY_PATH,
        ].filter(Boolean).join(path.delimiter);
    }

    return environment;
}

function phpArguments(iniPath, args) {
    return [
        '-c',
        iniPath,
        ...args,
    ];
}

function runPhp(args, iniPath, environment, logDirectory) {
    return new Promise((resolve, reject) => {
        const child = spawn(PHP_BINARY, phpArguments(iniPath, args), {
            cwd: APP_ROOT,
            env: environment,
            stdio: ['ignore', 'pipe', 'pipe'],
            windowsHide: true,
        });
        let stdout = '';
        let stderr = '';

        appendProcessLog('initialize', child.stdout, logDirectory);
        appendProcessLog('initialize', child.stderr, logDirectory);
        child.stdout.on('data', chunk => { stdout += chunk.toString(); });
        child.stderr.on('data', chunk => { stderr += chunk.toString(); });
        child.once('error', reject);
        child.once('close', code => {
            if (code === 0) resolve(stdout);
            else reject(new Error(`PHP initialization failed (${code}). ${stderr.slice(-4000)}`));
        });
    });
}

function getAvailablePort() {
    return new Promise((resolve, reject) => {
        const probe = net.createServer();
        probe.once('error', reject);
        probe.listen(0, '127.0.0.1', () => {
            const address = probe.address();
            probe.close(error => error ? reject(error) : resolve(address.port));
        });
    });
}

async function waitForServer(url, child) {
    const deadline = Date.now() + 60000;
    let lastError = 'No response from the local app.';

    while (Date.now() < deadline) {
        if (child.exitCode !== null) {
            throw new Error(`The local PHP server stopped unexpectedly (exit ${child.exitCode}).`);
        }

        try {
            const response = await fetch(`${url}/up`, { signal: AbortSignal.timeout(2000) });
            if (response.ok) return;
            lastError = `Health check returned HTTP ${response.status}.`;
        } catch (error) {
            lastError = error.message;
        }

        await new Promise(resolve => setTimeout(resolve, 500));
    }

    throw new Error(`The local app did not start. ${lastError}`);
}

async function startLocalApp() {
    const userData = app.getPath('userData');
    const databaseDirectory = path.join(userData, 'data');
    const storagePath = path.join(userData, 'laravel-storage');
    const bootstrapPath = path.join(userData, 'laravel-bootstrap');
    const logDirectory = path.join(userData, 'logs');
    const databasePath = path.join(databaseDirectory, 'thai2d3d.sqlite');
    const iniPath = path.join(userData, 'php.ini');

    for (const directory of [
        databaseDirectory,
        path.join(storagePath, 'app/private'),
        path.join(storagePath, 'app/public'),
        path.join(storagePath, 'framework/cache/data'),
        path.join(storagePath, 'framework/sessions'),
        path.join(storagePath, 'framework/views'),
        path.join(storagePath, 'logs'),
        bootstrapPath,
        path.join(bootstrapPath, 'cache'),
        logDirectory,
    ]) {
        ensureDirectory(directory);
    }
    fs.closeSync(fs.openSync(databasePath, 'a'));

    const bundledProviders = path.join(APP_ROOT, 'bootstrap', 'providers.php');
    const writableProviders = path.join(bootstrapPath, 'providers.php');
    if (!fs.existsSync(writableProviders)) {
        fs.copyFileSync(bundledProviders, writableProviders);
    }

    const appKey = loadOrCreateAppKey(userData);
    const iniContents = fs.readFileSync(PHP_INI, 'utf8')
        .replace(/^extension_dir=.*$/m, `extension_dir=${path.join(PHP_ROOT, 'ext').replaceAll('\\', '/')}`);
    fs.writeFileSync(iniPath, iniContents);
    const port = await getAvailablePort();
    serverOrigin = `http://127.0.0.1:${port}`;
    const environment = createRuntimeEnvironment(
        databasePath,
        storagePath,
        bootstrapPath,
        appKey,
        serverOrigin,
        path.join(PHP_ROOT, 'conf.d'),
    );

    const initialization = await runPhp(
        ['artisan', 'thai2d3d:desktop-initialize', '--no-interaction'],
        iniPath,
        environment,
        logDirectory,
    );
    const needsOwnerSetup = initialization.includes('DESKTOP_OWNER_SETUP_REQUIRED=1');

    serverProcess = spawn(PHP_BINARY, phpArguments(iniPath, [
        '-S',
        `127.0.0.1:${port}`,
        '-t',
        path.join(APP_ROOT, 'public'),
        path.join(APP_ROOT, 'public', 'index.php'),
    ]), {
        cwd: APP_ROOT,
        env: environment,
        stdio: ['ignore', 'pipe', 'pipe'],
        windowsHide: true,
    });
    appendProcessLog('server', serverProcess.stdout, logDirectory);
    appendProcessLog('server', serverProcess.stderr, logDirectory);
    serverProcess.once('error', error => console.error('Local PHP server error:', error));
    serverProcess.once('close', code => {
        if (!shuttingDown && app.isReady()) {
            dialog.showErrorBox('Thai2D3D stopped', `The local app server exited (${code}). See ${path.join(logDirectory, 'server.log')}.`);
            app.quit();
        }
    });

    await waitForServer(serverOrigin, serverProcess);

    schedulerProcess = spawn(PHP_BINARY, phpArguments(iniPath, [
        'artisan',
        'schedule:work',
        '--no-interaction',
    ]), {
        cwd: APP_ROOT,
        env: environment,
        stdio: ['ignore', 'pipe', 'pipe'],
        windowsHide: true,
    });
    appendProcessLog('scheduler', schedulerProcess.stdout, logDirectory);
    appendProcessLog('scheduler', schedulerProcess.stderr, logDirectory);
    schedulerProcess.once('error', error => console.error('Local scheduler error:', error));

    return needsOwnerSetup
        ? `${serverOrigin}/desktop/owner-setup`
        : `${serverOrigin}/`;
}

function createWindow(url) {
    mainWindow = new BrowserWindow({
        width: 1280,
        height: 860,
        minWidth: 900,
        minHeight: 600,
        frame: false,
        backgroundColor: '#f4f7f5',
        autoHideMenuBar: true,
        webPreferences: {
            preload: path.join(__dirname, 'preload.js'),
            partition: WEB_PARTITION,
            contextIsolation: true,
            nodeIntegration: false,
            spellcheck: false,
        },
    });

    const openExternalLink = (event, urlToOpen) => {
        if (new URL(urlToOpen).origin === serverOrigin) return;
        event.preventDefault();
        if (urlToOpen.startsWith('https://')) shell.openExternal(urlToOpen);
    };

    mainWindow.webContents.setWindowOpenHandler(({ url: urlToOpen }) => {
        if (urlToOpen.startsWith('https://')) shell.openExternal(urlToOpen);
        return { action: 'deny' };
    });
    mainWindow.webContents.on('will-navigate', openExternalLink);
    mainWindow.loadURL(url);
}

ipcMain.on('window:minimize', event => {
    const window = BrowserWindow.fromWebContents(event.sender);
    if (window === mainWindow) window.minimize();
});
ipcMain.on('window:toggle-maximize', event => {
    const window = BrowserWindow.fromWebContents(event.sender);
    if (window !== mainWindow) return;
    if (window.isMaximized()) window.unmaximize();
    else window.maximize();
});
ipcMain.on('window:close', event => {
    const window = BrowserWindow.fromWebContents(event.sender);
    if (window === mainWindow) window.close();
});

function stopChild(child) {
    if (child && child.exitCode === null && !child.killed) child.kill();
}

app.whenReady().then(async () => {
    try {
        const initialUrl = await startLocalApp();
        createWindow(initialUrl);
        app.on('activate', () => {
            if (BrowserWindow.getAllWindows().length === 0) createWindow(`${serverOrigin}/`);
        });
    } catch (error) {
        console.error(error);
        dialog.showErrorBox('Thai2D3D could not start', error.message);
        app.quit();
    }
});

app.on('before-quit', () => {
    shuttingDown = true;
    stopChild(schedulerProcess);
    stopChild(serverProcess);
});

app.on('window-all-closed', () => {
    if (process.platform !== 'darwin') app.quit();
});
