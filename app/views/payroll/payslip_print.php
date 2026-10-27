<?php
$rawMonth = $detail->month_year ?? date('Y-m');
$monthYearFormatted = date('F Y', strtotime($rawMonth . '-01'));
$payPeriod = date('F Y', strtotime($rawMonth . '-01'));
$payDate = date('j F Y', strtotime($rawMonth . '-01 +1 month'));

$empName = !empty($detail->name) ? $detail->name : 'Employee';
$empCode = !empty($detail->employee_code) ? $detail->employee_code : '-';
$dojVal = !empty($detail->date_of_joining) ? $detail->date_of_joining : (!empty($detail->hire_date) ? $detail->hire_date : '');
$doj = !empty($dojVal) ? date('d M Y', strtotime($dojVal)) : '-';
$rawDesig = !empty($detail->job_title) ? $detail->job_title : '-';
$desig = str_replace(['–', '—'], '-', $rawDesig);
$location = !empty($detail->work_location) ? $detail->work_location : '-';
$dept = !empty($detail->department) ? $detail->department : '-';

$paidDays = (int)($detail->present_days ?? 30);
$lopDays = (int)($detail->absent_days ?? 0);
$bankAc = !empty($detail->account_number) ? trim($detail->account_number) : '-';
$ifsc = !empty($detail->ifsc_code) ? trim($detail->ifsc_code) : '-';
$uan = !empty($detail->uan) ? trim($detail->uan) : '-';

$basic = round((float)($detail->basic_salary ?? 0));
$hra = round((float)($detail->hra ?? 0));
$special = round((float)($detail->special_allowance ?? 0));
$otherAll = round((float)($detail->other_allowance ?? 0));
$bonus = round((float)($detail->bonus ?? 0));
$otherEarnings = round((float)($detail->other_earnings ?? 0));

$actualBasic = round((float)(isset($detail->actual_basic) && (float)$detail->actual_basic > 0 ? $detail->actual_basic : $basic));
$actualHra = round((float)(isset($detail->actual_hra) && (float)$detail->actual_hra > 0 ? $detail->actual_hra : $hra));
$actualSpecial = round((float)(isset($detail->actual_special_allowance) && (float)$detail->actual_special_allowance > 0 ? $detail->actual_special_allowance : $special));
$actualOther = round((float)(isset($detail->actual_other_allowance) && (float)$detail->actual_other_allowance > 0 ? $detail->actual_other_allowance : $otherAll));

$grossActual = round($actualBasic + $actualHra + $actualSpecial + $actualOther);
$gross = round((float)($detail->gross_salary ?? ($basic + $hra + $special + $otherAll + $bonus + $otherEarnings)));

$epf = round((float)($detail->pf ?? ($basic * 0.12)));
$pt = round((float)($detail->professional_tax ?? 200.00));
$otherDeductions = round((float)($detail->other_deductions ?? 0.00));
$deductions = round((float)($detail->total_deductions ?? ($epf + $pt + $otherDeductions)));

$employerPf = round((float)(isset($detail->employer_pf) && (float)$detail->employer_pf > 0 ? $detail->employer_pf : $epf));
$gratuity = round((float)(isset($detail->gratuity) && (float)$detail->gratuity > 0 ? $detail->gratuity : round($basic * 0.0481)));
$totalBenefits = round($employerPf + $gratuity);

$ctc = round((float)(isset($detail->ctc) && (float)$detail->ctc > 0 ? $detail->ctc : ($gross + $totalBenefits)));
$netPay = round((float)($detail->net_salary ?? ($gross - $deductions)));
$inWords = PayslipHelper::numberToWords($netPay);

// Helper for formatting without thousands comma (matching sample)
$fmt = function($num, $decimals = 2) {
    return number_format($num, $decimals, '.', '');
};

// Robust Base64 Logo embedding for seamless printing and standalone display
$logoCandidates = [
    (defined('APPROOT') ? dirname(APPROOT) . '/public/payslip_logo.jpg' : ''),
    dirname(__DIR__, 3) . '/public/payslip_logo.jpg',
    dirname(__DIR__, 2) . '/public/payslip_logo.jpg',
    '/var/www/html/public/payslip_logo.jpg',
    __DIR__ . '/../../../public/payslip_logo.jpg'
];
$logoBase64 = '';
foreach ($logoCandidates as $cand) {
    if (!empty($cand) && file_exists($cand)) {
        $logoBase64 = base64_encode(file_get_contents($cand));
        break;
    }
}

$zohoCandidates = [
    (defined('APPROOT') ? dirname(APPROOT) . '/public/zoho_payroll_logo.jpg' : ''),
    dirname(__DIR__, 3) . '/public/zoho_payroll_logo.jpg',
    dirname(__DIR__, 2) . '/public/zoho_payroll_logo.jpg',
    '/var/www/html/public/zoho_payroll_logo.jpg',
    __DIR__ . '/../../../public/zoho_payroll_logo.jpg'
];
$zohoLogoBase64 = '';
foreach ($zohoCandidates as $zcand) {
    if (!empty($zcand) && file_exists($zcand)) {
        $zohoLogoBase64 = base64_encode(file_get_contents($zcand));
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - <?php echo htmlspecialchars($empName); ?> - <?php echo htmlspecialchars($monthYearFormatted); ?></title>
    <!-- Inter Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
            background-color: #f1f5f9;
            padding: 2.5rem 1rem;
            -webkit-font-smoothing: antialiased;
        }

        .payslip-page {
            max-width: 840px;
            margin: 0 auto;
            background: #ffffff;
            padding: 40px 45px;
            border-radius: 4px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
            position: relative;
        }

        /* Top Header */
        .company-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
        }

        .company-brand {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .company-logo {
            width: 68px;
            height: 68px;
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .company-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .company-info h1 {
            font-size: 21px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
            letter-spacing: -0.2px;
        }

        .company-info p {
            font-size: 11.5px;
            color: #475569;
            line-height: 1.45;
        }

        .payslip-period-badge {
            text-align: right;
            padding-top: 4px;
        }

        .payslip-period-badge .label {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 4px;
            display: block;
        }

        .payslip-period-badge .period-month {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
        }

        /* Outlined Box Base */
        .outlined-box {
            border: 1.2px solid #1c3052;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 16px;
            background: #ffffff;
        }

        .box-title {
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 14px;
        }

        /* Employee Summary Layout */
        .summary-grid {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 20px;
        }

        .kv-table {
            width: 100%;
            border-collapse: collapse;
        }

        .kv-table td {
            padding: 3.5px 0;
            font-size: 12px;
            vertical-align: middle;
        }

        .kv-table td.key {
            color: #334155;
            font-weight: 400;
            width: 120px;
        }

        .kv-table td.colon {
            width: 15px;
            color: #334155;
            font-weight: 400;
        }

        .kv-table td.val {
            color: #0f172a;
            font-weight: 700;
        }

        /* Gradient Highlight Cards */
        .gradient-card {
            background: linear-gradient(180deg, #a6c1ff 0%, #c4d7fc 25%, #e3ebff 52%, #e3ebff 100%);
            border: 1.2px solid #1c3052;
            border-radius: 10px;
            padding: 14px 16px;
            text-align: center;
        }

        .gradient-card .amount {
            font-size: 26px;
            font-weight: 400;
            color: #0f172a;
            letter-spacing: 0.2px;
            margin-bottom: 2px;
            font-family: 'Times New Roman', Times, Georgia, serif;
            line-height: 1.15;
        }

        .gradient-card .subtitle {
            font-size: 11.5px;
            color: #334155;
            font-weight: 400;
            margin-bottom: 8px;
        }

        .card-meta-list {
            margin-top: 6px;
            font-size: 11.5px;
            color: #1e293b;
            text-align: left;
            display: inline-block;
        }

        .card-meta-list div {
            padding: 2px 0;
        }

        .bank-summary-sub {
            margin-top: 14px;
        }

        /* Split Tables (Earnings / Deductions) */
        .split-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            border: 1.2px solid #1c3052;
            border-radius: 8px;
            margin-bottom: 16px;
            overflow: hidden;
        }

        .table-column {
            padding: 14px 18px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            font-size: 11.5px;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            padding-bottom: 8px;
            border-bottom: none;
        }

        .data-table td {
            font-size: 12px;
            padding: 4px 0;
            color: #1e293b;
        }

        .data-table td.col-name {
            font-weight: 400;
        }

        .data-table td.col-num {
            text-align: right;
            font-weight: 400;
            font-variant-numeric: tabular-nums;
            font-family: 'Times New Roman', Times, Georgia, serif;
            font-size: 13.5px;
        }

        .data-table tr.total-row td {
            font-weight: 700;
            color: #0f172a;
            padding-top: 10px;
            padding-bottom: 2px;
        }

        .data-table tr.total-row td.col-num {
            font-family: 'Times New Roman', Times, Georgia, serif;
            font-weight: 700;
            font-size: 14px;
        }

        /* Benefits & CTC */
        .benefits-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 20px;
            align-items: center;
        }

        /* Total Net Payable Full Banner */
        .payable-banner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1.2px solid #1c3052;
            border-radius: 8px;
            padding: 8px 12px 8px 18px;
            margin-top: 14px;
            margin-bottom: 8px;
        }

        .payable-banner .label-pane .main-label {
            font-size: 12.5px;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .payable-banner .label-pane .sub-label {
            font-size: 11.5px;
            color: #64748b;
        }

        .payable-banner .amount-box {
            background: linear-gradient(180deg, #a6c1ff 0%, #c4d7fc 45%, #dfe8ff 100%);
            border: 1.2px solid #1c3052;
            border-radius: 8px;
            padding: 8px 30px;
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
            min-width: 250px;
            text-align: center;
            font-family: 'Times New Roman', Times, Georgia, serif;
        }

        .amount-in-words {
            text-align: center;
            font-size: 11.5px;
            color: #334155;
            margin-bottom: 30px;
            margin-top: 6px;
        }

        /* Footer */
        .payslip-footer {
            margin-top: 35px;
            text-align: center;
        }

        .payslip-footer .system-gen {
            font-size: 11px;
            color: #64748b;
            margin-bottom: 8px;
        }

        /* Floating Action Bar */
        .floating-action-bar {
            position: fixed;
            bottom: 24px;
            right: 24px;
            display: flex;
            gap: 12px;
            z-index: 999;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #1e3a8a;
            color: #ffffff;
            font-weight: 600;
            font-size: 14px;
            padding: 12px 22px;
            border-radius: 30px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(30, 58, 138, 0.35);
            transition: all 0.2s ease;
        }

        .btn-action:hover {
            background: #172554;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(30, 58, 138, 0.45);
        }

        .btn-action.btn-secondary-action {
            background: #ffffff;
            color: #1e293b;
            border: 1px solid #cbd5e1;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
        }

        .btn-action.btn-secondary-action:hover {
            background: #f8fafc;
            border-color: #94a3b8;
        }

        /* Print Media Styles */
        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }

            .payslip-page {
                box-shadow: none;
                padding: 0;
                max-width: 100%;
                margin: 0;
            }

            .floating-action-bar {
                display: none !important;
            }

            @page {
                size: A4 portrait;
                margin: 10mm 12mm;
            }
        }
    </style>
</head>
<body>

    <!-- Floating Actions -->
    <div class="floating-action-bar">
        <a href="index.php?route=payroll/payslip_view/<?php echo $detail->payroll_detail_id; ?>&download=pdf" class="btn-action">
            <i class="fa-solid fa-file-pdf"></i> Download PDF
        </a>
        <button onclick="window.print()" class="btn-action btn-secondary-action">
            <i class="fa-solid fa-print"></i> Print Payslip
        </button>
    </div>

    <!-- Main Payslip Container -->
    <div class="payslip-page">
        <!-- 1. Header -->
        <div class="company-header">
            <div class="company-brand">
                <div class="company-logo">
                    <?php if (!empty($logoBase64)): ?>
                        <img src="data:image/jpeg;base64,<?php echo $logoBase64; ?>" alt="Axiora Global Solutions Logo">
                    <?php else: ?>
                        <img src="public/payslip_logo.jpg" alt="Axiora Global Solutions Logo">
                    <?php endif; ?>
                </div>
                <div class="company-info">
                    <h1>Axiora Global Solutions</h1>
                    <p>3rd Floor, Pranava Business Park, beside Harsha Toyota showroom<br>
                    Kothaguda, Kondapur, Hyderabad, Telangana 500084 India.</p>
                </div>
            </div>
            <div class="payslip-period-badge">
                <span class="label">Payslip For the Month</span>
                <span class="period-month"><?php echo htmlspecialchars($monthYearFormatted); ?></span>
            </div>
        </div>

        <!-- 2. Employee Summary -->
        <div class="outlined-box">
            <div class="box-title">EMPLOYEE SUMMARY</div>
            <div class="summary-grid">
                <!-- Left Key Values -->
                <div>
                    <table class="kv-table">
                        <tr>
                            <td class="key">Employee Name</td>
                            <td class="colon">:</td>
                            <td class="val"><?php echo htmlspecialchars($empName); ?></td>
                        </tr>
                        <tr>
                            <td class="key">Employee ID</td>
                            <td class="colon">:</td>
                            <td class="val"><?php echo htmlspecialchars($empCode); ?></td>
                        </tr>
                        <tr>
                            <td class="key">Pay Period</td>
                            <td class="colon">:</td>
                            <td class="val"><?php echo htmlspecialchars($payPeriod); ?></td>
                        </tr>
                        <tr>
                            <td class="key">Pay Date</td>
                            <td class="colon">:</td>
                            <td class="val"><?php echo htmlspecialchars($payDate); ?></td>
                        </tr>
                        <tr>
                            <td class="key">Date of Joining</td>
                            <td class="colon">:</td>
                            <td class="val"><?php echo htmlspecialchars($doj); ?></td>
                        </tr>
                        <tr>
                            <td class="key">Designation</td>
                            <td class="colon">:</td>
                            <td class="val"><?php echo htmlspecialchars($desig); ?></td>
                        </tr>
                        <tr>
                            <td class="key">Location</td>
                            <td class="colon">:</td>
                            <td class="val"><?php echo htmlspecialchars($location); ?></td>
                        </tr>
                        <tr>
                            <td class="key">Department</td>
                            <td class="colon">:</td>
                            <td class="val"><?php echo htmlspecialchars($dept); ?></td>
                        </tr>
                    </table>
                </div>

                <!-- Right Net Pay Card & Bank Info -->
                <div>
                    <div class="gradient-card">
                        <div class="amount">₹<?php echo $fmt($netPay, 0); ?></div>
                        <div class="subtitle">Total Net Pay</div>
                        <div class="card-meta-list">
                            <div>Paid Days &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: &nbsp;&nbsp;&nbsp;&nbsp;<strong><?php echo $paidDays; ?></strong></div>
                            <div>LOP Days &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: &nbsp;&nbsp;&nbsp;&nbsp;<strong><?php echo $lopDays; ?></strong></div>
                        </div>
                    </div>

                    <div class="bank-summary-sub">
                        <table class="kv-table">
                            <tr>
                                <td class="key" style="width: 110px;">Bank A/C Number</td>
                                <td class="colon">:</td>
                                <td class="val"><?php echo htmlspecialchars($bankAc); ?></td>
                            </tr>
                            <tr>
                                <td class="key">IFSC Code</td>
                                <td class="colon">:</td>
                                <td class="val text-uppercase"><?php echo htmlspecialchars($ifsc); ?></td>
                            </tr>
                            <tr>
                                <td class="key">UAN Number</td>
                                <td class="colon">:</td>
                                <td class="val"><?php echo htmlspecialchars($uan); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Earnings & Deductions -->
        <div class="split-grid">
            <!-- Left Earnings -->
            <div class="table-column">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="text-align: left; width: 44%;">EARNINGS</th>
                            <th style="text-align: right; width: 28%;">ACTUAL</th>
                            <th style="text-align: right; width: 28%;">AMOUNT</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="col-name">Basic</td>
                            <td class="col-num">₹<?php echo $fmt($actualBasic); ?></td>
                            <td class="col-num">₹<?php echo $fmt($basic); ?></td>
                        </tr>
                        <tr>
                            <td class="col-name">House Rent Allowance</td>
                            <td class="col-num">₹<?php echo $fmt($actualHra); ?></td>
                            <td class="col-num">₹<?php echo $fmt($hra); ?></td>
                        </tr>
                        <tr>
                            <td class="col-name">Special Allowance</td>
                            <td class="col-num">₹<?php echo $fmt($actualSpecial); ?></td>
                            <td class="col-num">₹<?php echo $fmt($special); ?></td>
                        </tr>
                        <?php if ($otherAll > 0 || $actualOther > 0): ?>
                        <tr>
                            <td class="col-name">Other Allowance</td>
                            <td class="col-num">₹<?php echo $fmt($actualOther); ?></td>
                            <td class="col-num">₹<?php echo $fmt($otherAll); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($bonus > 0): ?>
                        <tr>
                            <td class="col-name">Bonus / Incentives</td>
                            <td class="col-num">₹<?php echo $fmt($bonus); ?></td>
                            <td class="col-num">₹<?php echo $fmt($bonus); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($otherEarnings > 0): ?>
                        <tr>
                            <td class="col-name">Arrears / Additional</td>
                            <td class="col-num">₹<?php echo $fmt($otherEarnings); ?></td>
                            <td class="col-num">₹<?php echo $fmt($otherEarnings); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr class="total-row">
                            <td class="col-name">Gross Earnings</td>
                            <td class="col-num">₹<?php echo $fmt($grossActual); ?></td>
                            <td class="col-num">₹<?php echo $fmt($gross); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Right Deductions -->
            <div class="table-column">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="text-align: left; width: 60%;">DEDUCTIONS</th>
                            <th style="text-align: right; width: 40%;">AMOUNT</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="col-name">EPF Contribution</td>
                            <td class="col-num">₹<?php echo $fmt($epf); ?></td>
                        </tr>
                        <tr>
                            <td class="col-name">Professional Tax</td>
                            <td class="col-num">₹<?php echo $fmt($pt); ?></td>
                        </tr>
                        <?php if ($otherDeductions > 0): ?>
                        <tr>
                            <td class="col-name">Other Deductions</td>
                            <td class="col-num">₹<?php echo $fmt($otherDeductions); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr class="total-row">
                            <td class="col-name">Total Deductions</td>
                            <td class="col-num">₹<?php echo $fmt($deductions); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 4. Employee Benefits & CTC -->
        <div class="outlined-box">
            <div class="benefits-grid">
                <!-- Left Benefits Table -->
                <div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="text-align: left; width: 65%;">Employee Benefits</th>
                                <th style="text-align: right; width: 35%;">AMOUNT</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="col-name">Employer EPF Contribution</td>
                                <td class="col-num">₹<?php echo $fmt($employerPf); ?></td>
                            </tr>
                            <tr>
                                <td class="col-name">Gratuity</td>
                                <td class="col-num">₹<?php echo $fmt($gratuity); ?></td>
                            </tr>
                            <tr class="total-row">
                                <td class="col-name">Gross Earnings</td>
                                <td class="col-num">₹<?php echo $fmt($totalBenefits); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Right CTC Card -->
                <div>
                    <div class="gradient-card">
                        <div class="amount">₹<?php echo $fmt($ctc, 0); ?></div>
                        <div class="subtitle" style="margin-bottom: 0;">Effective Monthly CTC</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. Total Net Payable Banner -->
        <div class="payable-banner">
            <div class="label-pane">
                <div class="main-label">TOTAL NET PAYABLE</div>
                <div class="sub-label">Gross Earnings – Total Deductions</div>
            </div>
            <div class="amount-box">
                ₹<?php echo $fmt($netPay); ?>
            </div>
        </div>

        <!-- Amount In Words -->
        <div class="amount-in-words">
            Amount In Words: Indian Rupee <?php echo htmlspecialchars($inWords); ?> Only
        </div>

        <!-- 6. Footer (Exact matching Zoho Payroll reference) -->
        <div class="payslip-footer">
            <div class="system-gen" style="margin-bottom: 24px;">-- This is a system-generated document. --</div>
            <div class="zoho-footer" style="display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 11px; color: #64748b;">
                <span>Powered by</span>
                <?php if (!empty($zohoLogoBase64)): ?>
                    <img src="data:image/jpeg;base64,<?php echo $zohoLogoBase64; ?>" alt="Zoho Payroll" style="height: 22px; width: auto; vertical-align: middle;">
                <?php else: ?>
                    <img src="public/zoho_payroll_logo.jpg" alt="Zoho Payroll" style="height: 22px; width: auto; vertical-align: middle;">
                <?php endif; ?>
                <span style="margin-left: 6px;">Simplify payroll and compliance. Visit <a href="https://zoho.com/payroll" target="_blank" style="color: #2563eb; text-decoration: none;">zoho.com/payroll</a></span>
            </div>
        </div>
    </div>

</body>
</html>
