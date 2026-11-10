# Raptor v2.2 Sprint-by-Sprint Implementation Roadmap

```
Document:             SPRINT-PLAN.md
Directory:            RAPTOR-V2.2-BLUEPRINT/ROADMAP/
Classification:       Sprint Implementation Plan & Resource Allocation
Target Release:       Raptor v2.2
Estimated Timeline:   6 Two-Week Sprints (12 Weeks Total)
```

---

## 1. Sprint Architecture & Overview

```mermaid
gantt
    title Raptor v2.2 Modernization Sprint Roadmap
    dateFormat  YYYY-MM-DD
    section Foundation & Security
    Sprint 1: Design System Core & Quick Search Patch :2026-11-15, 14d
    section Core Workspaces
    Sprint 2: CRM & Customer 360 Usability             :2026-11-29, 14d
    Sprint 3: Statutory Payroll & Pre-Payroll Polish   :2026-12-13, 14d
    section Demand Gen & People
    Sprint 4: Marketing AI & Deliverability Experience :2026-12-27, 14d
    Sprint 5: HR Management & Operations Refactoring   :2027-01-10, 14d
    section Polish & Release
    Sprint 6: E2E Quality Gates, PWA & Final Release   :2027-01-24, 14d
```

---

## 2. Sprint Breakdown & Task Specifications

### Sprint 1: Design System Core & Quick Search Security Patch
- **Objective**: Establish centralized CSS tokens, deploy the 24 reusable components, and patch the horizontal authorization flaw in `QuickSearchController`.
- **Target Modules**: `DESIGN-SYSTEM`, `QuickSearchController`, `main.php` layout.
- **Tasks**:
  1. Deploy `assets/css/raptor-tokens.css` with dark/light variables.
  2. Implement FOUT-free theme resolver script in document head.
  3. Patch `QuickSearchController` to enforce `visibleUserIds()` and `Policy::canViewRecord()`.
  4. Create `app/views/components/` library (card, modal, badge, button, search).
- **AI Agent Scope**: Generate component PHP partials and refactor `QuickSearchController` authorization filters.
- **QA & Security Review**: Verify STRIDE threat `TH-04` mitigated. Run `bin/test_modernization_extensions.php`.
- **Effort**: 18 Story Points (2 Engineers, 1 AI Agent).

---

### Sprint 2: CRM, Sales Pipeline & Customer 360 Modernization
- **Objective**: Accelerate sales representative workflows with slide-over lead preview drawers, DataTables export integration, and AI Smart Reply assistance.
- **Target Modules**: `LeadsController`, `CustomersController`, `AccountSalesController`.
- **Tasks**:
  1. Modernize Leads table view with quick filter pills (Hot, Cold, Qualified).
  2. Build right-side slide-over drawer (`raptor-drawer`) for lead details.
  3. Integrate AI Smart Reply drafting using `AiService::generate()` inside customer communications.
  4. Integrate DataTables HTML5 CSV and Excel export buttons on Deals and Contacts.
- **AI Agent Scope**: Refactor lead views to use standardized card and drawer components.
- **QA & Security Review**: Execute `bin/test_customer_360_sprint1.php` & `bin/test_lead_capture_sprint2.php` (78 assertions).
- **Effort**: 22 Story Points (2 Engineers, 1 AI Agent).

---

### Sprint 3: Statutory Payroll & Compensation Workflow Polish
- **Objective**: Enhance payroll manager usability, add pre-payroll checklist blocking alerts, and preserve 100% statutory payroll calculation accuracy.
- **Target Modules**: `PayrollController`, `PayslipPdfService`, `payroll/*` views.
- **Tasks**:
  1. Add visual warning badges when pre-payroll checklist items are pending before run execution.
  2. Implement sticky-header matrix view for month-over-month salary variance reconciliation.
  3. Cache generated Times New Roman payslip PDFs in `STORAGE_PATH` for instant employee re-download.
- **AI Agent Scope**: Implement UI reconciliation view refactoring; strictly prohibited from modifying EPF/ESIC/PT math in `Payroll.php`.
- **QA & Security Review**: Execute `bin/test_payroll_pipeline.php` (184 assertions). Verify zero calculation variance.
- **Effort**: 16 Story Points (2 Engineers, 1 Payroll Specialist).

---

### Sprint 4: Marketing AI Copilot & Deliverability Intelligence
- **Objective**: Embed native generative AI tools into campaign authoring and provide visual preflight deliverability scoring.
- **Target Modules**: `CampaignsController`, `EmailCampaignsController`, `PreflightEngine`.
- **Tasks**:
  1. Add "AI Subject Line & Pre-Check" button in campaign creator calling `AiService`.
  2. Build visual Deliverability Health Meter (0–100) displaying real-time checks across rules `D-001`–`D-020`.
  3. Add 1-click test broadcast preview with automated spam trigger word highlighting.
- **AI Agent Scope**: Build campaign preflight UI modal and wire `AiService` AJAX handlers.
- **QA & Security Review**: Execute `bin/test_deliverability_intelligence.php` & `bin/test_email_campaigns_e2e.php` (75 assertions).
- **Effort**: 20 Story Points (2 Engineers, 1 AI Agent).

---

### Sprint 5: HR Management & Operations Service Decoupling
- **Objective**: Extract business logic from legacy controllers into `AttendanceService` and `LeaveService`, modernizing employee directory views.
- **Target Modules**: `AttendanceController`, `LeavesController`, `UsersController`, `HrmsController`.
- **Tasks**:
  1. Extract `AttendanceService` (geofence verification, shift grace period, daily clock-in).
  2. Extract `LeaveService` (holiday calendar deduction, statutory leave balance tracking).
  3. Modernize Employee Directory cards with 1-click communication actions (Call, WhatsApp, Email).
  4. Implement visual team hierarchy tree view.
- **AI Agent Scope**: Refactor controllers to delegate to new services; maintain existing method signatures.
- **QA & Security Review**: Full regression test run across HR and attendance modules.
- **Effort**: 24 Story Points (3 Engineers, 1 AI Agent).

---

### Sprint 6: E2E Playwright Automation, PWA Hardening & Release Acceptance
- **Objective**: Run complete automated browser test suite, audit Core Web Vitals, verify rollback runbooks, and tag release `v2.2.0`.
- **Target Modules**: `tests/e2e/`, `public/manifest.json`, `bin/raptor`.
- **Tasks**:
  1. Implement and verify 10 Playwright E2E browser journeys (`bin/raptor test:ui`).
  2. Audit Lighthouse score: achieve >= 90 Performance, >= 95 Accessibility, >= 95 Best Practices.
  3. Verify PWA offline manifest caching and mobile app installation.
  4. Perform dry-run deployment and rollback verification on staging server.
  5. CTO and technical lead final sign-off.
- **AI Agent Scope**: Execute test runners and generate release documentation.
- **QA & Security Review**: Final sign-off against `RELEASE-ACCEPTANCE.md`.
- **Effort**: 16 Story Points (Full Team + CTO).
