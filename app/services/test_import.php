<?php
// Unit Test Script for BulkImportService

if (!defined('APPROOT')) {
    define('APPROOT', dirname(dirname(__FILE__)));
}
require_once APPROOT . '/config/config.php';

// Register autoloader
spl_autoload_register(function ($className) {
    $corePath = APPROOT . '/core/' . $className . '.php';
    $modelPath = APPROOT . '/models/' . $className . '.php';
    $servicePath = APPROOT . '/services/' . $className . '.php';
    
    if (file_exists($corePath)) {
        require_once $corePath;
    } elseif (file_exists($modelPath)) {
        require_once $modelPath;
    } elseif (file_exists($servicePath)) {
        require_once $servicePath;
    }
});

echo "==================================================\n";
echo "RAPTOR BULK UPLOAD VALIDATION UNIT TESTS\n";
echo "==================================================\n\n";

$importer = new BulkImportService();

// Define Employee Field Config
$config = [
    'table' => 'users',
    'unique_key' => 'email',
    'fields' => [
        'employee_code' => ['required' => false, 'type' => 'text', 'unique_in_file' => true],
        'first_name' => ['required' => true, 'type' => 'text'],
        'last_name' => ['required' => true, 'type' => 'text'],
        'email' => ['required' => true, 'type' => 'email', 'unique_in_file' => true],
        'phone_number' => ['required' => false, 'type' => 'text'],
        'department' => ['required' => true, 'type' => 'text'],
        'job_title' => ['required' => true, 'type' => 'text'],
        'reporting_manager_email' => ['required' => false, 'type' => 'email'],
        'date_of_joining' => ['required' => true, 'type' => 'date'],
        'employment_type' => ['required' => false, 'type' => 'enum', 'allowed_values' => ['Full-time', 'Part-time', 'Contract', 'Intern']],
        'status' => ['required' => false, 'type' => 'enum', 'allowed_values' => ['active', 'inactive', 'suspended']],
        'work_location' => ['required' => false, 'type' => 'enum', 'allowed_values' => ['Office', 'Remote']],
        'date_of_birth' => ['required' => false, 'type' => 'date'],
        'bio' => ['required' => false, 'type' => 'text'],
        'emergency_contact' => ['required' => false, 'type' => 'text'],
        'account_holder_name' => ['required' => false, 'type' => 'text'],
        'bank_name' => ['required' => false, 'type' => 'text'],
        'account_number' => ['required' => false, 'type' => 'text'],
        'ifsc_code' => ['required' => false, 'type' => 'text'],
        'branch_name' => ['required' => false, 'type' => 'text'],
        'account_type' => ['required' => false, 'type' => 'enum', 'allowed_values' => ['Savings', 'Current']],
        'pan_number' => ['required' => false, 'type' => 'text'],
        'aadhaar_number' => ['required' => false, 'type' => 'text'],
        'uan' => ['required' => false, 'type' => 'text'],
        'esic_number' => ['required' => false, 'type' => 'text'],
        'salary' => ['required' => false, 'type' => 'number'],
        'pay_grade' => ['required' => false, 'type' => 'text'],
        'pf_applicable' => ['required' => false, 'type' => 'enum', 'allowed_values' => ['0', '1']],
        'role_in_system' => ['required' => true, 'type' => 'enum', 'allowed_values' => ['admin', 'manager', 'analyst', 'employer']],
        'temp_password' => ['required' => false, 'type' => 'text']
    ]
];

// Test Cases
$testCases = [
    // 1. Fully Valid Row
    [
        'employee_code' => 'EMP9991',
        'first_name' => 'ValidFirst',
        'last_name' => 'ValidLast',
        'email' => 'valid.user@example.com',
        'phone_number' => '+919999999999',
        'department' => 'Sales',
        'job_title' => 'Sales Representative',
        'reporting_manager_email' => '',
        'date_of_joining' => '2026-07-15',
        'employment_type' => 'Full-time',
        'status' => 'active',
        'work_location' => 'Office',
        'date_of_birth' => '1995-10-10',
        'bio' => 'A test bio',
        'emergency_contact' => 'Mary - 9999999998',
        'account_holder_name' => 'ValidFirst ValidLast',
        'bank_name' => 'Test Bank',
        'account_number' => '100293848',
        'ifsc_code' => 'TEST0001',
        'branch_name' => 'Main',
        'account_type' => 'Savings',
        'pan_number' => 'ABCDE1234F',
        'aadhaar_number' => '123456789012',
        'uan' => '100293848',
        'esic_number' => 'ESIC1002938',
        'salary' => '40000.00',
        'pay_grade' => 'Band A',
        'pf_applicable' => '1',
        'role_in_system' => 'manager',
        'temp_password' => 'Pass123'
    ],
    // 2. Missing Required Field (First Name & Job Title)
    [
        'employee_code' => 'EMP9992',
        'first_name' => '',
        'last_name' => 'NoFirstName',
        'email' => 'no.first@example.com',
        'phone_number' => '',
        'department' => 'Sales',
        'job_title' => '',
        'reporting_manager_email' => '',
        'date_of_joining' => '2026-07-15',
        'employment_type' => 'Full-time',
        'status' => 'active',
        'work_location' => 'Office',
        'date_of_birth' => '',
        'bio' => '',
        'emergency_contact' => '',
        'account_holder_name' => '',
        'bank_name' => '',
        'account_number' => '',
        'ifsc_code' => '',
        'branch_name' => '',
        'account_type' => 'Savings',
        'pan_number' => '',
        'aadhaar_number' => '',
        'uan' => '',
        'esic_number' => '',
        'salary' => '',
        'pay_grade' => '',
        'pf_applicable' => '1',
        'role_in_system' => 'analyst',
        'temp_password' => ''
    ],
    // 3. Bad Email & Invalid Enum
    [
        'employee_code' => 'EMP9993',
        'first_name' => 'BadFormat',
        'last_name' => 'User',
        'email' => 'invalid-email-format',
        'phone_number' => '',
        'department' => 'Sales',
        'job_title' => 'Sales Lead',
        'reporting_manager_email' => '',
        'date_of_joining' => '2026-07-15',
        'employment_type' => 'Full-time',
        'status' => 'active',
        'work_location' => 'Office',
        'date_of_birth' => '',
        'bio' => '',
        'emergency_contact' => '',
        'account_holder_name' => '',
        'bank_name' => '',
        'account_number' => '',
        'ifsc_code' => '',
        'branch_name' => '',
        'account_type' => 'Savings',
        'pan_number' => '',
        'aadhaar_number' => '',
        'uan' => '',
        'esic_number' => '',
        'salary' => '',
        'pay_grade' => '',
        'pf_applicable' => '1',
        'role_in_system' => 'super_admin_invalid_enum',
        'temp_password' => ''
    ],
    // 4. Duplicate Email within File (Same email as row 1)
    [
        'employee_code' => 'EMP9994',
        'first_name' => 'FileDuplicate',
        'last_name' => 'User',
        'email' => 'valid.user@example.com',
        'phone_number' => '',
        'department' => 'Sales',
        'job_title' => 'Representative',
        'reporting_manager_email' => '',
        'date_of_joining' => '2026-07-15',
        'employment_type' => 'Full-time',
        'status' => 'active',
        'work_location' => 'Office',
        'date_of_birth' => '',
        'bio' => '',
        'emergency_contact' => '',
        'account_holder_name' => '',
        'bank_name' => '',
        'account_number' => '',
        'ifsc_code' => '',
        'branch_name' => '',
        'account_type' => 'Savings',
        'pan_number' => '',
        'aadhaar_number' => '',
        'uan' => '',
        'esic_number' => '',
        'salary' => '',
        'pay_grade' => '',
        'pf_applicable' => '1',
        'role_in_system' => 'manager',
        'temp_password' => ''
    ]
];

// Mock Callbacks
$callbacks = [
    'validate_field_department' => function($val) {
        $allowed = ['sales', 'marketing', 'engineering', 'hr', 'finance', 'operations'];
        if (!in_array(strtolower($val), $allowed)) {
            return "Department does not exist.";
        }
        return null;
    },
    'db_duplicate_check' => function($row) {
        // Mock existing user in DB with email: admin@raptor.com
        if ($row['email'] === 'admin@raptor.com') {
            return ['user_id' => 1, 'message' => 'Duplicate Email: admin already exists in database.'];
        }
        return null;
    }
];

// Add an extra row to test DB duplicate check
$testCases[] = [
    'employee_code' => 'EMP9995',
    'first_name' => 'DbDuplicate',
    'last_name' => 'User',
    'email' => 'admin@raptor.com',
    'phone_number' => '',
    'department' => 'Sales',
    'job_title' => 'Representative',
    'reporting_manager_email' => '',
    'date_of_joining' => '2026-07-15',
    'employment_type' => 'Full-time',
    'status' => 'active',
    'work_location' => 'Office',
    'date_of_birth' => '',
    'bio' => '',
    'emergency_contact' => '',
    'account_holder_name' => '',
    'bank_name' => '',
    'account_number' => '',
    'ifsc_code' => '',
    'branch_name' => '',
    'account_type' => 'Savings',
    'pan_number' => '',
    'aadhaar_number' => '',
    'uan' => '',
    'esic_number' => '',
    'salary' => '',
    'pay_grade' => '',
    'pf_applicable' => '1',
    'role_in_system' => 'manager',
    'temp_password' => ''
];

// Run Validation
$res = $importer->validate($testCases, $config, $callbacks);

echo "Total Rows Parsed: " . $res['total_rows'] . "\n";
echo "Valid Rows: " . $res['valid_count'] . "\n";
echo "Error Rows: " . $res['error_count'] . "\n\n";

$pass = true;

// Assertions
// Row 1: Valid
if ($res['rows'][0]['status'] !== 'valid') {
    echo "[FAIL] Row 1 should be Valid.\n";
    $pass = false;
} else {
    echo "[PASS] Row 1 is Valid.\n";
}

// Row 2: Errors (first_name, job_title)
if ($res['rows'][1]['status'] !== 'error' || empty($res['rows'][1]['errors']['first_name']) || empty($res['rows'][1]['errors']['job_title'])) {
    echo "[FAIL] Row 2 should fail validation on first_name and job_title.\n";
    $pass = false;
} else {
    echo "[PASS] Row 2 failed correctly on missing required fields.\n";
}

// Row 3: Errors (email, role_in_system)
if ($res['rows'][2]['status'] !== 'error' || empty($res['rows'][2]['errors']['email']) || empty($res['rows'][2]['errors']['role_in_system'])) {
    echo "[FAIL] Row 3 should fail validation on email and role_in_system.\n";
    $pass = false;
} else {
    echo "[PASS] Row 3 failed correctly on bad formats/enums.\n";
}

// Row 4: Duplicate within File
if ($res['rows'][3]['status'] !== 'error' || empty($res['rows'][3]['errors']['email']) || strpos($res['rows'][3]['errors']['email'], 'Duplicate record found') === false) {
    echo "[FAIL] Row 4 should fail as a duplicate in file.\n";
    $pass = false;
} else {
    echo "[PASS] Row 4 flagged duplicate within the file correctly.\n";
}

// Row 5: Duplicate in DB
if ($res['rows'][4]['status'] !== 'valid' || $res['rows'][4]['action'] !== 'duplicate') {
    echo "[FAIL] Row 5 should validate but flag action as duplicate.\n";
    $pass = false;
} else {
    echo "[PASS] Row 5 correctly flagged as database duplicate.\n";
}

echo "\n--------------------------------------------------\n";
if ($pass) {
    echo "OVERALL RESULT: ALL TESTS PASSED!\n";
} else {
    echo "OVERALL RESULT: SOME TESTS FAILED.\n";
}
echo "--------------------------------------------------\n";
