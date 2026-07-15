# Raptor CRM → Sales-Force Monitoring & Performance System
## Upgrade Plan (v1.0)

**Prepared as:** Product + CRM + sales-automation + architecture review
**Date:** 2026-07-06
**Baseline analyzed:** Raptor CRM codebase (PHP 8 MVC, MySQL 8/PDO), 3 requirement docs, live `dashboard_schema.sql`, all controllers/models/views, `bin/` migrations.

### Locked architectural decisions (from stakeholder)
1. **Field app = PWA** on the existing PHP stack (a new REST/JSON API layer will be added).
2. **Hosting = VPS/cloud** (enables cron workers, object storage, background jobs).
3. **Comms tracking (v1) = manual entry + proof upload** (no paid call/WhatsApp/email integrations yet).
4. **Delivery = add sales module to the same app/DB**, reusing auth/RBAC/MVC core.

> **Key technical honesty note:** A PWA cannot do reliable 24/7 background GPS or fake-GPS detection. v1 uses *periodic foreground capture + event-based check-ins*. The schema is designed so a future thin native Android wrapper can feed the same tables with zero data-model change.

---

## 20. Current Application Summary

Raptor CRM today is a **digital-marketing-agency analytics CRM**, not a sales-force tool.

- **Stack:** PHP 8 MVC, MySQL 8 (PDO), Bootstrap 5 + jQuery, ApexCharts/Chart.js. Server-rendered pages via `?route=controller/method` ([app/core/App.php](app/core/App.php)). No REST API in practice.
- **Security core (reusable):** bcrypt auth, CSRF auto-enforced on POST, 30-min idle timeout, session-cached RBAC (`hasPermission`) in [app/core/Controller.php](app/core/Controller.php).
- **Roles:** `admin`, `manager`, `analyst`, `employer(client)` — an **agency↔client** model.
- **Modules present:** Auth, 3 marketing dashboards (Executive / Channels / Customer Intelligence), Clients, Campaigns, Leads, Tasks, Invoices+Payments, Reports, Users, Settings; plus `daily_activity_logs`, `notifications`, `calendar_events` from `bin/` migrations.
- **Data:** clients, leads (thin), campaigns, social_accounts/posts/analytics, aggregated metrics, demographics/sentiment, invoices/payments, activity_logs, settings.

**Reusable ~15%:** auth/CSRF/RBAC core, MVC skeleton, users/roles/permissions tables, Axiora Pulse UI shell, Leads/Tasks/Notifications scaffolding (all need heavy extension). **~85% of the sales-monitoring product is net-new.**

---

## 21. Gap Analysis

| Target capability | Today | Action |
|---|---|---|
| Attendance / login-logout / hours / late-early | none (text log only) | **NEW** `attendance`, `attendance_sessions`, `breaks` |
| Selfie at login/logout | none (no uploads) | **NEW** object storage + `attendance` selfie refs |
| Location / travel / route / distance / geofence | none (no lat/long anywhere) | **NEW** `location_logs`, `travel_summary`, `geofences` |
| Target planning (calls…revenue) | none | **NEW** `targets`, `target_items` |
| Target achievement tracking | none | **NEW** derived from activities + `target_progress` |
| Daily task mgmt (proof, carry-forward) | partial `tasks` | **EXTEND** tasks (+proof, +carry-forward, +review) |
| Lead full lifecycle | `new/contacted/qualified/lost` only | **EXTEND** status enum + `lead_status_history` |
| Follow-ups / reminders / ageing / escalation | none | **NEW** `follow_ups`, cron workers |
| Comms tracking (call/WA/SMS/email) | social counters only | **NEW** `communications` (manual + proof) |
| Meetings / demos / check-in-out / outcome | generic calendar only | **NEW** `meetings`, `meeting_checkins` |
| Manager live-monitoring | marketing mock data | **NEW** manager dashboards on real activity |
| Employee performance scoring | none | **NEW** `performance_scores` + scoring engine |
| Team hierarchy / team leader role | single `reporting_manager_id` | **NEW** `teams`, roles `sales_person`,`team_leader` |
| Mobile field app | desktop-only | **NEW** PWA + REST API |
| Notifications/alerts engine | table exists, no engine | **EXTEND** + cron + push |

---

## 22. Recommended New Modules

1. **Attendance & Shift** — check-in/out, selfie, geo-stamp, hours, late/early, breaks, approvals.
2. **Location & Field Tracking** — periodic capture, client check-ins, distance, geofence, route map.
3. **Target Planning & Achievement** — plan by activity type/period/scope; planned-vs-achieved.
4. **Task Management (extended)** — assignment, priority, proof, carry-forward, review.
5. **Lead Management (extended)** — full lifecycle, sources, value, probability, assignment.
6. **Follow-up Engine** — scheduling, reminders, ageing, missed-follow-up escalation.
7. **Communication Log** — manual call/WhatsApp/SMS/email/social entries with proof.
8. **Meetings & Demos** — schedule, check-in/out, geo+selfie proof, outcome.
9. **Manager Monitoring** — live status, team performance, pipeline, alerts.
10. **Performance & Scoring Engine** — weighted scores, ranking, trends.
11. **Notifications & Alerts** — rules, cron dispatch, web push.
12. **Admin & Config** — teams, territories, products, sources, scoring weights, geofence rules, retention.
13. **Reports & Exports** — 22 report types, PDF/Excel/CSV.
14. **Field PWA** — installable mobile app for sales persons.

---

## 23. User Role Structure

Extend `roles` with `sales_person` and `team_leader`; add a real `teams` hierarchy. Keep `admin`, `manager`; keep `analyst`/`employer` for the legacy marketing side.

| Role | Scope |
|---|---|
| **Admin** | Everything: users, teams, territories, products, scoring rules, geofences, integrations, retention, audit. |
| **Manager** | Multi-team oversight: assign leads/targets/tasks, approve attendance exceptions, view all monitoring + reports for their teams, escalations. |
| **Team Leader** | One team: assign/reassign within team, approve their team's attendance, review tasks, first-line escalation, team dashboard. |
| **Sales Person** | Self only (via PWA): attendance, location, targets, tasks, leads, follow-ups, comms, meetings/demos, own performance. |
| **Analyst** *(legacy)* | Read-only analytics/reports. |
| **Employer/Client** *(legacy)* | Read-only executive view. |

**Hierarchy:** `sales_person → team_leader → manager → admin`. Add `employees.team_id`; use existing `reporting_manager_id` for direct-report links. Data visibility is scoped by team subtree.

---

## 24. Complete Feature List (by module)

**Attendance:** login/logout, working-hours calc, late-login, early-logout, break tracking, daily status (present/half/absent/leave/holiday), login selfie, logout selfie, geo-stamp both ends, device+IP capture, geofence check, PWA-integrity flags (mock-location hint where the browser exposes it), manager/TL exception approval.

**Location & Travel:** periodic GPS while app active, client check-in/out with geo-verification, time-at-location, distance travelled (haversine sum), route polyline, daily travel summary, location-mismatch alerts, map view for managers.

**Target Planning:** per activity type (calls, emails, WhatsApp, SMS, social posts, meetings, demos, follow-ups, lead-gen, conversions, revenue, collections, visits, proposals); daily/weekly/monthly; scope = employee/team/product/territory; auto-generated templates; manager/TL approval.

**Target Achievement:** planned vs achieved, pending, missed, overachieved, conversion %, productivity %, revenue achievement, per-activity completion rates — all derived from logged activities.

**Tasks:** create (manager/TL/self), priority, deadline, status, remarks, proof attachment, completion %, pending carry-forward, review/approval, per-task performance.

**Leads:** full lifecycle (below), full data capture (below), assignment, reassignment with history, duplicate detection, ageing, hot/warm/cold.

**Follow-ups:** reminders, auto-scheduling on stage change, missed-follow-up alerts, history, next-action, ageing, escalation.

**Communications (manual+proof):** calls made/received/missed + duration, emails, WhatsApp, SMS, social messages/posts, campaign-wise responses, outcome, proof upload.

**Meetings & Demos:** schedule, check-in/out, geo+selfie proof, notes, outcome, client feedback, next follow-up, manager visibility.

**Manager Monitoring:** live login status, live/last location, attendance, task status, target planned-vs-achieved, comms completed, meetings/demos, leads gen/followed/converted, pending+missed follow-ups, pipeline, revenue forecast, low/high performers, productivity scores.

**Performance:** attendance, punctuality, activity, target, lead-gen, follow-up, conversion, revenue, meeting, demo scores → weighted overall rating; daily/weekly/monthly; team ranking.

**Admin:** employees, managers, teams, branches, territories, products, lead sources, target categories, task categories, attendance rules, location rules, scoring weights, permissions, reports, export, integrations.

**Alerts:** late/no login, missing logout, location disabled, target not updated, task overdue, follow-up due/missed, lead unattended, meeting/demo reminders, low performance, approval pending, high-value lead pending, lead not contacted within SLA.

**Automation:** auto lead assignment, auto reminders, auto follow-up scheduling, auto escalation, auto scoring, auto daily/weekly/monthly summaries, auto inactive-lead detection, auto duplicate detection, auto target progress, auto manager alerts.

---

## 25. Suggested Database Structure (extends existing schema)

New/changed tables (MySQL 8, InnoDB, utf8mb4). Existing `users`, `roles`, `permissions`, `employees`, `leads`, `tasks`, `notifications`, `activity_logs`, `settings` are extended, not replaced.

```sql
-- ROLES: seed two new rows: 'sales_person', 'team_leader'

-- TEAMS & HIERARCHY
teams(team_id PK, name, team_leader_user_id FK users, manager_user_id FK users,
      branch_id FK, territory_id FK, status, created_at)
branches(branch_id PK, name, address, lat, lng, created_at)
territories(territory_id PK, name, description)
-- employees: ADD team_id FK, branch_id FK  (reporting_manager_id already exists)

-- ATTENDANCE
attendance(attendance_id PK, user_id FK, work_date DATE,
   login_at DATETIME, logout_at DATETIME,
   login_lat DECIMAL(10,7), login_lng DECIMAL(10,7),
   logout_lat DECIMAL(10,7), logout_lng DECIMAL(10,7),
   login_selfie_url, logout_selfie_url,
   login_device, login_ip, logout_device, logout_ip,
   worked_minutes INT, break_minutes INT,
   is_late BOOL, is_early_logout BOOL,
   geofence_ok BOOL, integrity_flag ENUM('ok','suspect'),
   status ENUM('present','half_day','absent','leave','holiday','wfh'),
   approval_status ENUM('auto','pending','approved','rejected'),
   approved_by FK, remarks, created_at,
   UNIQUE(user_id, work_date))
breaks(break_id PK, attendance_id FK, start_at, end_at, minutes, reason)

-- LOCATION & TRAVEL
location_logs(loc_id BIGINT PK, user_id FK, captured_at DATETIME,
   lat, lng, accuracy_m, source ENUM('periodic','checkin','manual'),
   battery_pct, is_moving BOOL)   -- index (user_id, captured_at)
geofences(geofence_id PK, name, center_lat, center_lng, radius_m,
   type ENUM('office','client','territory'), ref_id, active BOOL)
travel_summary(id PK, user_id FK, work_date DATE, distance_km DECIMAL(8,2),
   points_count INT, first_at, last_at, route_polyline MEDIUMTEXT,
   UNIQUE(user_id, work_date))

-- TARGETS
target_categories(category_id PK, name, unit ENUM('count','currency','percent'), active)
targets(target_id PK, owner_type ENUM('employee','team'), owner_id,
   period ENUM('daily','weekly','monthly'), period_start DATE, period_end DATE,
   product_id NULL, territory_id NULL,
   created_by FK, approval_status ENUM('pending','approved','rejected'),
   approved_by FK, created_at)
target_items(item_id PK, target_id FK, category_id FK, planned_value DECIMAL(12,2))
target_progress(id PK, target_id FK, category_id FK,
   achieved_value DECIMAL(12,2), computed_at)  -- refreshed by cron

-- TASKS: EXTEND existing tasks table
-- ADD: proof_url, is_carry_forward BOOL, source_task_id, review_status, reviewed_by
--      (start_date, completed_at, progress_percent, remarks already added via bin/)

-- LEADS: EXTEND existing leads table
-- CHANGE status ENUM -> ('new','assigned','contacted','qualified','follow_up',
--   'meeting','demo','proposal','negotiation','converted','lost','reopened','closed')
-- ADD: company_name, campaign_source, product_id, location, priority,
--   next_follow_up_at, lost_reason, converted_at, probability, team_id
lead_status_history(id PK, lead_id FK, from_status, to_status,
   changed_by FK, note, changed_at)
lead_assignments(id PK, lead_id FK, from_user_id, to_user_id,
   assigned_by FK, reason, assigned_at)

-- FOLLOW-UPS
follow_ups(followup_id PK, lead_id FK, user_id FK, due_at DATETIME,
   channel ENUM('call','whatsapp','email','sms','meeting','visit'),
   status ENUM('scheduled','done','missed','cancelled'),
   outcome, next_action, completed_at, created_at)

-- COMMUNICATIONS (manual + proof)
communications(comm_id PK, user_id FK, lead_id NULL, type
   ENUM('call_made','call_received','call_missed','email','whatsapp','sms',
        'social_msg','social_post'), direction ENUM('in','out'),
   count INT DEFAULT 1, duration_sec INT NULL, outcome, notes,
   proof_url, occurred_at, created_at)

-- MEETINGS & DEMOS
meetings(meeting_id PK, lead_id NULL, user_id FK, type ENUM('meeting','demo'),
   scheduled_at, location_label, geo_lat, geo_lng, status
   ENUM('scheduled','checked_in','completed','cancelled','no_show'),
   outcome, client_feedback, next_follow_up_at, created_at)
meeting_checkins(id PK, meeting_id FK, phase ENUM('in','out'),
   at DATETIME, lat, lng, selfie_url, geofence_ok BOOL)

-- PRODUCTS & SOURCES
products(product_id PK, name, category, price DECIMAL(12,2), active)
lead_sources(source_id PK, name, channel, active)

-- PERFORMANCE
performance_scores(score_id PK, user_id FK, period ENUM('daily','weekly','monthly'),
   period_start DATE,
   attendance_score, punctuality_score, activity_score, target_score,
   lead_gen_score, follow_up_score, conversion_score, revenue_score,
   meeting_score, demo_score, overall_score DECIMAL(5,2),
   team_rank INT, computed_at, UNIQUE(user_id, period, period_start))
manager_reviews(review_id PK, user_id FK, reviewer_id FK, period_start,
   rating TINYINT, comments, created_at)
scoring_weights(metric VARCHAR PK, weight DECIMAL(5,2))  -- admin-editable

-- ATTACHMENTS (generic) & NOTIFICATIONS (extend)
attachments(att_id PK, owner_type, owner_id, file_url, mime, size_bytes,
   uploaded_by FK, created_at)
-- notifications: ADD action_url, severity, category (rule-driven)
alert_rules(rule_id PK, code, description, threshold_json, active)

-- AUDIT (extend activity_logs): ADD entity_type, entity_id, before_json, after_json
-- CONSENT
location_consents(id PK, user_id FK, consented BOOL, consented_at, ip, policy_version)
```

---

## 26. Workflow Diagrams (text)

**System data flow**
```
[Sales PWA] --HTTPS/JSON--> [PHP REST API] --PDO--> [MySQL]
     |  (selfie/proof upload)        |                  ^
     v                               v                  |
 [Object Storage]            [Cron Workers] --refresh--/
                             (reminders, scoring, escalation,
                              travel rollup, summaries)
                                     |
                                     v
                          [Notifications + Web Push]
```

## 27. Employee (Sales Person) Daily Workflow
```
Open PWA -> Login (selfie + GPS + device) -> Attendance=Present (geofence check)
  -> See today: approved Targets + assigned Tasks + due Follow-ups + meetings
  -> Field work:
       Check-in at client (GPS+selfie) -> log Meeting/Demo -> outcome + next follow-up
       Log Calls/WhatsApp/Email (count+outcome+proof)
       Create/convert Leads; move lead stage; schedule follow-up
       Periodic GPS captured while app active
  -> Update task progress + upload proof
  -> Logout (selfie + GPS) -> hours + distance computed
  -> Cron rolls up travel_summary, target_progress, next-day carry-forward
```

## 28. Manager / Team-Leader Monitoring Workflow
```
Login -> Team live board:
   who's in / late / no-login / location-disabled
   live/last location map (foreground caveat noted)
   today: targets planned vs achieved, tasks, comms, meetings, leads
Review queue:
   approve/reject attendance exceptions
   approve planned targets
   review completed tasks + proof
Pipeline:
   leads by stage, ageing, pending + missed follow-ups
   escalations (unattended/high-value leads)
Act:
   reassign leads, adjust targets, send nudge/notification
Periodic:
   weekly/monthly performance + ranking review
```

## 29. Lead Lifecycle Workflow
```
Generated -> Assigned -> Contacted -> Qualified
   -> Follow-up scheduled -> Meeting -> Demo -> Proposal sent
   -> Negotiation -> Converted (converted_at, revenue) 
                  \-> Lost (lost_reason) -> Reopened -> ...
Every transition writes lead_status_history + may auto-create a follow_up.
SLA: if not Contacted within X hrs -> alert + escalation.
Ageing: days in current stage -> hot/warm/cold reclassification.
```

## 30. Performance Scoring Logic
```
overall = Σ(metric_score × weight)      (weights admin-editable in scoring_weights)

attendance_score   = present_days / working_days
punctuality_score  = on_time_logins / total_logins
activity_score     = Σ achieved activity counts / Σ planned
target_score       = achieved_value / planned_value  (capped, overachieve bonus)
lead_gen_score     = leads_created / target
follow_up_score    = completed_followups / due_followups
conversion_score   = converted / qualified
revenue_score      = revenue_achieved / revenue_target
meeting_score      = meetings_done / meetings_planned
demo_score         = demos_done / demos_planned

Each normalized 0-100; overall 0-100 -> band (A/B/C/D); team_rank = rank within team.
Recomputed nightly (daily) + rolling weekly/monthly by cron.
```

---

## 31. Dashboards & Reports

**Dashboards:** (a) Sales-person self dashboard, (b) Team-leader team board + live map, (c) Manager multi-team monitoring + pipeline + forecast, (d) Admin config/ops, (e) legacy marketing dashboards retained.

**22 Reports** (PDF/Excel/CSV via existing DomPDF/PhpSpreadsheet plan): attendance, login/logout, location-travel, distance, task completion, target planned-vs-achieved, lead generation, lead status, lead conversion, follow-up, missed follow-up, call activity, email activity, message activity, meeting, demo, revenue, employee performance, manager performance, team performance, daily summary, monthly summary.

---

## 32. Mobile PWA Requirements
- Installable PWA: manifest + service worker; offline queue for activity logs (sync on reconnect).
- Camera selfie via `getUserMedia` / `<input capture>`; upload to object storage.
- Geolocation via `navigator.geolocation` (periodic `watchPosition` while foreground) — **document the foreground-only limitation to users**.
- Web Push for reminders/alerts.
- Screens: attendance, today (targets+tasks+follow-ups), leads, lead detail + stage move, follow-up, meeting/demo check-in, comms log, my performance.
- Consent screen for location tracking (working-hours-only).

## 33. Web Admin Panel Requirements
- Manage users/roles, teams, branches, territories, products, lead sources, target/task categories.
- Attendance rules (shift times, grace, geofence radius), location rules (capture interval, working-hours window), scoring weights.
- Approvals, reassignment, alert-rule config, integrations, data retention, audit-log viewer, exports.

## 34. API Requirements (new REST layer for PWA)
```
POST /api/auth/login, /api/auth/logout
POST /api/attendance/checkin        (selfie, lat/lng, device)
POST /api/attendance/checkout
POST /api/attendance/break
POST /api/location/ping             (batch periodic points)
GET  /api/me/today                  (targets, tasks, follow-ups, meetings)
GET/POST/PATCH /api/leads, /api/leads/{id}, /api/leads/{id}/status
POST /api/follow-ups, PATCH /api/follow-ups/{id}
POST /api/communications            (+proof)
POST /api/meetings, /api/meetings/{id}/checkin
POST /api/tasks/{id}/progress       (+proof)
GET  /api/me/performance
-- Manager: GET /api/team/live, /api/team/performance, /api/pipeline
Auth via token/session; CSRF for cookie flows; rate-limit; all writes audited.
```

## 35. Integration Requirements
- **v1 (manual):** proof-upload for comms; object storage (S3-compatible / VPS disk).
- **Maps:** Google Maps / Leaflet+OpenStreetMap for route + geofence display.
- **Push:** Web Push (VAPID) — free.
- **Email:** existing PHPMailer/SMTP for password reset + alert digests.
- **Future (phase 3+):** WhatsApp Business API, Android call-log reader (native wrapper), IMAP email sync, payment gateway for collections.

## 36. Security & Privacy
- RBAC scoped by team subtree; server-side permission checks on every API route.
- **Location consent** (`location_consents`), **working-hours-only** capture window enforced server + client side.
- Encrypt tokens at rest; HTTPS only; signed, size/type-validated uploads to private storage (no public listing).
- Full audit (`activity_logs` extended with before/after JSON), access logs, admin-action tracking.
- Configurable **data-retention** (e.g., purge raw location points > N days, keep daily summaries).
- Keep existing bcrypt + CSRF; add API rate-limiting and brute-force lockout.

---

## 37. Development Roadmap

**Phase 0 — Foundations (infra + API):** VPS migration, object storage, add `sales_person`/`team_leader` roles + `teams`/`branches`/`territories`, build REST API layer + token auth, PWA shell (manifest/service worker/push).

**Phase 1 — MVP (see §38).**

**Phase 2 — Monitoring depth:** live manager map, travel rollups, geofencing, performance scoring engine + ranking, full report suite, alert-rule engine.

**Phase 3 — Automation & intelligence:** auto lead assignment, escalation, duplicate detection, auto summaries, forecast, Gemini-powered insights (reuse `GeminiService`).

**Phase 4 — Deep integrations:** native Android wrapper for background GPS + call logs, WhatsApp Business API, email sync, collections/payments.

## 38. MVP Feature List (Phase 1)
1. Roles + teams + sales-person accounts.
2. Attendance: check-in/out with selfie + GPS + device/IP, hours, late/early, geofence check, TL/manager approval.
3. Leads (extended lifecycle) + assignment + status history.
4. Follow-ups with reminders (cron) + missed-follow-up alerts.
5. Tasks (assign, proof, complete, carry-forward).
6. Communications manual log + proof.
7. Meetings/demos with check-in (GPS+selfie) + outcome.
8. Targets (plan + approval) + basic planned-vs-achieved.
9. Sales-person PWA dashboard + Team-leader/Manager monitoring board.
10. Core reports: attendance, lead status, follow-up, daily summary.
11. Notifications + Web Push. Consent + working-hours capture. Audit logging.

## 39. Advanced Feature List (future)
Fake-GPS/native background tracking, AI lead scoring + forecast, auto lead assignment & routing, WhatsApp/call/email auto-capture, advanced attribution, collections/payment tracking, gamification/leaderboards, offline-first heavy sync, anomaly detection on location/activity, configurable custom KPIs.

---

## Immediate next steps
1. Confirm MVP scope (§38) and role/team model (§23).
2. Stand up VPS + object storage; run role/teams migrations.
3. Build the REST API + PWA shell (Phase 0), then attendance + leads first (highest-value, lowest-integration-risk).
