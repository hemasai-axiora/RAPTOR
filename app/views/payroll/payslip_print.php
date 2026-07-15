<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - <?php echo htmlspecialchars($detail->name); ?> - <?php echo htmlspecialchars($detail->month_year); ?></title>
    <!-- Inter Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            color: #333;
            background-color: #fff;
            padding: 2rem;
        }

        .payslip-card {
            max-width: 800px;
            margin: auto;
            border: 1px solid #eaeaea;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .payslip-header {
            display: flex;
            justify-content: space-between;
            border-bottom: 2px solid #eaeaea;
            padding-bottom: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .company-title {
            color: #6366f1;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .payslip-title {
            font-weight: 700;
            color: #1a1c29;
            font-size: 1.75rem;
        }

        .info-table td {
            padding: 0.4rem 0;
            font-size: 0.85rem;
            border: none;
        }

        .breakdown-table {
            width: 100%;
            margin-bottom: 2rem;
            border-collapse: collapse;
        }

        .breakdown-table th {
            background-color: #f8fafc;
            font-weight: 600;
            color: #1a1c29;
            padding: 0.75rem 1rem;
            border-bottom: 2px solid #eaeaea;
            border-top: 1px solid #eaeaea;
        }

        .breakdown-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #eaeaea;
            font-size: 0.9rem;
        }

        .net-pay-section {
            display: flex;
            justify-content: space-between;
            background-color: #f8fafc;
            padding: 1.25rem 2rem;
            border-radius: 8px;
            margin-top: 1rem;
            border: 1px solid #eaeaea;
        }

        .net-pay-title {
            font-weight: 700;
            color: #1a1c29;
            font-size: 1.1rem;
        }

        .net-pay-value {
            font-size: 1.3rem;
            font-weight: 700;
            color: #10b981;
        }

        .floating-print-bar {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 100;
        }

        @media print {
            .floating-print-bar {
                display: none;
            }
            body {
                padding: 0;
            }
            .payslip-card {
                border: none;
                box-shadow: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Floating Action Button -->
    <div class="floating-print-bar">
        <button onclick="window.print()" class="btn btn-primary btn-lg shadow px-4 py-2" style="background: #6366f1; border:none; border-radius:30px;">
            <i class="fa-solid fa-print me-2"></i>Print Payslip / PDF
        </button>
    </div>

    <div class="payslip-card">
        <div class="payslip-header">
            <div>
                <h3 class="company-title">Raptor Marketing Agency</h3>
                <div class="text-secondary small">100 Creator Square, Business Bay</div>
                <div class="text-secondary small">hr@raptor-agency.com</div>
            </div>
            <div class="text-end">
                <h1 class="payslip-title">PAYSLIP</h1>
                <div style="font-size: 1.1rem; color: #666; font-weight:500;">
                    Period: <?php echo htmlspecialchars($detail->month_year); ?>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <!-- Employee Info -->
            <div class="col-6">
                <table class="table info-table">
                    <tr>
                        <td class="text-secondary" style="width: 140px;">Employee Name:</td>
                        <td><strong><?php echo htmlspecialchars($detail->name); ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-secondary">Employee ID:</td>
                        <td class="font-monospace"><?php echo htmlspecialchars($detail->employee_code); ?></td>
                    </tr>
                    <tr>
                        <td class="text-secondary">Designation:</td>
                        <td><?php echo htmlspecialchars($detail->job_title ?: 'Staff'); ?></td>
                    </tr>
                    <tr>
                        <td class="text-secondary">Department:</td>
                        <td><?php echo htmlspecialchars($detail->department ?: 'Sales'); ?></td>
                    </tr>
                </table>
            </div>
            <!-- Bank & Attendance Info -->
            <div class="col-6">
                <table class="table info-table">
                    <tr>
                        <td class="text-secondary" style="width: 140px;">Bank Name:</td>
                        <td><?php echo htmlspecialchars($detail->bank_name ?: 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <td class="text-secondary">Account Number:</td>
                        <td class="font-monospace"><?php echo htmlspecialchars($detail->account_number ?: 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <td class="text-secondary">IFSC Code:</td>
                        <td class="font-monospace text-uppercase"><?php echo htmlspecialchars($detail->ifsc_code ?: 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <td class="text-secondary">Days Present:</td>
                        <td><?php echo htmlspecialchars($detail->present_days); ?> / <?php echo htmlspecialchars($detail->working_days); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="row g-4">
            <!-- Earnings -->
            <div class="col-6">
                <table class="breakdown-table">
                    <thead>
                        <tr>
                            <th>Earnings Component</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Basic Salary</td>
                            <td class="text-end font-monospace">Rs. <?php echo number_format($detail->basic_salary, 2); ?></td>
                        </tr>
                        <tr>
                            <td>HRA (House Rent Allow.)</td>
                            <td class="text-end font-monospace">Rs. <?php echo number_format($detail->hra, 2); ?></td>
                        </tr>
                        <tr>
                            <td>Special Allowance</td>
                            <td class="text-end font-monospace">Rs. <?php echo number_format($detail->special_allowance, 2); ?></td>
                        </tr>
                        <tr>
                            <td>Medical Allowance</td>
                            <td class="text-end font-monospace">Rs. <?php echo number_format($detail->medical_allowance, 2); ?></td>
                        </tr>
                        <tr>
                            <td>Travel Allowance</td>
                            <td class="text-end font-monospace">Rs. <?php echo number_format($detail->travel_allowance, 2); ?></td>
                        </tr>
                        <?php if ((float)$detail->bonus > 0): ?>
                            <tr>
                                <td>Bonus</td>
                                <td class="text-end font-monospace">Rs. <?php echo number_format($detail->bonus, 2); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ((float)$detail->other_earnings > 0): ?>
                            <tr>
                                <td>Other Earnings</td>
                                <td class="text-end font-monospace">Rs. <?php echo number_format($detail->other_earnings, 2); ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr class="table-light">
                            <td class="font-weight-bold">Total Gross Earnings</td>
                            <td class="text-end font-weight-bold font-monospace">Rs. <?php echo number_format($detail->gross_salary, 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Deductions -->
            <div class="col-6">
                <table class="breakdown-table">
                    <thead>
                        <tr>
                            <th>Deduction Component</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Provident Fund (PF)</td>
                            <td class="text-end font-monospace">Rs. <?php echo number_format($detail->pf, 2); ?></td>
                        </tr>
                        <tr>
                            <td>ESIC Contribution</td>
                            <td class="text-end font-monospace">Rs. <?php echo number_format($detail->esic, 2); ?></td>
                        </tr>
                        <tr>
                            <td>Professional Tax</td>
                            <td class="text-end font-monospace">Rs. <?php echo number_format($detail->professional_tax, 2); ?></td>
                        </tr>
                        <tr>
                            <td>TDS (Income Tax)</td>
                            <td class="text-end font-monospace">Rs. <?php echo number_format($detail->tds, 2); ?></td>
                        </tr>
                        <tr class="table-light">
                            <td class="font-weight-bold">Total Deductions</td>
                            <td class="text-end font-weight-bold font-monospace">Rs. <?php echo number_format($detail->total_deductions, 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="net-pay-section">
            <span class="net-pay-title">Net Salary Distributed (NEFT):</span>
            <span class="net-pay-value font-monospace">Rs. <?php echo number_format($detail->net_salary, 2); ?></span>
        </div>

        <div class="text-center text-secondary small mt-5 pt-4 border-top">
            <p class="mb-1">This is a system generated payslip of RAPTOR and does not require physical signature.</p>
            <p style="font-size: 0.72rem;">Generated on <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </div>
</body>
</html>
