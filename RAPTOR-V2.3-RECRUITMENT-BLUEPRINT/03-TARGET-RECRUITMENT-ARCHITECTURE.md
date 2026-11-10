# Raptor v2.3 — Enterprise Recruitment Management Module (ATS)
## 03. Target Recruitment Architecture & Component Boundaries

```
Document:             03-TARGET-RECRUITMENT-ARCHITECTURE.md
Directory:            RAPTOR-V2.3-RECRUITMENT-BLUEPRINT/
Classification:       Modular System Architecture & Component Specification
Target Release:       Raptor v2.3
```

---

## 1. Architectural Principles & Layered Hierarchy

The Recruitment Management Module is engineered as a first-class, modular domain within Raptor's custom enterprise MVC architecture. It strictly decouples presentation, business orchestration, domain invariants, and data access into distinct layers:

```
┌────────────────────────────────────────────────────────────────────────┐
│                        PRESENTATION LAYER                              │
│  - Internal Blade-style Views (Bootstrap 5.3 + Glassmorphic Tokens)    │
│  - Public Career Portal (Responsive, Accessible, Unauthenticated)      │
│  - Interactive Kanban & Calendar Modals (Vanilla JS + jQuery 3.7)      │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
┌───────────────────────────────────▼────────────────────────────────────┐
│                        CONTROLLER LAYER                                │
│  - Thin HTTP entry points; Authentication & CSRF validation            │
│  - Permission gating (`requirePermission`) & Subtree scoping           │
│  - Input sanitization, parameter mapping, output serialization         │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
┌───────────────────────────────────▼────────────────────────────────────┐
│                    APPLICATION SERVICE LAYER                           │
│  - Use-case orchestration (e.g. ScheduleInterview, ReleaseOffer)       │
│  - Coordination across Storage, Messaging, and AI abstractions         │
│  - PSR-14 Domain Event dispatching & Transaction boundary management   │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
┌───────────────────────────────────▼────────────────────────────────────┐
│                       DOMAIN SERVICE LAYER                             │
│  - Pure business logic & validation rules (State machine transitions)  │
│  - Candidate deduplication scoring algorithms                          │
│  - Offer compensation calculations & salary bracket validation         │
│  - Interview conflict detection logic                                  │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
┌───────────────────────────────────▼────────────────────────────────────┐
│                     REPOSITORY & MODEL LAYER                           │
│  - Data retrieval & persistence via PDO prepared statements            │
│  - Tenant & team-subtree scoping queries (`Policy::...`)               │
│  - Relational mapping & entity hydration                               │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
┌───────────────────────────────────▼────────────────────────────────────┐
│                         DATABASE LAYER                                 │
│  - MySQL 8.0+ / MariaDB InnoDB engine with row-level locking           │
│  - Foreign key constraints, atomic transactions, SKIP LOCKED queues    │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Directory Structure & File Mapping

```
app/
  controllers/
    RecruitmentDashboardController.php     <-- Central recruitment analytics, pipeline KPIs & alerts
    RecruitmentRequisitionController.php   <-- Manpower planning, requisition CRUD & multi-level approvals
    RecruitmentJobController.php           <-- Job publishing, career portal endpoints, SEO structured data
    RecruitmentCandidateController.php     <-- Candidate master profiles, deduplication & document uploads
    RecruitmentApplicationController.php   <-- Applicant tracking, Kanban board & stage transitions
    RecruitmentScreeningController.php     <-- Initial screening evaluations & weighted scorecard forms
    RecruitmentInterviewController.php     <-- Multi-round interview calendar, scheduling & blind feedback
    RecruitmentAssessmentController.php    <-- Technical/aptitude assignment distribution & grading
    RecruitmentOfferController.php         <-- Compensation breakdown, offer approvals & release workflow
    RecruitmentOnboardingController.php    <-- Background verification, pre-joining checklist & employee conversion
    RecruitmentReportController.php        <-- Time-to-fill, source effectiveness & recruiter performance exports
    RecruitmentSettingsController.php      <-- Workflow stages, scorecard rubrics, approval rules & email templates

  services/
    recruitment/
      RecruitmentService.php               <-- Central module coordinator & status aggregations
      RequisitionService.php               <-- Headcount validation, requisition lifecycle & revisions
      RequisitionApprovalService.php       <-- Multi-step approval chain & segregation of duties engine
      JobPublishingService.php             <-- Job publishing scheduler & JSON-LD structured data generator
      CandidateService.php                 <-- Candidate master creation, profile updates & privacy controls
      CandidateDeduplicationService.php    <-- Multi-signal deduplication & profile merge engine
      ApplicationService.php               <-- Application linking, status querying & recruiter assignment
      ApplicationStageService.php          <-- Validated stage state-machine & audit trail logger
      ResumeParsingService.php             <-- Document text extraction & draft profile populator
      ScreeningService.php                 <-- Weighted screening evaluation calculations & shortlist gates
      InterviewService.php                 <-- Schedule conflict detection, panel assignment & calendar sync
      InterviewEvaluationService.php       <-- Independent scorecard submission, blind review & feedback audits
      AssessmentService.php                <-- Assignment distribution, due-date tracking & score recording
      OfferService.php                     <-- Compensation breakdown, versioning & offer document generation
      OfferApprovalService.php             <-- Threshold-based salary approvals & release authorizations
      BackgroundVerificationService.php    <-- Third-party & internal BGV tracking, consent records
      EmployeeConversionService.php        <-- Transaction-safe conversion of candidate into Raptor employee
      RecruitmentCommunicationService.php  <-- Queue-backed email & WhatsApp notifications with rate limits
      RecruitmentAiService.php             <-- Multi-provider AI resume summaries, matching & prompt guards
      RecruitmentAnalyticsService.php      <-- Metric calculation (time-to-fill, conversion funnels, aging)
      RecruitmentRetentionService.php      <-- DPDP Act data retention, right-to-erasure & anonymization cron

  models/
    recruitment/
      RecruitmentRequisition.php           <-- Model for recruitment_requisitions & approvals
      RecruitmentJob.php                   <-- Model for recruitment_jobs & publishing records
      RecruitmentCandidate.php             <-- Model for recruitment_candidates, contacts & documents
      RecruitmentApplication.php           <-- Model for recruitment_applications & stage history
      RecruitmentInterview.php             <-- Model for recruitment_interviews & panel feedback
      RecruitmentOffer.php                 <-- Model for recruitment_offers & approvals
      RecruitmentOnboarding.php            <-- Model for recruitment_onboarding & BGV checks
```

---

## 3. Key Service Responsibilities & Invariants

### 3.1 `EmployeeConversionService` (Idempotent Conversion Engine)
- **Problem**: When a candidate accepts an offer, converting them into an employee must never create duplicate records or leave partial data if a failure occurs.
- **Implementation**:
  ```php
  public function convertCandidateToEmployee(int $applicationId, int $actorUserId, array $overrides = []): int {
      $db = Database::getInstance()->getConnection();
      $db->beginTransaction();
      try {
          // 1. Lock application and offer records FOR UPDATE
          $app = $this->appRepo->findForConversionLocked($applicationId);
          if ($app->status !== 'offer_accepted') {
              throw new DomainException("Application must be in offer_accepted stage.");
          }

          // 2. Validate that candidate is not already converted
          if ($app->converted_employee_id !== null) {
              throw new DomainException("Candidate has already been converted to employee #{$app->converted_employee_id}.");
          }

          // 3. Pre-check for duplicate user/employee records
          $existingUser = $this->userRepo->findByEmail($app->candidate_email);
          $userId = $existingUser ? $existingUser->user_id : $this->userRepo->createFromCandidate($app);

          // 4. Generate unique employee code & persist employee record
          $employeeCode = $this->generateUniqueEmployeeCode($app->department);
          $employeeId = $this->hrmsRepo->createEmployeeRecord($userId, $employeeCode, $app, $overrides);

          // 5. Update application stage to 'hired' & link converted_employee_id
          $this->appRepo->markAsHired($applicationId, $employeeId, $actorUserId);

          // 6. Log audit event & commit transaction
          $this->auditService->log('candidate_converted', 'recruitment_applications', $applicationId, null, ['employee_id' => $employeeId]);
          $db->commit();

          // 7. Dispatch post-commit domain event
          $this->eventDispatcher->dispatch(new RecruitmentEmployeeConversionCompleted($applicationId, $employeeId));
          return $employeeId;
      } catch (\Throwable $e) {
          $db->rollBack();
          throw $e;
      }
  }
  ```

### 3.2 `ApplicationStageService` (State Machine Engine)
- **Problem**: Arbitrary stage jumps (e.g. moving directly from "Application Received" to "Hired") corrupt audit trails and bypass compliance gates.
- **Implementation**:
  Enforces a strict transition graph. Transitions from `Application Received` must pass through `Screening` before `Interview`, and from `Interview` through `Offer Preparation` before `Hired`. Every transition records `from_stage`, `to_stage`, `actor_user_id`, `reason_code`, and `timestamp`.

### 3.3 `RecruitmentAiService` (Human-in-the-Loop AI Engine)
- **Problem**: Autonomous AI rejections or candidate ranking introduce algorithmic bias and legal liability.
- **Implementation**:
  - Leverages existing `AiService.php` (`gemini`, `openai`, `anthropic`).
  - Strict PII minimization: candidate names, phone numbers, home addresses, and religious/caste references are redacted before sending prompt payloads to external LLMs.
  - LLM outputs are treated as **untrusted suggestions**: stored in draft state (`match_score_advisory`, `extracted_skills_json`) requiring explicit recruiter review and verification.

---

## 4. Transaction & Event Dispatching Architecture

All business-critical operations follow the **Transactional Outbox & Post-Commit Dispatch** pattern:
1. Database changes (status updates, stage changes, record insertions) are executed inside a database transaction.
2. Events intended for external dispatch (e.g., sending an interview invitation email or WhatsApp message) are written to the database queue (`recruitment_queue`) within the same transaction.
3. The transaction is committed.
4. If the database commit succeeds, the queue worker processes the message asynchronously with exponential backoff and DLQ guarantees. Under no circumstances are external API calls executed inside an open database transaction.
