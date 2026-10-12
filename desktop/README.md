# Winning Yields Always (WYA) Desktop

The desktop app starts the bundled Laravel application and PHP server on
`127.0.0.1`, then opens it in an Electron window. It does not connect to a
remote website. The database and Laravel writable files live in the current
OS user's application-data directory, not inside the installed app.

## Ready-to-run packages

The clean project folder ships the tested standalone packages under `release/`:

- **Linux x86-64** — `../release/Linux/Thai2D3D-1.1.0.AppImage`. Double-click it
  in the file manager to run it. If the file manager blocks launching it, enable
  executable permission in file Properties.
- **Windows x64** — run `../release/Windows/Start Thai2D3D.cmd` (or
  `Thai2D3D\Thai2D3D.exe` inside that folder). The app is a portable folder —
  copy it anywhere and run it; no installation is needed.

On first launch, create the local Owner account shown by the app.

The packages are standalone for offline use. They do not start a tunnel or sync
their SQLite database to a VPS. For the Burmese operating guide, see
[`../docs/USER_MANUAL_MM.md`](../docs/USER_MANUAL_MM.md).

## First use

Open the `.AppImage`, or on Windows run `Start Thai2D3D.cmd` (or the
`Thai2D3D.exe` inside the app folder). On first launch, create a local Owner
account with an email and a password of at least 6 characters (step 1), then
enter the install pass key issued for that email (step 2). Without a matching
key the Owner account cannot be created. The app then opens the
login/dashboard workflow without requiring internet access.

## Issuing install pass keys

The setup wizard only accepts a pass key that matches the admin email entered
in step 1. The customer sends you the email they will install with; issue the
key with the vendor-only keygen tool:

- Open `keygen/keygen.html` in any browser (works offline, no install needed), or
- Run `keygen/keygen.bat` (uses the bundled PHP runtime), or
- Run `php keygen/keygen.php <email>` with any PHP 8.

All three derive the same key, so a key can always be re-issued later. The
`keygen` folder is vendor-only and is not part of the packaged app.

## Local data

Each computer has its own SQLite database:

- Linux: `~/.config/Thai2D3D/data/thai2d3d.sqlite`
- Windows: `%APPDATA%\\Thai2D3D\\data\\thai2d3d.sqlite`

To back up or move an installation, close Winning Yields Always and copy the entire
`Thai2D3D` application-data directory. Restore it to the same OS user's
application-data location before opening the app. Do not copy a live database
while the app is running.

Standalone desktop databases are independent from the online server and from
other computers. There is no desktop-to-server synchronization in this build.

## Build

Run commands from this directory after installing dependencies with `npm ci`:

- `npm run dist:linux` builds an x86-64 AppImage at
  `dist/standalone-linux/Thai2D3D-1.1.0.AppImage`.
- `npm run dist:win` downloads the official PHP 8.3 NTS x64 runtime and builds
  a portable Windows executable at `dist/standalone-win/`.

The Windows build needs network access to `windows.php.net` the first time.
The Linux build bundles the local PHP CLI and its SQLite/runtime libraries.

After a Windows rebuild, refresh the portable folder by replacing
`../release/Windows/Thai2D3D/` with the new `dist/standalone-win/win-unpacked/`
contents; `Start Thai2D3D.cmd` and `_superseded/` stay as they are. On a
machine without Node.js the same folder can be reassembled manually: copy
`win-unpacked`, replace `resources/laravel` and `resources/php` with a fresh
`stage/` built by the same copy rules as `scripts/prepare.js`, and repack
`resources/app.asar` from `main.js`, `preload.js`, `package.json` and
`scripts/` using `@electron/asar` from `node_modules`.
