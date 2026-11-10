# Raptor v2.3 — Enterprise Recruitment Management Module (ATS)
## 02. Existing HR Architecture & Technical Baseline Audit

```
Document:             02-EXISTING-HR-ARCHITECTURE-AUDIT.md
Directory:            RAPTOR-V2.3-RECRUITMENT-BLUEPRINT/
Classification:       Technical Architecture Audit & Repository Inspection
Target Release:       Raptor v2.3
Audit Date:           November 2026
Baseline Release:     Raptor v2.2.0 (878 Automated Tests Passing)
```

---

## 1. Executive Summary of Technical Baseline

Before architecting the Recruitment Management Module for Raptor v2.3, a thorough technical inspection of the production repository (`e:\raptor`) was conducted. This audit validates existing models, database schemas, access controls, storage handlers, queue workers, and integration surfaces to ensure the recruitment module integrates smoothly without introducing regressions or architectural divergence.

---

## 2. Core Subsystem Audits

### 2.1 The Employee & User Master Subsystem
- **User Record (`users` table)**:
  - Serves as the central authentication entity for internal personnel (`user_id`, `name`, `email`, `password_hash`, `role_id`, `status`).
  - Internal users (Recruiters, Hiring Managers, Interviewers, Approvers, HR Personnel) authenticate against this table.
  - External candidates must **not** be seeded directly into `users` with internal role privileges; external candidate authentication must remain isolated.
- **Employee Record (`employees` table)**:
  - Extends `users` with employment metadata: `employee_id`, `user_id` (1-to-1 unique), `employee_code` (unique string), `department`, `job_title`, `hire_date`, `date_of_joining`, `phone_number`, `salary`, `date_of_birth`, `employment_type`, `work_location`, `profile_photo`, `bio`, `emergency_contact`, `pan_number`, `aadhaar_number`, `uan`, `pf_applicable`, `esic_number`, `pay_grade`, `status`, `reporting_manager_id`, `team_id`, `branch_id`, `blood_group`, `address`, `experience_years`, `skills`, `documents_json`.
  - **Audit Finding**: When an accepted candidate is hired, the system must create an entry in `users` (or link to an existing user account) and create a corresponding record in `employees`. This creation must be handled through a dedicated, transaction-safe service (`EmployeeConversionService`) to guarantee data integrity and prevent orphaned records.

### 2.2 Organization Hierarchy & Subtree Scoping
- **Organizational Model**:
  - `teams` (`team_id`, `name`, `team_leader_user_id`, `manager_user_id`, `branch_id`, `territory_id`).
  - `branches` (`branch_id`, `name`, `address`, `lat`, `lng`, `status`) used for location and geofencing.
- **Data Scoping Invariant**:
  - `Policy::getTeamSubtreeUserIds($userId)` resolves the complete reporting tree under a user.
  - In Recruitment, hiring managers must only view job requisitions and candidate applications corresponding to their assigned department or team subtree, while recruiters and HR managers possess broader departmental/organizational visibility based on configured roles.

### 2.3 RBAC & Permission Enforcement Engine
- **Implementation**:
  - `app/core/PermissionService.php` acts as the single source of truth for authorization.
  - Supports module-action checks (`PermissionService::can('recruitment', 'create_requisition')`) with optional record-level scope checks (`'own'`, `'team'`, `'all'`).
  - `app/core/Policy.php` provides high-level convenience methods (`Policy::isAdmin()`, `Policy::isHr()`, `Policy::isManager()`).
  - **Audit Finding**: Recruitment permissions must be registered in the existing `permissions` table and mapped to `role_permissions` using idempotent database migrations (sequential migration `0059_...`).

### 2.4 Private Document Storage Subsystem
- **Implementation**:
  - `app/core/Storage.php` stores private files strictly outside the public web root in `STORAGE_PATH`.
  - Files are renamed into hashed storage keys of format: `category/YYYY/MM/<32-char-md5>.<ext>`.
  - Real MIME types are validated using PHP `finfo` (magic byte inspection) against a strict whitelist (`jpg`, `png`, `webp`, `pdf`).
  - Files are served exclusively through `FileController::show` via authenticated routes (`index.php?route=file/show&key=<key>`) with headers `X-Content-Type-Options: nosniff` and `Cache-Control: private, max-age=0, no-store`.
  - **Audit Finding**: The existing `Storage` engine must be extended to support recruitment document formats (`application/msword` `.doc`, `application/vnd.openxmlformats-officedocument.wordprocessingml.document` `.docx`) while maintaining strict file size and signature validation to prevent macro-enabled malware execution.

### 2.5 Queue & Background Automation Engine
- **Implementation**:
  - The platform uses transactional database queues (`campaign_email_queue`, `social_publish_queue`) using `status`, `scheduled_at`, `locked_at`, `locked_by`, `attempts`, and `max_attempts`.
  - Workers use MySQL `FOR UPDATE SKIP LOCKED` row locking to prevent race conditions during parallel CLI worker execution.
  - **Audit Finding**: Recruitment communication (email notifications, WhatsApp interview reminders, offer dispatch) must utilize a dedicated `recruitment_queue` following this proven queue pattern, operated via `bin/recruitment_worker.php`.

### 2.6 Multi-Provider AI Service
- **Implementation**:
  - `app/services/AiService.php` abstracts Google Gemini (`gemini-2.5-flash`), OpenAI (`gpt-4o-mini`), and Anthropic Claude (`claude-3-5-haiku-20241022`).
  - Supports connection testing, prompt dispatching, fallback provider resolution, and API key management in `settings`.
  - **Audit Finding**: Recruitment intelligence (resume parsing, matching, question generation) will leverage `AiService::dispatch()` with strict PII minimization (stripping candidate addresses, phone numbers, and identifying data before transmission).

### 2.7 Audit & Activity Logging Subsystem
- **Implementation**:
  - `activity_logs` records `user_id`, `action`, `entity_type`, `entity_id`, `before_json`, `after_json`, `ip_address`, `user_agent`, and `created_at`.
  - **Audit Finding**: Material recruitment lifecycle actions (requisition approval, stage change, candidate score submission, salary release, offer decision, employee conversion) must generate structured entries in `activity_logs` to maintain a tamper-evident audit trail.

---

## 3. Technical Debt & Pre-Existing Constraints

1. **Database Singleton Handling**:
   - `app/core/Database.php` connects via PDO. When MySQL is unreachable in local CLI testing environments, it executes `die()`. High-level tests and background workers must handle PDO connection state gracefully without crashing runner pipelines.
2. **Sequential Migration Rule**:
   - Existing migration files (`0000_` through `0058_`) must never be modified. All recruitment DDL and seed scripts must begin sequentially at `migrations/0059_recruitment_core_schema.php`.
3. **No External Frontend Frameworks**:
   - The UI must leverage Bootstrap 5.3, native Vanilla JavaScript (ES6+), and existing jQuery 3.7. No React, Vue, or compilation pipelines may be added.

---

## 4. Integration Surface Matrix

| Raptor Subsystem | Component File | Recruitment Interaction Point |
|---|---|---|
| **User Management** | `app/models/User.php` | Authentication of recruiters, managers, interviewers; account provisioning for converted employees |
| **HRMS & Org** | `app/models/Hrms.php` | Employee record generation upon offer acceptance; department & branch lookup |
| **Authorization** | `app/core/PermissionService.php` | Registration of 28 recruitment permissions; route & action authorization |
| **File Storage** | `app/core/Storage.php` | Secure upload and authenticated serving of candidate resumes and portfolio documents |
| **Messaging & Queues** | `app/services/EmailService.php` | Delivery of candidate acknowledgements, interview invites, and offer letters |
| **AI Layer** | `app/services/AiService.php` | Resume summarization, skill extraction, and candidate-job criteria matching |
| **Audit Trails** | `activity_logs` table | Immutable recording of hiring approvals, evaluation scorecards, and salary releases |
