const { Readable } = require('node:stream');
const { pipeline } = require('node:stream/promises');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');

const desktopRoot = path.resolve(__dirname, '..');
const cacheRoot = path.join(desktopRoot, '.cache');
const runtimeRoot = path.join(desktopRoot, 'runtime', 'php-win');
const archivePath = path.join(cacheRoot, 'php-win-x64.zip');
const listingUrl = 'https://windows.php.net/downloads/releases/';
const requestedVersion = process.env.THAI2D3D_PHP_WIN_VERSION;
const localArchive = process.env.THAI2D3D_PHP_WIN_ARCHIVE;

async function main() {
    fs.mkdirSync(cacheRoot, { recursive: true });
    if (localArchive) {
        const source = path.resolve(localArchive);
        if (!fs.statSync(source).isFile()) {
            throw new Error(`Windows PHP archive is not a file: ${source}`);
        }
        fs.copyFileSync(source, archivePath);
    } else {
        let archiveUrl = process.env.THAI2D3D_PHP_WIN_URL;

        if (!archiveUrl) {
            const response = await fetch(listingUrl);
            if (!response.ok) throw new Error(`PHP release index returned HTTP ${response.status}.`);
            const html = await response.text();
            const candidates = [...html.matchAll(/href=["'](php-8\.3\.(\d+)-nts-Win32-vs16-x64\.zip)["']/g)]
                .map(match => ({ file: match[1], patch: Number(match[2]) }))
                .filter(item => !requestedVersion || item.file.startsWith(`php-${requestedVersion}-`))
                .sort((left, right) => right.patch - left.patch);
            if (candidates.length === 0) {
                throw new Error('No matching official PHP 8.3 NTS x64 Windows runtime was found.');
            }
            archiveUrl = new URL(candidates[0].file, listingUrl).toString();
        }

        const download = await fetch(archiveUrl);
        if (!download.ok || !download.body) {
            throw new Error(`Could not download the Windows PHP runtime (HTTP ${download.status}).`);
        }
        await pipeline(Readable.fromWeb(download.body), fs.createWriteStream(archivePath));
    }

    fs.rmSync(runtimeRoot, { recursive: true, force: true });
    fs.mkdirSync(runtimeRoot, { recursive: true });
    execFileSync('unzip', ['-q', archivePath, '-d', runtimeRoot], { stdio: 'inherit' });

    for (const required of ['php.exe', path.join('ext', 'php_pdo_sqlite.dll'), path.join('ext', 'php_sqlite3.dll')]) {
        if (!fs.existsSync(path.join(runtimeRoot, required))) {
            throw new Error(`Downloaded PHP runtime is missing ${required}.`);
        }
    }

    fs.writeFileSync(path.join(runtimeRoot, 'php.ini'), [
        'extension_dir=ext',
        'extension=mbstring',
        'extension=fileinfo',
        'extension=tokenizer',
        'extension=dom',
        'extension=xml',
        'extension=xmlreader',
        'extension=xmlwriter',
        'extension=curl',
        'extension=pdo_sqlite',
        'extension=sqlite3',
        'date.timezone=Asia/Yangon',
        'display_errors=Off',
        'log_errors=On',
        '',
    ].join('\n'));

    console.log(localArchive
        ? `Prepared local PHP Windows runtime from ${path.resolve(localArchive)}`
        : `Prepared official PHP Windows runtime from ${archiveUrl}`);
}

main().catch(error => {
    console.error(error.message);
    process.exitCode = 1;
});
