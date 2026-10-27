<?php
/**
 * Axiora Global Solutions — Salary Structure & Employee Importer
 * Imports all 43 employees from "Salary structure and format/Payslip_PF.xlsx"
 * into users, employees, bank_accounts, salary_structures, and seeds September 2026 payroll run.
 */

require_once __DIR__ . '/../app/config/config.php';
require_once APPROOT . '/core/Database.php';

$excelPath = dirname(__DIR__) . '/Salary structure and format/Payslip_PF.xlsx';
if (!file_exists($excelPath)) {
    die("Error: File not found: {$excelPath}\n");
}

// Ensure work_location can hold 'Hyderabad' and payroll_details has other_deductions
$db = Database::getInstance()->getConnection();
try {
    $db->exec("ALTER TABLE employees MODIFY COLUMN work_location VARCHAR(100) DEFAULT 'Hyderabad'");
} catch (Exception $e) {}
try {
    $db->exec("ALTER TABLE payroll_details ADD COLUMN other_deductions DECIMAL(10,2) DEFAULT 0.00 AFTER tds");
} catch (Exception $e) {}

// Load extracted JSON directly if present, else fallback to python extraction
$jsonAssetPath = __DIR__ . '/axiora_employees_data.json';
if (file_exists($jsonAssetPath)) {
    $rows = json_decode(file_get_contents($jsonAssetPath), true);
} else {
    $pyScript = "
import openpyxl, json, sys
sys.stdout.reconfigure(encoding='utf-8')
wb = openpyxl.load_workbook(r'{$excelPath}', data_only=True)
sheet = wb.active
headers = [sheet.cell(1, c).value for c in range(1, sheet.max_column+1)]
rows = []
for r in range(2, sheet.max_row+1):
    row = {headers[c-1]: sheet.cell(r, c).value for c in range(1, sheet.max_column+1)}
    if row.get('EmpID'):
        rows.append(row)
print(json.dumps(rows, default=str))
";

    $tmpPy = __DIR__ . '/_temp_extract.py';
    file_put_contents($tmpPy, $pyScript);
    $output = shell_exec("python \"" . $tmpPy . "\"");
    @unlink($tmpPy);
    $rows = json_decode($output, true);
}

if (empty($rows)) {
    die("Error: Failed to parse rows from Excel.\n");
}

echo "Found " . count($rows) . " employee rows in Payslip_PF.xlsx\n";

// Get employee role_id
$stmtRole = $db->query("SELECT role_id FROM roles WHERE role_name IN ('employee', 'staff') ORDER BY role_id ASC LIMIT 1");
$employeeRoleId = (int)$stmtRole->fetchColumn();
if (!$employeeRoleId) {
    $employeeRoleId = 3;
}

// Ensure September 2026 payroll run exists
$stmtRun = $db->prepare("SELECT payroll_run_id FROM payroll_runs WHERE month_year = '2026-09' LIMIT 1");
$stmtRun->execute();
$runId = (int)$stmtRun->fetchColumn();

if (!$runId) {
    $db->prepare("INSERT INTO payroll_runs (month_year, status, created_by, approved_by, released_by, created_at)
                  VALUES ('2026-09', 'released', 1, 1, 1, '2026-10-01 10:00:00')")->execute();
    $runId = (int)$db->lastInsertId();
    echo "Created Payroll Run for 2026-09 (Run ID: {$runId})\n";
} else {
    echo "Using existing Payroll Run for 2026-09 (Run ID: {$runId})\n";
    $db->prepare("UPDATE payroll_runs SET status = 'released' WHERE payroll_run_id = :id")->execute([':id' => $runId]);
}

$importedCount = 0;

foreach ($rows as $r) {
    $empCode = trim($r['EmpID'] ?? '');
    $empName = trim($r['Emp Name'] ?? '');
    if (empty($empCode) || empty($empName)) continue;

    $rawDoj = trim($r['DOJ'] ?? '');
    $doj = date('Y-m-d', strtotime($rawDoj ?: '2026-03-02'));
    $desig = trim($r['Desig'] ?? 'Associate Software Engineer');
    $dept = trim($r['Dep'] ?? 'Development');
    $location = trim($r['Loc'] ?? 'Hyderabad');
    $bankAc = trim((string)($r['Bank A/C'] ?? ''));
    $ifsc = trim((string)($r['IFSC'] ?? ''));
    $uan = trim((string)($r['UAN No'] ?? ''));

    $abasic = round((float)($r['Abasic'] ?? 0));
    $basic = round((float)($r['Basic'] ?? $abasic));
    $ahra = round((float)($r['AHRA'] ?? 0));
    $hra = round((float)($r['HRA'] ?? $ahra));
    $asa = round((float)($r['ASA'] ?? 1600));
    $special = round((float)($r['Special Al'] ?? $asa));
    $aspec = round((float)($r['Spec Allo'] ?? 0));
    $other = round((float)($r['Other Al'] ?? $aspec));
    $age = round((float)($r['AGE'] ?? ($abasic + $ahra + $asa + $aspec)));
    $gross = round((float)($r['Gross Ea'] ?? ($basic + $hra + $special + $other)));

    $epf = round((float)($r['EPF'] ?? 1800));
    $pt = round((float)($r['PT'] ?? 200));
    $tdsVal = trim((string)($r['TDS'] ?? '0'));
    $tds = (is_numeric($tdsVal)) ? round((float)$tdsVal) : 0;
    $ded = round((float)($r['Ded'] ?? ($epf + $pt + $tds)));

    $erPf = round((float)($r['ErPF'] ?? $epf));
    $grat = round((float)($r['Grat'] ?? round($abasic * 0.0481)));
    $ben = round((float)($r['Ben'] ?? ($erPf + $grat)));
    $ctc = round((float)($r['CTC'] ?? ($age + $ben)));
    $netPay = round((float)($r['NET PAY'] ?? ($gross - $ded)));

    $paidDays = (int)($r['Paid Days'] ?? 30);
    $lopDays = (int)($r['LOP Days'] ?? 0);

    // 1. Create or Find User
    // Clean name for email
    $emailPrefix = strtolower(preg_replace('/[^a-z0-9]+/i', '.', $empName));
    $emailPrefix = trim($emailPrefix, '.');
    $email = $emailPrefix . '@axioraglobalsolutions.com';

    $stmtU = $db->prepare("SELECT user_id FROM users WHERE email = :email LIMIT 1");
    $stmtU->execute([':email' => $email]);
    $userId = (int)$stmtU->fetchColumn();

    if (!$userId) {
        // Also check by employee_code
        $stmtByCode = $db->prepare("SELECT u.user_id FROM employees e JOIN users u ON e.user_id = u.user_id WHERE e.employee_code = :code LIMIT 1");
        $stmtByCode->execute([':code' => $empCode]);
        $userId = (int)$stmtByCode->fetchColumn();
    }

    if (!$userId) {
        // Also check by normalized name match
        $stmtByName = $db->prepare("SELECT user_id FROM users WHERE LOWER(REPLACE(name, ' ', '')) = LOWER(REPLACE(:name, ' ', '')) LIMIT 1");
        $stmtByName->execute([':name' => $empName]);
        $userId = (int)$stmtByName->fetchColumn();
    }

    if (!$userId) {
        $hash = password_hash('Password@2026', PASSWORD_DEFAULT);
        $stmtInsUser = $db->prepare("
            INSERT INTO users (role_id, name, email, password, status, created_at)
            VALUES (:role_id, :name, :email, :pwd, 'active', NOW())
        ");
        $stmtInsUser->execute([
            ':role_id' => $employeeRoleId,
            ':name' => $empName,
            ':email' => $email,
            ':pwd' => $hash
        ]);
        $userId = (int)$db->lastInsertId();
    } else {
        $db->prepare("UPDATE users SET name = :name, status = 'active' WHERE user_id = :uid")
           ->execute([':name' => $empName, ':uid' => $userId]);
    }

    // 2. Create or Update Employee
    $stmtEmp = $db->prepare("SELECT employee_id FROM employees WHERE employee_code = :code LIMIT 1");
    $stmtEmp->execute([':code' => $empCode]);
    $empId = (int)$stmtEmp->fetchColumn();

    if (!$empId) {
        $stmtEmp2 = $db->prepare("SELECT employee_id FROM employees WHERE user_id = :uid LIMIT 1");
        $stmtEmp2->execute([':uid' => $userId]);
        $empId = (int)$stmtEmp2->fetchColumn();
    }

    if (!$empId) {
        $stmtInsEmp = $db->prepare("
            INSERT INTO employees 
            (user_id, employee_code, department, job_title, salary, date_of_joining, hire_date, work_location, uan, status, pf_applicable)
            VALUES 
            (:uid, :code, :dept, :desig, :salary, :doj, :hire_date, :loc, :uan, 'active', 1)
        ");
        $stmtInsEmp->execute([
            ':uid' => $userId,
            ':code' => $empCode,
            ':dept' => $dept,
            ':desig' => $desig,
            ':salary' => $ctc,
            ':doj' => $doj,
            ':hire_date' => $doj,
            ':loc' => $location,
            ':uan' => $uan
        ]);
        $empId = (int)$db->lastInsertId();
    } else {
        $db->prepare("
            UPDATE employees SET 
                employee_code = :code,
                department = :dept,
                job_title = :desig,
                salary = :salary,
                date_of_joining = :doj,
                hire_date = :hire_date,
                work_location = :loc,
                uan = :uan,
                status = 'active'
            WHERE employee_id = :eid
        ")->execute([
            ':code' => $empCode,
            ':dept' => $dept,
            ':desig' => $desig,
            ':salary' => $ctc,
            ':doj' => $doj,
            ':hire_date' => $doj,
            ':loc' => $location,
            ':uan' => $uan,
            ':eid' => $empId
        ]);
    }

    // 3. Insert or Update Bank Account
    if (!empty($bankAc) && !str_starts_with($bankAc, '=')) {
        $bankPrefix = strtoupper(substr(trim($ifsc), 0, 4));
        $bankNames = [
            'FDRL' => 'Federal Bank',
            'CNRB' => 'Canara Bank',
            'SBIN' => 'State Bank of India',
            'HDFC' => 'HDFC Bank',
            'ICIC' => 'ICICI Bank',
            'UTIB' => 'Axis Bank',
            'KKBK' => 'Kotak Mahindra Bank',
            'UBIN' => 'Union Bank of India',
            'PUNB' => 'Punjab National Bank',
            'BARB' => 'Bank of Baroda'
        ];
        $bankName = $bankNames[$bankPrefix] ?? ($bankPrefix . ' Bank');
        $db->prepare("DELETE FROM bank_accounts WHERE employee_id = :eid")->execute([':eid' => $empId]);
        $db->prepare("
            INSERT INTO bank_accounts (employee_id, account_holder_name, account_number, ifsc_code, bank_name, account_type)
            VALUES (:eid, :hname, :ac, :ifsc, :bname, 'Savings')
        ")->execute([
            ':eid' => $empId,
            ':hname' => $empName,
            ':ac' => $bankAc,
            ':ifsc' => $ifsc,
            ':bname' => $bankName
        ]);
    }

    // 4. Save Salary Structure
    $stmtSS = $db->prepare("SELECT salary_structure_id FROM salary_structures WHERE employee_id = :eid LIMIT 1");
    $stmtSS->execute([':eid' => $empId]);
    $ssId = (int)$stmtSS->fetchColumn();

    if (!$ssId) {
        $db->prepare("
            INSERT INTO salary_structures 
            (employee_id, salary_type, basic_salary, hra, special_allowance, other_allowance, medical_allowance, travel_allowance, bonus, other_earnings, pf, employer_pf, gratuity, esic, professional_tax, tds)
            VALUES 
            (:eid, 'Monthly', :basic, :hra, :special, :other, 0, 0, 0, 0, :pf, :emp_pf, :grat, 0, :pt, :tds)
        ")->execute([
            ':eid' => $empId,
            ':basic' => $abasic,
            ':hra' => $ahra,
            ':special' => $asa,
            ':other' => $aspec,
            ':pf' => $epf,
            ':emp_pf' => $erPf,
            ':grat' => $grat,
            ':pt' => $pt,
            ':tds' => $tds
        ]);
    } else {
        $db->prepare("
            UPDATE salary_structures SET 
                basic_salary = :basic,
                hra = :hra,
                special_allowance = :special,
                other_allowance = :other,
                pf = :pf,
                employer_pf = :emp_pf,
                gratuity = :grat,
                professional_tax = :pt,
                tds = :tds
            WHERE employee_id = :eid
        ")->execute([
            ':basic' => $abasic,
            ':hra' => $ahra,
            ':special' => $asa,
            ':other' => $aspec,
            ':pf' => $epf,
            ':emp_pf' => $erPf,
            ':grat' => $grat,
            ':pt' => $pt,
            ':tds' => $tds,
            ':eid' => $empId
        ]);
    }

    // 5. Ensure Baseline Salary Revision History
    $stmtRev = $db->prepare("SELECT COUNT(*) FROM salary_revision_history WHERE employee_id = :eid");
    $stmtRev->execute([':eid' => $empId]);
    if ((int)$stmtRev->fetchColumn() === 0) {
        $db->prepare("
            INSERT INTO salary_revision_history 
            (employee_id, revision_date, effective_date, revised_by, reason, 
             previous_basic, previous_hra, previous_special_allowance, previous_other_allowance, previous_gross, previous_ctc,
             new_basic, new_hra, new_special_allowance, new_other_allowance, new_gross, new_ctc, details_json)
            VALUES 
            (:eid, '2026-03-01', :eff_date, 1, 'Initial Salary Structure - Axiora Global Solutions',
             0, 0, 0, 0, 0, 0,
             :b, :h, :s, :o, :g, :c, :details)
        ")->execute([
            ':eid' => $empId,
            ':eff_date' => $doj,
            ':b' => $abasic,
            ':h' => $ahra,
            ':s' => $asa,
            ':o' => $aspec,
            ':g' => $age,
            ':c' => $ctc,
            ':details' => json_encode(['note' => 'Initial Baseline from Payslip_PF.xlsx'])
        ]);
    }

    // 6. Seed / Update September 2026 Payroll Details
    $db->prepare("DELETE FROM payroll_details WHERE payroll_run_id = :rid AND employee_id = :eid")
       ->execute([':rid' => $runId, ':eid' => $empId]);

    $db->prepare("
        INSERT INTO payroll_details 
        (payroll_run_id, employee_id, working_days, present_days, absent_days, leave_days, 
         overtime_hours, late_marks, basic_salary, hra, special_allowance, other_allowance, medical_allowance, 
         travel_allowance, bonus, other_earnings, actual_basic, actual_hra, actual_special_allowance, actual_other_allowance,
         pf, employer_pf, gratuity, esic, professional_tax, tds, other_deductions, gross_salary, total_deductions, net_salary, ctc, payment_status)
        VALUES 
        (:rid, :eid, 30, :pdays, :abdays, 0, 0, 0,
         :basic, :hra, :special, :other, 0, 0, 0, 0,
         :abasic, :ahra, :aspecial, :aother,
         :pf, :emp_pf, :grat, 0, :pt, :tds, 0, :gross, :ded, :net, :ctc, 'paid')
    ")->execute([
        ':rid' => $runId,
        ':eid' => $empId,
        ':pdays' => $paidDays,
        ':abdays' => $lopDays,
        ':basic' => $basic,
        ':hra' => $hra,
        ':special' => $special,
        ':other' => $other,
        ':abasic' => $abasic,
        ':ahra' => $ahra,
        ':aspecial' => $asa,
        ':aother' => $aspec,
        ':pf' => $epf,
        ':emp_pf' => $erPf,
        ':grat' => $grat,
        ':pt' => $pt,
        ':tds' => $tds,
        ':gross' => $gross,
        ':ded' => $ded,
        ':net' => $netPay,
        ':ctc' => $ctc
    ]);

    $importedCount++;
    echo "  [OK] Imported #{$empCode} - {$empName} (CTC: ₹{$ctc}, Net: ₹{$netPay})\n";
}

echo "\nSuccessfully imported and configured {$importedCount} employees and salary structures!\n";
