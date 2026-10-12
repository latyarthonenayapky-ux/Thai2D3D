# WYA Agent mobile app (Android)

Agents record 2D/3D sales on their phone. Every entry is uploaded to the office
VPS as soon as it is saved; when the phone has no signal the entry stays in a
local queue and uploads itself when the connection returns.

This folder holds the Dart sources only. The Android project files are generated
by Flutter on the build machine, so they are not committed here.

## Build the APK

Requires Flutter 3.22+ (Dart 3.4+) and Android SDK 34 on the build machine.

```bash
cd mobile
flutter pub get
flutter create --platforms=android --org com.wya --project-name wya_agent_mobile .
flutter build apk --release --dart-define=WYA_SERVER_URL=https://your-vps-address
```

`flutter create` fills in `android/` (gradle files, manifest, wrapper). It does
not touch `lib/`, `pubspec.yaml` or `analysis_options.yaml`.

## Building it on GitHub Actions (no Flutter needed)

`.github/workflows/android-apk.yml` runs on every push that touches `mobile/**`
(or manually, from **Actions → Android APK → Run workflow**, where you can type
the VPS address). It installs Flutter 3.24.5, generates `android/`, labels the
app **WYA Agent**, allows a plain-HTTP VPS, and uploads two artifacts:

| Artifact | File | Use it for |
| --- | --- | --- |
| `wya-agent-debug-apk` | `app-debug.apk` | quick checks; verbose logs, kept 30 days |
| `wya-agent-release-apk` | `app-release.apk` | handing to Agents; smaller, kept 90 days |

Flutter signs release builds with the debug key, so `app-release.apk` installs on
a phone as-is. Before relying on in-place upgrades from the Play-less sideload,
replace it with a real keystore stored as a repository secret.

Download the artifact from the run page, then publish it under **Settings →
Agent mobile app (APK)** on the office webapp. That page is the only place the
file can be pulled from: it lives on the private disk and the download route is
admin-only, so Operators and Agents get 403.

## Server address

The baked-in default comes from `--dart-define=WYA_SERVER_URL`
(see `lib/main.dart`). Each Agent can also change the address on the sign-in
screen; the value they use is stored on the phone.

For a VPS without HTTPS yet, allow cleartext traffic in
`android/app/src/main/AndroidManifest.xml` (the CI build does this for you):

```xml
<application android:usesCleartextTraffic="true" ...>
```

## Sign in

The office issues credentials under **Settings → Agents → Mobile app access**:

- Mobile login code (4–32 characters, letters/numbers)
- Mobile password (6–64 characters)

Signing in returns a bearer token valid for `AGENT_TOKEN_TTL_DAYS` (default 180
days). Rotating the password or disabling mobile access signs every phone of
that Agent out immediately.

## What the app does with each entry

1. `lib/queue.dart` writes the entry to a SQLite queue with a UUID key.
2. `lib/sync.dart` immediately POSTs it to `POST /api/v1/sales/upload`.
3. The server answers per entry: `synced`, `pending_review` (Round closed or
   number conflict — the office reviews it), `invalid` (could not be read),
   or `error` (kept for retry).
4. Retries run every 20 seconds, on reconnect, and when the app is reopened.
   After 8 failed attempts an entry moves to **Needs attention** so the Agent
   can retry or discard it. The UUID means a retry can never double-book a sale.
