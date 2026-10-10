# Online 2D/3D and offline-sync production launch

For an end-to-end Ubuntu VPS procedure in Burmese, see
[`VPS_SETUP_MM.md`](VPS_SETUP_MM.md). For application workflows, see the
[Burmese User Manual](USER_MANUAL_MM.md).

This checklist covers authenticated 2D/3D sales entry, Admin live sales, Round management, results/settlements, period reports, and browser-local offline capture with later synchronization. Offline capture requires the Operator to open the Offline sales page while online once, uses the same browser/device, and requires a manual sync after connectivity returns. A conflict is retained for Admin review; there is no background sync or push notification while the app is closed.

## Server requirements

- PHP 8.3 or newer with the extensions required by Laravel 12 and `pdo_mysql`.
- MySQL or MariaDB with a persistent database and automated backups.
- Composer 2, HTTPS, and a web server whose document root is the application's `public/` directory.
- A cron service that runs the Laravel scheduler every minute.
- SMTP credentials if users need password-reset emails.

## Deploy

1. Deploy the release to a non-public application directory. Keep `.env`, `storage/`, and the rest of the source outside the web server document root.
2. Install production dependencies:

   ```sh
   composer install --no-dev --prefer-dist --optimize-autoloader
   ```

3. Configure `.env` with `APP_ENV=production`, `APP_DEBUG=false`, the HTTPS `APP_URL`, a generated `APP_KEY`, MySQL credentials (`DB_CONNECTION=mysql`), secure session cookies (`SESSION_SECURE_COOKIE=true`), and working SMTP settings. Keep this file private and preserve the same `APP_KEY` across deployments and backups.
4. Grant the web process write access to `storage/` and `bootstrap/cache/`. Run database migrations and seed the built-in parser rules:

   ```sh
   php artisan migrate --force
   php artisan db:seed --force
   ```

5. Create the initial software Owner interactively. This command refuses to run if any account already exists:

   ```sh
   php artisan thai2d3d:make-owner
   ```

6. Cache production configuration and views, then point the HTTPS virtual host at `public/`:

   ```sh
   php artisan optimize
   ```

7. Configure the scheduler:

   ```cron
   * * * * * cd /path/to/Thai2D3D && php artisan schedule:run >> /dev/null 2>&1
   ```

8. Before accepting live sales, sign in as Owner, create the Admin, then have the Admin create Operators and Agents. Configure the Round times, any 2D values to block (`00`–`99`), 3D number-count limits, 2D/3D payout multipliers, Agent commission rates, and any 2D/3D Hot Numbers and per-Agent Amount Limits. Use a separate Operator login to claim an Agent and enter controlled 2D and 3D sales.

## Go-live checks

- Confirm `https://<host>/up` returns a healthy response and HTTP redirects to HTTPS.
- Confirm the Admin dashboard shows only that Admin's business, including accepted 2D and 3D totals; rejected/excluded entries must not increase accepted totals.
- Confirm an Operator can enter sales only for their claimed Agent in an open Round, and cannot view another Operator's sales.
- Confirm the Round scheduler opens and closes a configured Round and closes its Agent sessions. Do not rely on automatic Round lifecycle until the every-minute cron is verified.
- Confirm 2D and 3D result entry and settlements after closing a controlled Round; compare accepted stakes, winning stakes, winnings, commissions, and business net against sale details.
- On each supported mobile browser, open an Agent's Offline sales page while signed in and online, queue both 2D and 3D entries, close/reopen the page, disconnect, and confirm the queue persists. Reconnect and manually sync; verify an accepted sale, an idempotent retry, an invalid entry retained locally, and a Hot Number/closed-Round conflict moved to Admin review and audited when approved or rejected.
- Treat browser-local IndexedDB as a temporary queue, not a backup. Test device loss/storage clearing recovery procedures and restrict access to shared devices. Confirm browser notification permission and audio alerts while the Admin dashboard is open; do not expect closed-app push.
- Confirm database backups can be restored before onboarding real sales.

Do not announce production readiness until the mobile offline checks, MySQL migration, cron, HTTPS, and restore procedure are verified on the actual host.
