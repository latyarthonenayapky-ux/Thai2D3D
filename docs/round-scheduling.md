# Round scheduling operations

Daily Round schedules are configured by an Owner or Admin in **Setting → Daily Round schedule**. Only the **close time** is required for each Round; the **start time is optional**. The schedule no longer requires a 2D Number Limit. Times are interpreted in `Asia/Yangon`; the three periods must be ordered and non-overlapping by close time.

When a Round's start time is left blank, it opens automatically the moment the previous Round on the same date closes (the first Round opens at the start of the day). This keeps Sale Entry continuously open through the day, controlled only by the configured close times. A Round with an explicit start time opens at that start time instead.

The Laravel scheduler must run every minute on the production server. Configure the server cron to invoke:

```cron
* * * * * cd /path/to/Thai2D3D && php artisan schedule:run >> /dev/null 2>&1
```

The scheduled `rounds:sync-schedule` command creates today's and tomorrow's Round records for configured schedules, opens Rounds when they become due (at their start time, or at the previous Round's close time when no start time is set), and closes them at their configured close time. Closing a Round also closes its open Agent Sessions, and sale processing independently checks that the Round is open. An Admin can manually close a Round at any time; the scheduler keeps that Round closed. A manually reopened Round remains open after its regular close time and closes when a later Round automatically opens.

Changing the schedule updates future and unopened current-day Rounds. A Round that has opened, or has Agent Session history, keeps its saved time. Configure 2D Number Limit values in Sale Entry: only exact listed values from `00` to `99` are blocked. The separate 3D number-count limit is configured in 3D settings.

The lifecycle migration adds unique constraints for one Round per date/number and one Agent per Operator. If an existing database contains duplicate records under either rule, resolve those duplicates before retrying the migration.
