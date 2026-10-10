# Thai2D3D Desktop

The desktop app starts the bundled Laravel application and PHP server on
`127.0.0.1`, then opens it in an Electron window. It does not connect to a
remote website. The database and Laravel writable files live in the current
OS user's application-data directory, not inside the installed app.

## Ready-to-run Linux package

The clean project folder includes the tested Linux x86-64 package at its root:
`../Thai2D3D-1.1.0.AppImage`. Double-click it in the file manager to run it.
On first launch, create the local Owner account shown by the app. If the file
manager blocks launching it, enable executable permission in file Properties.

The package is standalone for offline use. It does not start a tunnel or sync
its SQLite database to a VPS. For the Burmese operating guide, see
[`../docs/USER_MANUAL_MM.md`](../docs/USER_MANUAL_MM.md).

## First use

Open the `.AppImage` or portable `.exe`. On first launch, create a local Owner
account with an email and a password of at least 12 characters. The app then
opens the login/dashboard workflow without requiring internet access.

## Local data

Each computer has its own SQLite database:

- Linux: `~/.config/Thai2D3D/data/thai2d3d.sqlite`
- Windows: `%APPDATA%\\Thai2D3D\\data\\thai2d3d.sqlite`

To back up or move an installation, close Thai2D3D and copy the entire
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
