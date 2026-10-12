<div class="pulse-card">
    <?php if (!empty($_SESSION['payroll_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($_SESSION['payroll_success']); unset($_SESSION['payroll_success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['payroll_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <i class="fa-solid fa-circle-xmark me-2"></i><?php echo htmlspecialchars($_SESSION['payroll_error']); unset($_SESSION['payroll_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <h4 class="text-white mb-4"><i class="fa-solid fa-receipt me-2" style="color: var(--primary);"></i>Payslips Directory</h4>

    <?php if (in_array($role, ['admin', 'hr', 'finance'], true)): ?>
        <!-- Admin/HR/Finance Selection Panel -->
        <form method="GET" action="index.php" class="row g-3 align-items-end mb-4 bg-dark bg-opacity-50 p-3 rounded border border-secondary">
            <input type="hidden" name="route" value="payroll/payslips">
            <div class="col-md-8">
                <label for="run_id" class="form-label text-secondary font-weight-bold small text-uppercase">Select Released Payroll Period</label>
                <select name="run_id" id="run_id" class="form-select bg-dark border-secondary text-white" onchange="this.form.submit()">
                    <option value="">-- Select Released Month --</option>
                    <?php foreach ($runs as $r): ?>
                        <?php if ($r->status === 'released'): ?>
                            <option value="<?php echo $r->payroll_run_id; ?>" <?php echo (int)$selected_run_id === (int)$r->payroll_run_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(PayslipHelper::formatMonthYear($r->month_year)); ?> (<?php echo htmlspecialchars($r->month_year); ?>)
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-outline-light w-100">
                    <i class="fa-solid fa-folder-open me-2"></i>Load Payslips
                </button>
            </div>
        </form>

        <!-- Selected Run Payslips -->
        <?php if ((int)$selected_run_id > 0): ?>
            <?php 
                $runMonthFormatted = !empty($current_run->month_year) ? PayslipHelper::formatMonthYear($current_run->month_year) : date('F Y');
            ?>
            <!-- Email Dispatch Template & Custom Subject/Message Box -->
            <div class="card bg-dark border-secondary mb-4 shadow-sm" style="border-radius: 10px;">
                <div class="card-header bg-dark border-secondary d-flex flex-wrap justify-content-between align-items-center py-2 px-3">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                            <i class="fa-solid fa-envelope-open-text me-1"></i> Custom Email Dispatch
                        </span>
                        <span class="text-white small font-weight-bold">Configure Email Subject, Message & Audit Record</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-secondary small font-monospace d-none d-md-inline">From: accounts@axioraglobalsolutions.com</span>
                        <button class="btn btn-sm btn-outline-secondary py-0 px-2" type="button" data-bs-toggle="collapse" data-bs-target="#emailTemplateBox" aria-expanded="true">
                            <i class="fa-solid fa-chevron-down"></i>
                        </button>
                    </div>
                </div>
                <div class="collapse show" id="emailTemplateBox">
                    <div class="card-body p-3">
                        <form action="index.php?route=payroll/bulk_email_payslips" method="POST" id="bulkEmailForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                            <input type="hidden" name="run_id" value="<?php echo $selected_run_id; ?>">
                            
                            <div class="row g-3">
                                <!-- Subject Line Box -->
                                <div class="col-lg-7 col-md-12">
                                    <label for="bulk_subject" class="form-label text-white small font-weight-bold mb-1">
                                        <i class="fa-solid fa-heading me-1 text-primary"></i>Email Subject Box
                                    </label>
                                    <input type="text" name="email_subject" id="bulk_subject" class="form-control form-control-sm bg-black bg-opacity-50 border-secondary text-white font-monospace" value="Payslip for {name} - <?php echo htmlspecialchars($runMonthFormatted); ?>" placeholder="e.g. Payslip for {name} - {month}">
                                    <div class="mt-1 d-flex flex-wrap gap-1 align-items-center">
                                        <span class="text-secondary" style="font-size: 0.72rem;">Insert Dynamic Tag:</span>
                                        <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1 text-info" style="font-size: 0.7rem;" onclick="insertTag('bulk_subject', '{name}')">{name}</button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1 text-info" style="font-size: 0.7rem;" onclick="insertTag('bulk_subject', '{employee_code}')">{employee_code}</button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1 text-info" style="font-size: 0.7rem;" onclick="insertTag('bulk_subject', '{month}')">{month}</button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1 text-info" style="font-size: 0.7rem;" onclick="insertTag('bulk_subject', '{net_pay}')">{net_pay}</button>
                                    </div>
                                </div>

                                <!-- Audit Record Notes Box -->
                                <div class="col-lg-5 col-md-12">
                                    <label for="bulk_notes" class="form-label text-white small font-weight-bold mb-1">
                                        <i class="fa-solid fa-bookmark me-1 text-warning"></i>Record Notes / Remarks Box
                                    </label>
                                    <input type="text" name="email_notes" id="bulk_notes" class="form-control form-control-sm bg-black bg-opacity-50 border-secondary text-white" placeholder="Optional audit record or message note (e.g. September Salary Released)">
                                    <span class="text-secondary d-block mt-1" style="font-size: 0.72rem;">Permanently saved to database record & appended to email.</span>
                                </div>

                                <!-- Message / Body Text Box -->
                                <div class="col-12">
                                    <label for="bulk_body" class="form-label text-white small font-weight-bold mb-1 d-flex justify-content-between">
                                        <span><i class="fa-solid fa-align-left me-1 text-success"></i>Email Message / Text Body Box</span>
                                        <span class="text-secondary font-normal" style="font-size: 0.75rem;">Official PDF Payslip is automatically attached</span>
                                    </label>
                                    <textarea name="email_body" id="bulk_body" rows="5" class="form-control form-control-sm bg-black bg-opacity-50 border-secondary text-white font-monospace" style="font-size: 0.8rem;" placeholder="Enter custom message body text to send with payslip...">Dear {name},

Please find attached your official payslip for the month of {month}.

Payroll Summary:
- Employee ID    : {employee_code}
- Gross Earnings : Rs. {gross_pay}
- Net Salary     : Rs. {net_pay}

Please find your official detailed payslip attached as a PDF document.

Warm regards,
Accounts & Payroll Team
Axiora Global Solutions
accounts@axioraglobalsolutions.com</textarea>
                                </div>

                                <!-- Action Buttons -->
                                <div class="col-12 d-flex flex-wrap justify-content-between align-items-center pt-2 border-top border-secondary gap-2">
                                    <div class="text-secondary small">
                                        <i class="fa-solid fa-circle-check text-success me-1"></i>Each recipient's tags will automatically personalize on dispatch.
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary btn-sm px-3" style="background: var(--primary); border: none; border-radius: 8px;" onclick="return confirm('Dispatch email payslips to all employees with this Subject and Message?');">
                                            <i class="fa-solid fa-paper-plane me-1"></i> Bulk Email All Payslips
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- List Header with Retry Action -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div>
                    <h5 class="text-white mb-0">Employee Payslips Directory</h5>
                    <span class="text-secondary small">Sender: <strong>accounts@axioraglobalsolutions.com</strong> with dynamic vector PDF attachments.</span>
                </div>
                <div>
                    <!-- Retry Failed Emails -->
                    <form action="index.php?route=payroll/retry_failed_emails" method="POST" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                        <input type="hidden" name="run_id" value="<?php echo $selected_run_id; ?>">
                        <button type="submit" class="btn btn-outline-warning btn-sm px-3" style="border-radius: 8px;">
                            <i class="fa-solid fa-rotate-right me-1"></i> Retry Failed Emails
                        </button>
                    </form>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle border-secondary small" id="admin-payslips-table">
                    <thead>
                        <tr class="text-secondary">
                            <th>Emp ID</th>
                            <th>Name & Registered Email</th>
                            <th>Job Title</th>
                            <th class="text-end">Gross</th>
                            <th class="text-end">Deductions</th>
                            <th class="text-end">Net Pay</th>
                            <th class="text-center">Email Status & Record</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($all_payslips)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-secondary">No payslips calculated for this run.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($all_payslips as $slip): ?>
                                <tr>
                                    <td class="font-monospace text-secondary"><?php echo htmlspecialchars($slip->employee_code); ?></td>
                                    <td>
                                        <div class="font-weight-bold text-white"><?php echo htmlspecialchars($slip->name); ?></div>
                                        <div class="text-secondary font-monospace" style="font-size: 0.75rem;"><?php echo htmlspecialchars($slip->email ?: 'No email configured'); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($slip->job_title ?: 'Staff'); ?></td>
                                    <td class="text-end font-monospace">₹<?php echo number_format($slip->gross_salary); ?></td>
                                    <td class="text-end font-monospace text-warning">₹<?php echo number_format($slip->total_deductions); ?></td>
                                    <td class="text-end font-monospace text-success font-weight-bold">₹<?php echo number_format($slip->net_salary); ?></td>
                                    <td class="text-center">
                                        <?php if (($slip->email_status ?? 'pending') === 'sent'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2" title="<?php echo !empty($slip->email_sent_at) ? 'Sent at ' . $slip->email_sent_at : 'Delivered'; ?>">
                                                <i class="fa-solid fa-check me-1"></i> Sent
                                            </span>
                                            <?php if (!empty($slip->email_subject)): ?>
                                                <button type="button" class="btn btn-outline-info btn-xs py-0 px-1 ms-1 text-decoration-none btn-view-record" 
                                                    data-name="<?php echo htmlspecialchars($slip->name, ENT_QUOTES); ?>"
                                                    data-email="<?php echo htmlspecialchars($slip->email, ENT_QUOTES); ?>"
                                                    data-subject="<?php echo htmlspecialchars($slip->email_subject, ENT_QUOTES); ?>"
                                                    data-body="<?php echo htmlspecialchars($slip->email_body ?? '', ENT_QUOTES); ?>"
                                                    data-sent-at="<?php echo htmlspecialchars($slip->email_sent_at ?? '', ENT_QUOTES); ?>"
                                                    title="View Dispatched Email Record">
                                                    <i class="fa-solid fa-file-lines"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php elseif (($slip->email_status ?? 'pending') === 'failed'): ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle py-1 px-2" title="<?php echo htmlspecialchars($slip->email_error ?: 'Delivery error'); ?>">
                                                <i class="fa-solid fa-triangle-exclamation me-1"></i> Failed
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary text-secondary-light border border-secondary py-1 px-2">
                                                <i class="fa-solid fa-clock me-1"></i> Pending
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="index.php?route=payroll/payslip_view/<?php echo $slip->payroll_detail_id; ?>" target="_blank" class="btn btn-outline-light py-1 px-2" title="View Printable Payslip">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                            <a href="index.php?route=payroll/payslip_view/<?php echo $slip->payroll_detail_id; ?>&download=pdf" class="btn btn-outline-info py-1 px-2" title="Download PDF Payslip">
                                                <i class="fa-solid fa-file-pdf"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-success py-1 px-2 btn-open-single-email"
                                                data-detail-id="<?php echo $slip->payroll_detail_id; ?>"
                                                data-name="<?php echo htmlspecialchars($slip->name, ENT_QUOTES); ?>"
                                                data-email="<?php echo htmlspecialchars($slip->email, ENT_QUOTES); ?>"
                                                data-emp-code="<?php echo htmlspecialchars($slip->employee_code, ENT_QUOTES); ?>"
                                                data-net-pay="<?php echo number_format($slip->net_salary, 2); ?>"
                                                data-gross-pay="<?php echo number_format($slip->gross_salary, 2); ?>"
                                                data-month="<?php echo htmlspecialchars($runMonthFormatted, ENT_QUOTES); ?>"
                                                title="Email Payslip with Custom Subject & Message Box">
                                                <i class="fa-solid fa-envelope"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5 text-secondary">
                <i class="fa-solid fa-folder-open mb-2 fs-3"></i>
                <p>Select a released payroll run to view all employee payslips and manage email distribution.</p>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- Standard Employee Payslips View -->
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle border-secondary text-white">
                <thead>
                    <tr class="text-secondary">
                        <th>Payroll Period</th>
                        <th>Working Days</th>
                        <th>Present Days</th>
                        <th class="text-end">Gross Payout</th>
                        <th class="text-end">Deductions</th>
                        <th class="text-end">Net Salary</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($my_payslips)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-secondary">No payslips have been released for your account yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($my_payslips as $slip): ?>
                            <tr>
                                <td class="font-weight-bold text-white font-monospace">
                                    <?php echo htmlspecialchars(PayslipHelper::formatMonthYear($slip->month_year)); ?>
                                    <span class="text-secondary small d-block"><?php echo htmlspecialchars($slip->month_year); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($slip->working_days); ?></td>
                                <td><?php echo htmlspecialchars($slip->present_days); ?></td>
                                <td class="font-monospace text-end">₹<?php echo number_format($slip->gross_salary); ?></td>
                                <td class="text-end font-monospace text-warning">₹<?php echo number_format($slip->total_deductions); ?></td>
                                <td class="text-end font-weight-bold text-success font-monospace">₹<?php echo number_format($slip->net_salary); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-success-subtle text-success border border-success-subtle text-uppercase" style="font-size: 0.65rem;">
                                        <?php echo htmlspecialchars($slip->payment_status); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="index.php?route=payroll/payslip_view/<?php echo $slip->payroll_detail_id; ?>" target="_blank" class="btn btn-outline-info py-1 px-3">
                                            <i class="fa-solid fa-receipt me-1"></i>View
                                        </a>
                                        <a href="index.php?route=payroll/payslip_view/<?php echo $slip->payroll_detail_id; ?>&download=pdf" class="btn btn-outline-light py-1 px-2" title="Download PDF">
                                            <i class="fa-solid fa-file-pdf"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal 1: Single Employee Custom Email Dispatch Modal -->
<div class="modal fade" id="singleEmailModal" tabindex="-1" aria-labelledby="singleEmailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-white border-secondary shadow-lg" style="border-radius: 12px;">
            <form action="" method="POST" id="singleEmailForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <div class="modal-header border-secondary py-3">
                    <h5 class="modal-title fs-6 d-flex align-items-center gap-2" id="singleEmailModalLabel">
                        <i class="fa-solid fa-paper-plane text-primary"></i>
                        <span>Email Payslip to <strong id="modalEmpName" class="text-info"></strong></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-secondary small font-weight-bold mb-1">Recipient Email</label>
                            <input type="text" id="modalEmpEmail" class="form-control form-control-sm bg-black bg-opacity-50 border-secondary text-secondary font-monospace" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small font-weight-bold mb-1">Employee ID</label>
                            <input type="text" id="modalEmpCode" class="form-control form-control-sm bg-black bg-opacity-50 border-secondary text-secondary font-monospace" readonly>
                        </div>

                        <!-- Email Subject Box -->
                        <div class="col-12">
                            <label for="modalSubject" class="form-label text-white small font-weight-bold mb-1 d-flex justify-content-between">
                                <span><i class="fa-solid fa-heading me-1 text-primary"></i>Email Subject Box</span>
                                <span class="text-secondary" style="font-size: 0.72rem;">You can freely edit or customize this subject</span>
                            </label>
                            <input type="text" name="email_subject" id="modalSubject" class="form-control bg-black bg-opacity-50 border-secondary text-white font-monospace" required>
                        </div>

                        <!-- Email Message / Body Box -->
                        <div class="col-12">
                            <label for="modalBody" class="form-label text-white small font-weight-bold mb-1 d-flex justify-content-between">
                                <span><i class="fa-solid fa-align-left me-1 text-success"></i>Email Text / Message Body Box</span>
                                <span class="text-secondary" style="font-size: 0.72rem;">Official PDF Payslip is attached automatically</span>
                            </label>
                            <textarea name="email_body" id="modalBody" rows="7" class="form-control bg-black bg-opacity-50 border-secondary text-white font-monospace" style="font-size: 0.82rem;" required></textarea>
                        </div>

                        <!-- Record Note / Remarks Box -->
                        <div class="col-12">
                            <label for="modalNotes" class="form-label text-white small font-weight-bold mb-1">
                                <i class="fa-solid fa-bookmark me-1 text-warning"></i>Audit Record Note / Remarks Box
                            </label>
                            <input type="text" name="email_notes" id="modalNotes" class="form-control bg-black bg-opacity-50 border-secondary text-white" placeholder="Optional audit record note or special remark for employee">
                            <span class="text-secondary small" style="font-size: 0.72rem;">This note will be recorded in the database history and included in the message.</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm px-4" style="border-radius: 8px;">
                        <i class="fa-solid fa-paper-plane me-1"></i> Send Email with PDF Attachment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: View Dispatched Email Record Modal -->
<div class="modal fade" id="emailRecordModal" tabindex="-1" aria-labelledby="emailRecordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-white border-secondary shadow-lg" style="border-radius: 12px;">
            <div class="modal-header border-secondary py-3">
                <h5 class="modal-title fs-6 d-flex align-items-center gap-2" id="emailRecordModalLabel">
                    <i class="fa-solid fa-file-lines text-info"></i>
                    <span>Dispatched Email Record: <strong id="recordEmpName" class="text-white"></strong></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3 d-flex flex-wrap justify-content-between align-items-center bg-black bg-opacity-50 p-2 rounded border border-secondary small">
                    <div>
                        <span class="text-secondary">Sent To:</span> <strong id="recordEmpEmail" class="text-white font-monospace"></strong>
                    </div>
                    <div>
                        <span class="text-secondary">Dispatched At:</span> <strong id="recordSentAt" class="text-success font-monospace"></strong>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small font-weight-bold mb-1">Dispatched Email Subject</label>
                    <input type="text" id="recordSubject" class="form-control bg-black bg-opacity-50 border-secondary text-white font-monospace" readonly>
                </div>
                <div class="mb-2">
                    <label class="form-label text-secondary small font-weight-bold mb-1">Dispatched Message Content</label>
                    <textarea id="recordBody" rows="8" class="form-control bg-black bg-opacity-50 border-secondary text-white font-monospace" style="font-size: 0.82rem;" readonly></textarea>
                </div>
            </div>
            <div class="modal-footer border-secondary py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Helper to insert tag into input or textarea
function insertTag(fieldId, tag) {
    const el = document.getElementById(fieldId);
    if (!el) return;
    const start = el.selectionStart || el.value.length;
    const end = el.selectionEnd || el.value.length;
    const text = el.value;
    el.value = text.substring(0, start) + tag + text.substring(end);
    el.focus();
    el.setSelectionRange(start + tag.length, start + tag.length);
}

$(document).ready(function() {
    if ($('#admin-payslips-table').length) {
        $('#admin-payslips-table').DataTable({
            "pageLength": 10,
            "lengthChange": false,
            "info": true,
            "searching": true,
            "language": {
                "search": "Filter Payslips:"
            }
        });
    }

    // Open single employee custom email modal
    $(document).on('click', '.btn-open-single-email', function() {
        const btn = $(this);
        const detailId = btn.data('detail-id');
        const name = btn.data('name');
        const email = btn.data('email');
        const empCode = btn.data('emp-code');
        const netPay = btn.data('net-pay');
        const grossPay = btn.data('gross-pay');
        const month = btn.data('month');

        $('#singleEmailForm').attr('action', 'index.php?route=payroll/send_single_email/' + detailId);
        $('#modalEmpName').text(name);
        $('#modalEmpEmail').val(email || 'No email configured');
        $('#modalEmpCode').val(empCode);
        $('#modalSubject').val('Payslip for ' + name + ' - ' + month);
        
        const defaultBody = "Dear " + name + ",\n\n" +
            "Please find attached your official payslip for the month of " + month + ".\n\n" +
            "Payroll Summary:\n" +
            "----------------------------------------\n" +
            "Employee ID    : " + empCode + "\n" +
            "Gross Earnings : Rs. " + grossPay + "\n" +
            "Net Salary     : Rs. " + netPay + "\n" +
            "----------------------------------------\n\n" +
            "Please find your official detailed payslip attached as a PDF document.\n\n" +
            "Warm regards,\n" +
            "Accounts & Payroll Team\n" +
            "Axiora Global Solutions\n" +
            "accounts@axioraglobalsolutions.com";

        $('#modalBody').val(defaultBody);
        $('#modalNotes').val('');

        const modal = new bootstrap.Modal(document.getElementById('singleEmailModal'));
        modal.show();
    });

    // View dispatched email record modal
    $(document).on('click', '.btn-view-record', function() {
        const btn = $(this);
        $('#recordEmpName').text(btn.data('name'));
        $('#recordEmpEmail').text(btn.data('email'));
        $('#recordSentAt').text(btn.data('sent-at') || 'Sent');
        $('#recordSubject').val(btn.data('subject') || 'N/A');
        $('#recordBody').val(btn.data('body') || 'No body recorded.');

        const modal = new bootstrap.Modal(document.getElementById('emailRecordModal'));
        modal.show();
    });
});
</script>
