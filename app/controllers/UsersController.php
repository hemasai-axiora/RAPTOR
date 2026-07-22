<?php
// Raptor CRM Employee Management Controller (Admin and HR)

class UsersController extends Controller {
    private $userModel;

    public function __construct() {
        $this->requireAuth();
        $role = $_SESSION['user_role'] ?? '';
        if (!in_array($role, ['admin', 'hr'], true)) {
            $this->redirect('index.php?route=dashboard/index');
            return;
        }

        $this->userModel = $this->model('User');
    }

    // List all team users
    public function index() {
        $users = $this->userModel->getUsers();
        $roles = $this->userModel->getRoles();
        if (Policy::isHr()) {
            $roles = array_values(array_filter($roles, function ($role) {
                return !in_array($role->role_name, ['admin'], true);
            }));
        }

        $db = Database::getInstance()->getConnection();
        
        // Departments list in system
        $stmt = $db->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' ORDER BY department ASC");
        $departments = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: ['Sales', 'Marketing', 'Engineering', 'HR', 'Finance', 'Operations', 'Executive'];
        
        // Job titles list in system
        $stmt = $db->query("SELECT DISTINCT job_title FROM employees WHERE job_title IS NOT NULL AND job_title != '' ORDER BY job_title ASC");
        $jobTitles = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: ['Sales Executive', 'Sales Manager', 'Business Analyst', 'HR Manager', 'System Administrator'];
        
        // Active manager/admin emails in system
        $stmt = $db->query("SELECT name, email FROM users u JOIN roles r ON u.role_id = r.role_id WHERE r.role_name IN ('admin', 'manager', 'team_leader') AND u.status = 'active' ORDER BY name ASC");
        $managers = $stmt->fetchAll(PDO::FETCH_OBJ) ?: [];

        $data = [
            'title' => 'Employee Management | Raptor CRM',
            'active_tab' => 'system',
            'users' => $users,
            'roles' => $roles,
            'departments' => $departments,
            'job_titles' => $jobTitles,
            'managers' => $managers
        ];

        $this->viewWithLayout('users/index', 'main', $data);
    }

    // Add user action
    public function add() {
        $this->requirePermission('employees', 'create');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];

            $data = [
                'role_id' => (int)trim($_POST['role_id']),
                'name' => trim($_POST['name']),
                'email' => trim($_POST['email']),
                'password' => trim($_POST['password']),
                'status' => trim($_POST['status']),
                
                // New employee fields
                'employee_code' => trim($_POST['employee_code']),
                'phone_number' => trim($_POST['phone_number']),
                'salary' => trim($_POST['salary']),
                'date_of_joining' => trim($_POST['date_of_joining']),
                'date_of_birth' => trim($_POST['date_of_birth']),
                'job_title' => trim($_POST['job_title']),
                'department' => trim($_POST['department'] ?? 'Sales'),
                'employment_type' => trim($_POST['employment_type']),
                'work_location' => trim($_POST['work_location']),
                'bio' => trim($_POST['bio']),
                'emergency_contact' => trim($_POST['emergency_contact']),
                'pan_number' => trim($_POST['pan_number']),
                'aadhaar_number' => trim($_POST['aadhaar_number']),
                'uan' => trim($_POST['uan']),
                'pf_applicable' => isset($_POST['pf_applicable']) ? 1 : 0,
                'esic_number' => trim($_POST['esic_number']),
                'pay_grade' => trim($_POST['pay_grade']),
                'force_password_reset' => isset($_POST['force_password_reset']) ? 1 : 0,

                // Bank details
                'account_holder_name' => trim($_POST['account_holder_name']),
                'bank_name' => trim($_POST['bank_name']),
                'account_number' => trim($_POST['account_number']),
                'ifsc_code' => trim($_POST['ifsc_code']),
                'branch_name' => trim($_POST['branch_name']),
                'account_type' => trim($_POST['account_type'])
            ];

            if ($this->userModel->isEmployeeCodeExists($data['employee_code'])) {
                $_SESSION['user_error'] = 'Employee ID already exists.';
                $this->redirect('index.php?route=users/index');
                return;
            }
            if (!empty($data['employee_code']) && !Validation::validateHasAlphanumeric($data['employee_code'])) {
                $_SESSION['user_error'] = 'Employee ID must contain alphanumeric characters.';
                $this->redirect('index.php?route=users/index');
                return;
            }

            // Handle Profile Photo Upload
            $photo = null;
            if (!empty($_FILES['profile_photo']['name'])) {
                try {
                    $photo = Storage::put($_FILES['profile_photo'], 'profile-photo');
                    $data['profile_photo'] = $photo;
                } catch (Exception $e) {
                    $_SESSION['user_error'] = 'Photo upload failed: ' . $e->getMessage();
                    $this->redirect('index.php?route=users/index');
                    return;
                }
            }

            // Validation
            if (!Validation::validateFullName($data['name'])) {
                $_SESSION['user_error'] = "Please enter a valid full name.";
                $this->redirect('index.php?route=users/index');
                return;
            }
            if (!Validation::validateEmail($data['email'])) {
                $_SESSION['user_error'] = "Please enter a valid email address.";
                $this->redirect('index.php?route=users/index');
                return;
            }
            if (!empty($data['phone_number']) && !Validation::validatePhoneNumber($data['phone_number'])) {
                $_SESSION['user_error'] = "Please enter a valid 10-digit phone number.";
                $this->redirect('index.php?route=users/index');
                return;
            }
            if (!empty($data['job_title']) && !Validation::validateJobTitle($data['job_title'])) {
                $_SESSION['user_error'] = "Please enter a valid job title.";
                $this->redirect('index.php?route=users/index');
                return;
            }
            if (!empty($data['department']) && !Validation::validateDepartment($data['department'])) {
                $_SESSION['user_error'] = "Please select a valid department.";
                $this->redirect('index.php?route=users/index');
                return;
            }
            if (!empty($data['date_of_birth']) && !Validation::validateDob($data['date_of_birth'])) {
                $_SESSION['user_error'] = "DOB must be in the past and employee must be at least 18 years old.";
                $this->redirect('index.php?route=users/index');
                return;
            }
            if (!empty($data['emergency_contact']) && !Validation::validatePhoneNumber($data['emergency_contact'])) {
                $_SESSION['user_error'] = "Emergency contact must be a valid 10-digit phone number.";
                $this->redirect('index.php?route=users/index');
                return;
            }
            if (!empty($data['account_holder_name']) && !Validation::validateFullName($data['account_holder_name'])) {
                $_SESSION['user_error'] = "Please enter a valid account holder name.";
                $this->redirect('index.php?route=users/index');
                return;
            }
            if (!empty($data['bank_name']) && !Validation::validateBankName($data['bank_name'])) {
                $_SESSION['user_error'] = "Please enter a valid bank name.";
                $this->redirect('index.php?route=users/index');
                return;
            }
            if (!empty($data['account_number']) && !Validation::validateAccountNumber($data['account_number'])) {
                $_SESSION['user_error'] = "Account number must be numeric.";
                $this->redirect('index.php?route=users/index');
                return;
            }
            if (!empty($_POST['confirm_account_number']) || !empty($data['account_number'])) {
                $confirmAcc = trim($_POST['confirm_account_number'] ?? '');
                if ($confirmAcc !== $data['account_number']) {
                    $_SESSION['user_error'] = "Confirm Account Number must be numeric and match Account Number.";
                    $this->redirect('index.php?route=users/index');
                    return;
                }
            }
            if (!empty($data['ifsc_code']) && !Validation::validateIfscCode($data['ifsc_code'])) {
                $_SESSION['user_error'] = "Please enter a valid IFSC code.";
                $this->redirect('index.php?route=users/index');
                return;
            }
            if (!empty($data['branch_name']) && !Validation::validateBankBranch($data['branch_name'])) {
                $_SESSION['user_error'] = "Please enter a valid bank branch name/address.";
                $this->redirect('index.php?route=users/index');
                return;
            }
            if (!empty($data['pan_number'])) {
                $data['pan_number'] = strtoupper($data['pan_number']);
                if (!Validation::validatePanNumber($data['pan_number'])) {
                    $_SESSION['user_error'] = "Please enter a valid PAN number.";
                    $this->redirect('index.php?route=users/index');
                    return;
                }
            }
            if (!empty($data['aadhaar_number']) && !Validation::validateAadhaarNumber($data['aadhaar_number'])) {
                $_SESSION['user_error'] = "Please enter a valid Aadhaar number.";
                $this->redirect('index.php?route=users/index');
                return;
            }
            if (!empty($data['salary']) && !Validation::validateSalary($data['salary'])) {
                $_SESSION['user_error'] = "Salary must be a positive numeric value greater than 0.";
                $this->redirect('index.php?route=users/index');
                return;
            }
            if (!empty($data['uan']) && !Validation::validateUan($data['uan'])) {
                $_SESSION['user_error'] = "UAN must be a valid 12-digit number.";
                $this->redirect('index.php?route=users/index');
                return;
            }
            if (!empty($data['esic_number']) && !Validation::validateEsicNumber($data['esic_number'])) {
                $_SESSION['user_error'] = "ESIC number must be a valid 17-digit number.";
                $this->redirect('index.php?route=users/index');
                return;
            }
            if (!empty($data['pay_grade']) && !Validation::validatePayGrade($data['pay_grade'])) {
                $_SESSION['user_error'] = "Please select a valid pay grade.";
                $this->redirect('index.php?route=users/index');
                return;
            }

            if (!empty($data['name']) && !empty($data['email']) && !empty($data['password']) && $data['role_id'] > 0) {
                if (Policy::isHr() && $this->userModel->getRoleNameById($data['role_id']) === 'admin') {
                    $this->redirect('index.php?route=users/index');
                    return;
                }
                if ($this->userModel->addUser($data)) {
                    $_SESSION['user_success'] = 'Employee added successfully.';
                    $this->redirect('index.php?route=users/index');
                    return;
                } else {
                    die('Something went wrong.');
                }
            }
        }
        $this->redirect('index.php?route=users/index');
    }

    // Edit user action
    public function edit() {
        $this->requirePermission('employees', 'edit');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];

            $data = [
                'user_id' => (int)trim($_POST['user_id']),
                'role_id' => (int)trim($_POST['role_id']),
                'name' => trim($_POST['name']),
                'email' => trim($_POST['email']),
                'password' => trim($_POST['password']), // optional password override
                'status' => trim($_POST['status']),

                // New employee fields
                'employee_code' => trim($_POST['employee_code']),
                'phone_number' => trim($_POST['phone_number']),
                'salary' => trim($_POST['salary']),
                'date_of_joining' => trim($_POST['date_of_joining']),
                'date_of_birth' => trim($_POST['date_of_birth']),
                'job_title' => trim($_POST['job_title']),
                'department' => trim($_POST['department'] ?? 'Sales'),
                'employment_type' => trim($_POST['employment_type']),
                'work_location' => trim($_POST['work_location']),
                'bio' => trim($_POST['bio']),
                'emergency_contact' => trim($_POST['emergency_contact']),
                'pan_number' => trim($_POST['pan_number']),
                'aadhaar_number' => trim($_POST['aadhaar_number']),
                'uan' => trim($_POST['uan']),
                'pf_applicable' => isset($_POST['pf_applicable']) ? 1 : 0,
                'esic_number' => trim($_POST['esic_number']),
                'pay_grade' => trim($_POST['pay_grade']),
                'force_password_reset' => isset($_POST['force_password_reset']) ? 1 : 0,

                // Bank details
                'account_holder_name' => trim($_POST['account_holder_name']),
                'bank_name' => trim($_POST['bank_name']),
                'account_number' => trim($_POST['account_number']),
                'ifsc_code' => trim($_POST['ifsc_code']),
                'branch_name' => trim($_POST['branch_name']),
                'account_type' => trim($_POST['account_type'])
            ];

            if ($this->userModel->isEmployeeCodeExists($data['employee_code'], $data['user_id'])) {
                $_SESSION['user_error'] = 'Employee ID already exists.';
                $this->redirect('index.php?route=users/index');
                return;
            }

            // Handle Profile Photo Upload
            if (!empty($_FILES['profile_photo']['name'])) {
                try {
                    $photo = Storage::put($_FILES['profile_photo'], 'profile-photo');
                    $data['profile_photo'] = $photo;
                } catch (Exception $e) {
                    $_SESSION['user_error'] = 'Photo upload failed: ' . $e->getMessage();
                    $this->redirect('index.php?route=users/index');
                    return;
                }
            } else {
                $data['profile_photo'] = null;
            }

            // Validation
            if (!Validation::validateFullName($data['name'])) {
                $_SESSION['user_error'] = "Please enter a valid full name.";
                $this->redirect('index.php?route=users/index');
                return;
            }
            if (!Validation::validateEmail($data['email'])) {
                $_SESSION['user_error'] = "Please enter a valid email address.";
                $this->redirect('index.php?route=users/index');
                return;
            }
            if (!empty($data['phone_number']) && !Validation::validatePhoneNumber($data['phone_number'])) {
                $_SESSION['user_error'] = "Please enter a valid 10-digit phone number.";
                $this->redirect('index.php?route=users/index');
                return;
            }
            if (!empty($data['job_title']) && !Validation::validateJobTitle($data['job_title'])) {
                $_SESSION['user_error'] = "Please enter a valid job title.";
                $this->redirect('index.php?route=users/index');
                return;
            }
            if (!empty($data['department']) && !Validation::validateDepartment($data['department'])) {
                $_SESSION['user_error'] = "Please select a valid department.";
                $this->redirect('index.php?route=users/index');
                return;
            }
            if (!empty($data['date_of_birth']) && !Validation::validateDob($data['date_of_birth'])) {
                $_SESSION['user_error'] = "DOB must be in the past and employee must be at least 18 years old.";
                $this->redirect('index.php?route=users/index');
                return;
            }
            if (!empty($data['emergency_contact']) && !Validation::validatePhoneNumber($data['emergency_contact'])) {
                $_SESSION['user_error'] = "Emergency contact must be a valid 10-digit phone number.";
                $this->redirect('index.php?route=users/index');
                return;
            }
            if (!empty($data['account_holder_name']) && !Validation::validateFullName($data['account_holder_name'])) {
                $_SESSION['user_error'] = "Please enter a valid account holder name.";
                $this->redirect('index.php?route=users/index');
                return;
            }
            if (!empty($data['bank_name']) && !Validation::validateBankName($data['bank_name'])) {
                $_SESSION['user_error'] = "Please enter a valid bank name.";
                $this->redirect('index.php?route=users/index');
                return;
            }
            if (!empty($data['account_number']) && !Validation::validateAccountNumber($data['account_number'])) {
                $_SESSION['user_error'] = "Account number must be numeric.";
                $this->redirect('index.php?route=users/index');
                return;
            }
            if (!empty($_POST['confirm_account_number']) || !empty($data['account_number'])) {
                $confirmAcc = trim($_POST['confirm_account_number'] ?? '');
                if ($confirmAcc !== $data['account_number']) {
                    $_SESSION['user_error'] = "Confirm Account Number must be numeric and match Account Number.";
                    $this->redirect('index.php?route=users/index');
                    return;
                }
            }
            if (!empty($data['ifsc_code']) && !Validation::validateIfscCode($data['ifsc_code'])) {
                $_SESSION['user_error'] = "Please enter a valid IFSC code.";
                $this->redirect('index.php?route=users/index');
                return;
            }
            if (!empty($data['branch_name']) && !Validation::validateBankBranch($data['branch_name'])) {
                $_SESSION['user_error'] = "Please enter a valid bank branch name/address.";
                $this->redirect('index.php?route=users/index');
                return;
            }
            if (!empty($data['pan_number'])) {
                $data['pan_number'] = strtoupper($data['pan_number']);
                if (!Validation::validatePanNumber($data['pan_number'])) {
                    $_SESSION['user_error'] = "Please enter a valid PAN number.";
                    $this->redirect('index.php?route=users/index');
                    return;
                }
            }
            if (!empty($data['aadhaar_number']) && !Validation::validateAadhaarNumber($data['aadhaar_number'])) {
                $_SESSION['user_error'] = "Please enter a valid Aadhaar number.";
                $this->redirect('index.php?route=users/index');
                return;
            }
            if (!empty($data['salary']) && !Validation::validateSalary($data['salary'])) {
                $_SESSION['user_error'] = "Salary must be a positive numeric value greater than 0.";
                $this->redirect('index.php?route=users/index');
                return;
            }
            if (!empty($data['uan']) && !Validation::validateUan($data['uan'])) {
                $_SESSION['user_error'] = "UAN must be a valid 12-digit number.";
                $this->redirect('index.php?route=users/index');
                return;
            }
            if (!empty($data['esic_number']) && !Validation::validateEsicNumber($data['esic_number'])) {
                $_SESSION['user_error'] = "ESIC number must be a valid 17-digit number.";
                $this->redirect('index.php?route=users/index');
                return;
            }
            if (!empty($data['pay_grade']) && !Validation::validatePayGrade($data['pay_grade'])) {
                $_SESSION['user_error'] = "Please select a valid pay grade.";
                $this->redirect('index.php?route=users/index');
                return;
            }

            if ($data['user_id'] > 0 && !empty($data['name']) && !empty($data['email']) && $data['role_id'] > 0) {
                if (Policy::isHr() && $this->userModel->getRoleNameById($data['role_id']) === 'admin') {
                    $this->redirect('index.php?route=users/index');
                    return;
                }
                if ($this->userModel->updateUser($data)) {
                    $_SESSION['user_success'] = 'Employee details updated successfully.';
                    $this->redirect('index.php?route=users/index');
                    return;
                } else {
                    die('Something went wrong.');
                }
            }
        }
        $this->redirect('index.php?route=users/index');
    }

    // Deactivate user action; physical deletion is disabled by policy.
    public function deactivate($id) {
        $this->requirePermission('employees', 'delete');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->userModel->deactivateUser((int) $id);
            $this->audit('Deactivated employee account', 'users', (int) $id);
        }
        $this->redirect('index.php?route=users/index');
    }

    /**
     * Download sample CSV template.
     */
    public function downloadTemplate() {
        $this->requirePermission('employees', 'view');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=employee_bulk_upload_template.csv');

        $output = fopen('php://output', 'w');
        
        // Write headers
        fputcsv($output, [
            'Employee_Code',
            'First_Name',
            'Last_Name',
            'Email',
            'Phone_Number',
            'Department',
            'Job_Title',
            'Reporting_Manager_Email',
            'Date_of_Joining',
            'Employment_Type',
            'Status',
            'Work_Location',
            'Date_of_Birth',
            'Bio',
            'Emergency_Contact',
            'Account_Holder_Name',
            'Bank_Name',
            'Account_Number',
            'IFSC_Code',
            'Branch_Name',
            'Account_Type',
            'PAN_Number',
            'Aadhaar_Number',
            'UAN',
            'ESIC_Number',
            'Salary',
            'Pay_Grade',
            'PF_Applicable',
            'Role_in_System',
            'Temp_Password'
        ]);

        // Write example row 1
        fputcsv($output, [
            'EMP1001',
            'John',
            'Doe',
            'john.doe@example.com',
            '+919876543210',
            'Sales',
            'Sales Manager',
            '',
            date('d-m-Y', strtotime('+1 day')),
            'Full-time',
            'active',
            'Office',
            '15-05-1990',
            'Experienced Sales Lead.',
            'Jane Doe - 9876543211',
            'John Doe',
            'Apex Bank',
            '100029384849',
            'APEX0009281',
            'Downtown Branch',
            'Savings',
            'ABCDE1234F',
            '123456789012',
            '10029384849',
            'ESIC1002938',
            '65000.00',
            'Band A',
            '1',
            'manager',
            'TempPass123'
        ]);

        // Write example row 2
        fputcsv($output, [
            'EMP1002',
            'Sarah',
            'Connor',
            'sarah.c@example.com',
            '+919876543212',
            'Marketing',
            'Marketing Analyst',
            'john.doe@example.com',
            date('d-m-Y', strtotime('+2 days')),
            'Full-time',
            'active',
            'Remote',
            '22-08-1994',
            'Growth marketing specialist.',
            'John Connor - 9876543213',
            'Sarah Connor',
            'Apex Bank',
            '100029384850',
            'APEX0009281',
            'Downtown Branch',
            'Savings',
            'FGHIJ5678K',
            '987654321098',
            '10029384850',
            'ESIC1002939',
            '45000.00',
            'Band B',
            '0',
            'analyst',
            'TempPass456'
        ]);

        fclose($output);
        exit();
    }

    /**
     * Parse and validate uploaded CSV.
     */
    public function bulkUpload() {
        $this->requirePermission('employees', 'create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('Invalid request method.', 405);
        }

        if (empty($_FILES['csv_file']['tmp_name'])) {
            $this->jsonError('No file uploaded.');
        }

        $file = $_FILES['csv_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            $this->jsonError('Only CSV files are allowed.');
        }

        try {
            $importer = new BulkImportService();
            $rows = $importer->parseCsv($file['tmp_name']);
            
            // Build the validation config mapping
            $employeeConfig = [
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

            // Setup lookups
            $db = Database::getInstance()->getConnection();
            
            // Get valid department list
            $stmt = $db->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != ''");
            $existingDepts = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            if (empty($existingDepts)) {
                $existingDepts = ['Sales', 'Marketing', 'Engineering', 'HR', 'Finance', 'Operations', 'Executive'];
            }

            $callbacks = [
                'validate_field_department' => function($val) use ($existingDepts) {
                    $allowed = array_map('strtolower', $existingDepts);
                    if (!in_array(strtolower($val), $allowed, true)) {
                        return "Department does not exist. Choose from: " . implode(', ', $existingDepts);
                    }
                    return null;
                },
                'validate_field_date_of_joining' => function($val) {
                    $today = new DateTime('today');
                    $doj = DateTime::createFromFormat('Y-m-d', $val);
                    if ($doj && $doj <= $today) {
                        return "Date of joining must be a future date (greater than today).";
                    }
                    return null;
                },
                'validate_field_reporting_manager_email' => function($val) use ($db) {
                    $stmt = $db->prepare("SELECT user_id FROM users WHERE email = :email");
                    $stmt->execute([':email' => $val]);
                    if (!$stmt->fetch()) {
                        return "No employee matches this reporting manager email.";
                    }
                    return null;
                },
                'db_duplicate_check' => function($mappedRow) use ($db) {
                    // Check email duplicate
                    $stmt = $db->prepare("SELECT user_id FROM users WHERE email = :email");
                    $stmt->execute([':email' => $mappedRow['email']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($user) {
                        return ['user_id' => $user['user_id'], 'message' => 'Duplicate Email: already in database.'];
                    }

                    // Check employee_code duplicate
                    if (!empty($mappedRow['employee_code'])) {
                        $stmt = $db->prepare("SELECT user_id FROM employees WHERE employee_code = :code");
                        $stmt->execute([':code' => $mappedRow['employee_code']]);
                        $emp = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($emp) {
                            return ['user_id' => $emp['user_id'], 'message' => 'Duplicate Employee Code: already in database.'];
                        }
                    }
                    return null;
                }
            ];

            // Run validator
            $results = $importer->validate($rows, $employeeConfig, $callbacks);

            // Store in session for the confirmation step
            $_SESSION['bulk_import_rows'] = $results['rows'];
            $_SESSION['bulk_import_metadata'] = [
                'filename' => htmlspecialchars($file['name']),
                'uploaded_at' => time()
            ];

            $this->json($results);
        } catch (Exception $e) {
            $this->jsonError($e->getMessage());
        }
    }

    /**
     * Execute the transactional import batch.
     */
    public function bulkImport() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('Invalid request method.', 405);
        }

        $input = $this->jsonInput();
        $strategy = $input['duplicate_strategy'] ?? 'skip';
        if (!in_array($strategy, ['skip', 'update', 'create'], true)) {
            $this->jsonError('Invalid duplicate resolution strategy.');
        }

        if (empty($_SESSION['bulk_import_rows'])) {
            $this->jsonError('No uploaded data found in session. Please upload first.');
        }

        $rows = $_SESSION['bulk_import_rows'];
        $meta = $_SESSION['bulk_import_metadata'] ?? ['filename' => 'bulk_upload.csv'];

        // Only process rows that were successfully validated or duplicates resolved
        $validRows = array_filter($rows, function($r) {
            return $r['status'] === 'valid' || $r['action'] === 'duplicate';
        });

        if (empty($validRows)) {
            $this->jsonError('No valid rows found to import.');
        }

        $importer = new BulkImportService();
        $db = Database::getInstance()->getConnection();

        // Custom import callback specifically mapping multi-table models
        $importCallback = function($row, $action, $duplicateStrategy) use ($db) {
            // 1. Resolve role_id
            $roleName = strtolower($row['role_in_system'] ?: 'employer');
            $stmt = $db->prepare("SELECT role_id FROM roles WHERE LOWER(role_name) = :name");
            $stmt->execute([':name' => $roleName]);
            $role = $stmt->fetch(PDO::FETCH_ASSOC);
            $role_id = $role ? (int)$role['role_id'] : 4; // fallback to employer

            // 2. Format name
            $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            if (empty($name)) {
                $name = 'Imported User';
            }

            // 3. Temporary password handling
            $tempPassword = $row['temp_password'] ?: bin2hex(random_bytes(6));

            // 4. Resolve reporting manager
            $reportingManagerId = null;
            if (!empty($row['reporting_manager_email'])) {
                $stmt = $db->prepare("SELECT user_id FROM users WHERE email = :email");
                $stmt->execute([':email' => $row['reporting_manager_email']]);
                $mgr = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($mgr) {
                    $reportingManagerId = (int)$mgr['user_id'];
                }
            }

            $data = [
                'role_id' => $role_id,
                'name' => $name,
                'email' => $row['email'],
                'password' => $tempPassword,
                'status' => strtolower($row['status'] ?: 'active'),
                'employee_code' => $row['employee_code'] ?: 'EMP' . rand(10000, 99999),
                'phone_number' => $row['phone_number'] ?: null,
                'salary' => !empty($row['salary']) ? $row['salary'] : null,
                'date_of_joining' => !empty($row['date_of_joining']) ? $row['date_of_joining'] : date('Y-m-d'),
                'date_of_birth' => !empty($row['date_of_birth']) ? $row['date_of_birth'] : null,
                'job_title' => $row['job_title'] ?: 'Sales Executive',
                'department' => $row['department'] ?: 'Sales',
                'employment_type' => $row['employment_type'] ?: 'Full-time',
                'work_location' => $row['work_location'] ?: 'Office',
                'bio' => $row['bio'] ?: null,
                'emergency_contact' => $row['emergency_contact'] ?: null,
                'pan_number' => $row['pan_number'] ?: null,
                'aadhaar_number' => $row['aadhaar_number'] ?: null,
                'uan' => $row['uan'] ?: null,
                'pf_applicable' => (int)($row['pf_applicable'] ?? 0),
                'esic_number' => $row['esic_number'] ?: null,
                'pay_grade' => $row['pay_grade'] ?: null,
                'force_password_reset' => 1,
                
                // Bank Details
                'account_holder_name' => $row['account_holder_name'] ?: $name,
                'bank_name' => $row['bank_name'] ?: null,
                'account_number' => $row['account_number'] ?: null,
                'ifsc_code' => $row['ifsc_code'] ?: null,
                'branch_name' => $row['branch_name'] ?: null,
                'account_type' => $row['account_type'] ?: 'Savings'
            ];

            $userModel = $this->model('User');

            if ($action === 'duplicate' && $duplicateStrategy === 'update') {
                // Find matching user ID
                $stmt = $db->prepare("SELECT user_id FROM users WHERE email = :email");
                $stmt->execute([':email' => $data['email']]);
                $u = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$u && !empty($data['employee_code'])) {
                    $stmt = $db->prepare("SELECT user_id FROM employees WHERE employee_code = :code");
                    $stmt->execute([':code' => $data['employee_code']]);
                    $u = $stmt->fetch(PDO::FETCH_ASSOC);
                }

                if ($u) {
                    $data['user_id'] = (int)$u['user_id'];
                    $data['profile_photo'] = null;
                    
                    // Call base model updater
                    if ($userModel->updateUser($data)) {
                        // Apply custom table updates missing from base updateUser
                        $stmt = $db->prepare("UPDATE employees SET reporting_manager_id = :mgr WHERE user_id = :uid");
                        $stmt->execute([':mgr' => $reportingManagerId, ':uid' => $data['user_id']]);

                        // Handle bank details update
                        $stmt = $db->prepare("SELECT employee_id FROM employees WHERE user_id = :uid");
                        $stmt->execute([':uid' => $data['user_id']]);
                        $emp = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($emp) {
                            $empId = (int)$emp['employee_id'];
                            $stmt = $db->prepare("SELECT bank_account_id FROM bank_accounts WHERE employee_id = :eid");
                            $stmt->execute([':eid' => $empId]);
                            $bank = $stmt->fetch(PDO::FETCH_ASSOC);
                            if ($bank) {
                                $stmt = $db->prepare("
                                    UPDATE bank_accounts 
                                    SET account_holder_name = :holder, bank_name = :bank, account_number = :acc, ifsc_code = :ifsc, branch_name = :branch, account_type = :type 
                                    WHERE employee_id = :eid
                                ");
                                $stmt->execute([
                                    ':holder' => $data['account_holder_name'],
                                    ':bank' => $data['bank_name'],
                                    ':acc' => $data['account_number'],
                                    ':ifsc' => $data['ifsc_code'],
                                    ':branch' => $data['branch_name'],
                                    ':type' => $data['account_type'],
                                    ':eid' => $empId
                                ]);
                            } else if (!empty($data['bank_name']) || !empty($data['account_number'])) {
                                $stmt = $db->prepare("
                                    INSERT INTO bank_accounts (employee_id, account_holder_name, bank_name, account_number, ifsc_code, branch_name, account_type) 
                                    VALUES (:eid, :holder, :bank, :acc, :ifsc, :branch, :type)
                                ");
                                $stmt->execute([
                                    ':eid' => $empId,
                                    ':holder' => $data['account_holder_name'],
                                    ':bank' => $data['bank_name'],
                                    ':acc' => $data['account_number'],
                                    ':ifsc' => $data['ifsc_code'],
                                    ':branch' => $data['branch_name'],
                                    ':type' => $data['account_type']
                                ]);
                            }
                        }
                        return true;
                    }
                    return false;
                }
            }

            // Create record
            if ($userModel->addUser($data)) {
                $stmt = $db->prepare("SELECT user_id FROM users WHERE email = :email");
                $stmt->execute([':email' => $data['email']]);
                $u = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($u) {
                    $newUid = (int)$u['user_id'];
                    $stmt = $db->prepare("UPDATE employees SET reporting_manager_id = :mgr WHERE user_id = :uid");
                    $stmt->execute([':mgr' => $reportingManagerId, ':uid' => $newUid]);
                }
                return true;
            }
            return false;
        };

        try {
            $logMetadata = [
                'user_id' => $_SESSION['user_id'],
                'module' => 'employees',
                'filename' => $meta['filename']
            ];

            $results = $importer->import($validRows, $strategy, $importCallback, $logMetadata);

            // Store failures in session for error CSV download
            $_SESSION['bulk_import_errors'] = $results['errors'];

            // Clear session data since import has completed
            unset($_SESSION['bulk_import_rows']);
            unset($_SESSION['bulk_import_metadata']);

            $this->json($results);
        } catch (Exception $e) {
            $this->jsonError($e->getMessage());
        }
    }

    /**
     * Download CSV report of failed rows.
     */
    public function downloadErrors() {
        if (empty($_SESSION['bulk_import_errors'])) {
            die("No error report available.");
        }

        $errors = $_SESSION['bulk_import_errors'];

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=employee_import_error_report.csv');

        $output = fopen('php://output', 'w');

        // Write header
        fputcsv($output, [
            'Row Number',
            'Reason for Failure',
            'Employee_Code',
            'First_Name',
            'Last_Name',
            'Email',
            'Phone_Number',
            'Department',
            'Job_Title',
            'Reporting_Manager_Email',
            'Date_of_Joining',
            'Employment_Type',
            'Status',
            'Work_Location',
            'Date_of_Birth',
            'Bio',
            'Emergency_Contact',
            'Account_Holder_Name',
            'Bank_Name',
            'Account_Number',
            'IFSC_Code',
            'Branch_Name',
            'Account_Type',
            'PAN_Number',
            'Aadhaar_Number',
            'UAN',
            'ESIC_Number',
            'Salary',
            'Pay_Grade',
            'PF_Applicable',
            'Role_in_System',
            'Temp_Password'
        ]);

        foreach ($errors as $err) {
            $d = $err['data'];
            fputcsv($output, [
                $err['row'],
                $err['reason'],
                $d['employee_code'] ?? '',
                $d['first_name'] ?? '',
                $d['last_name'] ?? '',
                $d['email'] ?? '',
                $d['phone_number'] ?? '',
                $d['department'] ?? '',
                $d['job_title'] ?? '',
                $d['reporting_manager_email'] ?? '',
                $d['date_of_joining'] ?? '',
                $d['employment_type'] ?? '',
                $d['status'] ?? '',
                $d['work_location'] ?? '',
                $d['date_of_birth'] ?? '',
                $d['bio'] ?? '',
                $d['emergency_contact'] ?? '',
                $d['account_holder_name'] ?? '',
                $d['bank_name'] ?? '',
                $d['account_number'] ?? '',
                $d['ifsc_code'] ?? '',
                $d['branch_name'] ?? '',
                $d['account_type'] ?? '',
                $d['pan_number'] ?? '',
                $d['aadhaar_number'] ?? '',
                $d['uan'] ?? '',
                $d['esic_number'] ?? '',
                $d['salary'] ?? '',
                $d['pay_grade'] ?? '',
                $d['pf_applicable'] ?? '',
                $d['role_in_system'] ?? '',
                $d['temp_password'] ?? ''
            ]);
        }

        fclose($output);
        exit();
    }
}
