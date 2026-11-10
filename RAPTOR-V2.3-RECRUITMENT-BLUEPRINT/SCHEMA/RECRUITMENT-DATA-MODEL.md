# Raptor v2.3 — Enterprise Recruitment Management Module (ATS)
## Schema 01. Normalized Relational Data Model (DDL)

```
Document:             SCHEMA/RECRUITMENT-DATA-MODEL.md
Directory:            RAPTOR-V2.3-RECRUITMENT-BLUEPRINT/SCHEMA/
Classification:       Database DDL Specification & Indexing Architecture
Target Release:       Raptor v2.3
Engine:               MySQL 8.0+ / MariaDB (InnoDB, utf8mb4_unicode_ci)
```

---

## 1. Relational Entity Overview

The Recruitment database architecture comprises **23 normalized tables** designed for enterprise scalability, tenant isolation, strict foreign key referential integrity, and high-performance querying:

```
Requisitions & Jobs:
  ├── recruitment_requisitions             <-- Headcount requests & vacancy tracking
  ├── recruitment_requisition_approvals   <-- Multi-level approver chain & decisions
  ├── recruitment_job_descriptions        <-- Standardized job description templates
  └── recruitment_jobs                    <-- Published vacancies & career site listings

Candidates & Documents:
  ├── recruitment_candidates              <-- Master candidate profiles (person record)
  ├── recruitment_candidate_documents    <-- Authenticated private storage references (resumes)
  ├── recruitment_candidate_consents     <-- DPDP Act / GDPR privacy consent logs
  └── recruitment_candidate_notes        <-- Internal recruiter notes & timeline entries

Applications & Screening:
  ├── recruitment_applications            <-- Candidate-to-Job application submissions
  ├── recruitment_application_stages      <-- Immutable stage movement & audit history
  └── recruitment_application_screenings  <-- Recruiter evaluation scorecards & criteria

Interviews & Assessments:
  ├── recruitment_interviews              <-- Scheduled interview rounds & calendar entries
  ├── recruitment_interview_panel         <-- Assigned interviewers per round
  ├── recruitment_interview_feedback      <-- Independent, blind interviewer scorecards
  ├── recruitment_assessments             <-- Coding/aptitude assignment definitions
  └── recruitment_assessment_submissions  <-- Candidate assignment submissions & grading

Offers, Onboarding & Communications:
  ├── recruitment_offers                  <-- Salary breakdown & offer letter revisions
  ├── recruitment_offer_approvals         <-- Multi-tiered offer sign-off matrix
  ├── recruitment_background_checks       <-- Third-party & internal BGV verification
  ├── recruitment_pre_joining_checklist   <-- Onboarding document collection checklist
  ├── recruitment_communications          <-- Candidate notification records (Email/WhatsApp)
  ├── recruitment_queue                   <-- Transactional background execution queue
  ├── recruitment_ai_logs                 <-- AI prompt execution, token usage & telemetry
  └── recruitment_settings                <-- Configurable module preferences & rubrics
```

---

## 2. Table DDL Specifications

### 2.1 Requisitions and Jobs

```sql
-- 1. Job Requisitions (Headcount authorizations)
CREATE TABLE IF NOT EXISTS recruitment_requisitions (
    requisition_id INT AUTO_INCREMENT PRIMARY KEY,
    requisition_code VARCHAR(50) NOT NULL UNIQUE,
    title VARCHAR(150) NOT NULL,
    department VARCHAR(100) NOT NULL,
    team_id INT NULL,
    branch_id INT NULL,
    hiring_manager_id INT NOT NULL,
    assigned_recruiter_id INT NULL,
    reporting_manager_id INT NULL,
    employment_type ENUM('full_time', 'part_time', 'contract', 'internship') NOT NULL DEFAULT 'full_time',
    workplace_arrangement ENUM('on_site', 'hybrid', 'remote') NOT NULL DEFAULT 'on_site',
    vacancies INT NOT NULL DEFAULT 1,
    hiring_priority ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
    hiring_reason ENUM('new_position', 'replacement', 'expansion', 'project_based') NOT NULL DEFAULT 'new_position',
    replacement_for_user_id INT NULL,
    min_experience_years DECIMAL(4,1) NOT NULL DEFAULT 0.0,
    max_experience_years DECIMAL(4,1) NULL,
    min_salary DECIMAL(14,2) NULL,
    max_salary DECIMAL(14,2) NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'INR',
    is_salary_confidential TINYINT(1) NOT NULL DEFAULT 1,
    budget_allocated DECIMAL(14,2) NULL,
    target_hire_date DATE NULL,
    requisition_expiry_date DATE NULL,
    business_justification TEXT NULL,
    job_description TEXT NOT NULL,
    responsibilities TEXT NULL,
    required_skills TEXT NULL,
    status ENUM('draft', 'pending_approval', 'changes_requested', 'approved', 'rejected', 'open', 'partially_filled', 'filled', 'on_hold', 'cancelled', 'closed') NOT NULL DEFAULT 'draft',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_rec_req_status (status),
    INDEX idx_rec_req_dept (department),
    INDEX idx_rec_req_mgr (hiring_manager_id),
    INDEX idx_rec_req_recruiter (assigned_recruiter_id),
    FOREIGN KEY (team_id) REFERENCES teams(team_id) ON DELETE SET NULL,
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id) ON DELETE SET NULL,
    FOREIGN KEY (hiring_manager_id) REFERENCES users(user_id) ON DELETE RESTRICT,
    FOREIGN KEY (assigned_recruiter_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Requisition Approvals (Multi-step approval chain)
CREATE TABLE IF NOT EXISTS recruitment_requisition_approvals (
    approval_id INT AUTO_INCREMENT PRIMARY KEY,
    requisition_id INT NOT NULL,
    step_number TINYINT NOT NULL DEFAULT 1,
    approver_role VARCHAR(50) NOT NULL, -- 'department_head', 'hr_manager', 'finance', 'ceo'
    approver_id INT NULL,               -- Explicit assigned user
    status ENUM('pending', 'approved', 'rejected', 'changes_requested') NOT NULL DEFAULT 'pending',
    comments TEXT NULL,
    actioned_by INT NULL,
    actioned_at DATETIME NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_req_appr (requisition_id, step_number),
    FOREIGN KEY (requisition_id) REFERENCES recruitment_requisitions(requisition_id) ON DELETE CASCADE,
    FOREIGN KEY (actioned_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Job Description Templates
CREATE TABLE IF NOT EXISTS recruitment_job_descriptions (
    template_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    department VARCHAR(100) NOT NULL,
    job_summary TEXT NOT NULL,
    responsibilities TEXT NOT NULL,
    required_skills TEXT NOT NULL,
    preferred_skills TEXT NULL,
    min_experience_years DECIMAL(4,1) NOT NULL DEFAULT 0.0,
    education_requirements VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Published Jobs (Public vacancies)
CREATE TABLE IF NOT EXISTS recruitment_jobs (
    job_id INT AUTO_INCREMENT PRIMARY KEY,
    requisition_id INT NOT NULL,
    job_code VARCHAR(50) NOT NULL UNIQUE,
    slug VARCHAR(180) NOT NULL UNIQUE,
    title VARCHAR(150) NOT NULL,
    department VARCHAR(100) NOT NULL,
    location VARCHAR(150) NOT NULL,
    employment_type ENUM('full_time', 'part_time', 'contract', 'internship') NOT NULL,
    workplace_arrangement ENUM('on_site', 'hybrid', 'remote') NOT NULL,
    job_description TEXT NOT NULL,
    responsibilities TEXT NOT NULL,
    required_skills TEXT NOT NULL,
    min_experience_years DECIMAL(4,1) NOT NULL DEFAULT 0.0,
    show_salary_publicly TINYINT(1) NOT NULL DEFAULT 0,
    min_salary DECIMAL(14,2) NULL,
    max_salary DECIMAL(14,2) NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'INR',
    published_at DATETIME NULL,
    closes_at DATETIME NULL,
    status ENUM('draft', 'published', 'paused', 'closed') NOT NULL DEFAULT 'draft',
    views_count INT NOT NULL DEFAULT 0,
    applications_count INT NOT NULL DEFAULT 0,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_rec_job_slug (slug),
    INDEX idx_rec_job_status (status),
    FOREIGN KEY (requisition_id) REFERENCES recruitment_requisitions(requisition_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 2.2 Candidates and Documents

```sql
-- 5. Candidate Master (Person Entity)
CREATE TABLE IF NOT EXISTS recruitment_candidates (
    candidate_id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_code VARCHAR(50) NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    current_company VARCHAR(150) NULL,
    current_designation VARCHAR(150) NULL,
    total_experience_years DECIMAL(4,1) NOT NULL DEFAULT 0.0,
    primary_skills TEXT NULL,
    secondary_skills TEXT NULL,
    highest_education VARCHAR(150) NULL,
    city VARCHAR(100) NULL,
    country VARCHAR(100) NOT NULL DEFAULT 'India',
    notice_period_days INT NULL,
    current_ctc DECIMAL(14,2) NULL,
    expected_ctc DECIMAL(14,2) NULL,
    linkedin_url VARCHAR(255) NULL,
    portfolio_url VARCHAR(255) NULL,
    source ENUM('career_portal', 'referral', 'linkedin', 'direct', 'agency', 'other') NOT NULL DEFAULT 'career_portal',
    source_detail VARCHAR(150) NULL,
    is_blacklisted TINYINT(1) NOT NULL DEFAULT 0,
    blacklist_reason TEXT NULL,
    candidate_owner_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_rec_cand_email (email),
    INDEX idx_rec_cand_phone (phone),
    FOREIGN KEY (candidate_owner_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Candidate Documents (Resumes, Portfolios, Proofs)
CREATE TABLE IF NOT EXISTS recruitment_candidate_documents (
    document_id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT NOT NULL,
    document_type ENUM('resume', 'cover_letter', 'portfolio', 'certificate', 'id_proof', 'salary_slip') NOT NULL,
    storage_key VARCHAR(255) NOT NULL, -- E.g. "recruitment/2026/11/ab12cd...pdf"
    file_name VARCHAR(255) NOT NULL,
    file_size_bytes INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    parsed_text MEDIUMTEXT NULL,
    is_primary_resume TINYINT(1) NOT NULL DEFAULT 0,
    uploaded_by_user_id INT NULL,     -- NULL if uploaded by candidate directly
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cand_doc (candidate_id, document_type),
    FOREIGN KEY (candidate_id) REFERENCES recruitment_candidates(candidate_id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Candidate Consents (DPDP Act & GDPR compliance)
CREATE TABLE IF NOT EXISTS recruitment_candidate_consents (
    consent_id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT NOT NULL,
    consent_type VARCHAR(100) NOT NULL, -- 'data_processing', 'communication', 'bgv_check'
    is_granted TINYINT(1) NOT NULL DEFAULT 1,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    revoked_at DATETIME NULL,
    INDEX idx_cand_consent (candidate_id, consent_type),
    FOREIGN KEY (candidate_id) REFERENCES recruitment_candidates(candidate_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Candidate Internal Notes
CREATE TABLE IF NOT EXISTS recruitment_candidate_notes (
    note_id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT NOT NULL,
    application_id INT NULL,
    author_user_id INT NOT NULL,
    note_text TEXT NOT NULL,
    is_confidential TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (candidate_id) REFERENCES recruitment_candidates(candidate_id) ON DELETE CASCADE,
    FOREIGN KEY (author_user_id) REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 2.3 Applications and Screening

```sql
-- 9. Candidate Applications (The link between candidate and job)
CREATE TABLE IF NOT EXISTS recruitment_applications (
    application_id INT AUTO_INCREMENT PRIMARY KEY,
    application_code VARCHAR(50) NOT NULL UNIQUE,
    candidate_id INT NOT NULL,
    job_id INT NOT NULL,
    current_stage ENUM(
        'applied', 'screening', 'shortlisted', 'assessment',
        'technical_interview', 'hr_interview', 'final_interview',
        'offer_preparation', 'offer_approved', 'offer_released',
        'offer_accepted', 'bgv', 'pre_joining', 'hired',
        'rejected', 'withdrawn', 'offer_declined'
    ) NOT NULL DEFAULT 'applied',
    stage_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    rejection_reason VARCHAR(255) NULL,
    rejection_notes TEXT NULL,
    assigned_recruiter_id INT NULL,
    match_score_advisory TINYINT NULL, -- 0-100 advisory score from AI or rubric
    converted_employee_id INT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_cand_job (candidate_id, job_id),
    INDEX idx_app_stage (current_stage),
    INDEX idx_app_recruiter (assigned_recruiter_id),
    FOREIGN KEY (candidate_id) REFERENCES recruitment_candidates(candidate_id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES recruitment_jobs(job_id) ON DELETE RESTRICT,
    FOREIGN KEY (assigned_recruiter_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (converted_employee_id) REFERENCES employees(employee_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Application Stages History (Audit log of movements)
CREATE TABLE IF NOT EXISTS recruitment_application_stages (
    stage_history_id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    from_stage VARCHAR(50) NOT NULL,
    to_stage VARCHAR(50) NOT NULL,
    actor_user_id INT NULL,
    reason_code VARCHAR(100) NULL,
    comments TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_stage_app (application_id),
    FOREIGN KEY (application_id) REFERENCES recruitment_applications(application_id) ON DELETE CASCADE,
    FOREIGN KEY (actor_user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Candidate Screening Evaluations
CREATE TABLE IF NOT EXISTS recruitment_application_screenings (
    screening_id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    screener_user_id INT NOT NULL,
    skills_rating TINYINT NOT NULL DEFAULT 3,     -- 1 to 5
    experience_rating TINYINT NOT NULL DEFAULT 3, -- 1 to 5
    communication_rating TINYINT NOT NULL DEFAULT 3,
    overall_score DECIMAL(5,2) NOT NULL,          -- Weighted percentage
    recommendation ENUM('shortlist', 'reject', 'hold', 'request_info') NOT NULL,
    evaluation_comments TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES recruitment_applications(application_id) ON DELETE CASCADE,
    FOREIGN KEY (screener_user_id) REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 2.4 Interviews and Assessments

```sql
-- 12. Interview Schedules
CREATE TABLE IF NOT EXISTS recruitment_interviews (
    interview_id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    round_number TINYINT NOT NULL DEFAULT 1,
    round_name VARCHAR(100) NOT NULL, -- 'Technical Round 1', 'Managerial', 'HR'
    interview_type ENUM('technical', 'functional', 'hr', 'leadership') NOT NULL DEFAULT 'technical',
    interview_mode ENUM('video', 'in_person', 'telephone') NOT NULL DEFAULT 'video',
    scheduled_start DATETIME NOT NULL,
    scheduled_end DATETIME NOT NULL,
    timezone VARCHAR(50) NOT NULL DEFAULT 'Asia/Kolkata',
    meeting_link VARCHAR(255) NULL,
    location_details VARCHAR(255) NULL,
    coordinator_user_id INT NOT NULL,
    status ENUM('scheduled', 'completed', 'rescheduled', 'cancelled', 'no_show') NOT NULL DEFAULT 'scheduled',
    reschedule_reason TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_int_time (scheduled_start),
    INDEX idx_int_status (status),
    FOREIGN KEY (application_id) REFERENCES recruitment_applications(application_id) ON DELETE CASCADE,
    FOREIGN KEY (coordinator_user_id) REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Interview Panel Members
CREATE TABLE IF NOT EXISTS recruitment_interview_panel (
    panel_id INT AUTO_INCREMENT PRIMARY KEY,
    interview_id INT NOT NULL,
    interviewer_user_id INT NOT NULL,
    is_lead TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_int_panel (interview_id, interviewer_user_id),
    FOREIGN KEY (interview_id) REFERENCES recruitment_interviews(interview_id) ON DELETE CASCADE,
    FOREIGN KEY (interviewer_user_id) REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Interview Feedback (Blind / Independent evaluation scorecards)
CREATE TABLE IF NOT EXISTS recruitment_interview_feedback (
    feedback_id INT AUTO_INCREMENT PRIMARY KEY,
    interview_id INT NOT NULL,
    interviewer_user_id INT NOT NULL,
    technical_rating TINYINT NOT NULL DEFAULT 3,   -- 1 to 5
    problem_solving_rating TINYINT NOT NULL DEFAULT 3,
    communication_rating TINYINT NOT NULL DEFAULT 3,
    cultural_fit_rating TINYINT NOT NULL DEFAULT 3,
    overall_rating TINYINT NOT NULL DEFAULT 3,
    recommendation ENUM('strongly_recommend', 'recommend', 'hold', 'do_not_recommend') NOT NULL,
    strengths TEXT NULL,
    concerns TEXT NULL,
    detailed_comments TEXT NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_int_feedback (interview_id, interviewer_user_id),
    FOREIGN KEY (interview_id) REFERENCES recruitment_interviews(interview_id) ON DELETE CASCADE,
    FOREIGN KEY (interviewer_user_id) REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. Assessment Definitions
CREATE TABLE IF NOT EXISTS recruitment_assessments (
    assessment_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    assessment_type ENUM('technical_task', 'coding_test', 'aptitude', 'writing_sample') NOT NULL,
    description TEXT NOT NULL,
    instructions TEXT NOT NULL,
    duration_minutes INT NOT NULL DEFAULT 120,
    max_score INT NOT NULL DEFAULT 100,
    passing_score INT NOT NULL DEFAULT 60,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. Assessment Submissions
CREATE TABLE IF NOT EXISTS recruitment_assessment_submissions (
    submission_id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    application_id INT NOT NULL,
    assigned_at DATETIME NOT NULL,
    due_date DATETIME NOT NULL,
    submitted_at DATETIME NULL,
    submission_url VARCHAR(255) NULL,
    submission_file_key VARCHAR(255) NULL,
    score_awarded INT NULL,
    evaluator_user_id INT NULL,
    evaluator_comments TEXT NULL,
    status ENUM('assigned', 'submitted', 'evaluated', 'expired') NOT NULL DEFAULT 'assigned',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assessment_id) REFERENCES recruitment_assessments(assessment_id) ON DELETE RESTRICT,
    FOREIGN KEY (application_id) REFERENCES recruitment_applications(application_id) ON DELETE CASCADE,
    FOREIGN KEY (evaluator_user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 2.5 Offers, Background Verification & Queues

```sql
-- 17. Offers Management
CREATE TABLE IF NOT EXISTS recruitment_offers (
    offer_id INT AUTO_INCREMENT PRIMARY KEY,
    offer_code VARCHAR(50) NOT NULL UNIQUE,
    application_id INT NOT NULL,
    version_number TINYINT NOT NULL DEFAULT 1,
    job_title VARCHAR(150) NOT NULL,
    department VARCHAR(100) NOT NULL,
    reporting_manager_id INT NULL,
    work_location VARCHAR(150) NOT NULL,
    employment_type ENUM('full_time', 'part_time', 'contract') NOT NULL DEFAULT 'full_time',
    annual_ctc DECIMAL(14,2) NOT NULL,
    fixed_salary DECIMAL(14,2) NOT NULL,
    variable_pay DECIMAL(14,2) NOT NULL DEFAULT 0.0,
    joining_bonus DECIMAL(14,2) NOT NULL DEFAULT 0.0,
    currency VARCHAR(10) NOT NULL DEFAULT 'INR',
    proposed_joining_date DATE NOT NULL,
    offer_expiry_date DATE NOT NULL,
    probation_period_months TINYINT NOT NULL DEFAULT 3,
    notice_period_days INT NOT NULL DEFAULT 30,
    offer_letter_storage_key VARCHAR(255) NULL,
    status ENUM('draft', 'pending_approval', 'changes_requested', 'approved', 'released', 'viewed', 'accepted', 'declined', 'expired', 'withdrawn') NOT NULL DEFAULT 'draft',
    decision_reason TEXT NULL,
    accepted_at DATETIME NULL,
    declined_at DATETIME NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ofr_app (application_id),
    INDEX idx_ofr_status (status),
    FOREIGN KEY (application_id) REFERENCES recruitment_applications(application_id) ON DELETE CASCADE,
    FOREIGN KEY (reporting_manager_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. Offer Approvals (Threshold sign-offs)
CREATE TABLE IF NOT EXISTS recruitment_offer_approvals (
    approval_id INT AUTO_INCREMENT PRIMARY KEY,
    offer_id INT NOT NULL,
    step_number TINYINT NOT NULL DEFAULT 1,
    approver_role VARCHAR(50) NOT NULL, -- 'hr_head', 'finance_director', 'ceo'
    approver_id INT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    comments TEXT NULL,
    actioned_by INT NULL,
    actioned_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (offer_id) REFERENCES recruitment_offers(offer_id) ON DELETE CASCADE,
    FOREIGN KEY (actioned_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 19. Background Verification (BGV)
CREATE TABLE IF NOT EXISTS recruitment_background_checks (
    bgv_id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    check_type ENUM('employment', 'education', 'criminal', 'reference', 'identity') NOT NULL,
    provider_name VARCHAR(100) NOT NULL DEFAULT 'internal',
    status ENUM('initiated', 'in_progress', 'clear', 'discrepancy', 'failed') NOT NULL DEFAULT 'initiated',
    report_storage_key VARCHAR(255) NULL,
    findings_summary TEXT NULL,
    verified_by_user_id INT NULL,
    verified_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES recruitment_applications(application_id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 20. Pre-Joining Checklist Items
CREATE TABLE IF NOT EXISTS recruitment_pre_joining_checklist (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    item_name VARCHAR(150) NOT NULL,
    is_mandatory TINYINT(1) NOT NULL DEFAULT 1,
    is_completed TINYINT(1) NOT NULL DEFAULT 0,
    verified_document_id INT NULL,
    verified_by_user_id INT NULL,
    verified_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES recruitment_applications(application_id) ON DELETE CASCADE,
    FOREIGN KEY (verified_document_id) REFERENCES recruitment_candidate_documents(document_id) ON DELETE SET NULL,
    FOREIGN KEY (verified_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 21. Recruitment Communications (Audit trail of dispatched emails & WhatsApp)
CREATE TABLE IF NOT EXISTS recruitment_communications (
    comm_id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT NOT NULL,
    application_id INT NULL,
    channel ENUM('email', 'whatsapp', 'sms') NOT NULL,
    template_key VARCHAR(100) NOT NULL,
    recipient_address VARCHAR(150) NOT NULL,
    subject VARCHAR(255) NULL,
    body_excerpt TEXT NULL,
    status ENUM('queued', 'sent', 'delivered', 'failed') NOT NULL DEFAULT 'queued',
    provider_message_id VARCHAR(255) NULL,
    error_message TEXT NULL,
    sent_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (candidate_id) REFERENCES recruitment_candidates(candidate_id) ON DELETE CASCADE,
    FOREIGN KEY (application_id) REFERENCES recruitment_applications(application_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 22. Transactional Recruitment Execution Queue
CREATE TABLE IF NOT EXISTS recruitment_queue (
    queue_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    job_type VARCHAR(100) NOT NULL, -- 'email_notification', 'resume_parsing', 'ai_matching'
    payload_json JSON NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'failed', 'dlq') NOT NULL DEFAULT 'pending',
    attempts TINYINT NOT NULL DEFAULT 0,
    max_attempts TINYINT NOT NULL DEFAULT 3,
    scheduled_at DATETIME NOT NULL,
    locked_at DATETIME NULL,
    locked_by VARCHAR(100) NULL,
    last_error TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rec_q_status (status, scheduled_at),
    INDEX idx_rec_q_lock (locked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 23. AI Execution & Telemetry Logs
CREATE TABLE IF NOT EXISTS recruitment_ai_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    provider ENUM('gemini', 'openai', 'anthropic') NOT NULL,
    model_name VARCHAR(100) NOT NULL,
    task_type VARCHAR(100) NOT NULL, -- 'resume_summary', 'job_matching', 'interview_questions'
    prompt_tokens INT NOT NULL DEFAULT 0,
    completion_tokens INT NOT NULL DEFAULT 0,
    latency_ms INT NOT NULL DEFAULT 0,
    is_success TINYINT(1) NOT NULL DEFAULT 1,
    error_message TEXT NULL,
    created_by_user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```
