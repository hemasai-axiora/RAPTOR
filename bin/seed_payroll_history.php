<?php
/**
 * Raptor CRM — Historical Payroll Seeder
 * Generates accurate historical payroll runs, details, claims, and bonuses for the last 6 months.
 */

require_once dirname(__DIR__) . '/app/config/config.php';
require_once APPROOT . '/core/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // 1. Fetch active employees
    $sql = "SELECT e.*, u.name 
            FROM employees e 
            JOIN users u ON e.user_id = u.user_id 
            WHERE u.status = 'active' AND u.role_id != 1"; // exclude admin
    $stmt = $db->query($sql);
    $employees = $stmt->fetchAll(PDO::FETCH_OBJ);

    if (empty($employees)) {
        echo "No active employee records found to seed. Run test_app.php first.\n";
        exit(1);
    }

    $months = ['2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06'];

    echo "Seeding last 6 months of payroll data...\n";

    // Clean existing data for these months to prevent duplicate entries
    foreach ($months as $month) {
        $stmtClean = $db->prepare("SELECT payroll_run_id FROM payroll_runs WHERE month_year = :month");
        $stmtClean->execute([':month' => $month]);
        $runId = $stmtClean->fetchColumn();
        if ($runId) {
            $db->prepare("DELETE FROM payroll_details WHERE payroll_run_id = :rid")->execute([':rid' => $runId]);
            $db->prepare("DELETE FROM payroll_runs WHERE payroll_run_id = :rid")->execute([':rid' => $runId]);
        }
    }

    foreach ($months as $index => $month) {
        // Create payroll run
        $stmtRun = $db->prepare("
            INSERT INTO payroll_runs (month_year, status, created_by, approved_by, released_by, created_at)
            VALUES (:month, 'released', 1, 1, 1, :created_at)
        ");
        
        $runDate = $month . '-28 10:00:00';
        $stmtRun->execute([
            ':month' => $month,
            ':created_at' => $runDate
        ]);
        $runId = $db->lastInsertId();

        echo "Created run {$month} (ID: {$runId})\n";

        // Seed details for each employee
        foreach ($employees as $emp) {
            // Fetch structure or define realistic fallback values based on employee_id hash
            $structStmt = $db->prepare("SELECT * FROM salary_structures WHERE employee_id = :eid");
            $structStmt->execute([':eid' => $emp->employee_id]);
            $struct = $structStmt->fetch(PDO::FETCH_OBJ);

            $basic = $struct ? (float)$struct->basic_salary : (25000 + ($emp->employee_id % 10) * 1500);
            $hra = $struct ? (float)$struct->hra : ($basic * 0.40);
            $special = $struct ? (float)$struct->special_allowance : 3000.00;
            $med = $struct ? (float)$struct->medical_allowance : 1250.00;
            $travel = $struct ? (float)$struct->travel_allowance : 1600.00;
            $other = 0.00;
            $bonus = 0.00;

            // Compute Indian compliance deductions
            $pf = $basic * 0.12;
            $esic = $basic * 0.0075;
            $pt = 200.00;
            $tds = ($basic > 35000) ? ($basic * 0.10) : 0.00;

            $workingDays = 22;
            // Generate minor variances in attendance (present days between 20 and 22)
            $presentDays = 22 - ($emp->employee_id % 3);
            $absentDays = 22 - $presentDays;

            // Pro-rate basic based on attendance
            $proRatedBasic = ($basic / $workingDays) * $presentDays;
            $gross = $proRatedBasic + $hra + $special + $med + $travel + $bonus + $other;
            $deductions = $pf + $esic + $pt + $tds;
            $net = $gross - $deductions;

            $stmtDetail = $db->prepare("
                INSERT INTO payroll_details 
                (payroll_run_id, employee_id, working_days, present_days, absent_days, leave_days, 
                 overtime_hours, late_marks, basic_salary, hra, special_allowance, medical_allowance, 
                 travel_allowance, bonus, other_earnings, pf, esic, professional_tax, tds, gross_salary, total_deductions, net_salary, payment_status, paid_at)
                VALUES 
                (:run_id, :emp_id, :working, :present, :absent, 0, 
                 0, 0, :basic, :hra, :special, :med, 
                 :travel, :bonus, :other, :pf, :esic, :pt, :tds, :gross, :deductions, :net, 'paid', :paid_at)
            ");

            $stmtDetail->execute([
                ':run_id' => $runId,
                ':emp_id' => $emp->employee_id,
                ':working' => $workingDays,
                ':present' => $presentDays,
                ':absent' => $absentDays,
                ':basic' => $proRatedBasic,
                ':hra' => $hra,
                ':special' => $special,
                ':med' => $med,
                ':travel' => $travel,
                ':bonus' => $bonus,
                ':other' => $other,
                ':pf' => $pf,
                ':esic' => $esic,
                ':pt' => $pt,
                ':tds' => $tds,
                ':gross' => $gross,
                ':deductions' => $deductions,
                ':net' => $net,
                ':paid_at' => $month . '-25 11:30:00'
            ]);
        }

        // Seed 1 bonus and 1 reimbursement claim for this month
        $randomEmp = $employees[array_rand($employees)];
        
        $stmtBonus = $db->prepare("
            INSERT INTO bonuses (employee_id, bonus_type, amount, description, status, created_at)
            VALUES (:emp_id, 'Performance', :amount, 'Excellent performance metrics', 'paid', :created_at)
        ");
        $stmtBonus->execute([
            ':emp_id' => $randomEmp->employee_id,
            ':amount' => 5000.00,
            ':created_at' => $month . '-15 09:00:00'
        ]);

        $randomEmp2 = $employees[array_rand($employees)];
        $stmtClaim = $db->prepare("
            INSERT INTO reimbursements (employee_id, claim_type, amount, description, attachment_url, status, created_at)
            VALUES (:emp_id, 'Travel', :amount, 'Client site travel expenses', 'reimbursements/dummy_receipt.pdf', 'finance_approved', :created_at)
        ");
        $stmtClaim->execute([
            ':emp_id' => $randomEmp2->employee_id,
            ':amount' => 2450.00,
            ':created_at' => $month . '-10 14:20:00'
        ]);
    }

    echo "\nSuccessfully seeded 6 months of historical payroll records.\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
