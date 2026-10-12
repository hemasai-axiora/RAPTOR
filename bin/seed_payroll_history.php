<?php
/**
 * Raptor CRM — Historical Payroll Seeder
 * Generates accurate historical salary structures, revision history, and 6 months of payroll runs with email dispatch logs.
 */

require_once dirname(__DIR__) . '/app/config/config.php';
require_once APPROOT . '/core/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // 1. Fetch active employees
    $sql = "SELECT e.*, u.name, u.email 
            FROM employees e 
            JOIN users u ON e.user_id = u.user_id 
            WHERE u.status = 'active' AND u.role_id != 1"; // exclude admin
    $stmt = $db->query($sql);
    $employees = $stmt->fetchAll(PDO::FETCH_OBJ);

    if (empty($employees)) {
        echo "No active employee records found to seed.\n";
        exit(1);
    }

    echo "Ensuring salary structures and revision history for " . count($employees) . " employees...\n";

    // Clear old salary structures and revisions to create a clean consistent baseline
    $db->exec("DELETE FROM salary_revision_history");
    $db->exec("DELETE FROM salary_structures");

    foreach ($employees as $emp) {
        $baseBasic = 25000 + ($emp->employee_id % 12) * 2000;
        $baseHra = round($baseBasic * 0.40, 2);
        $baseSpecial = round($baseBasic * 0.20, 2);
        $baseOther = 3000.00;
        $med = 1250.00;
        $travel = 1600.00;
        $bonus = 0.00;
        $otherEarn = 0.00;

        $pf = round($baseBasic * 0.12, 2);
        $empPf = $pf;
        $gratuity = round($baseBasic * 0.0481, 2);
        $esic = 0.00;
        $pt = 200.00;
        $tds = ($baseBasic > 35000) ? round($baseBasic * 0.10, 2) : 0.00;

        // 1. Insert Initial Baseline Structure
        $stmtStruct = $db->prepare("
            INSERT INTO salary_structures 
            (employee_id, salary_type, basic_salary, hra, special_allowance, other_allowance, medical_allowance, travel_allowance, bonus, other_earnings, pf, employer_pf, gratuity, esic, professional_tax, tds)
            VALUES 
            (:eid, 'Monthly', :basic, :hra, :spec, :other_all, :med, :travel, :bonus, :other, :pf, :emp_pf, :grat, :esic, :pt, :tds)
        ");
        $stmtStruct->execute([
            ':eid' => $emp->employee_id,
            ':basic' => $baseBasic,
            ':hra' => $baseHra,
            ':spec' => $baseSpecial,
            ':other_all' => $baseOther,
            ':med' => $med,
            ':travel' => $travel,
            ':bonus' => $bonus,
            ':other' => $otherEarn,
            ':pf' => $pf,
            ':emp_pf' => $empPf,
            ':grat' => $gratuity,
            ':esic' => $esic,
            ':pt' => $pt,
            ':tds' => $tds
        ]);

        $initialGross = $baseBasic + $baseHra + $baseSpecial + $baseOther + $med + $travel + $bonus + $otherEarn;
        $initialCtc = $initialGross + $empPf + $gratuity;

        // Record Initial Baseline in Revision History
        $db->prepare("
            INSERT INTO salary_revision_history 
            (employee_id, revision_date, effective_date, revised_by, reason, previous_basic, previous_hra, previous_special_allowance, previous_other_allowance, previous_gross, previous_ctc, new_basic, new_hra, new_special_allowance, new_other_allowance, new_gross, new_ctc, details_json)
            VALUES 
            (:eid, '2025-06-01', '2025-06-01', 1, 'Initial Employment Package', 0, 0, 0, 0, 0, 0, :nb, :nh, :ns, :no, :ng, :nc, :details)
        ")->execute([
            ':eid' => $emp->employee_id,
            ':nb' => $baseBasic,
            ':nh' => $baseHra,
            ':ns' => $baseSpecial,
            ':no' => $baseOther,
            ':ng' => $initialGross,
            ':nc' => $initialCtc,
            ':details' => json_encode(['note' => 'Initial Joining Package'])
        ]);

        // Record an Appraisal / Promotion Revision for a portion of employees
        if ($emp->employee_id % 2 === 0) {
            $revBasic = $baseBasic + 5000;
            $revHra = round($revBasic * 0.40, 2);
            $revSpecial = round($revBasic * 0.20, 2);
            $revOther = $baseOther + 1000;
            $revEmpPf = round($revBasic * 0.12, 2);
            $revGrat = round($revBasic * 0.0481, 2);
            $revGross = $revBasic + $revHra + $revSpecial + $revOther + $med + $travel;
            $revCtc = $revGross + $revEmpPf + $revGrat;

            // Update active structure to revised
            $db->prepare("
                UPDATE salary_structures 
                SET basic_salary = :b, hra = :h, special_allowance = :s, other_allowance = :o, pf = :pf, employer_pf = :epf, gratuity = :grat 
                WHERE employee_id = :eid
            ")->execute([
                ':b' => $revBasic,
                ':h' => $revHra,
                ':s' => $revSpecial,
                ':o' => $revOther,
                ':pf' => $revEmpPf,
                ':epf' => $revEmpPf,
                ':grat' => $revGrat,
                ':eid' => $emp->employee_id
            ]);

            // Add Revision Entry
            $db->prepare("
                INSERT INTO salary_revision_history 
                (employee_id, revision_date, effective_date, revised_by, reason, previous_basic, previous_hra, previous_special_allowance, previous_other_allowance, previous_gross, previous_ctc, new_basic, new_hra, new_special_allowance, new_other_allowance, new_gross, new_ctc, details_json)
                VALUES 
                (:eid, '2026-04-01', '2026-04-01', 1, 'Annual Appraisal & Performance Increment 2026', :pb, :ph, :ps, :po, :pg, :pc, :nb, :nh, :ns, :no, :ng, :nc, :details)
            ")->execute([
                ':eid' => $emp->employee_id,
                ':pb' => $baseBasic,
                ':ph' => $baseHra,
                ':ps' => $baseSpecial,
                ':po' => $baseOther,
                ':pg' => $initialGross,
                ':pc' => $initialCtc,
                ':nb' => $revBasic,
                ':nh' => $revHra,
                ':ns' => $revSpecial,
                ':no' => $revOther,
                ':ng' => $revGross,
                ':nc' => $revCtc,
                ':details' => json_encode(['appraisal_rating' => 'Exceeds Expectations'])
            ]);
        }
    }

    $months = ['2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06'];

    echo "Seeding last 6 months of historical payroll runs & payslips...\n";

    // Clean existing data for these months
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
            $structStmt = $db->prepare("SELECT * FROM salary_structures WHERE employee_id = :eid");
            $structStmt->execute([':eid' => $emp->employee_id]);
            $struct = $structStmt->fetch(PDO::FETCH_OBJ);

            $actualBasic = $struct ? (float)$struct->basic_salary : 25000.00;
            $actualHra = $struct ? (float)$struct->hra : round($actualBasic * 0.40, 2);
            $actualSpecial = $struct ? (float)$struct->special_allowance : 3000.00;
            $actualOther = $struct ? (float)($struct->other_allowance ?? 0.00) : 0.00;
            $med = $struct ? (float)$struct->medical_allowance : 1250.00;
            $travel = $struct ? (float)$struct->travel_allowance : 1600.00;
            $other = 0.00;
            $bonus = 0.00;

            $workingDays = 22;
            $presentDays = 22 - ($emp->employee_id % 3);
            $absentDays = 22 - $presentDays;

            // Pro-rate earned basic based on attendance
            $proRateFactor = $presentDays / $workingDays;
            $basic = round($actualBasic * $proRateFactor, 2);
            $hra = round($actualHra * $proRateFactor, 2);
            $special = round($actualSpecial * $proRateFactor, 2);
            $otherAll = round($actualOther * $proRateFactor, 2);

            // Statutory compliance
            $pf = round($basic * 0.12, 2);
            $employerPf = $pf;
            $gratuity = round($basic * 0.0481, 2);
            $esic = round($basic * 0.0075, 2);
            $pt = 200.00;
            $tds = ($basic > 35000) ? round($basic * 0.10, 2) : 0.00;

            $gross = $basic + $hra + $special + $otherAll + $med + $travel + $bonus + $other;
            $deductions = $pf + $esic + $pt + $tds;
            $net = $gross - $deductions;
            $ctc = $gross + $employerPf + $gratuity;

            $stmtDetail = $db->prepare("
                INSERT INTO payroll_details 
                (payroll_run_id, employee_id, working_days, present_days, absent_days, leave_days, 
                 overtime_hours, late_marks, basic_salary, hra, special_allowance, other_allowance, medical_allowance, 
                 travel_allowance, bonus, other_earnings, actual_basic, actual_hra, actual_special_allowance, actual_other_allowance,
                 pf, employer_pf, gratuity, esic, professional_tax, tds, gross_salary, total_deductions, net_salary, ctc, payment_status, paid_at, email_status, email_sent_at)
                VALUES 
                (:run_id, :emp_id, :working, :present, :absent, 0, 
                 0, 0, :basic, :hra, :special, :other_all, :med, 
                 :travel, :bonus, :other, :act_b, :act_h, :act_s, :act_o,
                 :pf, :emp_pf, :grat, :esic, :pt, :tds, :gross, :deductions, :net, :ctc, 'paid', :paid_at, 'sent', :email_at)
            ");

            $stmtDetail->execute([
                ':run_id' => $runId,
                ':emp_id' => $emp->employee_id,
                ':working' => $workingDays,
                ':present' => $presentDays,
                ':absent' => $absentDays,
                ':basic' => $basic,
                ':hra' => $hra,
                ':special' => $special,
                ':other_all' => $otherAll,
                ':med' => $med,
                ':travel' => $travel,
                ':bonus' => $bonus,
                ':other' => $other,
                ':act_b' => $actualBasic,
                ':act_h' => $actualHra,
                ':act_s' => $actualSpecial,
                ':act_o' => $actualOther,
                ':pf' => $pf,
                ':emp_pf' => $employerPf,
                ':grat' => $gratuity,
                ':esic' => $esic,
                ':pt' => $pt,
                ':tds' => $tds,
                ':gross' => $gross,
                ':deductions' => $deductions,
                ':net' => $net,
                ':ctc' => $ctc,
                ':paid_at' => $month . '-25 11:30:00',
                ':email_at' => $month . '-25 11:35:00'
            ]);
        }
    }

    echo "\nSuccessfully seeded salary structures, revision history, and 6 months of payslips.\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
