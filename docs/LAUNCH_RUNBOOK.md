# Raptor Launch Runbook

## Pre-Launch
1. Apply migrations with `php bin/migrate.php`.
2. Set production environment variables from `app/config/config.sample.php`.
3. Confirm HTTPS and `URLROOT` point to the production `/public` URL.
4. Create the first admin user and verify role permissions.
5. Configure Admin Configuration Hub:
   - attendance shift and geofence rules
   - location retention
   - alert rules and thresholds
   - report digest recipients
   - Web Push VAPID keys, if push delivery is enabled

## Cron Schedule
Use the server cron user that owns the app files.

```cron
*/5 * * * * php /path/to/RAPTOR-main/bin/cron_alerts.php
*/5 * * * * php /path/to/RAPTOR-main/bin/cron_push_notifications.php
15 * * * * php /path/to/RAPTOR-main/bin/cron_followups.php
30 0 * * * php /path/to/RAPTOR-main/bin/cron_travel_rollup.php
45 0 * * * php /path/to/RAPTOR-main/bin/cron_task_carry_forward.php
0 1 * * * php /path/to/RAPTOR-main/bin/cron_target_progress.php
15 1 * * * php /path/to/RAPTOR-main/bin/cron_scoring.php
30 1 * * * php /path/to/RAPTOR-main/bin/cron_report_summaries.php
0 2 * * * php /path/to/RAPTOR-main/bin/cron_retention.php
```

## Smoke Test
1. Login as admin, manager/team leader, and sales person.
2. Sales person performs a full day flow from attendance check-in to check-out.
3. Manager opens Command Center and verifies the sales person appears correctly.
4. Create and convert a lead, then confirm it appears in reports and performance.
5. Run `Reports Center` CSV export and print/PDF export.
6. Trigger `php bin/cron_alerts.php` and confirm notifications appear.

## Rollback
1. Put the site behind maintenance mode at the web server.
2. Restore the latest database backup.
3. Restore the previous release directory.
4. Re-run smoke tests before reopening access.
