# Raptor v2.3 — Enterprise Recruitment Management Module (ATS)
## API 01. RESTful API Endpoints & Request/Response Contracts

```
Document:             API/RECRUITMENT-API-SPECIFICATION.md
Directory:            RAPTOR-V2.3-RECRUITMENT-BLUEPRINT/API/
Classification:       RESTful API Specification & OpenAPI 3.1 Alignment
Target Release:       Raptor v2.3
Base URL:             /api/v1/recruitment/
```

---

## 1. Global API Conventions

1. **Authentication & Session**:
   - Internal endpoints require an authenticated user session cookie or Bearer API token.
   - External candidate endpoints are scoped strictly to the candidate session or public career portal tokens.
2. **Standard Headers**:
   - `Content-Type: application/json`
   - `X-CSRF-Token: <token>` (mandatory on all state-altering `POST`, `PATCH`, `DELETE` calls)
   - `X-Request-ID: <uuid>` (propagated across logs and Sentry telemetry)
3. **Standard Response Envelopes**:
   ```json
   {
       "success": true,
       "data": { ... },
       "meta": { "total": 120, "page": 1, "per_page": 20 }
   }
   ```
   ```json
   {
       "success": false,
       "error": {
           "code": "VALIDATION_FAILED",
           "message": "The compensation exceeds the approved requisition budget.",
           "details": { "annual_ctc": ["Must not exceed 1,800,000 INR"] }
       }
   }
   ```

---

## 2. Core API Endpoint Catalog

### 2.1 Requisitions Management

#### `GET /api/v1/recruitment/requisitions`
- **Description**: Returns paginated list of job requisitions scoped to user's authorized subtree.
- **Query Params**: `status`, `department`, `page`, `per_page`.
- **Response**:
  ```json
  {
      "success": true,
      "data": [
          {
              "requisition_id": 14,
              "requisition_code": "REQ-2026-0014",
              "title": "Senior Backend PHP Engineer",
              "department": "Engineering",
              "vacancies": 2,
              "status": "approved",
              "hiring_manager": "Sarah Jenkins",
              "target_hire_date": "2026-12-15"
          }
      ],
      "meta": { "total": 1, "page": 1, "per_page": 20 }
  }
  ```

#### `POST /api/v1/recruitment/requisitions`
- **Description**: Creates a new draft job requisition.
- **Request Body**:
  ```json
  {
      "title": "Senior Backend PHP Engineer",
      "department": "Engineering",
      "hiring_manager_id": 5,
      "employment_type": "full_time",
      "workplace_arrangement": "hybrid",
      "vacancies": 2,
      "hiring_priority": "high",
      "min_experience_years": 5.0,
      "min_salary": 1400000.00,
      "max_salary": 1800000.00,
      "currency": "INR",
      "business_justification": "Expansion of marketing automation microservices.",
      "job_description": "We are seeking a senior engineer...",
      "required_skills": "PHP 8.2, MySQL, Redis, REST APIs"
  }
  ```

#### `POST /api/v1/recruitment/requisitions/{id}/approve`
- **Description**: Approves or rejects a pending requisition in the approval chain.
- **Request Body**:
  ```json
  {
      "decision": "approved", // "approved" | "rejected" | "changes_requested"
      "comments": "Approved within Q4 departmental headcount budget."
  }
  ```

---

### 2.2 Jobs & Career Portal

#### `GET /api/v1/recruitment/public/jobs`
- **Description**: Public career portal endpoint returning active published vacancies.
- **Security**: Public, unauthenticated, rate-limited (60 req/min per IP).
- **Query Params**: `department`, `location`, `type`, `search`.

#### `POST /api/v1/recruitment/public/jobs/{job_id}/apply`
- **Description**: Public application intake endpoint.
- **Payload**: `multipart/form-data` with `first_name`, `last_name`, `email`, `phone`, `total_experience`, `skills`, `resume` (file binary), `consent_granted` (`1`).

---

### 2.3 Candidates & ATS Applications

#### `GET /api/v1/recruitment/applications`
- **Description**: Returns applications for the interactive Kanban board or tabular view.
- **Query Params**: `job_id`, `stage`, `recruiter_id`.

#### `POST /api/v1/recruitment/applications/{id}/stage`
- **Description**: Moves an application through the recruitment pipeline.
- **Request Body**:
  ```json
  {
      "to_stage": "technical_interview",
      "reason_code": "PASSED_SCREENING",
      "comments": "Candidate showed strong mastery of PHP 8.2 and architectural design."
  }
  ```

#### `POST /api/v1/recruitment/applications/{id}/screen`
- **Description**: Submits recruiter screening scorecard.
- **Request Body**:
  ```json
  {
      "skills_rating": 4,
      "experience_rating": 5,
      "communication_rating": 4,
      "recommendation": "shortlist",
      "evaluation_comments": "Exceptional experience in enterprise CRM systems."
  }
  ```

---

### 2.4 Interviews & Feedback

#### `POST /api/v1/recruitment/interviews`
- **Description**: Schedules an interview round.
- **Request Body**:
  ```json
  {
      "application_id": 88,
      "round_number": 1,
      "round_name": "Technical Architecture Round",
      "interview_type": "technical",
      "interview_mode": "video",
      "scheduled_start": "2026-11-18 14:00:00",
      "scheduled_end": "2026-11-18 15:00:00",
      "meeting_link": "https://meet.google.com/xyz-abcd-efg",
      "panel_user_ids": [12, 18]
  }
  ```

#### `POST /api/v1/recruitment/interviews/{id}/feedback`
- **Description**: Submits an independent, blind evaluation scorecard.
- **Request Body**:
  ```json
  {
      "technical_rating": 5,
      "problem_solving_rating": 4,
      "communication_rating": 4,
      "cultural_fit_rating": 5,
      "recommendation": "strongly_recommend",
      "strengths": "Deep knowledge of database indexing and concurrency.",
      "concerns": "None identified.",
      "detailed_comments": "Outstanding candidate, clear hire."
  }
  ```

---

### 2.5 Offers & Employee Conversion

#### `POST /api/v1/recruitment/offers`
- **Description**: Generates a structured employment offer.
- **Request Body**:
  ```json
  {
      "application_id": 88,
      "job_title": "Senior Backend PHP Engineer",
      "department": "Engineering",
      "reporting_manager_id": 5,
      "annual_ctc": 1700000.00,
      "fixed_salary": 1500000.00,
      "variable_pay": 200000.00,
      "proposed_joining_date": "2026-12-01",
      "offer_expiry_date": "2026-11-25"
  }
  ```

#### `POST /api/v1/recruitment/applications/{id}/convert`
- **Description**: Converts an accepted candidate into a permanent employee in Raptor HRMS.
- **Request Body**:
  ```json
  {
      "date_of_joining": "2026-12-01",
      "pay_grade": "E4",
      "work_location": "Hyderabad Campus",
      "pf_applicable": 1
  }
  ```
- **Response**:
  ```json
  {
      "success": true,
      "data": {
          "employee_id": 412,
          "employee_code": "RAP-2026-0412",
          "user_id": 89,
          "status": "active"
      }
  }
  ```
