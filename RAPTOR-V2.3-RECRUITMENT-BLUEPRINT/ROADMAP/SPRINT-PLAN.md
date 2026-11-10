# Raptor v2.3 — Enterprise Recruitment Management Module (ATS)
## Roadmap 01. Sprint Implementation Plan (Sprints 0 through 10)

```
Document:             ROADMAP/SPRINT-PLAN.md
Directory:            RAPTOR-V2.3-RECRUITMENT-BLUEPRINT/ROADMAP/
Classification:       Sprint Roadmap, User Stories & Engineering Milestones
Target Release:       Raptor v2.3
Development Strategy: Incremental / Test-Driven / Zero-Regression
```

---

## 1. Master Sprint Schedule Overview

```
Phase 0: Discovery & Architecture (Sprint 0)
  └── Sprint 0: Technical Audit, System Architecture & Blueprint Sign-Off (COMPLETED)

Phase 1: Foundation & Requisitions (Sprints 1 - 2)
  ├── Sprint 1: Database Migrations (0059-0064), RBAC Permissions, Base Services & Navigation
  └── Sprint 2: Manpower Demand, Requisition Management & Multi-Level Approval Chains

Phase 2: Sourcing, Career Portal & ATS (Sprints 3 - 4)
  ├── Sprint 3: Job Publishing Engine, Career Portal & Candidate Application Intake Wizard
  └── Sprint 4: Candidate Database, Deduplication, Private Documents & ATS Kanban Board

Phase 3: Evaluation, Interviews & Assessments (Sprints 5 - 6)
  ├── Sprint 5: Structured Screening, Multi-Round Interview Calendar & Blind Scorecards
  └── Sprint 6: Assessment Distribution, Assignment Grading & Candidate Communication Logs

Phase 4: Offers, Onboarding & Employee Conversion (Sprints 7 - 8)
  ├── Sprint 7: Offer Builder, Salary Masking, Threshold Approvals & Offer Letter Generation
  └── Sprint 8: Background Verification (BGV), Pre-Joining Checklist & Employee Conversion Engine

Phase 5: Automation, Analytics & Production Release (Sprints 9 - 10)
  ├── Sprint 9: Queue-Backed Messaging (Email/WhatsApp) & Multi-Provider AI Recruitment Copilot
  └── Sprint 10: Recruitment Analytics Funnel, Playwright E2E Automation & Release Sign-Off
```

---

## 2. Sprint Details & Acceptance Criteria

### Sprint 1: Schema Migrations, RBAC & Core Navigation
- **Scope**:
  - Implement migrations `migrations/0059_...` through `0064_...`.
  - Register 28 recruitment permissions in `app/core/PermissionService.php`.
  - Add Recruitment item to sidebar navigation (`views/layout/sidebar.php`).
  - Create base controller `RecruitmentDashboardController` and shell dashboard view.
- **Verification Gate**:
  - `php bin/raptor migrate:status` and `php bin/raptor migrate:run` succeed.
  - Zero regression across all 878 existing test assertions.

### Sprint 2: Requisitions & Multi-Level Approvals
- **Scope**:
  - Create `RecruitmentRequisitionController` and `RequisitionService`.
  - Build requisition creation wizard (`views/recruitment/requisitions/create.php`).
  - Implement multi-level approval chain with segregation of duties (Hiring Manager → Dept Head → HR → Finance).
- **Verification Gate**:
  - Draft requisitions cannot be published without formal multi-step signoff.
  - Unit tests for requisition lifecycle state transitions.

### Sprint 3: Job Publishing & Public Career Portal
- **Scope**:
  - Create `RecruitmentJobController` and `JobPublishingService`.
  - Implement public career portal (`index.php?route=careers`).
  - Generate Google Jobs structured data (JSON-LD).
  - Multi-step application intake wizard with resume upload and anti-spam honeypot.
- **Verification Gate**:
  - Validated Google `JobPosting` schema test.
  - Rate-limiting prevents automated application flooding.

### Sprint 4: Candidate 360, Deduplication & ATS Kanban
- **Scope**:
  - Create `CandidateService`, `CandidateDeduplicationService`, and `ApplicationStageService`.
  - Implement Candidate 360 profile (`views/recruitment/candidates/profile.php`).
  - Build interactive Recruitment Kanban board with drag-and-drop stage updates.
  - Integrate `Storage::put()` for private resume and certificate handling.
- **Verification Gate**:
  - Deduplication detects identical email/phone submissions.
  - Direct file URL downloads without active session return 403 Forbidden.

### Sprint 5: Screening & Blind Interview Scorecards
- **Scope**:
  - Create `ScreeningService`, `InterviewService`, and `InterviewEvaluationService`.
  - Build interview calendar view with conflict detection.
  - Implement blind evaluation modal: interviewers cannot view peer feedback until their scorecard is submitted.
- **Verification Gate**:
  - Overlapping interview bookings are prevented.
  - Blind feedback API prevents leakage of peer ratings.

### Sprint 6: Assessments & Assignment Evaluation
- **Scope**:
  - Create `AssessmentService` and `RecruitmentAssessmentController`.
  - Implement assessment assignment, due-date enforcement, and submission upload.
  - Candidate scoring rubrics and recruiter evaluation view.
- **Verification Gate**:
  - Submissions past due dates are flagged.
  - Assessment scores feed into application pipeline scorecards.

### Sprint 7: Offer Management & Salary Protection
- **Scope**:
  - Create `OfferService` and `OfferApprovalService`.
  - Build offer builder with compensation breakdowns (Fixed, Variable, Joining Bonus).
  - Implement salary confidentiality masking for unauthorized roles.
  - Versioned offer letter generation.
- **Verification Gate**:
  - Offers exceeding requisition budget trigger mandatory executive approval.
  - Salary fields remain masked for interviewers and standard recruiters.

### Sprint 8: Background Verification & Employee Conversion
- **Scope**:
  - Create `BackgroundVerificationService` and `EmployeeConversionService`.
  - Implement pre-joining document checklist (`recruitment_pre_joining_checklist`).
  - Build 1-click transaction-safe employee conversion into Raptor HRMS (`employees` table).
- **Verification Gate**:
  - Conversion is strictly idempotent: 0 duplicate employee records created.
  - Converted employee links to original recruitment application with `hired` status.

### Sprint 9: Queue Messaging & Multi-Provider AI Copilot
- **Scope**:
  - Implement background queue worker (`bin/recruitment_worker.php`) for transactional email and WhatsApp dispatch.
  - Create `RecruitmentAiService` integrating Gemini, OpenAI, and Claude for:
    - Resume parsing and skill extraction.
    - Candidate-job evidence matching.
    - Role-specific interview question suggestions.
- **Verification Gate**:
  - System operates smoothly when external AI APIs are disabled.
  - PII redaction verified on prompt payloads.

### Sprint 10: Analytics, Playwright E2E & Release Acceptance
- **Scope**:
  - Build recruitment analytics dashboard (Time-to-fill, Funnel conversion, Source ROI).
  - Implement Playwright E2E browser automation journeys (`tests/e2e/recruitment.e2e.spec.ts`).
  - Perform release signoff against 10 acceptance criteria.
- **Verification Gate**:
  - All existing 878 baseline assertions + all new recruitment assertions pass (target: >950 assertions).
  - Zero critical security vulnerabilities; zero regressions.
