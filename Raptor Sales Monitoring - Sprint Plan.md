# Raptor Sales Monitoring — Sprint-Wise Delivery Plan (v1.0)

**Stack:** PHP 8 MVC + MySQL 8 (extend existing Raptor CRM). Internal JSON endpoints (AJAX) for dynamic UI.
**Delivery:** **Web only**, fully responsive (mobile browser → desktop). No native app.
**Cadence:** 2-week sprints. **Sprint 0 + 14 delivery sprints ≈ 30 weeks (~7 months).**

### Team assumption (adjust velocity if different)
2 × PHP backend, 1 × frontend (Bootstrap/JS), 1 × QA, 0.5 × PM/UX. If solo/pair, multiply timeline ~2–2.5×; sprint *content* stays the same.

### Web-only reality (accepted)
- GPS/selfie via `navigator.geolocation` + `getUserMedia`/`<input capture>` — **foreground only**. No background tracking, no reliable fake-GPS detection. Location = periodic capture while tab open + event check-ins.
- Optional: make it an installable PWA (manifest + service worker + Web Push) — still web, no app store.

---

## Cross-cutting standards (apply every sprint — part of Definition of Done)

**Responsive-first UI**
- Mobile-first CSS; single layout that reflows. Breakpoints: `<576` phone, `576–992` tablet, `>992` desktop.
- Sidebar collapses to a bottom nav / hamburger drawer on phones; tables become stacked cards or horizontally scroll in an `overflow-x` wrapper (never break the page width).
- All forms single-column on phone; touch targets ≥ 44px; charts resize via ApexCharts responsive config.
- Test each screen at 360px, 768px, 1440px before the story is "done."

**Engineering DoD (every story):** PDO prepared statements, server-side RBAC check on every route/endpoint, CSRF on writes, input validation + output escaping, audit-log entry on state change, works offline-tolerant where relevant, QA-passed on phone + desktop, no console errors.

---

## Sprint 0 — Foundations & Infrastructure (2 wks)
**Goal:** Everything needed before feature work; nothing user-facing yet.
- Provision VPS/cloud, PHP 8 + MySQL 8, HTTPS, backups, cron enabled.
- Object storage (S3-compatible or private disk) for selfies/proof; signed-URL upload helper + type/size validation.
- Add a lightweight **JSON response layer** in the base Controller (`json()` helper) + token/session auth guard for AJAX endpoints.
- CI: lint, migration runner (formalize the `bin/` pattern into ordered migrations), seed script.
- Responsive layout refactor of `layouts/main.php`: mobile drawer nav + bottom nav; verify existing pages reflow.
- Env config split (dev/prod), move DB creds out of committed `config.php`.

**Deliverables:** deployable env, migration framework, upload service, responsive shell.
**Acceptance:** existing app runs on VPS over HTTPS on a phone screen without horizontal scroll; a test file uploads to storage and returns a signed URL.

---

## Sprint 1 — Roles, Teams, Hierarchy & Access Control (2 wks)
**Goal:** The org model the whole product depends on.
- Migration: seed roles `sales_person`, `team_leader`; create `teams`, `branches`, `territories`; add `employees.team_id`, `employees.branch_id`.
- RBAC: extend permission set + role_permissions for new roles; team-subtree data scoping helper (`visibleUserIds()`).
- Admin CRUD: teams, branches, territories, assign team leader/manager, assign sales persons to teams.
- Redirect map: sales_person → self dashboard; team_leader/manager → monitoring board.
- Extend `activity_logs` (entity_type, entity_id, before/after JSON).

**Acceptance:** admin creates a team with a leader + 3 sales persons; a team leader logging in sees only their team; a sales person sees only self.

---

## Sprint 2 — Attendance: Check-in / Check-out (2 wks)
**Goal:** Core daily entry point for sales persons.
- Migration: `attendance`, `breaks`, `location_consents`.
- Sales-person screen: **Check-in** captures selfie (camera) + GPS + device + IP; **Check-out** same; shows today's status + working hours live.
- Server: compute worked_minutes, late/early flags vs shift config; one attendance row per user/day.
- Consent screen (location, working-hours-only) gated before first check-in.

**Acceptance:** on a phone, a sales person checks in with selfie+location, sees "Present, late by X," checks out later, hours computed correctly; images stored privately.

---

## Sprint 3 — Attendance: Breaks, Geofence, Approvals & Report (2 wks)
**Goal:** Make attendance manageable and trustworthy.
- Break start/stop; break minutes deducted from worked hours.
- Migration: `geofences`; office/branch geofence check on check-in (flag `geofence_ok`, `integrity_flag`).
- Team-leader/manager approval queue for exceptions (late, out-of-geofence, missing logout); approve/reject with remark.
- Attendance report (filter by team/date range) + CSV/PDF export.

**Acceptance:** out-of-geofence check-in is flagged and appears in the leader's approval queue; approved/rejected states persist and audit-log.

---

## Sprint 4 — Location Capture, Travel Summary & Map (2 wks)
**Goal:** Field-movement visibility (within web-only limits).
- Migration: `location_logs`, `travel_summary`.
- Client: periodic `watchPosition` batching points while app is foregrounded; client-side working-hours guard.
- Endpoint: `POST /location/ping` (batch); server stores points, ignores outside working hours.
- Cron: nightly travel rollup (haversine distance, first/last, route polyline).
- Map view (Leaflet + OpenStreetMap or Google Maps) — sales person's day route + client check-ins; manager can view a team member's day.
- **UI copy** clearly states foreground-only tracking.

**Acceptance:** a day of movement produces a route polyline + distance_km; manager opens the map and sees the path and check-in pins.

---

## Sprint 5 — Lead Management (Full Lifecycle) (2 wks)
**Goal:** Turn the thin marketing `leads` table into a real sales pipeline.
- Migration: extend `leads` (new status enum, company_name, campaign_source, product_id, location, priority, next_follow_up_at, lost_reason, converted_at, probability, team_id); `lead_status_history`, `lead_assignments`; `products`, `lead_sources`.
- Lead list (responsive: table on desktop, cards on phone) with filters (status, quality, source, assignee, ageing).
- Lead detail: full data, stage move (writes history), assign/reassign (writes assignment history), duplicate detection (phone/email match warning).
- Kanban pipeline view (stages) on desktop; stacked list on phone.

**Acceptance:** create a lead → move New→Contacted→Qualified→Converted; each transition logged; duplicate phone triggers a warning; reassignment recorded.

---

## Sprint 6 — Follow-ups & Reminder Engine (2 wks)
**Goal:** Nothing falls through the cracks.
- Migration: `follow_ups`.
- Schedule follow-up from a lead (channel, due_at, note); auto-create follow-up on certain stage changes.
- Cron worker: mark overdue → `missed`; generate "due today" + "missed" notifications; SLA check ("lead not contacted within X hrs" → escalate to team leader).
- Lead ageing recompute + hot/warm/cold reclassification.
- "My follow-ups today" screen for sales person.

**Acceptance:** a follow-up due in the past flips to missed via cron and notifies the owner + escalates to the leader; SLA breach creates an escalation.

---

## Sprint 7 — Task Management (Extended) (2 wks)
**Goal:** Daily task discipline with proof.
- Extend `tasks`: proof_url, is_carry_forward, source_task_id, review_status, reviewed_by (progress/remarks already exist).
- Assign tasks (manager/TL/self), priority, deadline, progress %, proof upload, complete.
- Cron: carry forward incomplete tasks to next day (linked via source_task_id).
- Leader review/approve completed tasks; task-wise completion metric.

**Acceptance:** sales person completes a task with a proof image; incomplete task auto-carries to tomorrow; leader approves and it counts toward performance.

---

## Sprint 8 — Communications Log + Meetings & Demos (2 wks)
**Goal:** Capture field activity (manual + proof) and verified visits.
- Migration: `communications`, `meetings`, `meeting_checkins`, `attachments`.
- Comms quick-log: call made/received/missed (+duration), WhatsApp/SMS/email/social, outcome, optional proof screenshot; linkable to a lead.
- Meetings/demos: schedule; **check-in/out with GPS + selfie**; outcome, client feedback, next follow-up.
- Manager visibility into comms + meetings per person (marked "self-reported + proof").

**Acceptance:** sales person logs 5 calls + 1 demo with check-in selfie; entries appear on the lead timeline and the manager view.

---

## Sprint 9 — Target Planning & Achievement (2 wks)
**Goal:** Plan vs actual across all activity types.
- Migration: `target_categories`, `targets`, `target_items`, `target_progress`.
- Plan targets (employee/team, daily/weekly/monthly, product/territory) across calls, emails, meetings, demos, leads, conversions, revenue, visits, proposals, etc.
- Manager/TL approval of planned targets.
- Cron: compute achieved values from attendance/comms/meetings/leads; refresh `target_progress`.
- Planned-vs-achieved view (per person + team) with completion %.

**Acceptance:** a monthly target of "100 calls, 10 conversions, ₹X revenue" shows live achieved values that match logged activity.

---

## Sprint 10 — Performance Scoring Engine & Ranking (2 wks)
**Goal:** Objective, configurable performance.
- Migration: `performance_scores`, `manager_reviews`, `scoring_weights`.
- Scoring engine (nightly + rolling weekly/monthly): attendance, punctuality, activity, target, lead-gen, follow-up, conversion, revenue, meeting, demo → weighted overall + band + team rank.
- Admin screen to edit weights.
- Employee performance profile screen (self); manager sees team ranking + low/high performers.

**Acceptance:** changing a weight in admin re-derives overall scores on next run; team ranking reflects actual activity.

---

## Sprint 11 — Manager / Team-Leader Monitoring Dashboards (2 wks)
**Goal:** The command center.
- Live board: who's in/late/no-login/location-off; last/live location tiles + map.
- Today rollup: targets planned vs achieved, tasks, comms, meetings, leads gen/followed/converted, pending + missed follow-ups.
- Pipeline + revenue forecast (from lead value × probability).
- Drill-down to any sales person's day.
- Fully responsive so a manager can check it on a phone.

**Acceptance:** manager opens one screen and answers "who's working, who's behind, what's the pipeline" without navigating away; loads on a phone cleanly.

---

## Sprint 12 — Reports Suite & Exports (2 wks)
**Goal:** The 22 reports + exports.
- Report framework (shared filters: team, person, date range) + PDF (DomPDF) + Excel/CSV (PhpSpreadsheet).
- Reports: attendance, login/logout, location-travel, distance, task completion, target planned-vs-achieved, lead generation, lead status, lead conversion, follow-up, missed follow-up, call/email/message activity, meeting, demo, revenue, employee/manager/team performance, daily summary, monthly summary.
- Cron: auto daily/weekly/monthly summary generation + email digest.

**Acceptance:** each report renders on screen and exports to PDF + Excel with correct filtered data; a monthly summary emails automatically.

---

## Sprint 13 — Notifications, Alerts Engine & Admin Config (2 wks)
**Goal:** Proactive system + full admin control.
- Migration: `alert_rules`; extend `notifications` (action_url, severity, category).
- Rule-driven alerts: late/no login, missing logout, location disabled, target not updated, task overdue, follow-up due/missed, lead unattended, meeting/demo reminders, low performance, approval pending, high-value lead pending, contact-SLA breach.
- Web Push (VAPID) + in-app notification center + optional email.
- Admin config hub: attendance rules, location rules (interval/working hours), scoring weights, alert thresholds, products, sources, categories, data-retention settings.

**Acceptance:** each configured rule fires a notification via cron/event and reaches the right user (in-app + push); admin toggles a rule off and it stops.

---

## Sprint 14 — Security, Privacy, Hardening, UAT & Launch (2 wks)
**Goal:** Production-ready.
- Data-retention jobs (purge raw location > N days, keep summaries); consent audit.
- API rate-limiting, brute-force lockout, security review (RBAC on every endpoint, upload safety, token handling), pen-test pass.
- Performance: indexes, query review on live-board/report queries, caching of dashboard aggregates.
- Full responsive QA sweep (360/768/1440), cross-browser, accessibility basics.
- UAT with real sales team; bug-fix buffer; data migration/backfill; go-live + runbook.

**Acceptance:** sign-off checklist green; a pilot team runs a full real day (check-in → field → leads → follow-ups → check-out) end-to-end on their phones.

---

## Sprint Map (quick reference)

| Sprint | Theme | Ships to users |
|---|---|---|
| 0 | Infra, storage, JSON layer, responsive shell | — |
| 1 | Roles, teams, hierarchy, RBAC | Admin team setup |
| 2 | Attendance check-in/out + selfie + GPS | Sales persons |
| 3 | Breaks, geofence, approvals, report | Leaders |
| 4 | Location capture, travel, map | Managers |
| 5 | Lead lifecycle + pipeline | Sales + managers |
| 6 | Follow-ups + reminder engine | Sales + leaders |
| 7 | Tasks (proof, carry-forward) | Sales + leaders |
| 8 | Comms log + meetings/demos check-in | Sales + managers |
| 9 | Targets plan vs achieved | Managers + sales |
| 10 | Performance scoring + ranking | All |
| 11 | Manager monitoring dashboards | Leaders/managers |
| 12 | Reports + exports | Managers/admin |
| 13 | Notifications/alerts + admin config | All |
| 14 | Security, privacy, UAT, launch | Go-live |

### MVP cut line (if you must ship earlier)
Sprints **0–8** = a usable field product (org + attendance + location + leads + follow-ups + tasks + comms/meetings). Sprints 9–14 add targets, scoring, dashboards, reports, alerts, hardening. Recommend not skipping **14**.

### Notes on sequencing
- Sprints 2–4 (attendance/location) and 5–8 (leads/follow-ups/tasks/comms) are semi-independent — with two backend devs they can partially parallelize.
- Cron worker infra is first introduced in Sprint 4 and reused in 6, 9, 10, 12, 13 — keep it a shared, well-tested component.
- Every sprint carries the responsive + security DoD; don't defer responsive polish to the end.
