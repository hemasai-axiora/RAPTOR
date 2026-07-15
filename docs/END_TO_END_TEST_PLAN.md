# Raptor CRM End-to-End Test Plan

This plan validates Raptor CRM with the six-month demo dataset.

## 1. Test Data Preparation

Run migrations first:

```bash
php bin/migrate.php
```

Load the six-month demo dataset:

```bash
php bin/seed_6_month_demo_data.php --fresh
```

Expected seeded users:

| Role | Count | Sample Login |
| --- | ---: | --- |
| Admin | 2 | `admin1@raptor.test` |
| HR | 2 | `hr1@raptor.test` |
| Analyst | 2 | `analyst1@raptor.test` |
| Manager | 10 | `manager1@raptor.test` |
| Employee | 90 | `employee1@raptor.test` |

Default password for all demo users:

```text
Raptor@12345
```

Expected data volume:

- 10 manager-led teams
- 90 employees assigned across those teams
- 6 months of attendance and location/travel summaries
- Leads, campaigns, clients, tasks, follow-ups, communications, meetings, invoices, targets, notifications, dashboard preferences, dashboard templates, and edit requests

## 2. Smoke Tests

### Application Boot

1. Open the app root URL.
2. Confirm the login screen loads.
3. Confirm no PHP warning or fatal error appears.
4. Confirm the white/blue theme is the default.
5. Toggle dark theme and refresh; the selected theme should persist.

### Login and Logout

Run for Admin, HR, Analyst, Manager, and Employee:

1. Login with the sample account.
2. Confirm role-appropriate landing page.
3. Confirm sidebar only shows allowed modules.
4. Logout.
5. Confirm the login screen appears again.

## 3. Role and Access Control Tests

### Admin

1. Login as `admin1@raptor.test`.
2. Confirm access to Dashboard Module, Reports Module, Employee Management, Organization, Data Edit Requests, Settings, finance, operations, monitoring, alerts, and notifications.
3. Open Employee Management and verify all roles/users are visible.
4. Open Data Edit Requests and verify pending requests are listed.
5. Approve one request and reject another.
6. Verify approved request changes status and rejected request keeps the source record unchanged.
7. Attempt to open a delete route manually, for example:

```text
index.php?route=leads/delete/1
```

Expected: deletion is blocked by governance policy.

### HR

1. Login as `hr1@raptor.test`.
2. Confirm Employee Management is visible.
3. Add a new employee.
4. Edit the employee status/details.
5. Verify HR cannot assign Admin role.
6. Verify HR cannot access Settings, Data Edit Approval, Organization admin controls, or finance deletion paths.

### Manager

1. Login as `manager1@raptor.test`.
2. Confirm dashboard and monitoring show only subordinate/team data.
3. Open Task Board.
4. Assign a task to a subordinate.
5. Confirm the task appears in the subordinate employee account.
6. Open Data Edit Requests.
7. Submit an update request for a lead with a clear manager comment.
8. Confirm Manager cannot approve the request.
9. Attempt to submit a normal edit form for an existing record.
10. Expected: blocked with governance message requiring edit request.

### Employee

1. Login as `employee1@raptor.test`.
2. Confirm attendance, follow-ups, leads, communications, meetings, tasks, targets, and performance views load.
3. Confirm employee sees only self-scoped data.
4. Update task progress.
5. Complete a task with proof upload.
6. Log a communication.
7. Schedule or complete a follow-up if allowed by workflow.
8. Confirm employee cannot access Employee Management, Settings, Data Edit Requests, Organization, or admin dashboards.

### Analyst

1. Login as `analyst1@raptor.test`.
2. Confirm analytics dashboard access.
3. Open Dashboard Templates.
4. Create a dashboard template.
5. Confirm template appears in the template list.
6. Confirm Analyst cannot access Employee Management or Data Edit Approval.

## 4. Dashboard Module Tests

Validate all five dedicated dashboards:

1. Executive & Analytics Overview
2. Sales Command Center
3. Field Activity
4. Pipeline & Revenue
5. Performance & Targets

For each accessible dashboard:

1. Load the dashboard.
2. Confirm KPI cards render values.
3. Confirm charts/lists render without JavaScript errors.
4. Configure widgets and save.
5. Refresh and confirm preferences persist.
6. Switch date range and confirm data reloads.

## 5. Reports Module Tests

1. Login as Admin or Analyst.
2. Open Reports Module.
3. Generate each available report type.
4. Confirm filters work.
5. Confirm results include seeded six-month data.
6. Confirm report summaries do not expose data outside role scope.

## 6. Sales Operations Tests

### Leads

1. Search/filter by status, source, assignee, and age.
2. Open lead detail.
3. Move lead status.
4. Verify status history and journey log.
5. Verify duplicate checks on add/edit.
6. Verify manager edit governance on existing lead edits.

### Tasks

1. Manager assigns tasks.
2. Employee updates progress.
3. Employee completes with proof.
4. Manager reviews completed task.
5. Rejected task returns to in-progress.
6. Carry-forward cron creates next-day task for overdue incomplete work.

### Attendance and Field Tracking

1. Employee checks in.
2. Verify late status if after shift start.
3. Verify geofence warning/approval behavior.
4. Employee checks out.
5. Manager views live board and day drill-down.
6. Travel rollup cron creates distance summaries.

### Follow-ups, Communications, Meetings

1. Schedule a follow-up for a lead.
2. Complete and miss follow-ups.
3. Log calls, WhatsApp, SMS, and email communications.
4. Schedule a meeting/demo.
5. Check in with location/selfie.
6. Complete meeting with outcome.

## 7. Security and Abuse Tests

1. Submit POST without CSRF token. Expected: 403.
2. Submit invalid CSRF token. Expected: 403.
3. Attempt rapid login failures. Expected: rate limit or lockout.
4. Attempt direct access to another manager's employee day view. Expected: 403.
5. Attempt employee access to admin route. Expected: redirect or 403.
6. Attempt direct `delete*` route. Expected: blocked.
7. Attempt upload above max file size. Expected: rejected.
8. Attempt unsupported upload type. Expected: rejected.
9. Confirm private file links go through controlled file endpoint.

## 8. Automation

Playwright tests are provided in:

```text
tests/e2e/raptor.e2e.spec.ts
```

Install dependencies:

```bash
npm install
npx playwright install
```

Run tests:

```bash
npm run test:e2e
```

Set a custom URL:

```bash
RAPTOR_BASE_URL=http://localhost:8080/public npm run test:e2e
```

On Windows PowerShell:

```powershell
$env:RAPTOR_BASE_URL="http://localhost:8080/public"
npm run test:e2e
```

## 9. Exit Criteria

The build is acceptable when:

- All role logins work.
- All modules load without PHP/JS fatal errors.
- Role scoping is correct.
- No physical delete path is available from UI or direct route.
- Manager edit requests require comments.
- Admin approval/rejection works.
- Employee data creation/management works for HR/Admin only.
- Dashboards and reports render seeded six-month data.
- Playwright smoke suite passes on a migrated and seeded environment.
