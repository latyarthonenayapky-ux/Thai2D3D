const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');

const desktopRoot = path.resolve(__dirname, '..');
const projectRoot = path.resolve(desktopRoot, '..');
const stageRoot = path.join(desktopRoot, 'stage');
const appStage = path.join(stageRoot, 'laravel');
const phpStage = path.join(stageRoot, 'php');
const target = process.argv[2];

if (!['linux', 'win'].includes(target)) {
    throw new Error('Usage: node scripts/prepare.js <linux|win>');
}

function copyDirectory(source, destination) {
    fs.cpSync(source, destination, {
        recursive: true,
        force: true,
        filter: entry => ![
            '.env',
            '.env.backup',
            '.env.production',
            '.git',
            'storage/framework/cache',
            'storage/framework/sessions',
            'storage/framework/views',
            'storage/logs',
        ].includes(path.basename(entry)),
    });
}

fs.rmSync(stageRoot, { recursive: true, force: true });
fs.mkdirSync(appStage, { recursive: true });

for (const directory of [
    'app',
    'bootstrap',
    'config',
    'database',
    'public',
    'resources',
    'routes',
    'vendor',
]) {
    copyDirectory(
        path.join(projectRoot, directory),
        path.join(appStage, directory),
    );
}

for (const file of ['artisan', 'composer.json', 'composer.lock']) {
    fs.copyFileSync(path.join(projectRoot, file), path.join(appStage, file));
}

fs.rmSync(path.join(appStage, 'bootstrap', 'cache'), { recursive: true, force: true });
fs.mkdirSync(path.join(appStage, 'bootstrap', 'cache'), { recursive: true });
fs.mkdirSync(path.join(phpStage, 'ext'), { recursive: true });
fs.mkdirSync(path.join(phpStage, 'lib'), { recursive: true });
fs.mkdirSync(path.join(phpStage, 'conf.d'), { recursive: true });

if (target === 'win') {
    const runtimeSource = path.join(desktopRoot, 'runtime', 'php-win');
    if (!fs.existsSync(path.join(runtimeSource, 'php.exe'))) {
        throw new Error(
            'Windows PHP runtime is missing. Add the official PHP 8.3 NTS x64 zip contents to desktop/runtime/php-win, including php.exe and ext/pdo_sqlite.dll.',
        );
    }

    fs.cpSync(runtimeSource, phpStage, { recursive: true });
    const iniPath = path.join(phpStage, 'php.ini');
    if (!fs.existsSync(iniPath)) {
        fs.writeFileSync(iniPath, [
            'extension_dir=ext',
            'extension=pdo_sqlite',
            'extension=sqlite3',
            'date.timezone=Asia/Yangon',
            'display_errors=Off',
            'log_errors=On',
            '',
        ].join('\n'));
    }
} else {
    const runtimeSource = path.join(desktopRoot, 'runtime', 'php-linux');
    const sourcePhp = process.env.THAI2D3D_PHP_BINARY
        || execFileSync('sh', ['-lc', 'command -v php'], { encoding: 'utf8' }).trim();
    const sourceExtensionDir = process.env.THAI2D3D_PHP_EXTENSION_DIR
        || execFileSync('php', ['-i'], { encoding: 'utf8' })
            .match(/^extension_dir => (.+?) =>/m)?.[1];
    const sqliteExtensionDirs = [
        process.env.THAI2D3D_SQLITE_EXTENSION_DIR,
        path.join(runtimeSource, 'ext'),
    ].filter(Boolean);
    const phpBinary = fs.realpathSync(sourcePhp);

    if (!sourceExtensionDir) {
        throw new Error('Could not determine the PHP extension directory.');
    }

    fs.mkdirSync(path.join(phpStage, 'bin'), { recursive: true });
    fs.copyFileSync(phpBinary, path.join(phpStage, 'bin', 'php'));
    fs.chmodSync(path.join(phpStage, 'bin', 'php'), 0o755);

    const extensions = [
        'ctype',
        'curl',
        'dom',
        'fileinfo',
        'mbstring',
        'pdo',
        'pdo_sqlite',
        'simplexml',
        'sqlite3',
        'tokenizer',
        'xml',
        'xmlreader',
        'xmlwriter',
    ];

    for (const extension of extensions) {
        const candidates = [
            path.join(sourceExtensionDir, `${extension}.so`),
            ...sqliteExtensionDirs.map(directory => path.join(directory, `${extension}.so`)),
        ].filter(Boolean);
        const source = candidates.find(candidate => fs.existsSync(candidate));
        if (!source && ['pdo_sqlite', 'sqlite3'].includes(extension)) {
            throw new Error(
                `Missing ${extension}.so. Install PHP's SQLite extension or set THAI2D3D_SQLITE_EXTENSION_DIR.`,
            );
        }
        if (source) fs.copyFileSync(source, path.join(phpStage, 'ext', `${extension}.so`));
    }

    const ini = [
        'extension_dir=ext',
        ...extensions
            .filter(extension => fs.existsSync(path.join(phpStage, 'ext', `${extension}.so`)))
            .map(extension => `extension=${extension}.so`),
        'date.timezone=Asia/Yangon',
        'display_errors=Off',
        'log_errors=On',
        '',
    ];
    fs.writeFileSync(path.join(phpStage, 'php.ini'), ini.join('\n'));

    const dependencyTargets = [
        path.join(phpStage, 'bin', 'php'),
        ...extensions
            .map(extension => path.join(phpStage, 'ext', `${extension}.so`))
            .filter(file => fs.existsSync(file)),
    ];
    const systemLibraries = /^(ld-linux|libc\.so|libm\.so|libpthread\.so|libdl\.so|librt\.so|libresolv\.so|libutil\.so)/;
    const inspected = new Set();
    const bundledLibraries = new Map();
    const pending = [...dependencyTargets];

    while (pending.length > 0) {
        const file = pending.pop();
        if (inspected.has(file)) continue;
        inspected.add(file);
        const dependencies = execFileSync('ldd', [file], { encoding: 'utf8' });

        for (const match of dependencies.matchAll(/(?:=>\s+)?(\/[^\s(]+)/g)) {
            const library = fs.realpathSync(match[1]);
            const name = path.basename(library);
            if (systemLibraries.test(name)) continue;
            if (!bundledLibraries.has(name)) bundledLibraries.set(name, library);
            if (!inspected.has(library)) pending.push(library);
        }
    }

    for (const [name, library] of bundledLibraries) {
        fs.copyFileSync(library, path.join(phpStage, 'lib', name));
    }
}

console.log(`Prepared ${target} desktop bundle at ${stageRoot}`);
