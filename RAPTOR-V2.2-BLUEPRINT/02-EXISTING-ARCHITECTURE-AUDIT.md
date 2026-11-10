# CHAPTER 2 — Existing System Assessment & Codebase Audit

```
Document:             02-EXISTING-ARCHITECTURE-AUDIT.md
Parent:               RAPTOR v2.2 Modernization Blueprint
Classification:       Comprehensive Codebase Audit
Target Release:       Raptor v2.2
Audit Date:           2026-11-10
Baseline Commit:      fc37d6e (Release v2.1.4)
```

---

## 1. Audit Methodology & Scope

The audit was conducted across the active repository on branch `feature/raptor-v2.2-blueprint`. Every subsystem was evaluated across eight architectural disciplines:
1. Bootstrapping, Routing & Core Infrastructure
2. Controller & Service Layer Decoupling
3. Data Models, Queries & Transaction Safety
4. Frontend Architecture, CSS Tokens & Layouts
5. Security, Authentication & Tenant Isolation
6. Queue Workers, DLQ & Background Automation
7. Testing Infrastructure & Suite Coverage
8. Developer Operations & Tooling

### Finding Severity Classification
- **Critical (P0)**: Severe security vulnerability, data integrity flaw, or production outage risk.
- **Needs Improvement (P1)**: Functional but exhibits architectural drift, code duplication, or poor maintainability.
- **Good (P2)**: Solid enterprise implementation meeting functional standards with minor polish opportunities.
- **Excellent (P3)**: State-of-the-art implementation following modern PHP 8.2+ and security best practices.
- **Not Verified**: Declared in documentation but lacking verification in the current test runner.

---

## 2. High-Level Subsystem Assessment Matrix

| Subsystem | Verified Components | Score / Status | Key Assessment Findings |
|---|---|---|---|
| **Bootstrapping & Routing** | `public/index.php`, `app/core/App.php` | **Good** | Clean front controller. Sentry initialized early with `X-Request-ID`. Route dispatching handles camelCase & snake_case actions. |
| **Database Abstraction** | `app/core/Database.php`, PDO Singleton | **Good** | Parameter-bound prepared statements. Supports SQLite mock connection for offline unit testing. Lacks query logger/profiler. |
| **Controller Architecture**| 50 Controllers in `app/controllers` | **Needs Improvement** | Several controllers (e.g. `AttendanceController`, `LeavesController`) contain inline SQL and rendering logic instead of delegating to services. |
| **Service Layer** | 22 Services in `app/services` | **Good** | Strong business isolation in newer services (`AiService`, `EmailQueueService`, `PreflightEngine`). Older modules lack dedicated services. |
| **Data Models** | 59 Models in `app/models` | **Needs Improvement** | Models inherit from `app/core/Model.php` which has basic PDO helpers, but many models still write direct SQL queries rather than encapsulated repository methods. |
| **Security & RBAC** | `Security.php`, `Policy.php`, `PermissionService.php` | **Good** | Granular RBAC, session expiry checks, CSRF validation on authenticated POSTs. Team-subtree scoping active. |
| **Frontend & Design System**| `main.php` layout, CSS in views | **Needs Improvement** | Scattered inline CSS (`rgba(15, 23, 42, 0.7)`), inconsistent font sizes, dark-mode styling hardcoded without centralized theme tokens. |
| **Command Palette & Toast**| `QuickSearchController.php`, Toast JS | **Good** | Functional `Ctrl + K` palette and Web Audio chimes. Search results need permission-aware scoping and keyboard action navigation. |
| **Background Processing** | `marketing_queue`, `bin/raptor queue:status` | **Excellent** | Database queue uses `FOR UPDATE SKIP LOCKED`, exponential backoff, attempt caps, and DLQ quarantine. |
| **Integrations & APM** | Sentry SDK, Gemini, OpenAI, Claude, Meta Cloud | **Excellent** | Multi-LLM provider abstraction with live connection test (`AiService.php`); live verified Sentry error capture (`adca6f98...`). |
| **Test Coverage** | 16 Suites, 772 Assertions | **Good** | High coverage of statutory payroll, campaigns, and deliverability. Zero automated UI/E2E assertions currently executed in CI. |

---

## 3. Detailed Audit Findings Register

### Finding AUD-001: Scattered Inline CSS and Hardcoded Color Values
- **Severity**: Needs Improvement (P1)
- **Subsystem**: Frontend & Design System
- **Evidence**: `app/views/settings/index.php#L30-L38`, `app/views/layouts/main.php#L20-L80`, `app/views/dashboard/index.php`
- **Description**: Background colors, border opacities, and glassmorphic blurs are defined directly in HTML elements using inline `style="background: rgba(...); backdrop-filter: blur(...);"` attributes. Over 300 instances of hardcoded hex codes (`#2563EB`, `#1E293B`, `#0F172A`) were discovered across view templates.
- **Business Impact**: Theme toggling between dark and light modes is fragmented. Inconsistent visual quality hurts enterprise customer perception.
- **Technical Impact**: Maintenance overhead; changes to brand colors require editing dozens of PHP view files instead of one CSS token sheet.
- **Recommended Action**: Centralize all design tokens into `assets/css/raptor-tokens.css` with semantic CSS variables (`--raptor-bg-surface`, `--raptor-border-subtle`, `--raptor-primary`).

---

### Finding AUD-002: Inconsistent Service Delegation in Legacy Controllers
- **Severity**: Needs Improvement (P1)
- **Subsystem**: Controller & Service Architecture
- **Evidence**: `app/controllers/AttendanceController.php#L140-L190`, `app/controllers/LeavesController.php#L75-L120`
- **Description**: While Sprint 1–8 marketing and payroll modules cleanly delegate business logic to dedicated services (`EmailQueueService`, `PayslipPdfService`), legacy controllers like `AttendanceController` and `LeavesController` execute complex PDO queries, date calculations, and permission queries directly inside controller actions.
- **Business Impact**: Higher risk of regression when updating attendance approval policies or shift calculation rules.
- **Technical Impact**: Violates Single Responsibility Principle; makes unit testing controller actions without a live database unnecessarily difficult.
- **Recommended Action**: Extract `AttendanceService` and `LeaveService` to encapsulate attendance status calculations, geofence checks, and leave balance deductions.

---

### Finding AUD-003: Client-Side Permissions Missing Server-Side Defense-in-Depth in Quick Search
- **Severity**: Critical (P0)
- **Subsystem**: Security & Command Palette
- **Evidence**: `app/controllers/QuickSearchController.php#L45-L110`
- **Description**: `QuickSearchController::index()` queries `users`, `leads`, and `campaigns`. Although `requireAuth()` is called, the lead and employee query results do not consistently apply the `Policy::getTeamSubtreeUserIds()` filter for non-admin roles (e.g. `sales_person`).
- **Business Impact**: A sales representative could potentially search and see names/emails of leads owned by another sales representative through the Command Palette (`Ctrl + K`).
- **Technical Impact**: Potential horizontal privilege escalation and breach of team-subtree data scoping.
- **Recommended Action**: Refactor `QuickSearchController` to pass all queries through `visibleUserIds()` and `Policy::canViewRecord()` before emitting JSON search results.

---

### Finding AUD-004: Lack of Automated UI/E2E Test Execution in Build Pipelines
- **Severity**: Needs Improvement (P1)
- **Subsystem**: QA & Automated Testing
- **Evidence**: `bin/run_all_campaign_tests.php`, `tests/e2e/raptor.e2e.spec.ts`
- **Description**: The 16 automated test suites execute 772 backend PHP assertions via CLI mock SQLite connections. While extremely fast and reliable for backend algorithms, there is no automated runner executing browser UI tests for modal behaviors, table pagination, Command Palette keyboard events, or mobile responsive layouts.
- **Business Impact**: Visual regressions or broken JavaScript modals can leak into production unnoticed.
- **Technical Impact**: Testing is purely server-side headless; frontend DOM integrity is manually verified.
- **Recommended Action**: Implement an automated Chrome DevTools / Playwright test harness executed via `php bin/raptor test:ui` verifying 10 critical user journeys.

---

### Finding AUD-005: Absence of Static Analysis Configuration in Repository
- **Severity**: Needs Improvement (P1)
- **Subsystem**: Developer Experience & Code Quality
- **Evidence**: Root directory lacks `phpstan.neon`, `.editorconfig`, or `.php-cs-fixer.php`.
- **Description**: The codebase lacks a standardized static analysis configuration file. Different developers and AI coding agents may introduce syntax style divergences, untyped parameter returns, or dead code.
- **Business Impact**: Inconsistent code quality and potential runtime fatal errors in unexercised execution branches.
- **Technical Impact**: AI agents and developers cannot run automated static code gates prior to submitting PRs.
- **Recommended Action**: Author `phpstan.neon` (configured at Level 5 with custom autoloader bootstrap), `.editorconfig`, and pre-commit lint scripts in `bin/raptor`.

---

### Finding AUD-006: Sentry Error Capture Active Locally but DSN Handling Requires Strict Environment Isolation
- **Severity**: Good (P2)
- **Subsystem**: Observability & Error Tracking
- **Evidence**: `public/index.php#L12-L42`, `app/config/config.php#L72-L76`
- **Description**: Sentry SDK v4.36 is cleanly bootstrapped, user contexts are attached, and test errors succeed (`Event ID: 2cedbcae...`). However, local CLI commands use the production DSN by default if `.env` contains it, which could pollute production Sentry issue streams with local developer test exceptions.
- **Business Impact**: Noise in production error monitoring dashboards.
- **Technical Impact**: Development exceptions mixed with live production customer incidents.
- **Recommended Action**: Automatically set `SENTRY_ENVIRONMENT = 'local-development'` or disable dispatch unless `APP_ENV === 'production'`.

---

### Finding AUD-007: Database Connection Missing Query Profiling & Slow Query Telemetry
- **Severity**: Needs Improvement (P1)
- **Subsystem**: Performance & Database Layer
- **Evidence**: `app/core/Database.php#L1-L60`
- **Description**: Database execution uses native PDO without an interception wrapper to log slow queries (>200ms) or attach query spans to Sentry performance transactions.
- **Business Impact**: Slow dashboard or report loading times cannot be traced to specific unindexed SQL queries.
- **Technical Impact**: Missing observability into database query duration and N+1 query patterns.
- **Recommended Action**: Enhance `Database.php` with a lightweight query profiling timer that logs queries exceeding 200ms to Sentry breadcrumbs when `SENTRY_TRACES_SAMPLE_RATE > 0`.

---

## 4. Verification of Baseline Test Suites (772 Assertions)

The following test suites were independently executed and verified on branch `feature/raptor-v2.2-blueprint`:

```text
======================================================================
  VERIFIED TEST BASELINE SCORECARD (v2.1.4)
======================================================================
#   Test Suite Name                                            Passed  Failed  Status
-------------------------------------------------------------------------------------
1   Sprint 1: Customer 360 & Foundations                       43      0       PASS
2   Sprint 2: Lead Ingestion & Tracking                        35      0       PASS
3   Sprint 3: Meta WhatsApp Cloud API                          34      0       PASS
4   Sprint 4: Shared Omnichannel Inbox                         40      0       PASS
5   Sprint 5: Automation & Lead Scoring                        40      0       PASS
6   Sprint 6: Telephony & Call Mgmt                            38      0       PASS
7   Sprint 8: Social Publishing & Lead Ads                     36      0       PASS
8   Phase 2: Contacts & Suppression                            38      0       PASS
9   Phase 3: Templates & Pre-Send Gate                         35      0       PASS
10  Phase 4: Queue & Provider Adapters                         28      0       PASS
11  Phase 5: Webhooks, Tracking & Stats                        53      0       PASS
12  Phase 6: Domains, DNS & Health Report                      63      0       PASS
13  Phase 7: End-to-End Lifecycle Suite                        43      0       PASS
14  Phase 8: Deliverability Intelligence (D-001–D-020 & UAT)   32      0       PASS
15  Extensions: Next-Gen UI & Architecture                     30      0       PASS
16  Regression: Statutory Payroll & Pay Band Calculations      184     0       PASS
=====================================================================================
TOTAL VERIFIED ASSERTIONS: 772 PASSED, 0 FAILED (Runtime: 66.66s)
=====================================================================================
```
