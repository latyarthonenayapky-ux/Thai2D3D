# Agent mobile sales (auto-upload)

Agents record 2D/3D sales in the Android app under `mobile/`. Each entry is
uploaded to the office VPS the moment it is saved; entries recorded without
signal stay in a phone-local queue and upload themselves when the connection
returns. The office webapp, dashboard and offline-review screen need no changes
to see them.

```
Agent phone (Flutter)  --HTTPS-->  VPS Laravel  /api/v1/*  -->  sale_inputs / sale_details
                                            \--> offline_sync_reviews (conflicts)
```

## 1. Credentials

Agents have no web login. The office issues mobile credentials:

**Settings → Agents → Mobile app access** (`admin/settings` page)

| Action | Route |
| --- | --- |
| Issue credentials automatically | `POST /admin/settings/agents/{agent}/mobile-access/issue` |
| Issue for every Agent without access | `POST /admin/settings/agents/mobile-access/issue-all` |
| Set login code + password by hand | `PATCH /admin/settings/agents/{agent}/mobile-access` |
| Sign out every phone | `DELETE /admin/settings/agents/{agent}/mobile-access/devices` |
| Disable mobile access | `DELETE /admin/settings/agents/{agent}/mobile-access` |

**Issue credentials** is the normal path when handing a phone to an Agent: the
login code becomes `AGT-0001` (the Agent's id, zero-padded; a suffix is added if
that code is already taken somewhere) and a random six-digit PIN is generated.
The Settings page then shows a one-time handover card with the sign-in URL from
`APP_URL`, the login code and the PIN in a copyable text block. Flash data ages
after that page load, so the PIN can never be read again — issue again to
replace a lost one. Re-issuing keeps the login code the Agent already knows and
only replaces the PIN, which signs every phone out.

`Issue for every Agent without access` does the same for every **active** Agent
in the business that is missing a code or a password, skips Agents that already
have credentials (their phones stay signed in), and lists all of the new PINs in
one handover card.

- Login code: 4–32 characters from `A-Z 0-9 . _ -`, stored uppercase, unique
  across every business.
- Password: 6–64 characters, bcrypt hashed, never shown again.
- Rotating the password, re-issuing, or disabling access revokes all bearer
  tokens at once.

The install file those phones need is published from the same Settings page —
see §5.

## 2. Token model

`POST /api/v1/auth/login` returns a 64-character bearer token. Only its
SHA-256 digest is stored (`agent_access_tokens`). Tokens expire after
`AGENT_TOKEN_TTL_DAYS` (default 180, see `config/mobileapi.php`) and are
recorded with device name and last-used time. Send it as
`Authorization: Bearer <token>`.

A sale uploaded from a phone is attributed to the Operator who holds that
Agent session (`agent_sessions.operator_id`), so reports, settlement and the
Sale Entry ledger keep working exactly as they do for operator-entered sales.

## 3. Endpoints

Base path `/api/v1`. All responses are JSON. Wrong password, missing, expired,
revoked or disabled tokens give `401`; a disabled Agent gives `403`.

| Method | Path | Purpose |
| --- | --- | --- |
| `POST` | `/auth/login` | Exchange login code + password for a token (throttled 10/min) |
| `GET` | `/me` | Agent profile, open rounds, open sessions, review count |
| `POST` | `/auth/logout` | Revoke the token of the calling phone |
| `POST` | `/sessions/open` | Open/claim this Agent's session for a Round |
| `GET` | `/sessions/{session}/state` | Session totals and the last 20 entries |
| `POST` | `/sales/upload` | Auto-upload: 1 record for a live entry, up to 100 for a backlog |

`POST /api/v1/sales/upload` request:

```json
{
  "records": [
    {
      "client_uuid": "b4d1e0a2-0f65-4c1a-9d3e-2c7f5a1b8ed0",
      "session_id": 41,
      "sale_type": "2d",
      "input": "121000",
      "recorded_at": "2026-10-13T09:12:04+00:00"
    }
  ]
}
```

Response (HTTP 200, one outcome per record):

```json
{
  "results": [
    {"client_uuid": "b4d1...", "status": "synced", "message": "Sale synchronized."}
  ],
  "uploaded_at": "2026-10-13T09:12:05+00:00"
}
```

| `status` | Meaning | Phone behaviour |
| --- | --- | --- |
| `synced` | Booked into the ledger (or already booked before) | Remove from queue |
| `pending_review` | Round/session closed, or a Hot Number / Number Limit conflict | Remove from queue, show "waiting for office review" |
| `invalid` | Parser could not read the entry | Move to **Needs attention** for editing |
| `error` | Session not available to this account | Retry, then give up after 8 attempts |

`client_uuid` is the idempotency key (`agents` → `sale_inputs.operator_id +
client_uuid` unique index). Re-sending the same record never double-books a
sale, which is why the app can retry freely. This is the same contract the
browser offline page uses at `POST /operator/offline-sync`, and conflicts land
in the same **Offline sync reviews** screen for the Admin.

## 4. Building the APK

See `mobile/README.md`. Summary:

```bash
cd mobile
flutter pub get
flutter create --platforms=android --org com.wya --project-name wya_agent_mobile .
flutter build apk --release --dart-define=WYA_SERVER_URL=https://your-vps-address
```

GitHub Actions does the same build for you: `android-apk.yml` runs on any push
that touches `mobile/**` (or on **Run workflow**, where the VPS address can be
typed in) and uploads `wya-agent-debug-apk` plus `wya-agent-release-apk`. The
workflow labels the app **WYA Agent** and enables cleartext so a VPS without
HTTPS yet can still be reached. Take `app-release.apk` from the run page and
publish it under **Settings → Agent mobile app (APK)** in §5 — that page is the
only way Agents can obtain the file. See `mobile/README.md` for the details.

## 5. Handing the APK to Agents (admin-only)

The install file is distributed from the webapp, not from a public folder.
**Settings → Agent mobile app** (`admin/settings` page) lets the business admin
upload the APK built in §4 and give Agents a download link they cannot share.

| Action | Route |
| --- | --- |
| Publish an APK | `POST /admin/settings/mobile-app` |
| Download it | `GET /admin/settings/mobile-app/download` |
| Remove it | `DELETE /admin/settings/mobile-app` |

- One row per business (`mobile_app_packages` is unique on `admin_id`), so
  publishing again replaces the previous build instead of accumulating versions.
  Each business keeps its own file under `mobile-apps/{admin}/`.
- The download is saved as **`{business-name}-agent-app-{version}.apk`** (the
  admin's own account name, or `admin-{id}` when that name is not ASCII, e.g.
  Burmese) so a phone holding builds handed out by two offices can tell them
  apart before installing one.
- The file lands on the **private** `local` disk at
  `storage/app/private/mobile-apps/{admin}/agent-app.apk`. Nothing is ever
  written to `storage/app/public`, so the only way to fetch it is the download
  route, which streams it with `Content-Type: application/vnd.android.package-archive`.
- Every route sits behind the `admin` middleware, so Operators, Agents and
  guests get 403 (guests are redirected to the login page). Downloading is
  therefore limited to the signed-in admin's own session — hand the file to a
  phone over USB/Share, or open the Settings page on that phone once.
- Uploads are validated as `extensions:apk`, capped at 120 MB, and are throttled
  to 10/min. The optional version field (32 characters) is only a label shown
  next to the file name and size.
- Removing the app deletes both the stored file and the row.
- Nothing crosses between businesses: the download and remove routes only ever
  look up `admin_id = the signed-in admin`, and every mobile API call resolves
  through the signed-in Agent's own `admin_id` — a Round of another business 404s
  on `POST /sessions/open`, another business' session is refused by
  `POST /sales/upload` and 404s on `GET /sessions/{id}/state`, and login codes are
  unique across the whole install. `AgentMobileBusinessIsolationTest` and
  `MobileAppPackageTest::test_each_admin_only_reaches_their_own_published_app`
  pin this down.

## 6. CI/CD

`.github/workflows/`:

| Workflow | Trigger | What it does |
| --- | --- | --- |
| `laravel-ci.yml` | push/PR to `master`, `VPS2D3D`, `MasterW` | `composer install` + full PHPUnit suite on PHP 8.3 (SQLite) |
| `deploy-vps.yml` | after CI succeeds on `master`, or manually | rsync app files (never `storage/`, `.env`, `vendor`), then `composer install --no-dev`, `migrate --force`, `db:seed --force`, `optimize`, health check `/up` |
| `android-apk.yml` | changes under `mobile/**`, or manually | labels the app `WYA Agent`, `flutter analyze`, then debug and release APK artifacts |

Required repository secrets for deployment: `VPS_HOST`, `VPS_USER`,
`VPS_SSH_PRIVATE_KEY`, `VPS_PATH`, optional `VPS_SSH_PORT`. Optional variables:
`VPS_HEALTH_URL` and `WYA_SERVER_URL` (baked into CI-built APKs). For this
deployment both point at the Z.com domain registered against the VPS at
`118.27.150.3`: `VPS_HOST=118.27.150.3`, `VPS_HEALTH_URL=https://wya2d3d.site/up`
and `WYA_SERVER_URL=https://wya2d3d.site`. `WYA_SERVER_URL` is only the
build-time default — the workflow falls back to the same address, and each Agent
can still change it on the sign-in screen. The deploy SSH key must be able to write
`VPS_PATH`, and `VPS_PATH` should be the non-public application directory
described in `docs/production-deployment.md`.

Two GitHub branches created earlier by Copilot
(`latyarthonenayapky-ux-mobile-agent-sale-entry`,
`latyarthonenayapky-ux-sale-entry-workflows`) contain only a Symfony test
workflow and an SLSA release workflow generated from a template. They do not
match this Laravel app, hold no mobile code, and should be deleted; the
workflows above replace them.
