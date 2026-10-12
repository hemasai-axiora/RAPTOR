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

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-white mb-1"><i class="fa-solid fa-calculator me-2" style="color: var(--primary);"></i>Salary Structures & Revision Management</h4>
            <p class="text-secondary small mb-0">Configure salary components, statutory compliance, and maintain historical revision logs.</p>
        </div>
    </div>

    <!-- Employee Selection Form -->
    <form method="GET" action="index.php" class="row g-3 align-items-end mb-4 bg-dark bg-opacity-50 p-3 rounded border border-secondary">
        <input type="hidden" name="route" value="payroll/structures">
        <div class="col-md-8">
            <label for="employee_id" class="form-label text-secondary font-weight-bold small text-uppercase">Select Employee</label>
            <select name="employee_id" id="employee_id" class="form-select bg-dark border-secondary text-white" onchange="this.form.submit()">
                <option value="">-- Choose Employee to Configure or View History --</option>
                <?php foreach ($employees as $emp): ?>
                    <option value="<?php echo $emp->employee_id; ?>" <?php echo $selected_employee_id === (int)$emp->employee_id ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($emp->name) . " (" . htmlspecialchars($emp->employee_code) . ")" . (!empty($emp->job_title) ? " — " . htmlspecialchars($emp->job_title) : ""); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <button type="submit" class="btn btn-primary w-100" style="background: var(--primary); border:none;">
                <i class="fa-solid fa-arrow-right-to-bracket me-2"></i>Load Employee Details
            </button>
        </div>
    </form>

    <?php if ($selected_employee_id > 0): ?>
        <!-- Nav Tabs for Configuration vs History -->
        <ul class="nav nav-tabs border-secondary mb-4" id="salaryTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active text-white" id="structure-tab" data-bs-toggle="tab" data-bs-target="#structure-pane" type="button" role="tab" aria-controls="structure-pane" aria-selected="true">
                    <i class="fa-solid fa-sliders me-2 text-primary"></i>Active Salary Structure
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link text-white" id="history-tab" data-bs-toggle="tab" data-bs-target="#history-pane" type="button" role="tab" aria-controls="history-pane" aria-selected="false">
                    <i class="fa-solid fa-clock-rotate-left me-2 text-info"></i>Salary Revision History 
                    <span class="badge bg-info text-dark rounded-pill ms-2"><?php echo count($revisions); ?></span>
                </button>
            </li>
        </ul>

        <div class="tab-content" id="salaryTabsContent">
            <!-- TAB 1: Structure Configuration Form -->
            <div class="tab-pane fade show active" id="structure-pane" role="tabpanel" aria-labelledby="structure-tab">
                <form action="index.php?route=payroll/save_structure" method="POST" id="salary-structure-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                    <input type="hidden" name="employee_id" value="<?php echo $selected_employee_id; ?>">

                    <div class="row g-4">
                        <!-- Employee Banner Summary -->
                        <div class="col-12">
                            <div class="p-3 rounded bg-dark border border-secondary d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="text-white mb-1"><?php echo htmlspecialchars($selected_employee->name ?? 'Employee'); ?></h5>
                                    <div class="text-secondary small">
                                        <span class="me-3"><i class="fa-solid fa-id-badge me-1"></i><?php echo htmlspecialchars($selected_employee->employee_code ?? ''); ?></span>
                                        <span class="me-3"><i class="fa-solid fa-briefcase me-1"></i><?php echo htmlspecialchars($selected_employee->job_title ?? 'Staff'); ?></span>
                                        <span class="me-3"><i class="fa-solid fa-building me-1"></i><?php echo htmlspecialchars($selected_employee->department ?? 'General'); ?></span>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                                        <i class="fa-solid fa-circle-check me-1"></i>Active Structure
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- General & Revision Metadata -->
                        <div class="col-md-4">
                            <label class="form-label text-secondary small font-weight-bold">Salary Type</label>
                            <select name="salary_type" class="form-select bg-dark border-secondary text-white">
                                <option value="Monthly" <?php echo ($structure && $structure->salary_type === 'Monthly') ? 'selected' : ''; ?>>Monthly Retainer (Fixed)</option>
                                <option value="Hourly" <?php echo ($structure && $structure->salary_type === 'Hourly') ? 'selected' : ''; ?>>Hourly Wages</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-secondary small font-weight-bold">Revision Effective Date *</label>
                            <input type="date" name="effective_date" class="form-control bg-dark border-secondary text-white" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-secondary small font-weight-bold">Revision Reason / Remarks *</label>
                            <input type="text" name="revision_reason" class="form-control bg-dark border-secondary text-white" placeholder="e.g. Annual Appraisal 2026, Promotion" value="Salary Structure Update" required>
                        </div>

                        <!-- 1. Earnings Components -->
                        <div class="col-12 mt-4">
                            <h5 class="text-white border-bottom border-secondary pb-2 mb-3">
                                <i class="fa-solid fa-wallet me-2 text-success"></i>Earnings Components (Monthly)
                            </h5>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label text-secondary small">Basic Salary *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-secondary">₹</span>
                                <input type="number" step="1" name="basic_salary" id="basic_salary" class="form-control bg-dark border-secondary text-white calc-gross" value="<?php echo $structure ? round((float)$structure->basic_salary) : '25000'; ?>" required>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label text-secondary small">House Rent Allowance (HRA)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-secondary">₹</span>
                                <input type="number" step="1" name="hra" id="hra" class="form-control bg-dark border-secondary text-white calc-gross" value="<?php echo $structure ? round((float)$structure->hra) : '10000'; ?>">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label text-secondary small">Special Allowance</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-secondary">₹</span>
                                <input type="number" step="1" name="special_allowance" id="special_allowance" class="form-control bg-dark border-secondary text-white calc-gross" value="<?php echo $structure ? round((float)$structure->special_allowance) : '5000'; ?>">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label text-secondary small">Other Allowance</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-secondary">₹</span>
                                <input type="number" step="1" name="other_allowance" id="other_allowance" class="form-control bg-dark border-secondary text-white calc-gross" value="<?php echo $structure ? round((float)($structure->other_allowance ?? 0)) : '3000'; ?>">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label text-secondary small">Medical Allowance</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-secondary">₹</span>
                                <input type="number" step="1" name="medical_allowance" id="medical_allowance" class="form-control bg-dark border-secondary text-white calc-gross" value="<?php echo $structure ? round((float)$structure->medical_allowance) : '1250'; ?>">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label text-secondary small">Travel / Conveyance Allowance</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-secondary">₹</span>
                                <input type="number" step="1" name="travel_allowance" id="travel_allowance" class="form-control bg-dark border-secondary text-white calc-gross" value="<?php echo $structure ? round((float)$structure->travel_allowance) : '1600'; ?>">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label text-secondary small">Regular Monthly Bonus</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-secondary">₹</span>
                                <input type="number" step="1" name="bonus" id="bonus" class="form-control bg-dark border-secondary text-white calc-gross" value="<?php echo $structure ? round((float)$structure->bonus) : '0'; ?>">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label text-secondary small">Other Earnings</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-secondary">₹</span>
                                <input type="number" step="1" name="other_earnings" id="other_earnings" class="form-control bg-dark border-secondary text-white calc-gross" value="<?php echo $structure ? round((float)$structure->other_earnings) : '0'; ?>">
                            </div>
                        </div>

                        <!-- 2. Deductions Components -->
                        <div class="col-12 mt-4">
                            <h5 class="text-white border-bottom border-secondary pb-2 mb-3">
                                <i class="fa-solid fa-receipt me-2 text-warning"></i>Employee Deductions
                            </h5>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label text-secondary small">EPF Contribution (Employee 12%)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-secondary">₹</span>
                                <input type="number" step="1" name="pf" id="pf" class="form-control bg-dark border-secondary text-white calc-ded" value="<?php echo $structure ? round((float)$structure->pf) : '3000'; ?>">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label text-secondary small">Professional Tax (PT)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-secondary">₹</span>
                                <input type="number" step="1" name="professional_tax" id="professional_tax" class="form-control bg-dark border-secondary text-white calc-ded" value="<?php echo $structure ? round((float)$structure->professional_tax) : '200'; ?>">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label text-secondary small">ESIC Contribution</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-secondary">₹</span>
                                <input type="number" step="1" name="esic" id="esic" class="form-control bg-dark border-secondary text-white calc-ded" value="<?php echo $structure ? round((float)$structure->esic) : '0'; ?>">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label text-secondary small">Tax Deducted at Source (TDS)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-secondary">₹</span>
                                <input type="number" step="1" name="tds" id="tds" class="form-control bg-dark border-secondary text-white calc-ded" value="<?php echo $structure ? round((float)$structure->tds) : '0'; ?>">
                            </div>
                        </div>

                        <!-- 3. Employee Benefits (Employer Share & CTC) -->
                        <div class="col-12 mt-4">
                            <h5 class="text-white border-bottom border-secondary pb-2 mb-3">
                                <i class="fa-solid fa-hand-holding-dollar me-2 text-info"></i>Employee Benefits & CTC Additions
                            </h5>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-secondary small">Employer EPF Contribution (12%)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-secondary">₹</span>
                                <input type="number" step="1" name="employer_pf" id="employer_pf" class="form-control bg-dark border-secondary text-white calc-ben" value="<?php echo $structure ? round((float)($structure->employer_pf ?? $structure->pf)) : '3000'; ?>">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-secondary small">Gratuity (4.81% of Basic)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-secondary">₹</span>
                                <input type="number" step="1" name="gratuity" id="gratuity" class="form-control bg-dark border-secondary text-white calc-ben" value="<?php echo $structure ? round((float)($structure->gratuity ?? (((float)$structure->basic_salary)*0.0481))) : '1203'; ?>">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-secondary small">Total Monthly Benefits</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-secondary">₹</span>
                                <input type="text" id="lbl-total-benefits" class="form-control bg-dark border-secondary text-info font-weight-bold" readonly value="4203">
                            </div>
                        </div>

                        <!-- Dynamic Summary Dashboard -->
                        <div class="col-12 mt-4">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <div class="p-3 bg-dark border border-secondary rounded text-center">
                                        <span class="text-secondary small d-block">Gross Earnings</span>
                                        <h4 class="text-white mb-0" id="card-gross">₹0.00</h4>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="p-3 bg-dark border border-secondary rounded text-center">
                                        <span class="text-secondary small d-block">Total Deductions</span>
                                        <h4 class="text-warning mb-0" id="card-ded">₹0.00</h4>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="p-3 bg-dark border border-secondary rounded text-center">
                                        <span class="text-secondary small d-block">Effective Monthly CTC</span>
                                        <h4 class="text-info mb-0" id="card-ctc">₹0.00</h4>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="p-3 bg-success bg-opacity-10 border border-success rounded text-center">
                                        <span class="text-success small d-block font-weight-bold">Net Monthly Take-Home</span>
                                        <h3 class="text-success mb-0" id="card-net">₹0.00</h3>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Buttons -->
                        <div class="col-12 d-flex justify-content-between align-items-center mt-4">
                            <button type="button" class="btn btn-outline-info" id="btn-auto-calc">
                                <i class="fa-solid fa-wand-magic-sparkles me-2"></i>Auto-Calculate Statutory (PF/Gratuity/HRA)
                            </button>
                            <div class="d-flex gap-3">
                                <button type="button" class="btn btn-outline-warning px-4" id="btn-random-salary">
                                    <i class="fa-solid fa-dice me-2"></i>Generate Sample Scale
                                </button>
                                <button type="submit" class="btn btn-primary px-4 py-2" style="background: var(--primary); border: none;">
                                    <i class="fa-solid fa-floppy-disk me-2"></i>Save & Record Revision
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- TAB 2: Revision History Timeline -->
            <div class="tab-pane fade" id="history-pane" role="tabpanel" aria-labelledby="history-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="text-white mb-0"><i class="fa-solid fa-timeline me-2 text-info"></i>Audit Trail & Revision History</h5>
                    <span class="text-secondary small">Previous records are permanently preserved and locked for historical payroll reproducibility.</span>
                </div>

                <?php if (empty($revisions)): ?>
                    <div class="text-center py-5 bg-dark bg-opacity-50 rounded border border-secondary text-secondary">
                        <i class="fa-solid fa-folder-open mb-2 fs-3"></i>
                        <p class="mb-0">No historical revisions recorded for this employee yet.</p>
                        <small>Revisions are automatically tracked each time the salary structure is updated.</small>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle border-secondary small">
                            <thead>
                                <tr class="text-secondary">
                                    <th>Rev #</th>
                                    <th>Revision Date</th>
                                    <th>Effective Date</th>
                                    <th>Reason / Remarks</th>
                                    <th class="text-end">Basic Salary</th>
                                    <th class="text-end">Gross Salary</th>
                                    <th class="text-end">Monthly CTC</th>
                                    <th>Revised By</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($revisions as $idx => $rev): ?>
                                    <tr>
                                        <td class="font-monospace text-secondary">#<?php echo count($revisions) - $idx; ?></td>
                                        <td><?php echo date('d M Y', strtotime($rev->revision_date)); ?></td>
                                        <td>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                                <?php echo date('d M Y', strtotime($rev->effective_date)); ?>
                                            </span>
                                        </td>
                                        <td class="text-white font-weight-bold"><?php echo htmlspecialchars($rev->reason ?: 'Salary Structure Revision'); ?></td>
                                        <td class="text-end font-monospace">
                                            <?php if ((float)$rev->previous_basic > 0 && (float)$rev->previous_basic != (float)$rev->new_basic): ?>
                                                <span class="text-secondary text-decoration-line-through me-1">₹<?php echo number_format($rev->previous_basic); ?></span>
                                                <span class="text-success font-weight-bold">₹<?php echo number_format($rev->new_basic); ?></span>
                                            <?php else: ?>
                                                ₹<?php echo number_format($rev->new_basic); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end font-monospace">
                                            <?php if ((float)$rev->previous_gross > 0 && (float)$rev->previous_gross != (float)$rev->new_gross): ?>
                                                <span class="text-secondary text-decoration-line-through me-1">₹<?php echo number_format($rev->previous_gross); ?></span>
                                                <span class="text-info font-weight-bold">₹<?php echo number_format($rev->new_gross); ?></span>
                                            <?php else: ?>
                                                ₹<?php echo number_format($rev->new_gross); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end font-monospace font-weight-bold text-white">
                                            ₹<?php echo number_format($rev->new_ctc); ?>
                                        </td>
                                        <td>
                                            <span class="text-secondary small"><i class="fa-solid fa-user me-1"></i><?php echo htmlspecialchars($rev->revised_by_name ?: 'System Admin'); ?></span>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-outline-light btn-sm py-0 px-2" onclick="showRevisionModal(<?php echo htmlspecialchars(json_encode($rev)); ?>)">
                                                <i class="fa-solid fa-eye me-1"></i>Details
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Revision Detail Modal -->
        <div class="modal fade" id="revisionDetailModal" tabindex="-1" aria-labelledby="revisionDetailModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content bg-dark border-secondary text-white">
                    <div class="modal-header border-secondary">
                        <h5 class="modal-title" id="revisionDetailModalLabel"><i class="fa-solid fa-receipt me-2 text-info"></i>Salary Revision Snapshot</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="modal-revision-body">
                        <!-- Populated by JavaScript -->
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <div class="text-center py-5 text-secondary">
            <i class="fa-solid fa-arrow-pointer mb-2 fs-3"></i>
            <p>Please select an employee from the dropdown list to manage their salary structure and view revision history.</p>
        </div>
    <?php endif; ?>
</div>

<script>
$(document).ready(function() {
    function recalcSummary() {
        const basic = parseFloat($('#basic_salary').val()) || 0;
        const hra = parseFloat($('#hra').val()) || 0;
        const special = parseFloat($('#special_allowance').val()) || 0;
        const otherAll = parseFloat($('#other_allowance').val()) || 0;
        const med = parseFloat($('#medical_allowance').val()) || 0;
        const travel = parseFloat($('#travel_allowance').val()) || 0;
        const bonus = parseFloat($('#bonus').val()) || 0;
        const otherEarn = parseFloat($('#other_earnings').val()) || 0;

        const gross = basic + hra + special + otherAll + med + travel + bonus + otherEarn;

        const pf = parseFloat($('#pf').val()) || 0;
        const esic = parseFloat($('#esic').val()) || 0;
        const pt = parseFloat($('#professional_tax').val()) || 0;
        const tds = parseFloat($('#tds').val()) || 0;

        const ded = pf + esic + pt + tds;

        const empPf = parseFloat($('#employer_pf').val()) || 0;
        const grat = parseFloat($('#gratuity').val()) || 0;
        const totalBen = empPf + grat;

        const ctc = gross + totalBen;
        const net = gross - ded;

        $('#card-gross').text('₹' + Math.round(gross).toLocaleString('en-IN'));
        $('#card-ded').text('₹' + Math.round(ded).toLocaleString('en-IN'));
        $('#card-ctc').text('₹' + Math.round(ctc).toLocaleString('en-IN'));
        $('#card-net').text('₹' + Math.round(net).toLocaleString('en-IN'));
        $('#lbl-total-benefits').val(Math.round(totalBen));
    }

    $('input.calc-gross, input.calc-ded, input.calc-ben').on('input change', recalcSummary);
    recalcSummary();

    $('#btn-auto-calc').on('click', function() {
        const basic = Math.round(parseFloat($('#basic_salary').val()) || 0);
        if (basic > 0) {
            const hra = Math.round(basic * 0.40);
            const pf = Math.round(basic * 0.12);
            const empPf = pf;
            const gratuity = Math.round(basic * 0.0481);

            $('#hra').val(hra);
            $('#pf').val(pf);
            $('#employer_pf').val(empPf);
            $('#gratuity').val(gratuity);
            $('#professional_tax').val('200');
            recalcSummary();
        }
    });

    $('#btn-random-salary').on('click', function() {
        const basic = Math.floor(Math.random() * (45000 - 20000 + 1)) + 20000;
        const hra = Math.round(basic * 0.40);
        const special = Math.round(basic * 0.20);
        const otherAll = Math.round(basic * 0.10);
        const pf = Math.round(basic * 0.12);
        const empPf = pf;
        const gratuity = Math.round(basic * 0.0481);

        $('#basic_salary').val(basic);
        $('#hra').val(hra);
        $('#special_allowance').val(special);
        $('#other_allowance').val(otherAll);
        $('#medical_allowance').val('1250');
        $('#travel_allowance').val('1600');
        $('#bonus').val('0');
        $('#other_earnings').val('0');

        $('#pf').val(pf);
        $('#employer_pf').val(empPf);
        $('#gratuity').val(gratuity);
        $('#professional_tax').val('200');
        $('#esic').val('0');
        $('#tds').val('0');

        recalcSummary();
    });
});

function showRevisionModal(rev) {
    let html = `
        <div class="row g-3">
            <div class="col-md-6">
                <div class="p-3 bg-dark bg-opacity-50 border border-secondary rounded">
                    <span class="text-secondary small d-block">Effective Date</span>
                    <strong class="text-white">${rev.effective_date}</strong>
                </div>
            </div>
            <div class="col-md-6">
                <div class="p-3 bg-dark bg-opacity-50 border border-secondary rounded">
                    <span class="text-secondary small d-block">Revised By</span>
                    <strong class="text-white">${rev.revised_by_name || 'System Admin'} (${rev.revision_date})</strong>
                </div>
            </div>
            <div class="col-12">
                <div class="p-3 bg-dark bg-opacity-50 border border-secondary rounded">
                    <span class="text-secondary small d-block">Reason / Remarks</span>
                    <strong class="text-info">${rev.reason || 'Salary Structure Revision'}</strong>
                </div>
            </div>
            <div class="col-12">
                <table class="table table-dark table-bordered border-secondary small mb-0">
                    <thead>
                        <tr class="text-secondary">
                            <th>Component</th>
                            <th class="text-end">Previous Scale</th>
                            <th class="text-end">Revised Scale</th>
                            <th class="text-end">Change</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Basic Salary</td>
                            <td class="text-end font-monospace">₹${Math.round(parseFloat(rev.previous_basic || 0)).toLocaleString('en-IN')}</td>
                            <td class="text-end font-monospace text-success font-weight-bold">₹${Math.round(parseFloat(rev.new_basic || 0)).toLocaleString('en-IN')}</td>
                            <td class="text-end font-monospace">${(Math.round(parseFloat(rev.new_basic||0)) - Math.round(parseFloat(rev.previous_basic||0))) >= 0 ? '+' : ''}${(Math.round(parseFloat(rev.new_basic||0)) - Math.round(parseFloat(rev.previous_basic||0))).toLocaleString('en-IN')}</td>
                        </tr>
                        <tr>
                            <td>HRA</td>
                            <td class="text-end font-monospace">₹${Math.round(parseFloat(rev.previous_hra || 0)).toLocaleString('en-IN')}</td>
                            <td class="text-end font-monospace text-success font-weight-bold">₹${Math.round(parseFloat(rev.new_hra || 0)).toLocaleString('en-IN')}</td>
                            <td class="text-end font-monospace">${(Math.round(parseFloat(rev.new_hra||0)) - Math.round(parseFloat(rev.previous_hra||0))) >= 0 ? '+' : ''}${(Math.round(parseFloat(rev.new_hra||0)) - Math.round(parseFloat(rev.previous_hra||0))).toLocaleString('en-IN')}</td>
                        </tr>
                        <tr>
                            <td>Special Allowance</td>
                            <td class="text-end font-monospace">₹${Math.round(parseFloat(rev.previous_special_allowance || 0)).toLocaleString('en-IN')}</td>
                            <td class="text-end font-monospace text-success font-weight-bold">₹${Math.round(parseFloat(rev.new_special_allowance || 0)).toLocaleString('en-IN')}</td>
                            <td class="text-end font-monospace">${(Math.round(parseFloat(rev.new_special_allowance||0)) - Math.round(parseFloat(rev.previous_special_allowance||0))) >= 0 ? '+' : ''}${(Math.round(parseFloat(rev.new_special_allowance||0)) - Math.round(parseFloat(rev.previous_special_allowance||0))).toLocaleString('en-IN')}</td>
                        </tr>
                        <tr>
                            <td>Other Allowance</td>
                            <td class="text-end font-monospace">₹${Math.round(parseFloat(rev.previous_other_allowance || 0)).toLocaleString('en-IN')}</td>
                            <td class="text-end font-monospace text-success font-weight-bold">₹${Math.round(parseFloat(rev.new_other_allowance || 0)).toLocaleString('en-IN')}</td>
                            <td class="text-end font-monospace">${(Math.round(parseFloat(rev.new_other_allowance||0)) - Math.round(parseFloat(rev.previous_other_allowance||0))) >= 0 ? '+' : ''}${(Math.round(parseFloat(rev.new_other_allowance||0)) - Math.round(parseFloat(rev.previous_other_allowance||0))).toLocaleString('en-IN')}</td>
                        </tr>
                        <tr class="table-active font-weight-bold">
                            <td>Gross Monthly Salary</td>
                            <td class="text-end font-monospace">₹${Math.round(parseFloat(rev.previous_gross || 0)).toLocaleString('en-IN')}</td>
                            <td class="text-end font-monospace text-info font-weight-bold">₹${Math.round(parseFloat(rev.new_gross || 0)).toLocaleString('en-IN')}</td>
                            <td class="text-end font-monospace text-info">${(Math.round(parseFloat(rev.new_gross||0)) - Math.round(parseFloat(rev.previous_gross||0))) >= 0 ? '+' : ''}${(Math.round(parseFloat(rev.new_gross||0)) - Math.round(parseFloat(rev.previous_gross||0))).toLocaleString('en-IN')}</td>
                        </tr>
                        <tr class="table-active font-weight-bold">
                            <td>Effective Monthly CTC</td>
                            <td class="text-end font-monospace">₹${Math.round(parseFloat(rev.previous_ctc || 0)).toLocaleString('en-IN')}</td>
                            <td class="text-end font-monospace text-primary font-weight-bold">₹${Math.round(parseFloat(rev.new_ctc || 0)).toLocaleString('en-IN')}</td>
                            <td class="text-end font-monospace text-primary">${(Math.round(parseFloat(rev.new_ctc||0)) - Math.round(parseFloat(rev.previous_ctc||0))) >= 0 ? '+' : ''}${(Math.round(parseFloat(rev.new_ctc||0)) - Math.round(parseFloat(rev.previous_ctc||0))).toLocaleString('en-IN')}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    `;
    $('#modal-revision-body').html(html);
    const modal = new bootstrap.Modal(document.getElementById('revisionDetailModal'));
    modal.show();
}
</script>
