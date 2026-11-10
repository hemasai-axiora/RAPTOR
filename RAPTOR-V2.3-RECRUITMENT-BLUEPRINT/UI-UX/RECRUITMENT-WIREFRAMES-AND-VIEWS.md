# Raptor v2.3 — Enterprise Recruitment Management Module (ATS)
## UI/UX 01. Recruitment Wireframes, View Hierarchy & Design System Integration

```
Document:             UI-UX/RECRUITMENT-WIREFRAMES-AND-VIEWS.md
Directory:            RAPTOR-V2.3-RECRUITMENT-BLUEPRINT/UI-UX/
Classification:       Enterprise UI/UX Specifications, Wireframes & View Hierarchy
Target Release:       Raptor v2.3
Design Baseline:      Raptor v2.2 Design System Tokens & Glassmorphism Engine
```

---

## 1. Design System & Theme Token Alignment

All recruitment views natively integrate with Raptor v2.2 design tokens (`assets/css/raptor-tokens.css`) and glass surfaces (`assets/css/raptor-glass.css`):
- **Glass Surfaces**: Restrained backdrop blur (`backdrop-filter: blur(12px)`), translucent border (`var(--raptor-glass-border)`), and soft layered elevation shadows.
- **Theme Support**: 100% parity across Dark Mode (`#0F172A` background, `#1E293B` cards) and Light Mode (`#F8FAFC` background, `#FFFFFF` cards) without hardcoded inline hex colors.
- **Component Re-use**: Built with Raptor's native component engine (`$this->component('card', [...])`, `stat_tile`, `badge`, `empty_state`).

---

## 2. Recruitment View Hierarchy

```
app/views/recruitment/
  ├── layout/
  │   └── recruitment_nav.php              <-- Sub-navigation tab bar
  ├── dashboard/
  │   └── index.php                        <-- Central recruitment KPIs & workload analytics
  ├── requisitions/
  │   ├── index.php                        <-- Requisition listing & status filter pills
  │   ├── create.php                       <-- Requisition creation & editing wizard
  │   └── view.php                         <-- Requisition details & approval timeline
  ├── jobs/
  │   ├── index.php                        <-- Active published jobs & status controls
  │   └── edit.php                         <-- Job publishing settings & description editor
  ├── candidates/
  │   ├── index.php                        <-- Candidate database, search & deduplication
  │   └── profile.php                      <-- Comprehensive Candidate 360 profile
  ├── applications/
  │   ├── index.php                        <-- Interactive Recruitment Kanban board
  │   └── detail.php                       <-- Application detail & stage history
  ├── interviews/
  │   ├── calendar.php                     <-- Interactive multi-interviewer calendar view
  │   └── feedback_modal.php               <-- Blind interview feedback evaluation modal
  ├── offers/
  │   ├── index.php                        <-- Offer listing & approval status
  │   └── create.php                       <-- Compensation builder & offer letter generator
  ├── onboarding/
  │   ├── index.php                        <-- Pre-joining checklist & BGV tracking
  │   └── convert.php                      <-- Employee conversion preview & confirmation
  ├── reports/
  │   └── index.php                        <-- Funnel conversion, time-to-fill & source ROI
  └── settings/
      └── index.php                        <-- Stage definitions, templates & rubrics
```

---

## 3. Core Wireframe Specifications

### 3.1 Recruitment Dashboard (`dashboard/index.php`)
```
+----------------------------------------------------------------------------------------------------+
|  RECRUITMENT COMMAND CENTER                       [+ New Requisition]  [+ Add Candidate]  [Reports] |
+----------------------------------------------------------------------------------------------------+
|  [ STAT TILE 1 ]     [ STAT TILE 2 ]     [ STAT TILE 3 ]     [ STAT TILE 4 ]     [ STAT TILE 5 ]   |
|  Active Requisitions Applications Pool   Interviews Today    Pending Offers      Hired This Month  |
|  18 (3 Urgent)       246 Active          8 Scheduled         4 Awaiting Signoff  12 Converted      |
+----------------------------------------------------------------------------------------------------+
|  RECRUITMENT PIPELINE FUNNEL (Real-Time Stages)                                                    |
|  Applied(84) -> Screened(42) -> Shortlisted(28) -> Interview(16) -> Offer(6) -> Hired(12)         |
+------------------------------------------------------------------+---------------------------------+
|  ACTIVE REQUISITIONS TRACKER                                     |  UPCOMING INTERVIEWS (TODAY)    |
|  [Requisition Code] [Job Title] [Dept] [Manager] [Target] [Stat] |  10:00 AM - Sarah (PHP Backend) |
|  REQ-2026-0042      Backend Eng  Eng    R. Gupta  Dec 01   Open  |  11:30 AM - Amit (Frontend Dev) |
|  REQ-2026-0043      Sales Exec   Sales  M. Khan   Nov 20   Urgent|  02:00 PM - Priya (HR Lead)     |
+------------------------------------------------------------------+---------------------------------+
```

### 3.2 Candidate 360 Profile (`candidates/profile.php`)
```
+----------------------------------------------------------------------------------------------------+
|  < Back to Candidates   CANDIDATE #CAN-2026-00192: Priya Sharma                                    |
+----------------------------------------------------------------------------------------------------+
|  [AVATAR]  Priya Sharma                             | Status: Interviewing (Round 2)               |
|            Lead Full-Stack Engineer @ TechCorp      | Recruiter: John Doe | Source: Career Portal  |
|            Email: priya.s***@example.com (Verified) | Experience: 6.5 Years | Location: Hyderabad  |
|            Phone: +91 98765 ***** (Masked)          | Notice: 30 Days | Expected: [Masked]         |
+-----------------------------------------------------+----------------------------------------------+
|  [Overview]  [Applications (2)]  [Interviews]  [Documents]  [Screening Scorecard]  [Timeline & Notes]|
+----------------------------------------------------------------------------------------------------+
|  CURRENT APPLICATION: Senior PHP Engineer (REQ-2026-0042)                                          |
|  Stage: Technical Interview Round 2                                                                |
|                                                                                                    |
|  AI EVIDENCE MATCHING SUMMARY (ADVISORY ONLY)                                                      |
|  Match Confidence: 88% | PHP 8.2 (Verified in Resume) | MySQL Optimization (Documented)           |
|                                                                                                    |
|  UPLOADED DOCUMENTS (AUTHENTICATED STORAGE)                                                        |
|  - Resume_Priya_Sharma_2026.pdf (245 KB) [View / Download] [Re-parse]                              |
|  - Portfolio_Architecture_Sample.pdf (1.2 MB) [View / Download]                                    |
|                                                                                                    |
|  INTERVIEW EVALUATION SCORECARD                                                                    |
|  Round 1 (Technical Basics): Recommended (Score: 4.2/5.0) by Alex Vance                            |
|  Round 2 (System Design): Scheduled for Tomorrow, 3:00 PM                                          |
+----------------------------------------------------------------------------------------------------+
```

### 3.3 Employee Conversion View (`onboarding/convert.php`)
```
+----------------------------------------------------------------------------------------------------+
|  CONVERT ACCEPTED CANDIDATE TO PERMANENT EMPLOYEE                                                 |
+----------------------------------------------------------------------------------------------------+
|  [!] CONFIRMATION GATE: This action creates an active employee record in Raptor HRMS.              |
+----------------------------------------------------------------------------------------------------+
|  Candidate: Priya Sharma | Requisition: REQ-2026-0042 (Senior Backend PHP Engineer)               |
|  Offer Status: Accepted on 2026-11-09 | Proposed Joining Date: 2026-12-01                          |
+----------------------------------------------------------------------------------------------------+
|  MAPPED EMPLOYEE PROFILE FIELDS:                                                                   |
|  Full Name:       [ Priya Sharma                 ]   Email:        [ priya.sharma@raptorcrm.com   ]|
|  Employee Code:   [ RAP-2026-0412 (Auto-Generated) ] Department:   [ Engineering                  ]|
|  Designation:     [ Senior Software Engineer     ]   Reporting To: [ Robert Gupta (ID: 5)         ]|
|  Work Location:   [ Hyderabad Tech Park          ]   Employment:   [ Full-Time Permanent          ]|
|  Pay Grade:       [ E4 - Senior Professional     ]   Base Salary:  [ ₹ 1,500,000.00 / Year        ]|
|  Date of Joining: [ 2026-12-01                   ]   PF Enabled:   [x] Yes  ESIC: [ ] No          |
+----------------------------------------------------------------------------------------------------+
|  [ Cancel / Return ]                                  [ CONFIRM & CONVERT TO EMPLOYEE ]            |
+----------------------------------------------------------------------------------------------------+
```
