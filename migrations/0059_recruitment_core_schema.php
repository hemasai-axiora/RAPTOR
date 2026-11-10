<?php
/**
 * Migration 0059: Recruitment Core Schema (Raptor v2.3 ATS)
 * -----------------------------------------------------------------------------------
 * Provisions core recruitment foundation tables:
 * 1. recruitment_requisitions (Headcount hiring authorizations)
 * 2. recruitment_requisition_approvals (Multi-level approval audit trail)
 * 3. recruitment_job_descriptions (Job description templates)
 * 4. recruitment_jobs (Published vacancies & career site listings)
 * 5. recruitment_candidates (Candidate master person records)
 * 6. recruitment_candidate_documents (Private resume & document storage keys)
 * 7. recruitment_candidate_consents (DPDP Act 2023 / GDPR consent logs)
 * 8. recruitment_candidate_notes (Internal recruiter evaluation notes)
 * 9. recruitment_applications (Candidate-to-Job application submissions)
 * 10. recruitment_application_stages (Application stage transition history)
 */

$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
echo "Starting Migration 0059: Recruitment Core Schema ({$driver})...\n";

if ($driver === 'sqlite') {
    // 1. Requisitions
    $db->exec("
    CREATE TABLE IF NOT EXISTS recruitment_requisitions (
        requisition_id INTEGER PRIMARY KEY AUTOINCREMENT,
        requisition_code VARCHAR(50) NOT NULL UNIQUE,
        title VARCHAR(150) NOT NULL,
        department VARCHAR(100) NOT NULL,
        team_id INT NULL,
        branch_id INT NULL,
        hiring_manager_id INT NOT NULL,
        assigned_recruiter_id INT NULL,
        reporting_manager_id INT NULL,
        employment_type VARCHAR(50) NOT NULL DEFAULT 'full_time',
        workplace_arrangement VARCHAR(50) NOT NULL DEFAULT 'on_site',
        vacancies INT NOT NULL DEFAULT 1,
        hiring_priority VARCHAR(50) NOT NULL DEFAULT 'medium',
        hiring_reason VARCHAR(50) NOT NULL DEFAULT 'new_position',
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
        status VARCHAR(50) NOT NULL DEFAULT 'draft',
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ");

    // 2. Requisition Approvals
    $db->exec("
    CREATE TABLE IF NOT EXISTS recruitment_requisition_approvals (
        approval_id INTEGER PRIMARY KEY AUTOINCREMENT,
        requisition_id INT NOT NULL,
        step_number TINYINT NOT NULL DEFAULT 1,
        approver_role VARCHAR(50) NOT NULL,
        approver_id INT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'pending',
        comments TEXT NULL,
        actioned_by INT NULL,
        actioned_at DATETIME NULL,
        ip_address VARCHAR(45) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ");

    // 3. Job Description Templates
    $db->exec("
    CREATE TABLE IF NOT EXISTS recruitment_job_descriptions (
        template_id INTEGER PRIMARY KEY AUTOINCREMENT,
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
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ");

    // 4. Published Jobs
    $db->exec("
    CREATE TABLE IF NOT EXISTS recruitment_jobs (
        job_id INTEGER PRIMARY KEY AUTOINCREMENT,
        requisition_id INT NOT NULL,
        job_code VARCHAR(50) NOT NULL UNIQUE,
        slug VARCHAR(180) NOT NULL UNIQUE,
        title VARCHAR(150) NOT NULL,
        department VARCHAR(100) NOT NULL,
        location VARCHAR(150) NOT NULL,
        employment_type VARCHAR(50) NOT NULL,
        workplace_arrangement VARCHAR(50) NOT NULL,
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
        status VARCHAR(50) NOT NULL DEFAULT 'draft',
        views_count INT NOT NULL DEFAULT 0,
        applications_count INT NOT NULL DEFAULT 0,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ");

    // 5. Candidate Master
    $db->exec("
    CREATE TABLE IF NOT EXISTS recruitment_candidates (
        candidate_id INTEGER PRIMARY KEY AUTOINCREMENT,
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
        source VARCHAR(50) NOT NULL DEFAULT 'career_portal',
        source_detail VARCHAR(150) NULL,
        is_blacklisted TINYINT(1) NOT NULL DEFAULT 0,
        blacklist_reason TEXT NULL,
        candidate_owner_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ");

    // 6. Documents
    $db->exec("
    CREATE TABLE IF NOT EXISTS recruitment_candidate_documents (
        document_id INTEGER PRIMARY KEY AUTOINCREMENT,
        candidate_id INT NOT NULL,
        document_type VARCHAR(50) NOT NULL,
        storage_key VARCHAR(255) NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_size_bytes INT NOT NULL,
        mime_type VARCHAR(100) NOT NULL,
        parsed_text MEDIUMTEXT NULL,
        is_primary_resume TINYINT(1) NOT NULL DEFAULT 0,
        uploaded_by_user_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ");

    // 7. Consents
    $db->exec("
    CREATE TABLE IF NOT EXISTS recruitment_candidate_consents (
        consent_id INTEGER PRIMARY KEY AUTOINCREMENT,
        candidate_id INT NOT NULL,
        consent_type VARCHAR(100) NOT NULL,
        is_granted TINYINT(1) NOT NULL DEFAULT 1,
        ip_address VARCHAR(45) NOT NULL,
        user_agent VARCHAR(255) NULL,
        granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        revoked_at DATETIME NULL
    );
    ");

    // 8. Notes
    $db->exec("
    CREATE TABLE IF NOT EXISTS recruitment_candidate_notes (
        note_id INTEGER PRIMARY KEY AUTOINCREMENT,
        candidate_id INT NOT NULL,
        application_id INT NULL,
        author_user_id INT NOT NULL,
        note_text TEXT NOT NULL,
        is_confidential TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ");

    // 9. Applications
    $db->exec("
    CREATE TABLE IF NOT EXISTS recruitment_applications (
        application_id INTEGER PRIMARY KEY AUTOINCREMENT,
        application_code VARCHAR(50) NOT NULL UNIQUE,
        candidate_id INT NOT NULL,
        job_id INT NOT NULL,
        current_stage VARCHAR(50) NOT NULL DEFAULT 'applied',
        stage_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        rejection_reason VARCHAR(255) NULL,
        rejection_notes TEXT NULL,
        assigned_recruiter_id INT NULL,
        match_score_advisory TINYINT NULL,
        converted_employee_id INT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (candidate_id, job_id)
    );
    ");

    // 10. Application Stages History
    $db->exec("
    CREATE TABLE IF NOT EXISTS recruitment_application_stages (
        stage_history_id INTEGER PRIMARY KEY AUTOINCREMENT,
        application_id INT NOT NULL,
        from_stage VARCHAR(50) NOT NULL,
        to_stage VARCHAR(50) NOT NULL,
        actor_user_id INT NULL,
        reason_code VARCHAR(100) NULL,
        comments TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ");

} else {
    // MySQL 8.0+ / MariaDB Engine
    // 1. Requisitions
    $db->exec("
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
    ");

    // 2. Requisition Approvals
    $db->exec("
    CREATE TABLE IF NOT EXISTS recruitment_requisition_approvals (
        approval_id INT AUTO_INCREMENT PRIMARY KEY,
        requisition_id INT NOT NULL,
        step_number TINYINT NOT NULL DEFAULT 1,
        approver_role VARCHAR(50) NOT NULL,
        approver_id INT NULL,
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
    ");

    // 3. Job Description Templates
    $db->exec("
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
    ");

    // 4. Published Jobs
    $db->exec("
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
    ");

    // 5. Candidate Master
    $db->exec("
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
    ");

    // 6. Documents
    $db->exec("
    CREATE TABLE IF NOT EXISTS recruitment_candidate_documents (
        document_id INT AUTO_INCREMENT PRIMARY KEY,
        candidate_id INT NOT NULL,
        document_type ENUM('resume', 'cover_letter', 'portfolio', 'certificate', 'id_proof', 'salary_slip') NOT NULL,
        storage_key VARCHAR(255) NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_size_bytes INT NOT NULL,
        mime_type VARCHAR(100) NOT NULL,
        parsed_text MEDIUMTEXT NULL,
        is_primary_resume TINYINT(1) NOT NULL DEFAULT 0,
        uploaded_by_user_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_cand_doc (candidate_id, document_type),
        FOREIGN KEY (candidate_id) REFERENCES recruitment_candidates(candidate_id) ON DELETE CASCADE,
        FOREIGN KEY (uploaded_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 7. Consents
    $db->exec("
    CREATE TABLE IF NOT EXISTS recruitment_candidate_consents (
        consent_id INT AUTO_INCREMENT PRIMARY KEY,
        candidate_id INT NOT NULL,
        consent_type VARCHAR(100) NOT NULL,
        is_granted TINYINT(1) NOT NULL DEFAULT 1,
        ip_address VARCHAR(45) NOT NULL,
        user_agent VARCHAR(255) NULL,
        granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        revoked_at DATETIME NULL,
        INDEX idx_cand_consent (candidate_id, consent_type),
        FOREIGN KEY (candidate_id) REFERENCES recruitment_candidates(candidate_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 8. Notes
    $db->exec("
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
    ");

    // 9. Applications
    $db->exec("
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
        match_score_advisory TINYINT NULL,
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
    ");

    // 10. Application Stages History
    $db->exec("
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
    ");
}

echo "  [OK] Migration 0059: 10 Recruitment Core Tables ensured successfully.\n";

