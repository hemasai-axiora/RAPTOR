<?php
/**
 * Migration 0061: Recruitment Offers & Onboarding Schema (Raptor v2.3 ATS)
 * -----------------------------------------------------------------------------------
 * Provisions offer generation, salary approvals, BGV, and pre-joining checklist:
 * 17. recruitment_offers (Compensation breakdown, revisions & candidate responses)
 * 18. recruitment_offer_approvals (Multi-step threshold salary sign-offs)
 * 19. recruitment_background_checks (Employment, criminal, education BGV checks)
 * 20. recruitment_pre_joining_checklist (Pre-onboarding statutory document checklist)
 */

$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
echo "Starting Migration 0061: Recruitment Offers & Onboarding ({$driver})...\n";

if ($driver === 'sqlite') {
    // 17. Offers
    $db->exec("
    CREATE TABLE IF NOT EXISTS recruitment_offers (
        offer_id INTEGER PRIMARY KEY AUTOINCREMENT,
        offer_code VARCHAR(50) NOT NULL UNIQUE,
        application_id INT NOT NULL,
        version_number TINYINT NOT NULL DEFAULT 1,
        job_title VARCHAR(150) NOT NULL,
        department VARCHAR(100) NOT NULL,
        reporting_manager_id INT NULL,
        work_location VARCHAR(150) NOT NULL,
        employment_type VARCHAR(50) NOT NULL DEFAULT 'full_time',
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
        status VARCHAR(50) NOT NULL DEFAULT 'draft',
        decision_reason TEXT NULL,
        accepted_at DATETIME NULL,
        declined_at DATETIME NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ");

    // 18. Offer Approvals
    $db->exec("
    CREATE TABLE IF NOT EXISTS recruitment_offer_approvals (
        approval_id INTEGER PRIMARY KEY AUTOINCREMENT,
        offer_id INT NOT NULL,
        step_number TINYINT NOT NULL DEFAULT 1,
        approver_role VARCHAR(50) NOT NULL,
        approver_id INT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'pending',
        comments TEXT NULL,
        actioned_by INT NULL,
        actioned_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ");

    // 19. Background Checks
    $db->exec("
    CREATE TABLE IF NOT EXISTS recruitment_background_checks (
        bgv_id INTEGER PRIMARY KEY AUTOINCREMENT,
        application_id INT NOT NULL,
        check_type VARCHAR(50) NOT NULL,
        provider_name VARCHAR(100) NOT NULL DEFAULT 'internal',
        status VARCHAR(50) NOT NULL DEFAULT 'initiated',
        report_storage_key VARCHAR(255) NULL,
        findings_summary TEXT NULL,
        verified_by_user_id INT NULL,
        verified_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ");

    // 20. Pre-Joining Checklist
    $db->exec("
    CREATE TABLE IF NOT EXISTS recruitment_pre_joining_checklist (
        item_id INTEGER PRIMARY KEY AUTOINCREMENT,
        application_id INT NOT NULL,
        item_name VARCHAR(150) NOT NULL,
        is_mandatory TINYINT(1) NOT NULL DEFAULT 1,
        is_completed TINYINT(1) NOT NULL DEFAULT 0,
        verified_document_id INT NULL,
        verified_by_user_id INT NULL,
        verified_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ");

} else {
    // MySQL 8.0+ / MariaDB Engine
    // 17. Offers
    $db->exec("
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
    ");

    // 18. Offer Approvals
    $db->exec("
    CREATE TABLE IF NOT EXISTS recruitment_offer_approvals (
        approval_id INT AUTO_INCREMENT PRIMARY KEY,
        offer_id INT NOT NULL,
        step_number TINYINT NOT NULL DEFAULT 1,
        approver_role VARCHAR(50) NOT NULL,
        approver_id INT NULL,
        status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
        comments TEXT NULL,
        actioned_by INT NULL,
        actioned_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (offer_id) REFERENCES recruitment_offers(offer_id) ON DELETE CASCADE,
        FOREIGN KEY (actioned_by) REFERENCES users(user_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 19. Background Checks
    $db->exec("
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
    ");

    // 20. Pre-Joining Checklist
    $db->exec("
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
    ");
}

echo "  [OK] Migration 0061: 4 Offers & Onboarding Tables ensured successfully.\n";

