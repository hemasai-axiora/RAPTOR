<?php
/**
 * Migration 0064: Recruitment Default Templates & Seed Data (Raptor v2.3 ATS)
 * -----------------------------------------------------------------------------------
 * Seeds foundational templates and configuration rubrics:
 * - Default Job Description Templates (Engineering, Sales, HR)
 * - Standard Recruitment Notification Templates
 */

$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
echo "Starting Migration 0064: Recruitment Default Templates ({$driver})...\n";

// Get first admin user ID for created_by
$adminUserId = 1;
try {
    $stmtAdmin = $db->query("SELECT user_id FROM users WHERE role_id = 1 LIMIT 1");
    $id = $stmtAdmin->fetchColumn();
    if ($id) {
        $adminUserId = (int) $id;
    }
} catch (Throwable $e) {}

// 1. Seed Default Job Description Templates
$templates = [
    [
        'title' => 'Senior Backend PHP Engineer',
        'department' => 'Engineering',
        'job_summary' => 'We are seeking an experienced Senior Backend PHP Engineer to build, scale, and optimize enterprise business application architectures.',
        'responsibilities' => "• Architect, develop, and maintain high-performance PHP 8.2+ services and relational database schemas.\n• Build resilient, queue-backed background automation and transactional workflows.\n• Collaborate with product management, security architects, and front-end engineers.",
        'required_skills' => 'PHP 8.2+, MySQL 8.0, Relational Database Indexing, OOP & SOLID, REST APIs, PSR Standards, Git',
        'preferred_skills' => 'Redis, Docker, Asynchronous Workers, High-Volume Data Pipelines',
        'min_experience_years' => 5.0,
        'education_requirements' => "Bachelor's or Master's in Computer Science or equivalent practical experience",
    ],
    [
        'title' => 'Enterprise Sales Account Executive',
        'department' => 'Sales',
        'job_summary' => 'Drive outbound revenue and enterprise client acquisition for the Raptor CRM and business platform.',
        'responsibilities' => "• Prospect, qualify, and close high-value enterprise software contracts.\n• Lead executive demos and solution alignment discussions with C-level stakeholders.\n• Maintain accurate CRM pipeline forecasting and sales metrics.",
        'required_skills' => 'B2B Enterprise Software Sales, Consultative Selling, CRM Pipeline Management, Negotiation',
        'preferred_skills' => 'SaaS Sales, Cloud Architecture familiarity',
        'min_experience_years' => 3.0,
        'education_requirements' => 'Bachelor’s degree in Business, Marketing, or related field',
    ],
    [
        'title' => 'Human Resources Generalist',
        'department' => 'Human Resources',
        'job_summary' => 'Manage end-to-end recruitment operations, statutory payroll compliance, employee relations, and onboarding.',
        'responsibilities' => "• Partner with department heads to execute headcount demand and recruitment pipelines.\n• Conduct candidate screening, coordinate interviews, and prepare structured offer letters.\n• Oversee employee documentation, statutory compliance, and onboarding orientation.",
        'required_skills' => 'Talent Acquisition, ATS Management, Statutory HR Compliance, Employee Relations',
        'preferred_skills' => 'Technical Hiring, HR Analytics, Payroll Administration',
        'min_experience_years' => 2.5,
        'education_requirements' => 'Degree in Human Resources, Business Administration, or equivalent',
    ]
];

$stmtChk = $db->prepare('SELECT COUNT(*) FROM recruitment_job_descriptions WHERE title = :title');
$stmtIns = $db->prepare('
    INSERT INTO recruitment_job_descriptions 
    (title, department, job_summary, responsibilities, required_skills, preferred_skills, min_experience_years, education_requirements, is_active, created_by)
    VALUES (:title, :dept, :summary, :resp, :req_skills, :pref_skills, :min_exp, :edu, 1, :uid)
');

foreach ($templates as $t) {
    $stmtChk->execute([':title' => $t['title']]);
    if ((int)$stmtChk->fetchColumn() === 0) {
        $stmtIns->execute([
            ':title' => $t['title'],
            ':dept' => $t['department'],
            ':summary' => $t['job_summary'],
            ':resp' => $t['responsibilities'],
            ':req_skills' => $t['required_skills'],
            ':pref_skills' => $t['preferred_skills'],
            ':min_exp' => $t['min_experience_years'],
            ':edu' => $t['education_requirements'],
            ':uid' => $adminUserId
        ]);
    }
}

echo "  [OK] Migration 0064: 3 Default Job Description Templates ensured successfully.\n";

