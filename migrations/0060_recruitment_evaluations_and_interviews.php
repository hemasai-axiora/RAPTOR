<?php
/**
 * Migration 0060: Recruitment Evaluations & Interviews Schema (Raptor v2.3 ATS)
 * -----------------------------------------------------------------------------------
 * Provisions screening, interview coordination, blind feedback, and assessment tables:
 * 11. recruitment_application_screenings (Recruiter screening rubrics & scores)
 * 12. recruitment_interviews (Multi-round interview schedules)
 * 13. recruitment_interview_panel (Panel interviewer assignments)
 * 14. recruitment_interview_feedback (Independent, blind scorecards & hiring recommendations)
 * 15. recruitment_assessments (Assignment & coding test definitions)
 * 16. recruitment_assessment_submissions (Candidate submissions & grades)
 */

$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
echo "Starting Migration 0060: Recruitment Evaluations & Interviews ({$driver})...\n";

if ($driver === 'sqlite') {
    // 11. Screenings
    $db->exec("
    CREATE TABLE IF NOT EXISTS recruitment_application_screenings (
        screening_id INTEGER PRIMARY KEY AUTOINCREMENT,
        application_id INT NOT NULL,
        screener_user_id INT NOT NULL,
        skills_rating TINYINT NOT NULL DEFAULT 3,
        experience_rating TINYINT NOT NULL DEFAULT 3,
        communication_rating TINYINT NOT NULL DEFAULT 3,
        overall_score DECIMAL(5,2) NOT NULL,
        recommendation VARCHAR(50) NOT NULL,
        evaluation_comments TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ");

    // 12. Interviews
    $db->exec("
    CREATE TABLE IF NOT EXISTS recruitment_interviews (
        interview_id INTEGER PRIMARY KEY AUTOINCREMENT,
        application_id INT NOT NULL,
        round_number TINYINT NOT NULL DEFAULT 1,
        round_name VARCHAR(100) NOT NULL,
        interview_type VARCHAR(50) NOT NULL DEFAULT 'technical',
        interview_mode VARCHAR(50) NOT NULL DEFAULT 'video',
        scheduled_start DATETIME NOT NULL,
        scheduled_end DATETIME NOT NULL,
        timezone VARCHAR(50) NOT NULL DEFAULT 'Asia/Kolkata',
        meeting_link VARCHAR(255) NULL,
        location_details VARCHAR(255) NULL,
        coordinator_user_id INT NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'scheduled',
        reschedule_reason TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ");

    // 13. Panel Members
    $db->exec("
    CREATE TABLE IF NOT EXISTS recruitment_interview_panel (
        panel_id INTEGER PRIMARY KEY AUTOINCREMENT,
        interview_id INT NOT NULL,
        interviewer_user_id INT NOT NULL,
        is_lead TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (interview_id, interviewer_user_id)
    );
    ");

    // 14. Blind Interview Feedback
    $db->exec("
    CREATE TABLE IF NOT EXISTS recruitment_interview_feedback (
        feedback_id INTEGER PRIMARY KEY AUTOINCREMENT,
        interview_id INT NOT NULL,
        interviewer_user_id INT NOT NULL,
        technical_rating TINYINT NOT NULL DEFAULT 3,
        problem_solving_rating TINYINT NOT NULL DEFAULT 3,
        communication_rating TINYINT NOT NULL DEFAULT 3,
        cultural_fit_rating TINYINT NOT NULL DEFAULT 3,
        overall_rating TINYINT NOT NULL DEFAULT 3,
        recommendation VARCHAR(50) NOT NULL,
        strengths TEXT NULL,
        concerns TEXT NULL,
        detailed_comments TEXT NOT NULL,
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (interview_id, interviewer_user_id)
    );
    ");

    // 15. Assessments
    $db->exec("
    CREATE TABLE IF NOT EXISTS recruitment_assessments (
        assessment_id INTEGER PRIMARY KEY AUTOINCREMENT,
        title VARCHAR(150) NOT NULL,
        assessment_type VARCHAR(50) NOT NULL,
        description TEXT NOT NULL,
        instructions TEXT NOT NULL,
        duration_minutes INT NOT NULL DEFAULT 120,
        max_score INT NOT NULL DEFAULT 100,
        passing_score INT NOT NULL DEFAULT 60,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ");

    // 16. Assessment Submissions
    $db->exec("
    CREATE TABLE IF NOT EXISTS recruitment_assessment_submissions (
        submission_id INTEGER PRIMARY KEY AUTOINCREMENT,
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
        status VARCHAR(50) NOT NULL DEFAULT 'assigned',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ");

} else {
    // MySQL 8.0+ / MariaDB Engine
    // 11. Screenings
    $db->exec("
    CREATE TABLE IF NOT EXISTS recruitment_application_screenings (
        screening_id INT AUTO_INCREMENT PRIMARY KEY,
        application_id INT NOT NULL,
        screener_user_id INT NOT NULL,
        skills_rating TINYINT NOT NULL DEFAULT 3,
        experience_rating TINYINT NOT NULL DEFAULT 3,
        communication_rating TINYINT NOT NULL DEFAULT 3,
        overall_score DECIMAL(5,2) NOT NULL,
        recommendation ENUM('shortlist', 'reject', 'hold', 'request_info') NOT NULL,
        evaluation_comments TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (application_id) REFERENCES recruitment_applications(application_id) ON DELETE CASCADE,
        FOREIGN KEY (screener_user_id) REFERENCES users(user_id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 12. Interviews
    $db->exec("
    CREATE TABLE IF NOT EXISTS recruitment_interviews (
        interview_id INT AUTO_INCREMENT PRIMARY KEY,
        application_id INT NOT NULL,
        round_number TINYINT NOT NULL DEFAULT 1,
        round_name VARCHAR(100) NOT NULL,
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
    ");

    // 13. Panel Members
    $db->exec("
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
    ");

    // 14. Blind Interview Feedback
    $db->exec("
    CREATE TABLE IF NOT EXISTS recruitment_interview_feedback (
        feedback_id INT AUTO_INCREMENT PRIMARY KEY,
        interview_id INT NOT NULL,
        interviewer_user_id INT NOT NULL,
        technical_rating TINYINT NOT NULL DEFAULT 3,
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
    ");

    // 15. Assessments
    $db->exec("
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
    ");

    // 16. Assessment Submissions
    $db->exec("
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
    ");
}

echo "  [OK] Migration 0060: 6 Interview & Evaluation Tables ensured successfully.\n";

