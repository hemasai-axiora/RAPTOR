# Raptor v2.3 — Enterprise Recruitment Management Module (ATS)
## Engineering Modernization & Architectural Blueprint (Master Index)

```
Document:             00-MASTER-INDEX.md
Directory:            RAPTOR-V2.3-RECRUITMENT-BLUEPRINT/
Classification:       Enterprise Architectural Blueprint & Governance Standard
Target Release:       Raptor v2.3
Document Status:      Architecture Approval / Development Baseline
Compatibility:        PHP 8.2 / 8.3, Bootstrap 5.3, MySQL 8.0+ / MariaDB, SQLite3 Mocking
```

---

## 1. Blueprint Taxonomy & Directory Structure

This document suite serves as the definitive engineering blueprint for the design, implementation, verification, and governance of the **Enterprise Recruitment Management Module (ATS)** in Raptor v2.3.

```
RAPTOR-V2.3-RECRUITMENT-BLUEPRINT/
│
├── 00-MASTER-INDEX.md                              <-- Master directory, cross-references & navigation
├── 01-EXECUTIVE-SUMMARY.md                          <-- Product vision, business objectives & KPIs
├── 02-EXISTING-HR-ARCHITECTURE-AUDIT.md            <-- Technical audit of existing Raptor HRMS & models
├── 03-TARGET-RECRUITMENT-ARCHITECTURE.md           <-- Target modular architecture & component boundaries
│
├── SCHEMA/
│   ├── RECRUITMENT-DATA-MODEL.md                   <-- Normalized relational DDL & indexing strategies
│   ├── MIGRATION-PLAN.md                           <-- Sequential migrations (0059+), rollback & SQLite rules
│   └── ENTITY-RELATIONSHIP-DICTIONARY.md           <-- Data dictionary, field constraints & foreign keys
│
├── SECURITY/
│   ├── RECRUITMENT-THREAT-MODEL.md                 <-- STRIDE threat model & attack surface mitigations
│   ├── RBAC-AND-PERMISSION-MATRIX.md               <-- Granular permissions, roles & server-side enforcement
│   └── PRIVACY-AND-DATA-PROTECTION.md              <-- DPDP Act 2023 / GDPR compliance & data retention
│
├── API/
│   ├── RECRUITMENT-API-SPECIFICATION.md            <-- Versioned REST API endpoints, schemas & error contracts
│   └── EVENT-AND-QUEUE-SPECIFICATION.md            <-- PSR-14 domain events, database queue & DLQ recovery
│
├── UI-UX/
│   ├── RECRUITMENT-WIREFRAMES-AND-VIEWS.md         <-- Design system tokens, layout tree & core view specs
│   ├── CAREERS-PORTAL-SPECIFICATION.md             <-- Public career site, application wizard & SEO JSON-LD
│   └── KANBAN-AND-INTERVIEW-UI.md                  <-- Pipeline Kanban, interview calendar & evaluation modal
│
├── ROADMAP/
│   ├── SPRINT-PLAN.md                              <-- Sprints 0 through 10 implementation schedule & gates
│   └── RELEASE-ACCEPTANCE.md                       <-- Production sign-off checklist & emergency rollback runbook
│
└── ARCHITECTURE-DECISIONS/
    └── ADR-REGISTER.md                             <-- Architectural Decision Records (ADR-001 through ADR-008)
```

---

## 2. Core Architectural Invariants

All design decisions and subsequent implementations across Sprints 0–10 must adhere to these non-negotiable engineering principles:

1. **Preserve Existing Architecture**:
   - Built directly on Raptor's custom enterprise MVC architecture (`public/index.php`, `app/core/App.php`, `Controller.php`, `Model.php`, `Database.php`).
   - Retain PHP 8.2/8.3 strict typing, Composer PSR-4 autoloading, and Bootstrap 5.3 + Vanilla JS/jQuery 3.7.
   - Never introduce heavy full-stack frameworks (e.g. Laravel, Symfony, React, Next.js).
2. **Zero Regression Guarantee**:
   - All 878 passing automated assertions across 16 test suites must continue passing without regression.
   - Recruitment test packs must be strictly additive.
3. **Candidate vs. Application Separation**:
   - A *Candidate* is an individual master record (PII, credentials, resume documents).
   - An *Application* represents that candidate's specific submission for a single job requisition.
   - A single candidate may possess multiple active applications without record duplication.
4. **Idempotent Employee Conversion**:
   - Selected candidates must convert into employees via the authoritative `Hrms` / `User` services inside a single database transaction.
   - Pre-check for duplicate employees (`email`, `phone_number`, `employee_code`) before creation; never generate duplicate employee entities.
5. **Private Document Storage**:
   - All uploaded resumes, certificates, and work samples must be stored outside the web root (`STORAGE_PATH`) via the authenticated `Storage::put()` engine.
   - Direct file path links are strictly prohibited; all downloads are authenticated and audited via `FileController::show` or dedicated recruitment file gateways.
6. **Mandatory Human-in-the-Loop for AI**:
   - AI capabilities (resume parsing, candidate-job matching, interview question generation) run through the multi-provider `AiService.php` (Gemini, OpenAI, Claude).
   - AI output is advisory only; automated rejections, shortlists, or offer releases are strictly forbidden.
   - Recruitment operations must remain 100% operational when external AI APIs are disabled or unavailable.
7. **Strict Multi-Tenant & Team-Subtree Data Scoping**:
   - Every candidate, requisition, interview, and offer query is strictly bound to the authenticated user's tenant, department, and team subtree (`Policy::getTeamSubtreeUserIds()`).
   - Interviewers only see candidate records and evaluation rubrics for their explicitly assigned interviews.

---

## 3. Master Document Cross-Reference Matrix

| Category | Primary Reference Document | Dependent Components / Code |
|---|---|---|
| **Strategic Vision** | [01-EXECUTIVE-SUMMARY.md](file:///e:/raptor/RAPTOR-V2.3-RECRUITMENT-BLUEPRINT/01-EXECUTIVE-SUMMARY.md) | Business goals, ROI metrics, hiring workflow |
| **Existing Code Audit** | [02-EXISTING-HR-ARCHITECTURE-AUDIT.md](file:///e:/raptor/RAPTOR-V2.3-RECRUITMENT-BLUEPRINT/02-EXISTING-HR-ARCHITECTURE-AUDIT.md) | `Hrms.php`, `employees`, `users`, `teams`, `branches` |
| **Target Architecture** | [03-TARGET-RECRUITMENT-ARCHITECTURE.md](file:///e:/raptor/RAPTOR-V2.3-RECRUITMENT-BLUEPRINT/03-TARGET-RECRUITMENT-ARCHITECTURE.md) | Services, controllers, models, repositories |
| **Database Schema** | [SCHEMA/RECRUITMENT-DATA-MODEL.md](file:///e:/raptor/RAPTOR-V2.3-RECRUITMENT-BLUEPRINT/SCHEMA/RECRUITMENT-DATA-MODEL.md) | 23 normalized tables, foreign keys, indexes |
| **Migrations** | [SCHEMA/MIGRATION-PLAN.md](file:///e:/raptor/RAPTOR-V2.3-RECRUITMENT-BLUEPRINT/SCHEMA/MIGRATION-PLAN.md) | `migrations/0059_...` through `0064_...` |
| **Entity Dictionary** | [SCHEMA/ENTITY-RELATIONSHIP-DICTIONARY.md](file:///e:/raptor/RAPTOR-V2.3-RECRUITMENT-BLUEPRINT/SCHEMA/ENTITY-RELATIONSHIP-DICTIONARY.md) | Cardinality, constraints, enum values |
| **Threat Model** | [SECURITY/RECRUITMENT-THREAT-MODEL.md](file:///e:/raptor/RAPTOR-V2.3-RECRUITMENT-BLUEPRINT/SECURITY/RECRUITMENT-THREAT-MODEL.md) | File upload malware, IDOR, brute-force |
| **RBAC & Authorization** | [SECURITY/RBAC-AND-PERMISSION-MATRIX.md](file:///e:/raptor/RAPTOR-V2.3-RECRUITMENT-BLUEPRINT/SECURITY/RBAC-AND-PERMISSION-MATRIX.md) | `PermissionService.php`, 28 recruitment permissions |
| **Privacy & DPDP** | [SECURITY/PRIVACY-AND-DATA-PROTECTION.md](file:///e:/raptor/RAPTOR-V2.3-RECRUITMENT-BLUEPRINT/SECURITY/PRIVACY-AND-DATA-PROTECTION.md) | Consent tracking, retention cron, anonymization |
| **REST APIs** | [API/RECRUITMENT-API-SPECIFICATION.md](file:///e:/raptor/RAPTOR-V2.3-RECRUITMENT-BLUEPRINT/API/RECRUITMENT-API-SPECIFICATION.md) | `/api/v1/recruitment/...` endpoints, JSON contracts |
| **Events & Queues** | [API/EVENT-AND-QUEUE-SPECIFICATION.md](file:///e:/raptor/RAPTOR-V2.3-RECRUITMENT-BLUEPRINT/API/EVENT-AND-QUEUE-SPECIFICATION.md) | PSR-14 events, `recruitment_queue`, CLI daemon |
| **UI Components** | [UI-UX/RECRUITMENT-WIREFRAMES-AND-VIEWS.md](file:///e:/raptor/RAPTOR-V2.3-RECRUITMENT-BLUEPRINT/UI-UX/RECRUITMENT-WIREFRAMES-AND-VIEWS.md) | Glassmorphic design tokens, 12 core view templates |
| **Careers Portal** | [UI-UX/CAREERS-PORTAL-SPECIFICATION.md](file:///e:/raptor/RAPTOR-V2.3-RECRUITMENT-BLUEPRINT/UI-UX/CAREERS-PORTAL-SPECIFICATION.md) | Public career page, applicant portal, JSON-LD |
| **Kanban & Interviews** | [UI-UX/KANBAN-AND-INTERVIEW-UI.md](file:///e:/raptor/RAPTOR-V2.3-RECRUITMENT-BLUEPRINT/UI-UX/KANBAN-AND-INTERVIEW-UI.md) | Drag-and-drop Kanban, calendar scheduling |
| **Sprint Roadmap** | [ROADMAP/SPRINT-PLAN.md](file:///e:/raptor/RAPTOR-V2.3-RECRUITMENT-BLUEPRINT/ROADMAP/SPRINT-PLAN.md) | Sprints 0–10 task breakdown and test criteria |
| **Release Acceptance** | [ROADMAP/RELEASE-ACCEPTANCE.md](file:///e:/raptor/RAPTOR-V2.3-RECRUITMENT-BLUEPRINT/ROADMAP/RELEASE-ACCEPTANCE.md) | 10 gates, zero-downtime deploy, rollback runbook |
| **ADR Register** | [ARCHITECTURE-DECISIONS/ADR-REGISTER.md](file:///e:/raptor/RAPTOR-V2.3-RECRUITMENT-BLUEPRINT/ARCHITECTURE-DECISIONS/ADR-REGISTER.md) | Decisions ADR-001 through ADR-008 |
