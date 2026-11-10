# Raptor v2.3 — Enterprise Recruitment Management Module (ATS)
## Security 02. RBAC & Granular Permission Matrix

```
Document:             SECURITY/RBAC-AND-PERMISSION-MATRIX.md
Directory:            RAPTOR-V2.3-RECRUITMENT-BLUEPRINT/SECURITY/
Classification:       Role-Based Access Control Specification & Enforcement Rules
Target Release:       Raptor v2.3
Engine:               app/core/PermissionService.php & Policy.php
```

---

## 1. Granular Permission Catalog (28 Permissions)

All recruitment actions are guarded by explicit, granular permission keys registered in `permissions`:

| # | Permission Key | Category | Description |
|---|---|---|---|
| 1 | `recruitment.dashboard.view` | Dashboard | Access central recruitment metrics, pipeline KPIs, and recruiter workload board. |
| 2 | `recruitment.manpower.manage` | Planning | Create, edit, and review organizational headcount demand and staffing forecasts. |
| 3 | `recruitment.requisition.create` | Requisitions | Draft and submit new job requisitions for approval. |
| 4 | `recruitment.requisition.view` | Requisitions | View job requisitions within assigned departmental/team scope. |
| 5 | `recruitment.requisition.edit` | Requisitions | Modify draft or rejected job requisitions. |
| 6 | `recruitment.requisition.approve`| Requisitions | Action approval requests (Approve / Reject / Request Changes) in the requisition chain. |
| 7 | `recruitment.job.publish` | Job Postings | Publish approved vacancies to public career portal; pause or close listings. |
| 8 | `recruitment.candidate.create` | Candidates | Add new candidate profiles manually or import from external sources. |
| 9 | `recruitment.candidate.view` | Candidates | View candidate master profiles, resumes, and contact information. |
| 10| `recruitment.candidate.edit` | Candidates | Update candidate contact details, skills, and professional histories. |
| 11| `recruitment.candidate.export` | Candidates | Export candidate records and resumes in bulk (strictly audited). |
| 12| `recruitment.candidate.delete` | Candidates | Anonymize or delete candidate profiles under DPDP Act / right-to-erasure. |
| 13| `recruitment.application.view` | ATS Pipeline | View submitted applications on the Kanban board and tabular lists. |
| 14| `recruitment.application.assign` | ATS Pipeline | Assign or reassign responsible recruiters to specific applications. |
| 15| `recruitment.application.stage.update` | ATS Pipeline | Transition applications through pipeline stages (Screening, Interview, Offer). |
| 16| `recruitment.application.reject` | ATS Pipeline | Formally reject candidate applications with mandatory reason codes. |
| 17| `recruitment.interview.schedule` | Interviews | Schedule interview rounds, assign panel members, and manage meeting links. |
| 18| `recruitment.interview.feedback.submit` | Interviews | Submit structured scorecards and hiring recommendations for assigned interviews. |
| 19| `recruitment.interview.feedback.view_all` | Interviews | View peer interviewer scorecards (available only after submitting own feedback). |
| 20| `recruitment.assessment.manage` | Assessments | Create, assign, and grade candidate technical and aptitude assessments. |
| 21| `recruitment.offer.create` | Offers | Prepare draft offer letters and structured compensation packages. |
| 22| `recruitment.offer.approve` | Offers | Approve compensation packages exceeding configured departmental thresholds. |
| 23| `recruitment.offer.release` | Offers | Formally dispatch approved offer letters to selected candidates. |
| 24| `recruitment.offer.view_salary` | Offers | View unmasked salary figures and compensation breakdowns. |
| 25| `recruitment.onboarding.manage` | Pre-Joining | Manage background verification checks and pre-joining document checklists. |
| 26| `recruitment.convert.employee` | Conversion | Execute automated employee conversion from accepted candidate into HRMS. |
| 27| `recruitment.reports.view` | Analytics | View and export recruitment analytics (time-to-fill, source ROI, funnel conversion). |
| 28| `recruitment.settings.manage` | Settings | Configure workflow stages, evaluation rubrics, approval matrices, and templates. |

---

## 2. Role-to-Permission Mapping Matrix

| Permission Key | Super Admin / CEO | HR Manager / Head | Recruiter | Hiring Manager | Interviewer | Candidate (External) |
|---|:---:|:---:|:---:|:---:|:---:|:---:|
| `recruitment.dashboard.view` | [x] Full | [x] Dept | [x] Assigned | [x] Team | [ ] | [ ] |
| `recruitment.manpower.manage` | [x] | [x] | [ ] | [x] Draft | [ ] | [ ] |
| `recruitment.requisition.create` | [x] | [x] | [x] | [x] | [ ] | [ ] |
| `recruitment.requisition.view` | [x] | [x] | [x] | [x] Scope | [ ] | [ ] |
| `recruitment.requisition.edit` | [x] | [x] | [x] Draft | [x] Draft | [ ] | [ ] |
| `recruitment.requisition.approve`| [x] | [x] | [ ] | [x] Chain | [ ] | [ ] |
| `recruitment.job.publish` | [x] | [x] | [x] | [ ] | [ ] | [ ] |
| `recruitment.candidate.create` | [x] | [x] | [x] | [ ] | [ ] | [ ] |
| `recruitment.candidate.view` | [x] | [x] | [x] | [x] Scope | [x] Assigned | [ ] |
| `recruitment.candidate.edit` | [x] | [x] | [x] | [ ] | [ ] | [ ] |
| `recruitment.candidate.export` | [x] | [x] | [ ] | [ ] | [ ] | [ ] |
| `recruitment.candidate.delete` | [x] | [x] | [ ] | [ ] | [ ] | [ ] |
| `recruitment.application.view` | [x] | [x] | [x] | [x] Scope | [x] Assigned | [x] Own |
| `recruitment.application.assign` | [x] | [x] | [ ] | [ ] | [ ] | [ ] |
| `recruitment.application.stage.update` | [x] | [x] | [x] | [ ] | [ ] | [ ] |
| `recruitment.application.reject` | [x] | [x] | [x] | [x] Recom | [ ] | [ ] |
| `recruitment.interview.schedule`| [x] | [x] | [x] | [ ] | [ ] | [ ] |
| `recruitment.interview.feedback.submit` | [x] | [x] | [x] | [x] | [x] | [ ] |
| `recruitment.interview.feedback.view_all` | [x] | [x] | [x] | [x] | [ ] | [ ] |
| `recruitment.assessment.manage` | [x] | [x] | [x] | [x] Review | [ ] | [ ] |
| `recruitment.offer.create` | [x] | [x] | [x] | [ ] | [ ] | [ ] |
| `recruitment.offer.approve` | [x] | [x] | [ ] | [ ] | [ ] | [ ] |
| `recruitment.offer.release` | [x] | [x] | [x] Apprvd | [ ] | [ ] | [ ] |
| `recruitment.offer.view_salary` | [x] | [x] | [x] | [ ] Masked | [ ] Masked | [x] Own Offer |
| `recruitment.onboarding.manage` | [x] | [x] | [x] | [ ] | [ ] | [ ] |
| `recruitment.convert.employee` | [x] | [x] | [ ] | [ ] | [ ] | [ ] |
| `recruitment.reports.view` | [x] | [x] | [x] Stats | [x] Team | [ ] | [ ] |
| `recruitment.settings.manage` | [x] | [x] | [ ] | [ ] | [ ] | [ ] |

---

## 3. Server-Side Enforcement Mechanisms

### 3.1 Controller-Level Gates
Every controller action executing a state-changing recruitment operation must invoke:
```php
$this->requireAuth();
$this->requirePermission('recruitment', 'application.stage.update');
$this->validateCsrfToken();
```

### 3.2 Dynamic Scope Filtering in Repositories
Data access methods in `CandidateRepository` and `ApplicationRepository` automatically inject team-subtree boundaries:
```php
public function getApplicationsForUser(int $userId, string $role): array {
    if (in_array($role, ['admin', 'ceo', 'hr'], true)) {
        // Broad organizational visibility
        return $this->queryAllApplications();
    }

    if ($role === 'manager' || $role === 'team_leader') {
        // Restrict to hiring manager's team subtree
        $subtreeUserIds = Policy::getTeamSubtreeUserIds($userId);
        return $this->queryApplicationsByManagers($subtreeUserIds);
    }

    if ($role === 'interviewer') {
        // Restrict strictly to applications with actively assigned interview rounds
        return $this->queryApplicationsByAssignedInterviewer($userId);
    }

    return [];
}
```
