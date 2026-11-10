# Raptor v2.3 — Enterprise Recruitment Management Module (ATS)
## Architecture Decisions Register (ADR Register)

```
Document:             ARCHITECTURE-DECISIONS/ADR-REGISTER.md
Directory:            RAPTOR-V2.3-RECRUITMENT-BLUEPRINT/ARCHITECTURE-DECISIONS/
Classification:       Architectural Decision Records (ADR-001 through ADR-008)
Target Release:       Raptor v2.3
```

---

## Index of Architectural Decisions

| ADR # | Title | Status | Date |
|---|---|---|---|
| **ADR-001** | Modular Custom MVC & Service Layer Extension | **Approved** | November 2026 |
| **ADR-002** | Separation of Candidate (Person) and Application (Submission) Entities | **Approved** | November 2026 |
| **ADR-003** | Public Career Portal Authentication & Session Isolation | **Approved** | November 2026 |
| **ADR-004** | Private Document Storage & Strict Magic-Byte MIME Validation | **Approved** | November 2026 |
| **ADR-005** | Configurable Multi-Level Requisition & Offer Approval Engine | **Approved** | November 2026 |
| **ADR-006** | Transaction-Safe Idempotent Employee Conversion Engine | **Approved** | November 2026 |
| **ADR-007** | Human-in-the-Loop AI Governance via Multi-Provider AiService | **Approved** | November 2026 |
| **ADR-008** | Database Queue with `SKIP LOCKED` Concurrency & DLQ | **Approved** | November 2026 |

---

## ADR-001: Modular Custom MVC & Service Layer Extension

### Context
Raptor is built on a high-performance custom enterprise PHP 8.2/8.3 MVC architecture. Implementing Recruitment requires substantial domain logic (deduplication, state machines, conflict detection, compensation approvals).

### Decision
Implement the ATS module as a modular domain within Raptor's existing MVC framework:
- Thin controllers in `app/controllers/Recruitment*Controller.php`.
- Reusable domain and application services in `app/services/recruitment/`.
- Native view components and blade-style templates in `app/views/recruitment/`.
- No full-stack JavaScript frameworks (React, Vue, Next.js) or external backend frameworks (Laravel) will be introduced.

### Consequences
- **Positive**: 100% architectural consistency, zero bloat, instant autoloading via Composer PSR-4, complete backward compatibility.
- **Negative**: Requires custom state machine and validation abstractions rather than leveraging framework-specific third-party packages.

---

## ADR-002: Separation of Candidate and Application Entities

### Context
In naive recruitment implementations, candidates and applications are modeled as a single table. This makes it impossible for a candidate to apply for multiple vacancies over time without duplicating their master record, resume history, and communication logs.

### Decision
Strictly decouple the **Candidate** (the individual human person: `recruitment_candidates`) from the **Application** (the candidate's submission for a specific job: `recruitment_applications`).
- A candidate has one master profile.
- A candidate may have multiple applications across different jobs.
- Each application maintains an independent stage, interview rounds, and offer lifecycle.

### Consequences
- **Positive**: Accurate deduplication, complete historical candidate timeline, support for talent pool re-engagement.
- **Negative**: Requires relational joins between candidate and application tables in queries.

---

## ADR-003: Public Career Portal Authentication & Session Isolation

### Context
The career portal must be accessible to public internet job seekers without exposing internal CRM/HRMS administrative login interfaces or cookies.

### Decision
Host the public career portal under `index.php?route=careers`.
- Endpoints are unauthenticated and isolated from internal `$_SESSION['user_id']`.
- Applications are submitted as guest candidates.
- Candidate self-service tracking uses single-use magic links or separate candidate session tokens.
- Internal administrative routes (`index.php?route=recruitment/*`) strictly enforce `requireAuth()` and `requirePermission()`.

### Consequences
- **Positive**: Eliminates internal credential brute-forcing via career portals; prevents privilege escalation.
- **Negative**: Requires distinct email verification workflows for external applicants.

---

## ADR-004: Private Document Storage & Strict Magic-Byte MIME Validation

### Context
Candidate resumes, identity proofs, and certificates contain highly sensitive personal information and represent a vector for macro malware, malicious PDFs, or zip bombs.

### Decision
All recruitment document uploads must leverage Raptor's `Storage::put()` engine:
- Stored outside the public web root (`STORAGE_PATH`).
- File types verified via magic bytes using PHP `finfo()`.
- Renamed to hashed keys (`category/YYYY/MM/<md5>.<ext>`).
- Served exclusively via authenticated streaming endpoints with `nosniff` and `no-store` headers. Direct web links are prohibited.

### Consequences
- **Positive**: Zero risk of remote code execution; zero unauthorized document leakage; full DPDP compliance.
- **Negative**: Document downloads require an active session and consume server CPU during streaming.

---

## ADR-005: Configurable Multi-Level Approval Engine

### Context
Requisitions and offers require multi-tiered sign-offs (e.g. Hiring Manager → Dept Head → HR → Finance → CEO) depending on salary thresholds and organizational policy. Hardcoding this sequence prevents organizational adaptability.

### Decision
Implement a database-driven approval chain (`recruitment_requisition_approvals` and `recruitment_offer_approvals`):
- Approval steps are defined sequentially with designated approver roles or specific user IDs.
- Material modifications to an approved requisition or offer invalidate existing approvals and reset the chain.
- Segregation of duties prevents an actor from approving their own request.

### Consequences
- **Positive**: Full auditability, compliance with enterprise financial governance, flexible multi-step signoffs.
- **Negative**: Adds database records and state validation overhead on approval transitions.

---

## ADR-006: Transaction-Safe Idempotent Employee Conversion Engine

### Context
When a candidate accepts an offer, they transition from an external applicant to an internal employee. If this process is interrupted or executed multiple times, duplicate employee records and conflicting payroll entries can occur.

### Decision
Orchestrate the conversion via `EmployeeConversionService` wrapped in a single database transaction:
- Pre-checks `recruitment_applications.converted_employee_id`.
- Checks for duplicate emails or phone numbers in `users` and `employees`.
- Generates a unique `employee_code` and persists records in `users` and `employees`.
- Updates application status to `hired` and stores the resulting `converted_employee_id`.
- The operation is strictly idempotent; repeated invocations return the existing employee ID without side-effects.

### Consequences
- **Positive**: Zero duplicate employees; seamless transition into HRMS, Attendance, and Payroll.
- **Negative**: Requires strict coordination between recruitment and HRMS schemas.

---

## ADR-007: Human-in-the-Loop AI Governance via Multi-Provider AiService

### Context
AI capabilities (resume parsing, skill extraction, candidate-job matching) can streamline hiring but risk introducing algorithmic bias, compliance violations, and hallucinations if allowed to make autonomous hiring decisions.

### Decision
Integrate AI exclusively as an **advisory copilot** via `AiService.php`:
- Supported providers: Google Gemini, OpenAI GPT, Anthropic Claude.
- PII (addresses, phone numbers) is redacted prior to sending payloads to LLMs.
- AI match scores and summaries are stored as advisory recommendations (`match_score_advisory`).
- Autonomous AI rejections, shortlists, or offer releases are strictly prohibited; human recruiter action is mandatory.
- The recruitment module remains 100% operational when AI APIs are disabled or offline.

### Consequences
- **Positive**: Strict regulatory compliance (DPDP / EU AI Act), defense against AI bias, provider flexibility.
- **Negative**: Requires recruiters to manually confirm stage movements.

---

## ADR-008: Database Queue with `SKIP LOCKED` Concurrency & Dead-Letter Recovery

### Context
Sending candidate emails, WhatsApp notifications, and processing large resume text asynchronously requires a reliable queue that supports multiple parallel workers without message duplication.

### Decision
Implement `recruitment_queue` utilizing MySQL 8.0+ `FOR UPDATE SKIP LOCKED`:
- Workers lock batches of pending jobs atomically without blocking concurrent worker threads.
- Includes exponential backoff on retryable failures (30s, 60s, 120s).
- Exhausted jobs (attempts $\ge 3$) are moved to dead-letter state (`'dlq'`) with error telemetry logged to Sentry.

### Consequences
- **Positive**: High throughput, zero message loss, zero third-party dependencies (no Redis or RabbitMQ required).
- **Negative**: Requires MySQL 8.0+ / MariaDB 10.6+ for native `SKIP LOCKED` support.
