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
     * Configuration for bulk employee import fields and validation.
     */
    private function getEmployeeImportConfig(): array {
        return [
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
    }

    /**
     * Callbacks for bulk employee validation and duplicate detection.
     */
    private function getEmployeeImportCallbacks(): array {
        $db = Database::getInstance()->getConnection();
        return [
            'validate_field_department' => function($val) {
                return null;
            },
            'validate_field_date_of_joining' => function($val) {
                return null;
            },
            'validate_field_reporting_manager_email' => function($val) {
                return null;
            },
            'validate_row' => function($mappedRow) {
                $errors = [];
                $role = strtolower(trim($mappedRow['role_in_system'] ?? ''));
                $jobTitle = strtolower(trim($mappedRow['job_title'] ?? ''));
                $empType = strtolower(trim($mappedRow['employment_type'] ?? ''));

                $isIntern = (strpos($role, 'intern') !== false) || 
                            (strpos($jobTitle, 'intern') !== false) || 
                            (strpos($empType, 'intern') !== false);

                $rawSalary = trim($mappedRow['salary'] ?? '');
                $cleanSalary = preg_replace('/[^0-9.]/', '', $rawSalary);
                $salaryVal = ($cleanSalary !== '') ? (float)$cleanSalary : 0.0;

                if ($isIntern && $salaryVal <= 0) {
                    $errors['salary'] = "Intern role with a salary of 0 cannot be processed.";
                }

                return $errors;
            },
            'db_duplicate_check' => function($mappedRow) use ($db) {
                // Check email duplicate
                $email = strtolower(trim($mappedRow['email'] ?? ''));
                if (!empty($email)) {
                    $stmt = $db->prepare("SELECT user_id, name FROM users WHERE LOWER(email) = :email LIMIT 1");
                    $stmt->execute([':email' => $email]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($user) {
                        return ['user_id' => $user['user_id'], 'message' => "Matches existing user '{$user['name']}' ({$email})"];
                    }
                }

                // Check employee_code duplicate
                $code = strtoupper(trim($mappedRow['employee_code'] ?? ''));
                if (!empty($code)) {
                    $stmt = $db->prepare("SELECT user_id, employee_code FROM employees WHERE UPPER(employee_code) = :code LIMIT 1");
                    $stmt->execute([':code' => $code]);
                    $emp = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($emp) {
                        return ['user_id' => $emp['user_id'], 'message' => "Matches existing employee code '{$emp['employee_code']}'"];
                    }
                }
                return null;
            }
        ];
    }

    /**
     * Import callback mapping all 30 employee CSV fields to User/Employee/Bank tables.
     */
    private function getEmployeeImportCallback(): callable {
        $db = Database::getInstance()->getConnection();
        $userModel = $this->model('User');

        // Preload roles
        $rolesStmt = $db->query("SELECT role_id, LOWER(role_name) AS rname FROM roles");
        $rolesMap = [];
        while ($r = $rolesStmt->fetch(PDO::FETCH_ASSOC)) {
            $rolesMap[$r['rname']] = (int)$r['role_id'];
        }
        $defaultRoleId = $rolesMap['employee'] ?? ($rolesMap['sales_person'] ?? 10);

        return function($row, $action, $duplicateStrategy) use ($db, $userModel, $rolesMap, $defaultRoleId) {
            // Guard: Intern role with salary 0 must not be processed
            $rawRole = strtolower(trim($row['role_in_system'] ?? ''));
            $rawJobTitle = strtolower(trim($row['job_title'] ?? ''));
            $rawEmpType = strtolower(trim($row['employment_type'] ?? ''));
            $isIntern = (strpos($rawRole, 'intern') !== false) || 
                        (strpos($rawJobTitle, 'intern') !== false) || 
                        (strpos($rawEmpType, 'intern') !== false);
            $cleanSalary = preg_replace('/[^0-9.]/', '', $row['salary'] ?? '');
            $salaryValNum = ($cleanSalary !== '') ? (float)$cleanSalary : 0.0;
            if ($isIntern && $salaryValNum <= 0) {
                throw new Exception("Intern role with a salary of 0 cannot be processed.");
            }

            // 1. Resolve role_id
            $rawRole = strtolower(trim($row['role_in_system'] ?? ''));
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
                $name = 'Imported Employee';
            }

            // 3. Password
            $tempPassword = !empty(trim($row['temp_password'] ?? '')) ? trim($row['temp_password']) : 'Raptor@2026';

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
                    $mgrStmt2 = $db->prepare("SELECT user_id FROM employees WHERE UPPER(employee_code) = :code LIMIT 1");
                    $mgrStmt2->execute([':code' => strtoupper($mgrInput)]);
                    $mgr2 = $mgrStmt2->fetch(PDO::FETCH_ASSOC);
                    if ($mgr2) {
                        $reportingManagerId = (int)$mgr2['user_id'];
                    }
                }
            }

            // 5. Clean phone number and handle Excel scientific notation
            $phone = trim($row['phone_number'] ?? '');
            if (preg_match('/^[+-]?[0-9]*\.?[0-9]+[eE][+-]?[0-9]+$/', $phone)) {
                $phone = sprintf('%.0f', (float)$phone);
            }
            $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
            if (strlen($cleanPhone) === 12 && substr($cleanPhone, 0, 2) === '91') {
                $cleanPhone = substr($cleanPhone, 2);
            }

            // 6. Clean numeric and string fields
            $salaryVal = preg_replace('/[^0-9.]/', '', $row['salary'] ?? '');
            $accNumber = trim($row['account_number'] ?? '');
            if (preg_match('/^[+-]?[0-9]*\.?[0-9]+[eE][+-]?[0-9]+$/', $accNumber)) {
                $accNumber = sprintf('%.0f', (float)$accNumber);
            }
            $aadhaar = preg_replace('/[^0-9]/', '', $row['aadhaar_number'] ?? '') ?: null;
            $uan = preg_replace('/[^0-9]/', '', $row['uan'] ?? '') ?: null;

            // 7. Dates
            $doj = !empty($row['date_of_joining']) ? $row['date_of_joining'] : date('Y-m-d');
            $dob = !empty($row['date_of_birth']) ? $row['date_of_birth'] : null;

            // 8. Employment type & Work location
            $rawEmpType = strtolower(trim($row['employment_type'] ?? ''));
            $empType = 'Full-time';
            if (strpos($rawEmpType, 'part') !== false) $empType = 'Part-time';
            elseif (strpos($rawEmpType, 'contract') !== false) $empType = 'Contract';
            elseif (strpos($rawEmpType, 'intern') !== false) $empType = 'Intern';

            $rawLoc = strtolower(trim($row['work_location'] ?? ''));
            $workLoc = 'Office';
            if (strpos($rawLoc, 'remote') !== false) $workLoc = 'Remote';
            elseif (strpos($rawLoc, 'hybrid') !== false) $workLoc = 'Hybrid';

            // 9. Status
            $rawStatus = strtolower(trim($row['status'] ?? 'active'));
            $userStatus = in_array($rawStatus, ['active', 'inactive', 'suspended'], true) ? $rawStatus : ($rawStatus === 'terminated' ? 'inactive' : 'active');

            // 10. Bank Details
            $bankName = trim($row['bank_name'] ?? '');
            $rawAccType = strtolower(trim($row['account_type'] ?? 'savings'));
            $accType = (strpos($rawAccType, 'current') !== false) ? 'Current' : 'Savings';

            $data = [
                'role_id' => $role_id,
                'name' => $name,
                'email' => strtolower(trim($row['email'])),
                'password' => $tempPassword,
                'status' => $userStatus,
                'employee_code' => trim($row['employee_code'] ?? ''),
                'phone_number' => $cleanPhone ?: null,
                'salary' => is_numeric($salaryVal) ? (float)$salaryVal : null,
                'date_of_joining' => $doj,
                'date_of_birth' => $dob,
                'job_title' => trim($row['job_title'] ?? '') ?: 'Staff',
                'department' => trim($row['department'] ?? '') ?: 'Sales',
                'employment_type' => $empType,
                'work_location' => $workLoc,
                'bio' => trim($row['bio'] ?? '') ?: null,
                'emergency_contact' => trim($row['emergency_contact'] ?? '') ?: null,
                'pan_number' => !empty($row['pan_number']) ? strtoupper(trim($row['pan_number'])) : null,
                'aadhaar_number' => $aadhaar,
                'uan' => $uan,
                'pf_applicable' => in_array(strtolower(trim($row['pf_applicable'] ?? '')), ['1', 'yes', 'true', 'y'], true) ? 1 : 0,
                'esic_number' => trim($row['esic_number'] ?? '') ?: null,
                'pay_grade' => trim($row['pay_grade'] ?? '') ?: null,
                'force_password_reset' => 1,
                'reporting_manager_id' => $reportingManagerId,
                
                // Bank Details
                'account_holder_name' => trim($row['account_holder_name'] ?? '') ?: $name,
                'bank_name' => $bankName ?: null,
                'account_number' => $accNumber ?: null,
                'ifsc_code' => !empty($row['ifsc_code']) ? strtoupper(trim($row['ifsc_code'])) : null,
                'branch_name' => trim($row['branch_name'] ?? '') ?: null,
                'account_type' => $accType
            ];

            if ($action === 'duplicate') {
                if ($duplicateStrategy === 'skip') {
                    return true;
                }
                if ($duplicateStrategy === 'update') {
                    // Find matching user ID
                    $stmt = $db->prepare("SELECT u.user_id, e.employee_code, e.hire_date FROM users u LEFT JOIN employees e ON u.user_id = e.user_id WHERE LOWER(u.email) = :email LIMIT 1");
                    $stmt->execute([':email' => $data['email']]);
                    $u = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$u && !empty($data['employee_code'])) {
                        $stmt = $db->prepare("SELECT u.user_id, e.employee_code, e.hire_date FROM employees e JOIN users u ON e.user_id = u.user_id WHERE UPPER(e.employee_code) = :code LIMIT 1");
                        $stmt->execute([':code' => strtoupper($data['employee_code'])]);
                        $u = $stmt->fetch(PDO::FETCH_ASSOC);
                    }

                    if ($u) {
                        $data['user_id'] = (int)$u['user_id'];
                        $data['profile_photo'] = null;
                        if (empty($data['employee_code']) && !empty($u['employee_code'])) {
                            $data['employee_code'] = $u['employee_code'];
                        }
                        if (empty($row['date_of_joining']) && !empty($u['hire_date'])) {
                            $data['date_of_joining'] = $u['hire_date'];
                        }
                        return $userModel->updateUser($data);
                    }
                }
            }

            // Create new record
            if (empty($data['employee_code'])) {
                $data['employee_code'] = 'EMP' . rand(10000, 99999);
            }
            return $userModel->addUser($data);
        };
    }

    /**
     * Parse and validate uploaded CSV for interactive preview modal.
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
            
            $employeeConfig = $this->getEmployeeImportConfig();
            $callbacks = $this->getEmployeeImportCallbacks();

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
     * Execute the transactional import batch from the interactive preview modal.
     */
    public function bulkImport() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('Invalid request method.', 405);
        }

        $input = $this->jsonInput();
        $strategy = $input['duplicate_strategy'] ?? 'update';
        if (!in_array($strategy, ['skip', 'update', 'create'], true)) {
            $this->jsonError('Invalid duplicate resolution strategy.');
        }

        if (empty($_SESSION['bulk_import_rows'])) {
            $this->jsonError('No uploaded data found in session. Please upload first.');
        }

        $rows = $_SESSION['bulk_import_rows'];
        $meta = $_SESSION['bulk_import_metadata'] ?? ['filename' => 'bulk_upload.csv'];

        // Only process rows that were successfully validated or duplicates to resolve
        $validRows = array_filter($rows, function($r) {
            return $r['status'] === 'valid' || $r['action'] === 'duplicate';
        });

        if (empty($validRows)) {
            $this->jsonError('No valid rows found to import.');
        }

        $importer = new BulkImportService();
        $importCallback = $this->getEmployeeImportCallback();

        try {
            $logMetadata = [
                'user_id' => $_SESSION['user_id'] ?? 0,
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
            $d = $err['data'] ?? [];
            fputcsv($output, [
                $err['row'] ?? '',
                $err['reason'] ?? '',
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

    /**
     * Direct CSV import fallback handler (for traditional form submit).
     */
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
        if (!in_array($duplicateStrategy, ['update', 'skip', 'create'], true)) {
            $duplicateStrategy = 'update';
        }

        try {
            $importer = new BulkImportService();
            $rows = $importer->parseCsv($file['tmp_name']);
            
            $employeeConfig = $this->getEmployeeImportConfig();
            $callbacks = $this->getEmployeeImportCallbacks();
            
            $results = $importer->validate($rows, $employeeConfig, $callbacks);
            
            $validRows = array_filter($results['rows'], function($r) {
                return $r['status'] === 'valid' || $r['action'] === 'duplicate';
            });

            if (empty($validRows)) {
                $errList = [];
                foreach ($results['rows'] as $r) {
                    if (!empty($r['errors'])) {
                        foreach ($r['errors'] as $f => $m) {
                            $errList[] = "Row {$r['index']} ($f): $m";
                        }
                    }
                }
                $_SESSION['user_error'] = "No valid employee rows found in CSV. " . implode('; ', array_slice($errList, 0, 3));
                $this->redirect('index.php?route=users/index');
                return;
            }

            $logMetadata = [
                'user_id' => $_SESSION['user_id'] ?? 0,
                'module' => 'employees',
                'filename' => htmlspecialchars($file['name'])
            ];

            $importCallback = $this->getEmployeeImportCallback();
            $importResults = $importer->import($validRows, $duplicateStrategy, $importCallback, $logMetadata);

            $_SESSION['bulk_import_errors'] = $importResults['errors'];

            $imported = $importResults['imported'];
            $skipped = $importResults['skipped'];
            $failed = $importResults['failed'] + $results['error_count'];

            if ($imported > 0) {
                $verb = ($duplicateStrategy === 'update') ? 'added/updated' : 'added';
                $_SESSION['user_success'] = "Bulk Upload Successful: {$imported} employee(s) {$verb} with full Personal, Bank, and Payroll details.";
            }
            if ($skipped > 0) {
                $_SESSION['user_warning'] = "{$skipped} existing record(s) were skipped (Duplicate Strategy: Skip).";
            }
            if ($failed > 0) {
                $failedReasons = [];
                foreach (array_slice($importResults['errors'], 0, 3) as $e) {
                    $failedReasons[] = "Row {$e['row']}: {$e['reason']}";
                }
                foreach (array_slice($results['rows'], 0, 3) as $r) {
                    if ($r['status'] === 'error') {
                        foreach ($r['errors'] as $f => $m) {
                            $failedReasons[] = "Row {$r['index']} ($f): $m";
                        }
                    }
                }
                $_SESSION['user_error'] = "{$failed} row(s) had errors and were not imported. " . implode('; ', array_slice($failedReasons, 0, 3));
            }
        } catch (Exception $e) {
            $_SESSION['user_error'] = "Bulk import failed: " . $e->getMessage();
        }

        $this->redirect('index.php?route=users/index');
    }
}
