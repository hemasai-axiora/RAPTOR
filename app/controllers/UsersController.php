<?php
// Raptor CRM Employee Management Controller (Admin and HR)

class UsersController extends Controller {
    private $userModel;

    public function __construct() {
        $this->requireAuth();
        $role = $_SESSION['user_role'] ?? '';
        if (!in_array($role, ['admin', 'manager', 'hr', 'ceo', 'analyst'], true)) {
            if ($this->isAjax()) {
                $this->jsonError('Access Denied: You do not have permission to access employee management.', 403);
            }
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
     * Download / Export full employee details report (CSV).
     */
    public function export() {
        $this->requirePermission('employees', 'view');
        $users = $this->userModel->getUsers();

        $filename = 'employee_details_' . date('Y-m-d_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        // Write CSV headers
        fputcsv($output, [
            'Emp_ID',
            'Name',
            'Email',
            'Role',
            'Job_Title',
            'Department',
            'Work_Location',
            'Joining_Date',
            'Status',
            'Phone_Number',
            'Salary',
            'Employment_Type',
            'Date_of_Birth',
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
            'PF_Applicable',
            'ESIC_Number',
            'Pay_Grade'
        ]);

        $canViewSensitive = in_array($_SESSION['user_role'] ?? '', ['admin', 'hr', 'finance', 'ceo'], true);

        foreach ($users as $user) {
            $joiningDate = !empty($user->date_of_joining) ? date('Y-m-d', strtotime($user->date_of_joining)) : (!empty($user->created_at) ? date('Y-m-d', strtotime($user->created_at)) : '');
            
            fputcsv($output, [
                $user->employee_code ?? 'N/A',
                $user->name ?? '',
                $user->email ?? '',
                strtoupper($user->role_name ?? ''),
                $user->job_title ?? '',
                $user->department ?? 'Sales',
                $user->work_location ?? 'Office',
                $joiningDate,
                ucfirst($user->status ?? 'active'),
                $user->phone_number ?? '',
                $canViewSensitive ? ($user->salary ?? '') : 'RESTRICTED',
                $user->employment_type ?? 'Full-time',
                $user->date_of_birth ?? '',
                $user->emergency_contact ?? '',
                $user->account_holder_name ?? '',
                $user->bank_name ?? '',
                $canViewSensitive ? ($user->account_number ?? '') : 'RESTRICTED',
                $user->ifsc_code ?? '',
                $user->branch_name ?? '',
                $user->account_type ?? 'Savings',
                $canViewSensitive ? ($user->pan_number ?? '') : 'RESTRICTED',
                $canViewSensitive ? ($user->aadhaar_number ?? '') : 'RESTRICTED',
                $user->uan ?? '',
                ($user->pf_applicable ?? 0) == 1 ? 'Yes' : 'No',
                $user->esic_number ?? '',
                $user->pay_grade ?? ''
            ]);
        }

        fclose($output);
        exit;
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
        $role = $_SESSION['user_role'] ?? '';
        if (!in_array($role, ['admin', 'manager', 'hr', 'ceo'], true) && !$this->hasPermission('employees')) {
            $this->requirePermission('employees', 'create');
        }
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
                    'employee_code' => ['required' => false, 'type' => 'text'],
                    'first_name' => ['required' => false, 'type' => 'text'],
                    'last_name' => ['required' => false, 'type' => 'text'],
                    'name' => ['required' => false, 'type' => 'text'],
                    'email' => ['required' => true, 'type' => 'email', 'unique_in_file' => true],
                    'phone_number' => ['required' => false, 'type' => 'text'],
                    'department' => ['required' => false, 'type' => 'text'],
                    'job_title' => ['required' => false, 'type' => 'text'],
                    'reporting_manager_email' => ['required' => false, 'type' => 'text'],
                    'date_of_joining' => ['required' => false, 'type' => 'date'],
                    'employment_type' => ['required' => false, 'type' => 'text'],
                    'status' => ['required' => false, 'type' => 'text'],
                    'work_location' => ['required' => false, 'type' => 'text'],
                    'date_of_birth' => ['required' => false, 'type' => 'date'],
                    'bio' => ['required' => false, 'type' => 'text'],
                    'emergency_contact' => ['required' => false, 'type' => 'text'],
                    'account_holder_name' => ['required' => false, 'type' => 'text'],
                    'bank_name' => ['required' => false, 'type' => 'text'],
                    'account_number' => ['required' => false, 'type' => 'text'],
                    'ifsc_code' => ['required' => false, 'type' => 'text'],
                    'branch_name' => ['required' => false, 'type' => 'text'],
                    'account_type' => ['required' => false, 'type' => 'text'],
                    'pan_number' => ['required' => false, 'type' => 'text'],
                    'aadhaar_number' => ['required' => false, 'type' => 'text'],
                    'uan' => ['required' => false, 'type' => 'text'],
                    'esic_number' => ['required' => false, 'type' => 'text'],
                    'salary' => ['required' => false, 'type' => 'number'],
                    'pay_grade' => ['required' => false, 'type' => 'text'],
                    'pf_applicable' => ['required' => false, 'type' => 'text'],
                    'role_in_system' => ['required' => false, 'type' => 'text'],
                    'temp_password' => ['required' => false, 'type' => 'text']
                ]
            ];

            // Setup lookups
            $db = Database::getInstance()->getConnection();

            $callbacks = [
                'validate_field_department' => function($val) {
                    return null;
                },
                'validate_field_date_of_joining' => function($val) {
                    if (empty(trim($val))) return null;
                    $clean = str_replace('/', '-', trim($val));
                    $ts = strtotime($clean);
                    if (!$ts) {
                        return "Date of joining must be a valid date.";
                    }
                    return null;
                },
                'validate_field_reporting_manager_email' => function($val) {
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

            // Format structured errors array
            $structuredErrors = [];
            foreach ($results['rows'] as $r) {
                if ($r['status'] === 'error') {
                    foreach ($r['errors'] as $field => $errMsg) {
                        $structuredErrors[] = [
                            'row' => $r['index'],
                            'field' => $field,
                            'message' => $errMsg
                        ];
                    }
                }
            }
            $results['structured_errors'] = $structuredErrors;

            if (!empty($structuredErrors)) {
                error_log("Bulk Upload Validation Errors (" . count($structuredErrors) . ") for file '" . ($file['name'] ?? 'csv') . "': " . json_encode($structuredErrors));
            }

            // Store in session for the confirmation step
            $_SESSION['bulk_import_rows'] = $results['rows'];
            $_SESSION['bulk_import_metadata'] = [
                'filename' => htmlspecialchars($file['name']),
                'uploaded_at' => time()
            ];

            $this->json($results);
        } catch (Exception $e) {
            error_log("[Bulk Upload Exception] " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => [
                    ['row' => 0, 'field' => 'file', 'message' => $e->getMessage()]
                ]
            ], 400);
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
            $rawRole = strtolower(trim($row['role_in_system'] ?? ''));
            $rolesStmt = $db->query("SELECT role_id, LOWER(role_name) AS rname FROM roles");
            $rolesMap = [];
            while ($r = $rolesStmt->fetch(PDO::FETCH_ASSOC)) {
                $rolesMap[$r['rname']] = (int)$r['role_id'];
            }
            $defaultRoleId = $rolesMap['employee'] ?? ($rolesMap['sales_person'] ?? 10);
            
            $cleanRole = str_replace([' ', '-'], '_', $rawRole);
            if (!empty($rawRole) && isset($rolesMap[$cleanRole])) {
                $role_id = $rolesMap[$cleanRole];
            } elseif (!empty($rawRole) && isset($rolesMap[$rawRole])) {
                $role_id = $rolesMap[$rawRole];
            } elseif (strpos($rawRole, 'sale') !== false) {
                $role_id = $rolesMap['sales_person'] ?? $defaultRoleId;
            } elseif (strpos($rawRole, 'manage') !== false) {
                $role_id = $rolesMap['manager'] ?? $defaultRoleId;
            } else {
                $role_id = $defaultRoleId;
            }

            // 2. Format name
            $firstName = trim($row['first_name'] ?? '');
            $lastName = trim($row['last_name'] ?? '');
            $fullName = trim($row['name'] ?? '');
            if (!empty($firstName) || !empty($lastName)) {
                $name = trim($firstName . ' ' . $lastName);
            } elseif (!empty($fullName)) {
                $name = $fullName;
            } else {
                $name = 'Imported User';
            }

            // 3. Temporary password handling
            $tempPassword = $row['temp_password'] ?: bin2hex(random_bytes(6));

            // 4. Resolve reporting manager
            $reportingManagerId = null;
            $mgrInput = trim($row['reporting_manager_email'] ?? '');
            if (!empty($mgrInput)) {
                $mgrStmt = $db->prepare("SELECT user_id FROM users WHERE LOWER(email) = :email LIMIT 1");
                $mgrStmt->execute([':email' => strtolower($mgrInput)]);
                $mgr = $mgrStmt->fetch(PDO::FETCH_ASSOC);
                if ($mgr) {
                    $reportingManagerId = (int)$mgr['user_id'];
                } else {
                    $mgrStmt2 = $db->prepare("SELECT user_id FROM employees WHERE employee_code = :code LIMIT 1");
                    $mgrStmt2->execute([':code' => $mgrInput]);
                    $mgr2 = $mgrStmt2->fetch(PDO::FETCH_ASSOC);
                    if ($mgr2) {
                        $reportingManagerId = (int)$mgr2['user_id'];
                    }
                }
            }

            $salaryVal = preg_replace('/[^0-9.]/', '', $row['salary'] ?? '');
            $cleanPhone = preg_replace('/[^0-9]/', '', $row['phone_number'] ?? '');
            if (strlen($cleanPhone) === 12 && substr($cleanPhone, 0, 2) === '91') {
                $cleanPhone = substr($cleanPhone, 2);
            }

            $data = [
                'role_id' => $role_id,
                'name' => $name,
                'email' => $row['email'],
                'password' => $tempPassword,
                'status' => strtolower($row['status'] ?: 'active'),
                'employee_code' => $row['employee_code'] ?: 'EMP' . rand(10000, 99999),
                'phone_number' => $cleanPhone ?: null,
                'salary' => is_numeric($salaryVal) ? (float)$salaryVal : null,
                'date_of_joining' => !empty($row['date_of_joining']) ? $row['date_of_joining'] : date('Y-m-d'),
                'date_of_birth' => !empty($row['date_of_birth']) ? $row['date_of_birth'] : null,
                'job_title' => $row['job_title'] ?: 'Staff',
                'department' => $row['department'] ?: 'Sales',
                'employment_type' => $row['employment_type'] ?: 'Full-time',
                'work_location' => $row['work_location'] ?: 'Office',
                'bio' => $row['bio'] ?: null,
                'emergency_contact' => $row['emergency_contact'] ?: null,
                'pan_number' => !empty($row['pan_number']) ? strtoupper($row['pan_number']) : null,
                'aadhaar_number' => !empty($row['aadhaar_number']) ? preg_replace('/[^0-9]/', '', $row['aadhaar_number']) : null,
                'uan' => !empty($row['uan']) ? preg_replace('/[^0-9]/', '', $row['uan']) : null,
                'pf_applicable' => in_array(strtolower($row['pf_applicable'] ?? ''), ['1', 'yes', 'true', 'y'], true) ? 1 : 0,
                'esic_number' => $row['esic_number'] ?: null,
                'pay_grade' => $row['pay_grade'] ?: null,
                'force_password_reset' => 1,
                'reporting_manager_id' => $reportingManagerId,
                
                // Bank Details
                'account_holder_name' => $row['account_holder_name'] ?: $name,
                'bank_name' => $row['bank_name'] ?: null,
                'account_number' => $row['account_number'] ?: null,
                'ifsc_code' => !empty($row['ifsc_code']) ? strtoupper($row['ifsc_code']) : null,
                'branch_name' => $row['branch_name'] ?: null,
                'account_type' => !empty($row['account_type']) ? $row['account_type'] : 'Savings'
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

    public function importCsv() {
        $role = $_SESSION['user_role'] ?? '';
        if (!in_array($role, ['admin', 'manager', 'hr', 'ceo'], true) && !$this->hasPermission('employees')) {
            $this->requirePermission('employees', 'create');
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['csv_file']['tmp_name'])) {
            $_SESSION['user_error'] = "Please select a valid CSV file to upload.";
            $this->redirect('index.php?route=users/index');
            return;
        }

        $file = $_FILES['csv_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            $_SESSION['user_error'] = "Invalid file type. Only .csv files are supported.";
            $this->redirect('index.php?route=users/index');
            return;
        }

        if ($file['size'] > 10 * 1024 * 1024) {
            $_SESSION['user_error'] = "File size exceeds 10MB limit.";
            $this->redirect('index.php?route=users/index');
            return;
        }

        $duplicateStrategy = $_POST['duplicate_strategy'] ?? 'update';

        $filePath = $file['tmp_name'];
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            $_SESSION['user_error'] = "Unable to open uploaded CSV file.";
            $this->redirect('index.php?route=users/index');
            return;
        }

        @ini_set('auto_detect_line_endings', true);

        // Auto-detect delimiter
        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = ',';
        if ($firstLine !== false) {
            $commas = substr_count($firstLine, ',');
            $semicolons = substr_count($firstLine, ';');
            $tabs = substr_count($firstLine, "\t");
            if ($semicolons > $commas && $semicolons > $tabs) {
                $delimiter = ';';
            } elseif ($tabs > $commas && $tabs > $semicolons) {
                $delimiter = "\t";
            }
        }

        // Read header
        $rawHeaders = fgetcsv($handle, 4096, $delimiter);
        if (!$rawHeaders) {
            fclose($handle);
            $_SESSION['user_error'] = "CSV file is empty or could not be read.";
            $this->redirect('index.php?route=users/index');
            return;
        }

        // Strip UTF-8 BOM if present
        if (!empty($rawHeaders[0]) && strpos($rawHeaders[0], "\xEF\xBB\xBF") === 0) {
            $rawHeaders[0] = substr($rawHeaders[0], 3);
        }

        // Normalize header strings
        $normalizedHeaders = [];
        foreach ($rawHeaders as $idx => $h) {
            $cleanH = strtolower(trim($h));
            $cleanH = preg_replace('/[^a-z0-9]+/', '_', $cleanH);
            $cleanH = trim($cleanH, '_');
            $normalizedHeaders[$idx] = $cleanH;
        }

        // Map column indices to canonical fields using alias dictionary
        $aliasMap = [
            'employee_code' => ['employee_code', 'employee_id', 'emp_code', 'emp_id', 'empid', 'code', 'id'],
            'first_name' => ['first_name', 'firstname', 'first'],
            'last_name' => ['last_name', 'lastname', 'last', 'surname'],
            'name' => ['name', 'full_name', 'fullname', 'employee_name', 'employee'],
            'email' => ['email', 'email_address', 'e_mail', 'mail'],
            'phone_number' => ['phone_number', 'phone', 'phone_no', 'mobile', 'mobile_number', 'contact_number', 'contact'],
            'department' => ['department', 'dept'],
            'job_title' => ['job_title', 'job', 'title', 'designation', 'position', 'role_title'],
            'reporting_manager_email' => ['reporting_manager_email', 'reporting_manager', 'reporting_id', 'reporting_to', 'manager_email', 'manager_id', 'manager', 'reports_to'],
            'date_of_joining' => ['date_of_joining', 'joining_date', 'doj', 'hire_date', 'date_of_join', 'joining'],
            'employment_type' => ['employment_type', 'employment', 'employmer', 'employmertype', 'type_of_employment'],
            'status' => ['status', 'user_status', 'state', 'active_status'],
            'work_location' => ['work_location', 'location', 'worklocation', 'work_place', 'workplace'],
            'date_of_birth' => ['date_of_birth', 'birth_date', 'dob', 'date_of_birth_dd_mm_yyyy'],
            'bio' => ['bio', 'notes', 'about', 'description', 'employee_bio'],
            'emergency_contact' => ['emergency_contact', 'emergency', 'emergency_phone', 'emergency_contact_number', 'emergency_no'],
            'account_holder_name' => ['account_holder_name', 'account_holder', 'holder_name', 'ac_holder_name', 'acc_holder_name'],
            'bank_name' => ['bank_name', 'bank'],
            'account_number' => ['account_number', 'account_no', 'acc_number', 'acc_no', 'ac_number', 'ac_no', 'bank_account_number'],
            'ifsc_code' => ['ifsc_code', 'ifsc', 'ifsc_no'],
            'branch_name' => ['branch_name', 'branch', 'branch_address', 'bank_branch'],
            'account_type' => ['account_type', 'acct_type', 'acc_type', 'type_of_account'],
            'pan_number' => ['pan_number', 'pan', 'pan_no', 'pancard', 'pan_card'],
            'aadhaar_number' => ['aadhaar_number', 'aadhaar', 'aadhaar_no', 'aadhar_number', 'aadhar', 'aadhar_no', 'uidai'],
            'uan' => ['uan', 'uan_number', 'uan_no', 'pf_uan'],
            'esic_number' => ['esic_number', 'esic', 'esic_no', 'esi_number', 'esi', 'esi_no'],
            'salary' => ['salary', 'ctc', 'salary_ctc', 'monthly_salary', 'basic_salary', 'gross_salary'],
            'pay_grade' => ['pay_grade', 'pay_band', 'salary_band', 'grade', 'band'],
            'pf_applicable' => ['pf_applicable', 'pf', 'is_pf_applicable', 'pf_eligible', 'provident_fund'],
            'role_in_system' => ['role_in_system', 'system_role', 'role', 'user_role', 'system_role_name'],
            'temp_password' => ['temp_password', 'temporary_password', 'password', 'new_password', 'pass'],
            'force_password_reset' => ['force_password_reset', 'force_reset', 'reset_password_on_login']
        ];

        $colToField = [];
        foreach ($normalizedHeaders as $idx => $nh) {
            foreach ($aliasMap as $canonical => $aliases) {
                if (in_array($nh, $aliases, true) || $nh === $canonical) {
                    $colToField[$idx] = $canonical;
                    break;
                }
            }
        }

        $db = Database::getInstance()->getConnection();

        // Preload roles
        $rolesStmt = $db->query("SELECT role_id, LOWER(role_name) AS rname FROM roles");
        $rolesMap = [];
        while ($r = $rolesStmt->fetch(PDO::FETCH_ASSOC)) {
            $rolesMap[$r['rname']] = (int)$r['role_id'];
        }
        $defaultRoleId = $rolesMap['employee'] ?? ($rolesMap['sales_person'] ?? 10);

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        // Helper function for flexible date parsing
        $parseDate = function($val) {
            if (empty(trim($val))) return null;
            $clean = trim($val);
            $clean = str_replace('/', '-', $clean);
            $ts = strtotime($clean);
            if ($ts && $ts > 0) {
                return date('Y-m-d', $ts);
            }
            foreach (['d-m-Y', 'Y-m-d', 'm-d-Y', 'd.m.Y'] as $fmt) {
                $d = DateTime::createFromFormat($fmt, $clean);
                if ($d !== false) {
                    return $d->format('Y-m-d');
                }
            }
            return null;
        };

        while (($row = fgetcsv($handle, 4096, $delimiter)) !== false) {
            if (empty(array_filter($row))) continue;

            $rowData = [];
            foreach ($row as $idx => $val) {
                $val = trim($val);
                // Prevent formula injection
                if (strlen($val) > 0 && in_array($val[0], ['=', '+', '-', '@'], true)) {
                    $val = ltrim($val, "=+-@ ");
                }
                if (isset($colToField[$idx])) {
                    $rowData[$colToField[$idx]] = $val;
                }
            }

            // Extract email
            $email = strtolower(trim($rowData['email'] ?? ''));
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $failed++;
                continue;
            }

            // Extract Name
            $firstName = trim($rowData['first_name'] ?? '');
            $lastName = trim($rowData['last_name'] ?? '');
            $fullName = trim($rowData['name'] ?? '');
            if (!empty($firstName) || !empty($lastName)) {
                $name = trim($firstName . ' ' . $lastName);
            } elseif (!empty($fullName)) {
                $name = $fullName;
            } else {
                $name = 'Imported User';
            }

            // Employee code
            $empCode = trim($rowData['employee_code'] ?? '');
            if (empty($empCode)) {
                $empCode = 'EMP' . rand(10000, 99999);
            }

            // Phone
            $phone = trim($rowData['phone_number'] ?? '');
            if (!empty($phone)) {
                $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
                if (strlen($cleanPhone) === 12 && substr($cleanPhone, 0, 2) === '91') {
                    $cleanPhone = substr($cleanPhone, 2);
                }
                if (strlen($cleanPhone) === 10) {
                    $phone = $cleanPhone;
                }
            }

            // Department & Job Title
            $dept = trim($rowData['department'] ?? 'Sales') ?: 'Sales';
            $jobTitle = trim($rowData['job_title'] ?? 'Staff') ?: 'Staff';

            // Dates
            $doj = $parseDate($rowData['date_of_joining'] ?? '') ?: date('Y-m-d');
            $dob = $parseDate($rowData['date_of_birth'] ?? '');

            // Employment Type & Location
            $rawEmpType = strtolower(trim($rowData['employment_type'] ?? ''));
            $empType = 'Full-time';
            if (strpos($rawEmpType, 'part') !== false) $empType = 'Part-time';
            elseif (strpos($rawEmpType, 'contract') !== false) $empType = 'Contract';
            elseif (strpos($rawEmpType, 'intern') !== false) $empType = 'Intern';

            $rawLoc = strtolower(trim($rowData['work_location'] ?? ''));
            $workLoc = 'Office';
            if (strpos($rawLoc, 'remote') !== false) $workLoc = 'Remote';
            elseif (strpos($rawLoc, 'hybrid') !== false) $workLoc = 'Hybrid';

            // Status
            $rawStatus = strtolower(trim($rowData['status'] ?? 'active'));
            $status = in_array($rawStatus, ['active', 'inactive', 'suspended', 'terminated'], true) ? $rawStatus : 'active';

            // Bio & Emergency contact
            $bio = trim($rowData['bio'] ?? '') ?: null;
            $emergencyContact = trim($rowData['emergency_contact'] ?? '') ?: null;

            // Compliance
            $pan = strtoupper(trim($rowData['pan_number'] ?? '')) ?: null;
            $aadhaar = preg_replace('/[^0-9]/', '', $rowData['aadhaar_number'] ?? '') ?: null;
            $uan = preg_replace('/[^0-9]/', '', $rowData['uan'] ?? '') ?: null;
            $esic = trim($rowData['esic_number'] ?? '') ?: null;

            $salaryVal = preg_replace('/[^0-9.]/', '', $rowData['salary'] ?? '');
            $salary = is_numeric($salaryVal) ? (float)$salaryVal : null;

            $payGrade = trim($rowData['pay_grade'] ?? '') ?: null;

            $pfVal = strtolower(trim($rowData['pf_applicable'] ?? '0'));
            $pfApplicable = in_array($pfVal, ['1', 'yes', 'true', 'y'], true) ? 1 : 0;

            // Role
            $rawRole = strtolower(trim($rowData['role_in_system'] ?? ''));
            $roleId = $defaultRoleId;
            if (!empty($rawRole)) {
                $cleanRole = str_replace([' ', '-'], '_', $rawRole);
                if (isset($rolesMap[$cleanRole])) {
                    $roleId = $rolesMap[$cleanRole];
                } elseif (isset($rolesMap[$rawRole])) {
                    $roleId = $rolesMap[$rawRole];
                } elseif (strpos($rawRole, 'sale') !== false) {
                    $roleId = $rolesMap['sales_person'] ?? $defaultRoleId;
                } elseif (strpos($rawRole, 'manage') !== false) {
                    $roleId = $rolesMap['manager'] ?? $defaultRoleId;
                }
            }

            // Reporting Manager
            $managerId = null;
            $mgrInput = trim($rowData['reporting_manager_email'] ?? '');
            if (!empty($mgrInput)) {
                $mgrStmt = $db->prepare("SELECT user_id FROM users WHERE LOWER(email) = :m_email LIMIT 1");
                $mgrStmt->execute([':m_email' => strtolower($mgrInput)]);
                $mgrFound = $mgrStmt->fetch(PDO::FETCH_ASSOC);
                if ($mgrFound) {
                    $managerId = (int)$mgrFound['user_id'];
                } else {
                    $mgrStmt2 = $db->prepare("SELECT user_id FROM employees WHERE employee_code = :m_code LIMIT 1");
                    $mgrStmt2->execute([':m_code' => $mgrInput]);
                    $mgrFound2 = $mgrStmt2->fetch(PDO::FETCH_ASSOC);
                    if ($mgrFound2) {
                        $managerId = (int)$mgrFound2['user_id'];
                    }
                }
            }

            // Bank details
            $bankHolder = trim($rowData['account_holder_name'] ?? '') ?: $name;
            $bankName = trim($rowData['bank_name'] ?? '') ?: null;
            $accNumber = trim($rowData['account_number'] ?? '') ?: null;
            $ifsc = strtoupper(trim($rowData['ifsc_code'] ?? '')) ?: null;
            $branch = trim($rowData['branch_name'] ?? '') ?: null;
            $rawAccType = strtolower(trim($rowData['account_type'] ?? 'savings'));
            $accType = (strpos($rawAccType, 'current') !== false) ? 'Current' : 'Savings';
            $hasBankData = (!empty($bankName) || !empty($accNumber) || !empty($ifsc));

            // Password
            $tempPass = trim($rowData['temp_password'] ?? '');
            $hashPass = password_hash(!empty($tempPass) ? $tempPass : 'Raptor@2026', PASSWORD_BCRYPT);
            $forceReset = isset($rowData['force_password_reset']) ? (int)$rowData['force_password_reset'] : 1;

            // Check if user already exists
            $stmt = $db->prepare("SELECT user_id FROM users WHERE LOWER(email) = :email");
            $stmt->execute([':email' => $email]);
            $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$existingUser && !empty($empCode)) {
                $stmt2 = $db->prepare("SELECT user_id FROM employees WHERE employee_code = :code");
                $stmt2->execute([':code' => $empCode]);
                $existingUser = $stmt2->fetch(PDO::FETCH_ASSOC);
            }

            if ($existingUser) {
                // EXISTING USER FOUND
                if ($duplicateStrategy === 'skip') {
                    $skipped++;
                    continue;
                }

                $uid = (int)$existingUser['user_id'];

                // Update users table
                if (!empty($tempPass)) {
                    $upUser = $db->prepare("UPDATE users SET name = :name, status = :status, role_id = :role_id, password = :pass, force_password_reset = :force_reset WHERE user_id = :uid");
                    $upUser->execute([
                        ':name' => $name,
                        ':status' => $status,
                        ':role_id' => $roleId,
                        ':pass' => $hashPass,
                        ':force_reset' => $forceReset,
                        ':uid' => $uid
                    ]);
                } else {
                    $upUser = $db->prepare("UPDATE users SET name = :name, status = :status, role_id = :role_id, force_password_reset = :force_reset WHERE user_id = :uid");
                    $upUser->execute([
                        ':name' => $name,
                        ':status' => $status,
                        ':role_id' => $roleId,
                        ':force_reset' => $forceReset,
                        ':uid' => $uid
                    ]);
                }

                // Check employee record
                $empCheck = $db->prepare("SELECT employee_id FROM employees WHERE user_id = :uid");
                $empCheck->execute([':uid' => $uid]);
                $existingEmp = $empCheck->fetch(PDO::FETCH_ASSOC);

                if ($existingEmp) {
                    $empId = (int)$existingEmp['employee_id'];
                    $upEmp = $db->prepare("
                        UPDATE employees SET
                            employee_code = :code,
                            department = :dept,
                            job_title = :job,
                            hire_date = :hire_date,
                            date_of_joining = :doj,
                            phone_number = :phone,
                            salary = :salary,
                            date_of_birth = :dob,
                            employment_type = :emp_type,
                            work_location = :work_loc,
                            bio = :bio,
                            emergency_contact = :emergency,
                            pan_number = :pan,
                            aadhaar_number = :aadhaar,
                            uan = :uan,
                            pf_applicable = :pf,
                            esic_number = :esic,
                            pay_grade = :grade,
                            reporting_manager_id = :mgr
                        WHERE employee_id = :eid
                    ");
                    $upEmp->execute([
                        ':code' => $empCode,
                        ':dept' => $dept,
                        ':job' => $jobTitle,
                        ':hire_date' => $doj,
                        ':doj' => $doj,
                        ':phone' => $phone ?: null,
                        ':salary' => $salary,
                        ':dob' => $dob,
                        ':emp_type' => $empType,
                        ':work_loc' => $workLoc,
                        ':bio' => $bio,
                        ':emergency' => $emergencyContact,
                        ':pan' => $pan,
                        ':aadhaar' => $aadhaar,
                        ':uan' => $uan,
                        ':pf' => $pfApplicable,
                        ':esic' => $esic,
                        ':grade' => $payGrade,
                        ':mgr' => $managerId,
                        ':eid' => $empId
                    ]);
                } else {
                    $insEmp = $db->prepare("
                        INSERT INTO employees (user_id, employee_code, department, job_title, hire_date, date_of_joining, phone_number, salary, date_of_birth, employment_type, work_location, bio, emergency_contact, pan_number, aadhaar_number, uan, pf_applicable, esic_number, pay_grade, reporting_manager_id)
                        VALUES (:uid, :code, :dept, :job, :hire_date, :doj, :phone, :salary, :dob, :emp_type, :work_loc, :bio, :emergency, :pan, :aadhaar, :uan, :pf, :esic, :grade, :mgr)
                    ");
                    $insEmp->execute([
                        ':uid' => $uid,
                        ':code' => $empCode,
                        ':dept' => $dept,
                        ':job' => $jobTitle,
                        ':hire_date' => $doj,
                        ':doj' => $doj,
                        ':phone' => $phone ?: null,
                        ':salary' => $salary,
                        ':dob' => $dob,
                        ':emp_type' => $empType,
                        ':work_loc' => $workLoc,
                        ':bio' => $bio,
                        ':emergency' => $emergencyContact,
                        ':pan' => $pan,
                        ':aadhaar' => $aadhaar,
                        ':uan' => $uan,
                        ':pf' => $pfApplicable,
                        ':esic' => $esic,
                        ':grade' => $payGrade,
                        ':mgr' => $managerId
                    ]);
                    $empId = (int)$db->lastInsertId();
                }

                // Update or Insert Bank Account
                if ($empId) {
                    $bankStmt = $db->prepare("SELECT bank_account_id FROM bank_accounts WHERE employee_id = :eid");
                    $bankStmt->execute([':eid' => $empId]);
                    $existingBank = $bankStmt->fetch(PDO::FETCH_ASSOC);

                    if ($existingBank) {
                        $upBank = $db->prepare("
                            UPDATE bank_accounts SET
                                account_holder_name = :holder,
                                bank_name = :bank,
                                account_number = :acc,
                                ifsc_code = :ifsc,
                                branch_name = :branch,
                                account_type = :type
                            WHERE employee_id = :eid
                        ");
                        $upBank->execute([
                            ':holder' => $bankHolder,
                            ':bank' => $bankName ?? '',
                            ':acc' => $accNumber ?? '',
                            ':ifsc' => $ifsc ?? '',
                            ':branch' => $branch ?? '',
                            ':type' => $accType,
                            ':eid' => $empId
                        ]);
                    } elseif ($hasBankData) {
                        $insBank = $db->prepare("
                            INSERT INTO bank_accounts (employee_id, account_holder_name, bank_name, account_number, ifsc_code, branch_name, account_type)
                            VALUES (:eid, :holder, :bank, :acc, :ifsc, :branch, :type)
                        ");
                        $insBank->execute([
                            ':eid' => $empId,
                            ':holder' => $bankHolder,
                            ':bank' => $bankName ?? '',
                            ':acc' => $accNumber ?? '',
                            ':ifsc' => $ifsc ?? '',
                            ':branch' => $branch ?? '',
                            ':type' => $accType
                        ]);
                    }
                }
                $updated++;
            } else {
                // NEW USER INSERTION
                $insUser = $db->prepare("
                    INSERT INTO users (role_id, name, email, password, status, force_password_reset)
                    VALUES (:role_id, :name, :email, :pass, :status, :force_reset)
                ");
                if ($insUser->execute([
                    ':role_id' => $roleId,
                    ':name' => $name,
                    ':email' => $email,
                    ':pass' => $hashPass,
                    ':status' => $status,
                    ':force_reset' => $forceReset
                ])) {
                    $newUid = (int)$db->lastInsertId();

                    $insEmp = $db->prepare("
                        INSERT INTO employees (user_id, employee_code, department, job_title, hire_date, date_of_joining, phone_number, salary, date_of_birth, employment_type, work_location, bio, emergency_contact, pan_number, aadhaar_number, uan, pf_applicable, esic_number, pay_grade, reporting_manager_id)
                        VALUES (:uid, :code, :dept, :job, :hire_date, :doj, :phone, :salary, :dob, :emp_type, :work_loc, :bio, :emergency, :pan, :aadhaar, :uan, :pf, :esic, :grade, :mgr)
                    ");
                    $insEmp->execute([
                        ':uid' => $newUid,
                        ':code' => $empCode,
                        ':dept' => $dept,
                        ':job' => $jobTitle,
                        ':hire_date' => $doj,
                        ':doj' => $doj,
                        ':phone' => $phone ?: null,
                        ':salary' => $salary,
                        ':dob' => $dob,
                        ':emp_type' => $empType,
                        ':work_loc' => $workLoc,
                        ':bio' => $bio,
                        ':emergency' => $emergencyContact,
                        ':pan' => $pan,
                        ':aadhaar' => $aadhaar,
                        ':uan' => $uan,
                        ':pf' => $pfApplicable,
                        ':esic' => $esic,
                        ':grade' => $payGrade,
                        ':mgr' => $managerId
                    ]);
                    $newEmpId = (int)$db->lastInsertId();

                    if ($newEmpId && $hasBankData) {
                        $insBank = $db->prepare("
                            INSERT INTO bank_accounts (employee_id, account_holder_name, bank_name, account_number, ifsc_code, branch_name, account_type)
                            VALUES (:eid, :holder, :bank, :acc, :ifsc, :branch, :type)
                        ");
                        $insBank->execute([
                            ':eid' => $newEmpId,
                            ':holder' => $bankHolder,
                            ':bank' => $bankName ?? '',
                            ':acc' => $accNumber ?? '',
                            ':ifsc' => $ifsc ?? '',
                            ':branch' => $branch ?? '',
                            ':type' => $accType
                        ]);
                    }
                    $imported++;
                } else {
                    $failed++;
                }
            }
        }
        fclose($handle);

        $_SESSION['user_success'] = "Bulk Upload Completed: {$imported} new employee(s) added, {$updated} existing record(s) updated with full Personal, Bank, and Payroll details.";
        if ($failed > 0) {
            $_SESSION['user_warning'] = "{$failed} row(s) had invalid data or missing emails and were skipped.";
        }
        $this->redirect('index.php?route=users/index');
    }
}
