# Module Modernization Implementation Plan

```
Document:             MODULE-IMPLEMENTATION-PLAN.md
Directory:            RAPTOR-V2.2-BLUEPRINT/MODULE-MODERNIZATION/
Classification:       File-Level Implementation Specifications
Target Release:       Raptor v2.2
```

---

## 1. Implementation Protocol by Module

Each module follows a structured 5-step modernization lifecycle:
1. **Audit & Scope**: Identify specific view files and controller methods to modernize.
2. **Design System Integration**: Replace hardcoded inline styling with CSS tokens and component partials.
3. **Backend & Security Hardening**: Verify CSRF, RBAC permissions, and team-subtree query scoping.
4. **Automated Verification**: Run regression test suites + verify DOM rendering via Chrome DevTools MCP.
5. **Rollback Safety**: Every change must be independently revertible via clean git commits.

---

## 2. Module Specifications

### 2.1 Settings & Quick Search (Tier P0)
- **Target Files**:
  - `app/controllers/QuickSearchController.php`
  - `app/controllers/SettingsController.php`
  - `app/views/settings/index.php`
  - `app/views/settings/dlq.php`
- **Modernization Actions**:
  1. Patch `QuickSearchController::index()` to pass queries through `Policy::getTeamSubtreeUserIds()` and `visibleUserIds()`.
  2. Implement keyboard navigation (`ArrowUp`, `ArrowDown`, `Enter`) in the Command Palette modal.
  3. Standardize the Universal AI Engine settings cards with tokenized variables and status badges.
- **Security Invariant**: Quick Search must never disclose leads, deals, or employee CTCs across unauthorized team boundaries.
- **Verification**: Run `bin/test_modernization_extensions.php` (30 assertions).

---

### 2.2 Statutory Payroll & Compensation (Tier P0)
- **Target Files**:
  - `app/controllers/PayrollController.php`
  - `app/views/payroll/index.php`
  - `app/views/payroll/runs.php`
  - `app/views/payroll/reconciliation.php`
  - `app/services/PayslipPdfService.php`
- **Modernization Actions**:
  1. Refactor payroll run summary tables to use responsive sticky header components.
  2. Add visual warning badges when pre-payroll checklist items (unapproved leaves, missing PAN/Bank) are pending.
  3. Maintain pixel-perfect Times New Roman corporate payslip PDF generation.
- **Security Invariant**: Payroll records become immutable once approved (`status = 'approved'`). Modifying a closed payroll run is prohibited.
- **Verification**: Run `bin/test_payroll_pipeline.php` (184 assertions).

---

### 2.3 Marketing & Demand Generation (Tier P0)
- **Target Files**:
  - `app/controllers/CampaignsController.php`
  - `app/controllers/EmailCampaignsController.php`
  - `app/services/PreflightEngine.php`
  - `app/views/campaigns/create.php`
  - `app/views/campaigns/deliverability.php`
- **Modernization Actions**:
  1. Embed the Universal AI Engine helper directly into the Campaign Creation wizard: "Generate Subject Lines with Gemini/Claude".
  2. Add real-time visual deliverability meter (0 to 100 score) powered by rules `D-001` through `D-020`.
  3. Add 1-click test send modal with instant Sentry APM trace inspection.
- **Security Invariant**: RFC 8058 one-click unsubscribe links must never require login credentials and must instantly append to `marketing_suppression_list`.
- **Verification**: Run `bin/test_deliverability_intelligence.php` & `bin/test_email_campaigns_e2e.php` (75 assertions).

---

### 2.4 CRM, Leads & Customer 360 (Tier P1)
- **Target Files**:
  - `app/controllers/LeadsController.php`
  - `app/controllers/CustomersController.php`
  - `app/views/leads/index.php`
  - `app/views/leads/detail.php`
  - `app/views/customers/view.php`
- **Modernization Actions**:
  1. Replace cumbersome table pagination with snappy DataTables HTML5 export integration.
  2. Add right-hand slide-over drawer (`raptor-drawer`) for instant lead preview without leaving the pipeline view.
  3. Integrate AI Smart Reply draft generator inside the Customer 360 communication tab.
- **Security Invariant**: Sales reps can only view leads assigned to them or their team lead's subtree.
- **Verification**: Run `bin/test_customer_360_sprint1.php` & `bin/test_lead_capture_sprint2.php` (78 assertions).

---

### 2.5 HR Management & Organization (Tier P1)
- **Target Files**:
  - `app/controllers/HrmsController.php`
  - `app/controllers/UsersController.php`
  - `app/controllers/LeavesController.php`
  - `app/views/hrms/index.php`
  - `app/views/users/index.php`
  - `app/views/leaves/index.php`
- **Modernization Actions**:
  1. Modernize Employee Directory cards with quick action buttons (Call, WhatsApp, Email, View Profile).
  2. Extract `LeaveService` to encapsulate leave balance arithmetic and holiday calendar exclusion.
  3. Implement visual team hierarchy tree view using SVG connector nodes.
- **Security Invariant**: Sensitive employee records (bank accounts, base salary, Aadhar) are masked except for users with `payroll.manage` or `hr.manage` permissions.
- **Verification**: Run `bin/run_all_campaign_tests.php` statutory regression suite.

---

### 2.6 Operations, Monitoring & Attendance (Tier P2)
- **Target Files**:
  - `app/controllers/AttendanceController.php`
  - `app/controllers/TasksController.php`
  - `app/views/attendance/index.php`
  - `app/views/attendance/approvals.php`
  - `app/views/tasks/index.php`
- **Modernization Actions**:
  1. Optimize mobile clock-in view with geolocation accuracy indicator and camera capture preview.
  2. Convert Kanban Task Board into a touch-friendly drag-and-drop workflow.
  3. Add system health telemetry badge in the top navigation linking to DLQ and queue diagnostics.
- **Security Invariant**: Geofence spoofing prevention: verify server-side distance calculation using the Haversine formula against configured office coordinates.
- **Verification**: Post-deployment diagnostic suite (`bin/raptor health:check`).

