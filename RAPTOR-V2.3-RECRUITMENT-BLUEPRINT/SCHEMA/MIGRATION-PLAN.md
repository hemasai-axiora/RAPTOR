# Raptor v2.3 — Enterprise Recruitment Management Module (ATS)
## Schema 02. Sequential Migration Plan & Execution Strategy

```
Document:             SCHEMA/MIGRATION-PLAN.md
Directory:            RAPTOR-V2.3-RECRUITMENT-BLUEPRINT/SCHEMA/
Classification:       Database Migration Governance & Safety Standard
Target Release:       Raptor v2.3
Sequential Start:     0059 (Preserves 0000 through 0058 without modification)
```

---

## 1. Governance Rules for Migrations

1. **Strict Immutability Invariant**:
   Existing migration scripts (`migrations/0000_...` through `0058_...`) are production baseline files and must **never** be edited or deleted.
2. **Idempotency Requirement**:
   All migration scripts must be fully idempotent:
   - Use `CREATE TABLE IF NOT EXISTS`.
   - Wrap `ALTER TABLE ADD COLUMN` with `information_schema.COLUMNS` existence checks.
   - Wrap `INSERT` statements with `INSERT IGNORE` or `ON DUPLICATE KEY UPDATE`.
3. **Dual Database Engine Compatibility**:
   - Primary: MySQL 8.0+ / MariaDB (InnoDB, utf8mb4_unicode_ci, foreign keys, row-level locking).
   - Local/CI Testing: SQLite 3 mock compatibility. SQLite does not support multiple `ADD COLUMN` statements in a single query or certain MySQL-specific `ENUM` DDL; migrations must check `$db->getAttribute(PDO::ATTR_DRIVER_NAME)` and adapt gracefully.

---

## 2. Migration Sequence Breakdown

```
migrations/
  ├── 0059_recruitment_core_schema.php                 <-- Requisitions, approvals, jobs, candidates, applications
  ├── 0060_recruitment_evaluations_and_interviews.php  <-- Screenings, interviews, panels, feedback, assessments
  ├── 0061_recruitment_offers_and_onboarding.php       <-- Offers, offer approvals, BGV, pre-joining checklist
  ├── 0062_recruitment_queue_and_communications.php   <-- Communications log, queue, AI telemetry log
  ├── 0063_recruitment_rbac_permissions.php           <-- Seed 28 recruitment permissions and assign to roles
  └── 0064_recruitment_default_templates.php          <-- Seed default job descriptions, email/WhatsApp templates
```

---

## 3. Detailed Migration Implementation Specifications

### Migration 0059: Core Schema (`migrations/0059_recruitment_core_schema.php`)
- **Tables Provisioned**:
  - `recruitment_requisitions`
  - `recruitment_requisition_approvals`
  - `recruitment_job_descriptions`
  - `recruitment_jobs`
  - `recruitment_candidates`
  - `recruitment_candidate_documents`
  - `recruitment_candidate_consents`
  - `recruitment_candidate_notes`
  - `recruitment_applications`
  - `recruitment_application_stages`
- **Safety Checks**: Verifies existence of `teams`, `branches`, `users`, and `employees` before foreign key creation.

### Migration 0060: Evaluations & Interviews (`migrations/0060_recruitment_evaluations_and_interviews.php`)
- **Tables Provisioned**:
  - `recruitment_application_screenings`
  - `recruitment_interviews`
  - `recruitment_interview_panel`
  - `recruitment_interview_feedback`
  - `recruitment_assessments`
  - `recruitment_assessment_submissions`
- **Indexes**: Composite unique index on `(interview_id, interviewer_user_id)` to enforce blind evaluation integrity.

### Migration 0061: Offers & Onboarding (`migrations/0061_recruitment_offers_and_onboarding.php`)
- **Tables Provisioned**:
  - `recruitment_offers`
  - `recruitment_offer_approvals`
  - `recruitment_background_checks`
  - `recruitment_pre_joining_checklist`
- **Safety Checks**: Links `recruitment_applications.converted_employee_id` to `employees.employee_id`.

### Migration 0062: Queues & Telemetry (`migrations/0062_recruitment_queue_and_communications.php`)
- **Tables Provisioned**:
  - `recruitment_communications`
  - `recruitment_queue`
  - `recruitment_ai_logs`
- **Indexes**: Composite index on `(status, scheduled_at)` and `(locked_at)` for high-throughput concurrency.

### Migration 0063: RBAC Permissions (`migrations/0063_recruitment_rbac_permissions.php`)
- **Seed Data**:
  - Inserts 28 recruitment permissions into `permissions` table:
    `recruitment.dashboard.view`, `recruitment.requisition.create`, `recruitment.requisition.approve`, `recruitment.job.publish`, `recruitment.candidate.create`, `recruitment.candidate.view`, `recruitment.application.stage.update`, `recruitment.interview.schedule`, `recruitment.interview.feedback.submit`, `recruitment.offer.create`, `recruitment.offer.approve`, `recruitment.offer.view_salary`, `recruitment.onboarding.manage`, etc.
  - Maps default permissions to `roles`:
    - `admin` & `ceo`: Full access to all 28 permissions.
    - `hr`: Full access except final executive offer sign-off.
    - `manager` & `team_leader`: Requisition create/view, candidate view (within team scope), interview evaluation.
    - `interviewer`: Interview view & feedback submission for assigned rounds only.

### Migration 0064: Templates & Seed Data (`migrations/0064_recruitment_default_templates.php`)
- **Seed Data**:
  - Default Job Description Templates: *Senior Full-Stack Engineer*, *Sales Executive*, *HR Generalist*, *DevOps Specialist*.
  - Standard Notification Templates: *Application Acknowledgement*, *Interview Invitation*, *Offer Letter Delivery*, *Joining Instructions*.
  - Standard Screening Criteria: *Technical Competence (35%)*, *Domain Experience (25%)*, *Communication (20%)*, *Cultural Fit (20%)*.

---

## 4. Verification & Rollback Strategy

1. **Pre-Migration Dry Run**:
   Execute `php bin/raptor migrate:status` to display pending migrations `0059` through `0064`.
2. **Apply Migrations**:
   Execute `php bin/raptor migrate:run`.
3. **Automated Verification**:
   Verify table creation and schema definitions via `php bin/raptor test:run`.
4. **Emergency Rollback Procedure**:
   If an unexpected schema failure occurs:
   - Rollback script drops tables in reverse dependency order:
     `recruitment_ai_logs`, `recruitment_queue`, `recruitment_communications`, `recruitment_pre_joining_checklist`, `recruitment_background_checks`, `recruitment_offer_approvals`, `recruitment_offers`, `recruitment_assessment_submissions`, `recruitment_assessments`, `recruitment_interview_feedback`, `recruitment_interview_panel`, `recruitment_interviews`, `recruitment_application_screenings`, `recruitment_application_stages`, `recruitment_applications`, `recruitment_candidate_notes`, `recruitment_candidate_consents`, `recruitment_candidate_documents`, `recruitment_candidates`, `recruitment_jobs`, `recruitment_job_descriptions`, `recruitment_requisition_approvals`, `recruitment_requisitions`.
   - Remove seeded permissions from `role_permissions` and `permissions`.
