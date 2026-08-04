<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Controller.php';
require_once __DIR__ . '/../app/core/PermissionService.php';
require_once __DIR__ . '/../app/services/BulkImportService.php';
require_once __DIR__ . '/../app/controllers/UsersController.php';

session_start();
$_SESSION['user_id'] = 20;
$_SESSION['user_role'] = 'admin';
$_SESSION['csrf_token'] = 'test_token';

$csvData = "Employee_Code,First_Name,Last_Name,Email,Phone_Number,Department,Job_Title,Reporting_Manager_Email,Date_of_Joining,Employment_Type,Status,Work_Location,Date_of_Birth,Bio,Emergency_Contact,Account_Holder_Name,Bank_Name,Account_Number,IFSC_Code,Branch_Name,Account_Type,PAN_Number,Aadhaar_Number,UAN,ESIC_Number,Salary,Pay_Grade,PF_Applicable,Role_in_System,Temp_Password\n" .
           "EMP008,srinivas,Nelapatla,test.srinivas.n@example.com,8919896777,Sales,Sales Associate,manager@example.com,05-08-2026,Full-time,active,Remote,11-02-1999,Experienced,sai - 8790463245,srinivas,Axis Bank,123456789012,UTIB0001234,Ameerpet Branch,Savings,ABCDE1234F,123456789012,123456789012,12345678901234567,50000,Band A,1,Sales Associate,Raptor@12345\n" .
           "EMP009,Mundlamuri,Mrudula,mrudula.n@example.com,9876543210,Sales,Sales Associate,manager@example.com,05-08-2026,Full-time,active,Remote,16-02-1997,Experienced,sai - 8790463245,Mrudula,Axis Bank,123456789013,UTIB0001234,Ameerpet Branch,Savings,ABCDE1235F,123456789013,123456789013,12345678901234568,50000,Band A,1,Sales Associate,Raptor@12345";

$tmpPath = sys_get_temp_dir() . '/test_upload.csv';
file_put_contents($tmpPath, $csvData);

$_FILES['csv_file'] = [
    'name' => 'employee_bulk_upload_template.csv',
    'type' => 'text/csv',
    'tmp_name' => $tmpPath,
    'error' => 0,
    'size' => strlen($csvData)
];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['csrf_token'] = 'test_token';

class TestUsersController extends UsersController {
    public function json($payload, int $status = 200) {
        file_put_contents(__DIR__ . '/res.json', json_encode($payload, JSON_PRETTY_PRINT));
        echo "WRITTEN_OK\n";
        exit();
    }
}

$controller = new TestUsersController();
$controller->bulkUpload();
