# Raptor Security Checklist

Run this before production launch and after each major release.

## Access Control
- Verify every controller constructor calls `requireAuth()` except `AuthController`.
- Verify every POST form includes `csrf_token`.
- Verify team-scoped screens call `visibleUserIds()` or enforce ownership in the model.
- Confirm employer/analyst roles cannot access sales-person private data.

## Authentication
- Run migration `0014_security_hardening`.
- Confirm login lockout settings are present:
  - `auth.max_failed_attempts`
  - `auth.lockout_minutes`
- Confirm failed logins write to `login_attempts`.
- Confirm `security_events` records rate limit and lockout events.

## Transport And Browser Headers
- Production must run over HTTPS.
- Confirm session cookies are `HttpOnly`, `SameSite=Lax`, and `Secure` on HTTPS.
- Confirm response headers include `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, and `Permissions-Policy`.

## Data Retention
- Schedule `php bin/cron_retention.php` nightly.
- Schedule `php bin/cron_travel_rollup.php` nightly before retention.
- Confirm `location.retention_days` / `retention.location_days` matches policy.
- Confirm read notifications and audit/security records purge only after approved retention windows.

## Cron
- `php bin/cron_followups.php`
- `php bin/cron_task_carry_forward.php`
- `php bin/cron_travel_rollup.php`
- `php bin/cron_target_progress.php`
- `php bin/cron_scoring.php`
- `php bin/cron_report_summaries.php`
- `php bin/cron_alerts.php`
- `php bin/cron_push_notifications.php`
- `php bin/cron_retention.php`

## UAT Pilot
- Sales person: check-in, break, route capture, lead, follow-up, task, communication, meeting, check-out.
- Team leader: monitoring board, approvals, tasks review, team reports, notifications.
- Manager/admin: targets, scoring, reports exports, alert rules, retention settings.
