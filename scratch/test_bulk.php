<?php
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Controller.php';
require_once __DIR__ . '/../app/core/PermissionService.php';
require_once __DIR__ . '/../app/services/BulkImportService.php';
require_once __DIR__ . '/../app/controllers/UsersController.php';

session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'admin';

$csvData = "Employee_Code,First_Name,Last_Name,Email,Phone_Number,Department,Job_Title,Reporting_Manager_Email,Date_of_Joining,Employment_Type,Status,Work_Location,Date_of_Birth,Bio,Emergency_Contact,Account_Holder_Name,Bank_Name,Account_Number,IFSC_Code,Branch_Name,Account_Type,PAN_Number,Aadhaar_Number,UAN,ESIC_Number,Salary,Pay_Grade,PF_Applicable,Role_in_System,Temp_Password\n" .
           "EMP008,srinivas,Nelapatla,test.srinivas.n@example.com,8919896777,Sales,Sales Associate,manager@example.com,05-08-2026,Full-time,active,Remote,11-02-1999,Experienced,sai - 8790463245,,,,,,,,,,,,,,Sales Associate,Raptor@12345";

$tmpPath = sys_get_temp_dir() . '/test_upload.csv';
file_put_contents($tmpPath, $csvData);

$_FILES['csv_file'] = [
    'name' => 'employee_bulk_upload_template (1).csv',
    'type' => 'text/csv',
    'tmp_name' => $tmpPath,
    'error' => 0,
    'size' => strlen($csvData)
];
$_SERVER['REQUEST_METHOD'] = 'POST';

class TestUsersController extends UsersController {
    public function jsonError(string $message, int $status = 400, $errors = null) {
        echo "JSON_ERROR ($status): $message\n";
        if ($errors) echo "ERRORS: " . print_r($errors, true) . "\n";
        exit();
    }
    public function jsonSuccess($data = [], $message = null) {
        echo "JSON_SUCCESS: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
        exit();
    }
}

$controller = new TestUsersController();
$controller->bulkUpload();
