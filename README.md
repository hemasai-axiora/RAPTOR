# Raptor Sales Monitoring CRM

Raptor is a PHP/MySQL sales monitoring and CRM application for field sales teams, managers, analysts, HR, and admins. It combines attendance, geolocation, leads, follow-ups, tasks, communications, meetings, targets, performance scoring, dashboards, reports, alerts, and governed access control in one responsive web application.

## Core Modules

- **Authentication and RBAC**: Admin, Manager, Employee, Analyst, HR, Employer, and legacy Team Leader support.
- **Employee Management**: HR/Admin employee creation, activation/deactivation, role assignment, and directory management.
- **Attendance and Field Tracking**: Check-in/check-out, late flags, geofence support, selfie/proof uploads, private file serving, and route tracking.
- **Leads and Pipeline**: Lead capture, duplicate checks, lifecycle stages, assignment history, status history, pipeline board, and source tracking.
- **Follow-ups**: Scheduled follow-ups, reminders, completion/cancellation, and daily employee follow-up views.
- **Tasks**: Manager assignment, employee progress updates, proof upload, completion review, carry-forward logic, and task metrics.
- **Communications and Meetings**: Calls/messages/emails logs, meeting/demo scheduling, check-in proof, outcomes, and lead timeline support.
- **Targets and Performance**: Target definitions, target progress, scoring weights, rankings, and manager reviews.
- **Dashboard Module**: Five customizable dashboards plus analyst/admin dashboard template creation.
- **Reports Module**: Report center, report summaries, and report result views.
- **Alerts and Notifications**: Rule-driven notifications for late/no login, stale targets, overdue tasks, SLA breaches, low performance, and pending approvals.
- **Governance Workflow**: Managers request data edits with comments; only Admins can approve/reject. Physical deletion is disabled.
- **Security Hardening**: CSRF protection, session timeout, login lockout/rate limiting, security event logs, private storage, and secure headers.

## Role Matrix

| Role | Access Summary |
| --- | --- |
| Admin | Full system control, settings, dashboards, reports, employee management, edit approvals, and organization setup. |
| Manager | Assigns and views subordinate tasks, sees scoped dashboards/monitoring, and submits data edit/archive requests with comments. |
| Employee | Uses self-service attendance, assigned leads/tasks/follow-ups, communications, meetings, targets, and performance views. |
| Analyst | Accesses analytics dashboards, reports, and creates custom dashboard templates. |
| HR | Creates and manages employee accounts and employee directory data. HR cannot assign Admin roles. |
| Employer | Read-only executive/summary dashboard access. |
| Team Leader | Legacy operational oversight role retained for backward compatibility. |

`employee` is the canonical field role. Existing `sales_person` references are supported as legacy compatibility during migration.

## Governance Rules

- No application route is allowed to execute `delete*` actions.
- Old model-level delete methods return `false` and do not run `DELETE FROM`.
- Retention workers are non-destructive while the no-delete policy is active.
- User removal is handled by deactivation, not deletion.
- Managers cannot directly submit normal edit forms; they must create a data edit request.
- Admins approve or reject manager requests from the Data Edit Requests module.
- Approved archive requests set archive metadata instead of physically deleting records.

## Tech Stack

- PHP MVC application with custom routing.
- MySQL 8 database.
- Bootstrap 5, DataTables, ApexCharts/Chart.js, Flatpickr, Font Awesome.
- Docker Compose support for local database/web services.

## Repository Structure

```text
app/
  config/          Environment-driven configuration
  controllers/     MVC controllers and route actions
  core/            Router, base controller/model, security, policy, storage
  models/          Database models and business logic
  views/           Layouts and module screens
bin/               Migration runner, cron workers, seed/test utilities
docs/              Launch and security checklists
migrations/        Ordered SQL/PHP database migrations
public/            Web entry point and public assets
```

## Local Setup

### Requirements

- PHP 8.1+ with PDO MySQL extension
- MySQL 8
- Apache/Nginx or PHP built-in server for local development
- Git
- Docker and Docker Compose, optional

### Configure Environment

Configuration is read from environment variables in [app/config/config.php](app/config/config.php). See [app/config/config.sample.php](app/config/config.sample.php) for all supported variables.

Typical local values:

```bash
DB_HOST=127.0.0.1
DB_USER=root
DB_PASS=rootpassword
DB_NAME=raptor_crm_db
URLROOT=http://localhost:8080/public
APP_ENV=development
STORAGE_PATH=/absolute/private/path/storage
```

For production, set `APP_ENV=production`, use strong database credentials, and place `STORAGE_PATH` outside the public web root.

### Database Setup

Create the database, then run migrations:

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS raptor_crm_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php bin/migrate.php
```

Check migration status:

```bash
php bin/migrate.php --status
```

### Six-Month Demo Dataset

For end-to-end testing and demos, load a large six-month sample dataset:

```bash
php bin/seed_6_month_demo_data.php --fresh
```

This creates 2 Admins, 2 HR users, 2 Analysts, 10 Managers, and 90 Employees, plus teams, attendance, location/travel summaries, leads, tasks, follow-ups, communications, meetings, invoices, targets, notifications, dashboard preferences, dashboard templates, and manager edit requests.

Default demo password:

```text
Raptor@12345
```

Sample accounts:

```text
admin1@raptor.test
hr1@raptor.test
analyst1@raptor.test
manager1@raptor.test
employee1@raptor.test
```

### Run Locally

With PHP installed locally:

```bash
php -S localhost:8080 -t public
```

Open:

```text
http://localhost:8080
```

With Docker Compose, this repo includes [docker-compose.yml](docker-compose.yml). The `web` service expects a PHP/Apache image named `mediatrack-web`; build or replace that image with your own PHP web image before running:

```bash
docker compose up -d
```

## Cron Workers

Schedule these as needed in production:

```bash
php bin/cron_alerts.php
php bin/cron_followups.php
php bin/cron_report_summaries.php
php bin/cron_scoring.php
php bin/cron_target_progress.php
php bin/cron_task_carry_forward.php
php bin/cron_travel_rollup.php
php bin/cron_retention.php
```

The retention cron is intentionally non-destructive under the current governance policy.

## Security Notes

- CSRF is enforced for authenticated POST/API writes.
- Session cookies are HTTP-only and SameSite Lax.
- Login attempts are rate-limited and can be locked.
- Uploaded proof/selfie files are stored privately and served through controlled endpoints.
- API endpoints return JSON auth/CSRF errors instead of redirecting HTML.
- Managers have scoped data visibility based on reporting/team structure.
- Admin-only approval is required for manager data edit/archive requests.

Review [docs/SECURITY_CHECKLIST.md](docs/SECURITY_CHECKLIST.md) before production launch.

## Testing

Manual end-to-end coverage is documented in [docs/END_TO_END_TEST_PLAN.md](docs/END_TO_END_TEST_PLAN.md).

Playwright automation is included under [tests/e2e](tests/e2e). Install and run:

```bash
npm install
npx playwright install
npm run test:e2e
```

Use a custom app URL:

```bash
RAPTOR_BASE_URL=http://localhost:8080/public npm run test:e2e
```

## Launch Checklist

See [docs/LAUNCH_RUNBOOK.md](docs/LAUNCH_RUNBOOK.md) for operational launch steps.

Minimum launch flow:

1. Configure production environment variables.
2. Create the MySQL database and run `php bin/migrate.php`.
3. Point the web server document root to `public/`.
4. Ensure private storage is writable by the PHP process.
5. Configure cron workers.
6. Confirm HTTPS, secure headers, and backup strategy.
7. Create initial Admin/HR/Manager users.
8. Verify role-scoped access, dashboard visibility, and edit request approvals.

## Current Verification Notes

The latest static sweep confirmed:

- No remaining application `DELETE FROM` statements.
- No visible delete routes, apart from employee deactivation wording.
- Physical delete methods have been disabled.
- `employee` is the canonical field role, with legacy `sales_person` compatibility.

Runtime PHP lint/migration execution should be run on a machine with PHP installed.

## License

Private/internal project unless a license is added by the repository owner.
