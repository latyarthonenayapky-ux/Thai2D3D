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
| Set login code + password | `PATCH /admin/settings/agents/{agent}/mobile-access` |
| Sign out every phone | `DELETE /admin/settings/agents/{agent}/mobile-access/devices` |
| Disable mobile access | `DELETE /admin/settings/agents/{agent}/mobile-access` |

- Login code: 4–32 characters from `A-Z 0-9 . _ -`, stored uppercase, unique
  across every business.
- Password: 6–64 characters, bcrypt hashed, never shown again.
- Rotating the password or disabling access revokes all bearer tokens at once.

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

## 5. CI/CD

`.github/workflows/`:

| Workflow | Trigger | What it does |
| --- | --- | --- |
| `laravel-ci.yml` | push/PR to `master`, `VPS2D3D`, `MasterW` | `composer install` + full PHPUnit suite on PHP 8.3 (SQLite) |
| `deploy-vps.yml` | after CI succeeds on `master`, or manually | rsync app files (never `storage/`, `.env`, `vendor`), then `composer install --no-dev`, `migrate --force`, `db:seed --force`, `optimize`, health check `/up` |
| `android-apk.yml` | changes under `mobile/**`, or manually | `flutter analyze` + debug APK artifact |

Required repository secrets for deployment: `VPS_HOST`, `VPS_USER`,
`VPS_SSH_PRIVATE_KEY`, `VPS_PATH`, optional `VPS_SSH_PORT`. Optional variables:
`VPS_HEALTH_URL` (for example `https://your-domain/up`) and `WYA_SERVER_URL`
(baked into CI-built APKs). The deploy SSH key must be able to write
`VPS_PATH`, and `VPS_PATH` should be the non-public application directory
described in `docs/production-deployment.md`.

Two GitHub branches created earlier by Copilot
(`latyarthonenayapky-ux-mobile-agent-sale-entry`,
`latyarthonenayapky-ux-sale-entry-workflows`) contain only a Symfony test
workflow and an SLSA release workflow generated from a template. They do not
match this Laravel app, hold no mobile code, and should be deleted; the
workflows above replace them.
