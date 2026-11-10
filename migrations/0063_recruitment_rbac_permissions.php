<?php
/**
 * Migration 0063: Recruitment RBAC Permissions (Raptor v2.3 ATS)
 * -----------------------------------------------------------------------------------
 * Seeds 28 granular recruitment permissions into permissions table and maps to canonical roles.
 */

$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
echo "Starting Migration 0063: Recruitment RBAC Permissions ({$driver})...\n";

$permissions = [
    ['recruitment', 'dashboard.view',              'recruitment.dashboard.view',              'Access central recruitment metrics, pipeline KPIs, and recruiter workload board'],
    ['recruitment', 'manpower.manage',             'recruitment.manpower.manage',             'Create, edit, and review organizational headcount demand and staffing forecasts'],
    ['recruitment', 'requisition.create',          'recruitment.requisition.create',          'Draft and submit new job requisitions for approval'],
    ['recruitment', 'requisition.view',            'recruitment.requisition.view',            'View job requisitions within assigned departmental/team scope'],
    ['recruitment', 'requisition.edit',            'recruitment.requisition.edit',            'Modify draft or rejected job requisitions'],
    ['recruitment', 'requisition.approve',         'recruitment.requisition.approve',         'Action approval requests in the requisition chain'],
    ['recruitment', 'job.publish',                 'recruitment.job.publish',                 'Publish approved vacancies to public career portal; pause or close listings'],
    ['recruitment', 'candidate.create',            'recruitment.candidate.create',            'Add new candidate profiles manually or import from external sources'],
    ['recruitment', 'candidate.view',              'recruitment.candidate.view',              'View candidate master profiles, resumes, and contact information'],
    ['recruitment', 'candidate.edit',              'recruitment.candidate.edit',              'Update candidate contact details, skills, and professional histories'],
    ['recruitment', 'candidate.export',            'recruitment.candidate.export',            'Export candidate records and resumes in bulk (strictly audited)'],
    ['recruitment', 'candidate.delete',            'recruitment.candidate.delete',            'Anonymize or delete candidate profiles under DPDP Act'],
    ['recruitment', 'application.view',            'recruitment.application.view',            'View submitted applications on the Kanban board and tabular lists'],
    ['recruitment', 'application.assign',          'recruitment.application.assign',          'Assign or reassign responsible recruiters to specific applications'],
    ['recruitment', 'application.stage.update',    'recruitment.application.stage.update',    'Transition applications through pipeline stages'],
    ['recruitment', 'application.reject',          'recruitment.application.reject',          'Formally reject candidate applications with mandatory reason codes'],
    ['recruitment', 'interview.schedule',          'recruitment.interview.schedule',          'Schedule interview rounds, assign panel members, and manage meeting links'],
    ['recruitment', 'interview.feedback.submit',   'recruitment.interview.feedback.submit',   'Submit structured scorecards and hiring recommendations'],
    ['recruitment', 'interview.feedback.view_all', 'recruitment.interview.feedback.view_all', 'View peer interviewer scorecards after submitting own feedback'],
    ['recruitment', 'assessment.manage',           'recruitment.assessment.manage',           'Create, assign, and grade candidate assessments'],
    ['recruitment', 'offer.create',                'recruitment.offer.create',                'Prepare draft offer letters and structured compensation packages'],
    ['recruitment', 'offer.approve',               'recruitment.offer.approve',               'Approve compensation packages exceeding configured thresholds'],
    ['recruitment', 'offer.release',               'recruitment.offer.release',               'Formally dispatch approved offer letters to selected candidates'],
    ['recruitment', 'offer.view_salary',           'recruitment.offer.view_salary',           'View unmasked salary figures and compensation breakdowns'],
    ['recruitment', 'onboarding.manage',           'recruitment.onboarding.manage',           'Manage background verification and pre-joining document checklists'],
    ['recruitment', 'convert.employee',            'recruitment.convert.employee',            'Execute automated employee conversion from accepted candidate into HRMS'],
    ['recruitment', 'reports.view',                'recruitment.reports.view',                'View and export recruitment analytics and pipeline metrics'],
    ['recruitment', 'settings.manage',             'recruitment.settings.manage',             'Configure workflow stages, rubrics, approval matrices, and templates'],
];

// Helper to ensure permission exists
$stmtPerm = $db->prepare('SELECT permission_id FROM permissions WHERE permission_name = :pname LIMIT 1');
$stmtInsPerm = $db->prepare('INSERT INTO permissions (module, action, permission_name, description) VALUES (:m, :a, :p, :d)');

foreach ($permissions as [$mod, $act, $pname, $desc]) {
    $stmtPerm->execute([':pname' => $pname]);
    $existing = $stmtPerm->fetchColumn();
    if (!$existing) {
        $stmtInsPerm->execute([':m' => $mod, ':a' => $act, ':p' => $pname, ':d' => $desc]);
    }
}
echo "  + 28 recruitment permissions registered in permissions table\n";

// Helper to grant permissions to roles
$getRoleId = function (PDO $db, string $roleName): ?int {
    $stmt = $db->prepare('SELECT role_id FROM roles WHERE role_name = :name LIMIT 1');
    $stmt->execute([':name' => $roleName]);
    $id = $stmt->fetchColumn();
    return $id !== false ? (int) $id : null;
};

$getPermissionId = function (PDO $db, string $permissionName): ?int {
    $stmt = $db->prepare('SELECT permission_id FROM permissions WHERE permission_name = :name LIMIT 1');
    $stmt->execute([':name' => $permissionName]);
    $id = $stmt->fetchColumn();
    return $id !== false ? (int) $id : null;
};

$grant = function (PDO $db, string $roleName, string $permissionName, string $scope = 'all') use ($getRoleId, $getPermissionId, $driver) {
    $rid = $getRoleId($db, $roleName);
    $pid = $getPermissionId($db, $permissionName);
    if ($rid && $pid) {
        if ($driver === 'sqlite') {
            $stmt = $db->prepare('INSERT OR REPLACE INTO role_permissions (role_id, permission_id, scope) VALUES (:r, :p, :s)');
        } else {
            $stmt = $db->prepare('INSERT INTO role_permissions (role_id, permission_id, scope) VALUES (:r, :p, :s) ON DUPLICATE KEY UPDATE scope = VALUES(scope)');
        }
        $stmt->execute([':r' => $rid, ':p' => $pid, ':s' => $scope]);
    }
};

// 1. HR Manager Role
$hrPerms = [
    'recruitment.dashboard.view', 'recruitment.manpower.manage', 'recruitment.requisition.create',
    'recruitment.requisition.view', 'recruitment.requisition.edit', 'recruitment.requisition.approve',
    'recruitment.job.publish', 'recruitment.candidate.create', 'recruitment.candidate.view',
    'recruitment.candidate.edit', 'recruitment.candidate.export', 'recruitment.application.view',
    'recruitment.application.assign', 'recruitment.application.stage.update', 'recruitment.application.reject',
    'recruitment.interview.schedule', 'recruitment.interview.feedback.submit', 'recruitment.interview.feedback.view_all',
    'recruitment.assessment.manage', 'recruitment.offer.create', 'recruitment.offer.approve',
    'recruitment.offer.release', 'recruitment.offer.view_salary', 'recruitment.onboarding.manage',
    'recruitment.convert.employee', 'recruitment.reports.view', 'recruitment.settings.manage'
];
foreach ($hrPerms as $p) {
    $grant($db, 'hr', $p, 'all');
}

// 2. Manager Role (Department/Team Scope)
$mgrPerms = [
    'recruitment.dashboard.view', 'recruitment.manpower.manage', 'recruitment.requisition.create',
    'recruitment.requisition.view', 'recruitment.requisition.edit', 'recruitment.requisition.approve',
    'recruitment.candidate.view', 'recruitment.application.view', 'recruitment.application.stage.update',
    'recruitment.interview.schedule', 'recruitment.interview.feedback.submit', 'recruitment.interview.feedback.view_all',
    'recruitment.assessment.manage', 'recruitment.reports.view'
];
foreach ($mgrPerms as $p) {
    $grant($db, 'manager', $p, 'team');
    $grant($db, 'team_leader', $p, 'team');
}

echo "  [OK] Migration 0063: Recruitment RBAC permissions and role assignments completed.\n";

