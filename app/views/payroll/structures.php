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

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="text-white mb-1"><i class="fa-solid fa-calculator me-2" style="color: var(--primary);"></i>Salary Structures & Revision Management</h4>
            <p class="text-secondary small mb-0">Configure salary components, statutory compliance, and maintain historical revision logs.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle btn-sm px-3 py-2 text-white" type="button" id="templateDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="border-color: rgba(255,255,255,0.2);">
                    <i class="fa-solid fa-download me-1 text-info"></i> Download Template
                </button>
                <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end shadow" aria-labelledby="templateDropdown">
                    <li><h6 class="dropdown-header text-uppercase small">CSV Spreadsheet Templates</h6></li>
                    <li>
                        <a class="dropdown-item py-2" href="index.php?route=payroll/download_salary_template">
                            <i class="fa-solid fa-users me-2 text-primary"></i> Pre-filled with Active Employees
                            <div class="text-muted small ps-4">Includes employee codes, names & current pay</div>
                        </a>
                    </li>
                    <li><hr class="dropdown-divider border-secondary"></li>
                    <li>
                        <a class="dropdown-item py-2" href="index.php?route=payroll/download_salary_template&blank=1">
                            <i class="fa-solid fa-file-csv me-2 text-secondary"></i> Blank Template with Samples
                            <div class="text-muted small ps-4">Empty template with instructional sample rows</div>
                        </a>
                    </li>
                </ul>
            </div>
            <button type="button" class="btn btn-primary btn-sm px-3 py-2 shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#bulkSalaryUploadModal" style="background: linear-gradient(135deg, #1F5FAE, #7c3aed); border: none;">
                <i class="fa-solid fa-cloud-arrow-up me-2"></i>Bulk Upload Structures
            </button>
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

                        <!-- Quick CTC Structure Setup -->
                        <div class="col-12">
                            <div class="p-3 rounded border border-primary border-opacity-50" style="background: linear-gradient(135deg, rgba(31,95,174,0.15), rgba(124,58,237,0.15));">
                                <div class="row align-items-center g-3">
                                    <div class="col-lg-4 col-md-5">
                                        <label class="form-label text-white fw-bold small text-uppercase mb-1">
                                            <i class="fa-solid fa-calculator text-info me-1"></i> Quick Setup by Monthly CTC
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-dark border-secondary text-primary fw-bold">₹</span>
                                            <input type="number" step="1" id="quick_monthly_ctc" class="form-control bg-dark border-secondary text-white fw-bold" placeholder="e.g. 35000, 45000" value="<?php 
                                                if ($structure) {
                                                    $b = (float)$structure->basic_salary;
                                                    $h = (float)$structure->hra;
                                                    $s = (float)$structure->special_allowance;
                                                    $o = (float)($structure->other_allowance ?? 0);
                                                    $ep = (float)($structure->employer_pf ?? $structure->pf);
                                                    $g = (float)($structure->gratuity ?? round($b * 0.0481));
                                                    echo round($b + $h + $s + $o + (float)$structure->medical_allowance + (float)$structure->travel_allowance + $ep + $g);
                                                } else {
                                                    echo round((float)($selected_employee->salary ?? 35000));
                                                }
                                            ?>">
                                        </div>
                                    </div>
                                    <div class="col-lg-5 col-md-4">
                                        <div class="text-secondary small">
                                            <span class="text-white fw-bold">Standard Axiora Structure Rules:</span><br>
                                            Basic (50% CTC) • HRA (50% Basic) • Special Allowance (₹1,600) • EPF (12% max ₹1,800) • Gratuity (4.81% Basic) • Other Allowance (balancing)
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-3 text-end">
                                        <button type="button" class="btn btn-primary w-100 fw-bold py-2 shadow-sm" id="btn-apply-ctc-structure" style="background: linear-gradient(135deg, #1F5FAE, #7c3aed); border: none;">
                                            <i class="fa-solid fa-wand-magic-sparkles me-2"></i>Auto-Populate Structure
                                        </button>
                                    </div>
                                </div>
                            </div>
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
        <div class="row g-4 my-3">
            <div class="col-md-6">
                <div class="p-4 rounded h-100 border border-secondary" style="background: rgba(255,255,255,0.03);">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rounded-circle p-3 me-3 text-primary" style="background: rgba(31, 95, 174, 0.15); width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-user-pen fs-5"></i>
                        </div>
                        <div>
                            <h6 class="text-white mb-0">Single Employee Configuration</h6>
                            <span class="text-secondary small">Fine-tune custom components & view revision timeline</span>
                        </div>
                    </div>
                    <p class="text-secondary small mb-3">Choose an individual employee from the dropdown selector above to edit components, statutory percentages, and view their complete revision audit trail.</p>
                    <div class="text-muted small">
                        <i class="fa-solid fa-circle-info me-1 text-info"></i> Supports automatic calculation of PF, Gratuity, ESIC & PT.
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="p-4 rounded h-100 border border-primary border-opacity-50" style="background: linear-gradient(145deg, rgba(31, 95, 174, 0.1), rgba(124, 58, 237, 0.08));">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rounded-circle p-3 me-3 text-white" style="background: linear-gradient(135deg, #1F5FAE, #7c3aed); width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-cloud-arrow-up fs-5"></i>
                        </div>
                        <div>
                            <h6 class="text-white mb-0">Bulk Upload Salary Structures</h6>
                            <span class="text-info small font-weight-bold">Configure entire organization via CSV spreadsheet</span>
                        </div>
                    </div>
                    <p class="text-secondary small mb-3">Upload a CSV spreadsheet with Basic, HRA, Special Allowance, and other earnings for all employees. The system auto-validates each row, calculates statutory compliance, and logs revision history.</p>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-primary btn-sm px-3 py-2 fw-bold" data-bs-toggle="modal" data-bs-target="#bulkSalaryUploadModal" style="background: linear-gradient(135deg, #1F5FAE, #7c3aed); border: none;">
                            <i class="fa-solid fa-cloud-arrow-up me-1"></i> Launch Bulk Upload
                        </button>
                        <a href="index.php?route=payroll/download_salary_template" class="btn btn-outline-light btn-sm px-3 py-2" style="border-color: rgba(255,255,255,0.25);">
                            <i class="fa-solid fa-download me-1"></i> Pre-filled Template (CSV)
                        </a>
                    </div>
                </div>
            </div>
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

    function applyExactStructure(ctc) {
        if (!ctc || ctc <= 0) return;
        ctc = Math.round(ctc);

        // 1. Basic = 50% of CTC
        const basic = Math.round(ctc * 0.50);

        // 2. HRA = 50% of Basic (25% of CTC)
        const hra = Math.round(basic * 0.50);

        // 3. Special Allowance = 1600 fixed across all employees
        const special = 1600;

        // 4. Employer EPF = 12% of basic capped at 1800
        const employerPf = (basic >= 15000) ? 1800 : Math.round(basic * 0.12);

        // 5. Gratuity = 4.81% of basic
        const gratuity = Math.round(basic * 0.0481);

        // 6. Total Benefits
        const benefits = employerPf + gratuity;

        // 7. Gross Earnings = CTC - Benefits
        const gross = ctc - benefits;

        // 8. Other Allowance = Gross - (Basic + HRA + Special)
        const otherAll = Math.max(0, gross - (basic + hra + special));

        // Employee Deductions: EPF matches employer PF, PT based on Telangana slabs
        const pf = employerPf;
        const pt = (gross > 20000) ? 200 : (gross > 15000 ? 150 : 0);

        $('#basic_salary').val(basic);
        $('#hra').val(hra);
        $('#special_allowance').val(special);
        $('#other_allowance').val(otherAll);
        $('#medical_allowance').val(0);
        $('#travel_allowance').val(0);
        $('#bonus').val(0);
        $('#other_earnings').val(0);

        $('#pf').val(pf);
        $('#employer_pf').val(employerPf);
        $('#gratuity').val(gratuity);
        $('#professional_tax').val(pt);
        $('#esic').val(0);
        $('#tds').val(0);

        recalcSummary();
    }

    $('#btn-apply-ctc-structure').on('click', function() {
        const ctc = parseFloat($('#quick_monthly_ctc').val()) || 0;
        if (ctc > 0) {
            applyExactStructure(ctc);
        } else {
            alert('Please enter a valid Monthly CTC amount greater than zero.');
        }
    });

    $('#quick_monthly_ctc').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#btn-apply-ctc-structure').click();
        }
    });

    $('#btn-auto-calc').on('click', function() {
        const basic = Math.round(parseFloat($('#basic_salary').val()) || 0);
        if (basic > 0) {
            // Apply exact structure based on Basic * 2 as CTC
            applyExactStructure(basic * 2);
            $('#quick_monthly_ctc').val(basic * 2);
        } else {
            const ctc = parseFloat($('#quick_monthly_ctc').val()) || 35000;
            applyExactStructure(ctc);
        }
    });

    $('#btn-random-salary').on('click', function() {
        // Choose from actual Axiora salary scale tiers
        const tiers = [15000, 17000, 17500, 23000, 25000, 30000, 35000, 45000, 50000, 100000];
        const chosenCtc = tiers[Math.floor(Math.random() * tiers.length)];
        $('#quick_monthly_ctc').val(chosenCtc);
        applyExactStructure(chosenCtc);
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

<!-- Bulk Salary Structures Upload Modal -->
<div class="modal fade" id="bulkSalaryUploadModal" tabindex="-1" aria-labelledby="bulkSalaryUploadModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content bg-dark border-secondary text-white shadow-lg" style="background: #141720 !important; border: 1px solid rgba(255,255,255,0.12) !important;">
            <div class="modal-header border-secondary py-3 px-4" style="background: rgba(255,255,255,0.02);">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle p-2 me-3" style="background: linear-gradient(135deg, rgba(31,95,174,0.3), rgba(124,58,237,0.3)); border: 1px solid rgba(124,58,237,0.4);">
                        <i class="fa-solid fa-cloud-arrow-up text-primary fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-white fw-bold mb-0" id="bulkSalaryUploadModalLabel">Bulk Upload Salary Structures</h5>
                        <p class="text-secondary small mb-0">Configure, revise, and audit salary structures across your organization via CSV spreadsheet.</p>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" id="bulk-modal-close-btn"></button>
            </div>
            
            <div class="modal-body p-4">
                <!-- STEP 1: Upload & Options -->
                <div id="bulk-step-upload">
                    <div class="row g-4">
                        <!-- Dropzone Column -->
                        <div class="col-lg-7">
                            <div class="p-4 rounded border-2 border-dashed text-center" id="salary-dropzone" style="border: 2px dashed rgba(255,255,255,0.2); background: rgba(255,255,255,0.02); cursor: pointer; transition: all 0.3s ease;">
                                <input type="file" id="salary_csv_input" accept=".csv" class="d-none">
                                <div id="dropzone-idle-state">
                                    <div class="mb-3">
                                        <i class="fa-solid fa-file-csv fa-3x" style="color: var(--primary);"></i>
                                    </div>
                                    <h6 class="text-white mb-1">Drag and drop your salary CSV file here</h6>
                                    <p class="text-secondary small mb-3">or <span class="text-primary text-decoration-underline fw-bold">browse your computer</span></p>
                                    <div class="badge bg-dark border border-secondary text-secondary px-3 py-2">
                                        <i class="fa-solid fa-shield-halved me-1"></i> Supports CSV format up to 10 MB
                                    </div>
                                </div>
                                <div id="dropzone-selected-state" class="d-none">
                                    <div class="p-3 rounded bg-dark border border-secondary d-flex align-items-center justify-content-between text-start">
                                        <div class="d-flex align-items-center">
                                            <i class="fa-solid fa-file-lines fs-3 text-success me-3"></i>
                                            <div>
                                                <div class="text-white fw-bold mb-0" id="selected-file-name">filename.csv</div>
                                                <div class="text-secondary small" id="selected-file-size">0 KB</div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-danger" id="btn-remove-selected-file" title="Remove file">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Sample Template Download Callout -->
                            <div class="mt-3 p-3 rounded border border-secondary d-flex justify-content-between align-items-center" style="background: rgba(255,255,255,0.03);">
                                <div class="d-flex align-items-center">
                                    <i class="fa-solid fa-table-list text-info fs-4 me-3"></i>
                                    <div>
                                        <div class="text-white small fw-bold">Need the correct column format?</div>
                                        <div class="text-secondary small">Download our template with pre-filled employee codes & names.</div>
                                    </div>
                                </div>
                                <div class="btn-group">
                                    <a href="index.php?route=payroll/download_salary_template" class="btn btn-outline-info btn-sm px-3" title="Template with current employees">
                                        <i class="fa-solid fa-download me-1"></i> Pre-filled CSV
                                    </a>
                                    <a href="index.php?route=payroll/download_salary_template&blank=1" class="btn btn-outline-secondary btn-sm" title="Blank Template">
                                        <i class="fa-solid fa-file-csv"></i>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Settings & Defaults Column -->
                        <div class="col-lg-5">
                            <div class="p-3 rounded bg-dark border border-secondary h-100 d-flex flex-column justify-content-between">
                                <div>
                                    <h6 class="text-white mb-3"><i class="fa-solid fa-sliders text-primary me-2"></i>Batch Settings</h6>
                                    
                                    <div class="mb-3">
                                        <label for="bulk_effective_date" class="form-label text-secondary small fw-bold text-uppercase">Effective Date</label>
                                        <input type="date" id="bulk_effective_date" class="form-control bg-dark border-secondary text-white" value="<?php echo date('Y-m-d'); ?>">
                                        <div class="form-text text-secondary" style="font-size: 0.75rem;">Used if the CSV row does not specify an effective date.</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="bulk_revision_reason" class="form-label text-secondary small fw-bold text-uppercase">Revision Reason / Remarks</label>
                                        <input type="text" id="bulk_revision_reason" class="form-control bg-dark border-secondary text-white" value="Bulk Salary Structure Upload" placeholder="e.g. Annual Appraisal 2026, Revision">
                                        <div class="form-text text-secondary" style="font-size: 0.75rem;">Logged into employee revision audit trail.</div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label text-secondary small fw-bold text-uppercase">Existing Structure Strategy</label>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="radio" name="duplicate_strategy" id="strat_update" value="update" checked>
                                            <label class="form-check-label text-white small" for="strat_update">
                                                <strong>Update & Record Revision History</strong>
                                                <div class="text-secondary" style="font-size: 0.75rem;">Updates active structure and appends full change snapshot to revision logs.</div>
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="duplicate_strategy" id="strat_skip" value="skip">
                                            <label class="form-check-label text-white small" for="strat_skip">
                                                <strong>Skip Existing Structures</strong>
                                                <div class="text-secondary" style="font-size: 0.75rem;">Only creates baseline for employees without any existing structure.</div>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="pt-3 border-t border-secondary">
                                    <button type="button" class="btn btn-primary w-100 py-2 fw-bold" id="btn-validate-upload" disabled style="background: linear-gradient(135deg, #1F5FAE, #7c3aed); border: none;">
                                        <i class="fa-solid fa-magnifying-glass-chart me-2"></i>Analyze & Preview CSV
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 2: Preview & Validation Results Table -->
                <div id="bulk-step-preview" class="d-none">
                    <!-- Metrics Row -->
                    <div class="row g-3 mb-3">
                        <div class="col-6 col-md-3">
                            <div class="p-3 rounded bg-dark border border-secondary text-center">
                                <div class="text-secondary small fw-bold text-uppercase">Total Rows</div>
                                <div class="fs-4 fw-bold text-white" id="stat-total-rows">0</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-3 rounded bg-dark border border-success border-opacity-50 text-center">
                                <div class="text-success small fw-bold text-uppercase">Valid / Ready</div>
                                <div class="fs-4 fw-bold text-success" id="stat-valid-rows">0</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-3 rounded bg-dark border border-info border-opacity-50 text-center">
                                <div class="text-info small fw-bold text-uppercase">Revisions / Updates</div>
                                <div class="fs-4 fw-bold text-info" id="stat-update-rows">0</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-3 rounded bg-dark border border-danger border-opacity-50 text-center">
                                <div class="text-danger small fw-bold text-uppercase">Errors / Issues</div>
                                <div class="fs-4 fw-bold text-danger" id="stat-error-rows">0</div>
                            </div>
                        </div>
                    </div>

                    <!-- Search & Filter Controls -->
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-secondary active preview-filter-btn" data-filter="all">All Rows (<span id="pill-all-count">0</span>)</button>
                            <button type="button" class="btn btn-outline-success preview-filter-btn" data-filter="valid">Valid (<span id="pill-valid-count">0</span>)</button>
                            <button type="button" class="btn btn-outline-info preview-filter-btn" data-filter="update">Revisions (<span id="pill-update-count">0</span>)</button>
                            <button type="button" class="btn btn-outline-danger preview-filter-btn" data-filter="error">Errors (<span id="pill-error-count">0</span>)</button>
                        </div>
                        <div class="input-group input-group-sm" style="max-width: 280px;">
                            <span class="input-group-text bg-dark border-secondary text-secondary"><i class="fa-solid fa-search"></i></span>
                            <input type="text" id="preview-search-input" class="form-control bg-dark border-secondary text-white" placeholder="Search employee code or name...">
                        </div>
                    </div>

                    <!-- Scrollable Table -->
                    <div class="table-responsive rounded border border-secondary" style="max-height: 380px;">
                        <table class="table table-dark table-hover table-striped align-middle small mb-0" id="preview-table">
                            <thead class="table-dark text-secondary sticky-top border-secondary">
                                <tr>
                                    <th style="width: 50px;">Row</th>
                                    <th>Status</th>
                                    <th>Employee</th>
                                    <th class="text-end">Basic (₹)</th>
                                    <th class="text-end">HRA (₹)</th>
                                    <th class="text-end">Allowances (₹)</th>
                                    <th class="text-end">Gross (₹)</th>
                                    <th class="text-end">Deductions (₹)</th>
                                    <th class="text-end">Net Pay (₹)</th>
                                    <th class="text-end">Monthly CTC (₹)</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody id="preview-table-body">
                                <!-- Populated dynamically -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Error Callout Banner if errors found -->
                    <div id="preview-error-alert" class="alert alert-warning border-warning border-opacity-50 mt-3 d-none mb-0 small">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>
                        <span id="preview-error-text">Some rows have errors and will be skipped. You can proceed to import the valid rows, or go back to upload a revised file.</span>
                    </div>
                </div>

                <!-- STEP 3: Progress State -->
                <div id="bulk-step-progress" class="py-5 text-center d-none">
                    <div class="spinner-border text-primary mb-3" style="width: 3.5rem; height: 3.5rem;" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <h5 class="text-white fw-bold mb-1">Applying Salary Structures...</h5>
                    <p class="text-secondary small mb-3">Updating employee compensation records, syncing directory salary scales, and recording revision audit histories.</p>
                    <div class="progress bg-secondary bg-opacity-25 mx-auto" style="height: 6px; max-width: 450px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 100%"></div>
                    </div>
                </div>

                <!-- STEP 4: Success / Results State -->
                <div id="bulk-step-result" class="py-4 text-center d-none">
                    <div class="rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 70px; height: 70px; background: rgba(25, 135, 84, 0.15); border: 2px solid #198754;">
                        <i class="fa-solid fa-circle-check fs-2 text-success"></i>
                    </div>
                    <h4 class="text-white fw-bold mb-1">Bulk Upload Completed!</h4>
                    <p class="text-secondary small mb-4" id="result-summary-text">Salary structures processed and revision histories updated successfully.</p>
                    
                    <div class="row g-3 justify-content-center mb-4">
                        <div class="col-auto">
                            <div class="px-4 py-3 rounded bg-dark border border-success border-opacity-50 text-center">
                                <div class="text-secondary small text-uppercase">Processed / Updated</div>
                                <div class="fs-4 fw-bold text-success" id="result-stat-processed">0</div>
                            </div>
                        </div>
                        <div class="col-auto">
                            <div class="px-4 py-3 rounded bg-dark border border-secondary text-center">
                                <div class="text-secondary small text-uppercase">Skipped</div>
                                <div class="fs-4 fw-bold text-secondary" id="result-stat-skipped">0</div>
                            </div>
                        </div>
                        <div class="col-auto d-none" id="result-stat-failed-box">
                            <div class="px-4 py-3 rounded bg-dark border border-danger border-opacity-50 text-center">
                                <div class="text-danger small text-uppercase">Failed</div>
                                <div class="fs-4 fw-bold text-danger" id="result-stat-failed">0</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-center gap-3">
                        <a href="index.php?route=payroll/download_salary_import_errors" class="btn btn-outline-danger btn-sm px-3 d-none" id="btn-download-import-errors">
                            <i class="fa-solid fa-file-arrow-down me-1"></i> Download Failed Rows CSV
                        </a>
                        <button type="button" class="btn btn-primary px-4" onclick="window.location.reload();" style="background: linear-gradient(135deg, #1F5FAE, #7c3aed); border: none;">
                            <i class="fa-solid fa-check me-2"></i> Done & Refresh Page
                        </button>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer border-secondary px-4 py-3" id="bulk-modal-footer">
                <div id="footer-step-1" class="d-flex justify-content-between align-items-center w-100">
                    <span class="text-secondary small"><i class="fa-solid fa-circle-info me-1 text-info"></i> Missing statutory values (PF, Gratuity, PT) will be auto-calculated.</span>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                </div>
                <div id="footer-step-2" class="d-flex justify-content-between align-items-center w-100 d-none">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-step2-back">
                        <i class="fa-solid fa-arrow-left me-1"></i> Choose Different File
                    </button>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-success btn-sm px-4 fw-bold" id="btn-confirm-import">
                            <i class="fa-solid fa-check-double me-1"></i> Confirm & Import Structures (<span id="btn-valid-count">0</span>)
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Bulk Salary Structure Upload Logic
(function() {
    let uploadedFile = null;
    let previewData = null;
    let currentFilter = 'all';

    const dropzone = document.getElementById('salary-dropzone');
    const fileInput = document.getElementById('salary_csv_input');
    const idleState = document.getElementById('dropzone-idle-state');
    const selectedState = document.getElementById('dropzone-selected-state');
    const fileNameDisplay = document.getElementById('selected-file-name');
    const fileSizeDisplay = document.getElementById('selected-file-size');
    const btnRemoveFile = document.getElementById('btn-remove-selected-file');
    const btnValidate = document.getElementById('btn-validate-upload');

    // Drag and drop event listeners
    if (dropzone && fileInput) {
        dropzone.addEventListener('click', function(e) {
            if (e.target.closest('#btn-remove-selected-file')) return;
            fileInput.click();
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, function(e) {
                e.preventDefault();
                e.stopPropagation();
                dropzone.style.borderColor = '#1F5FAE';
                dropzone.style.background = 'rgba(31, 95, 174, 0.08)';
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, function(e) {
                e.preventDefault();
                e.stopPropagation();
                dropzone.style.borderColor = 'rgba(255,255,255,0.2)';
                dropzone.style.background = 'rgba(255,255,255,0.02)';
            });
        });

        dropzone.addEventListener('drop', function(e) {
            const files = e.dataTransfer.files;
            if (files && files.length > 0) {
                handleFileSelect(files[0]);
            }
        });

        fileInput.addEventListener('change', function() {
            if (fileInput.files && fileInput.files.length > 0) {
                handleFileSelect(fileInput.files[0]);
            }
        });

        if (btnRemoveFile) {
            btnRemoveFile.addEventListener('click', function(e) {
                e.stopPropagation();
                resetFileInput();
            });
        }
    }

    function handleFileSelect(file) {
        if (!file.name.toLowerCase().endsWith('.csv')) {
            alert('Please select a valid CSV file.');
            resetFileInput();
            return;
        }
        if (file.size > 10 * 1024 * 1024) {
            alert('File exceeds 10MB size limit.');
            resetFileInput();
            return;
        }

        uploadedFile = file;
        fileNameDisplay.textContent = file.name;
        fileSizeDisplay.textContent = (file.size / 1024).toFixed(1) + ' KB';
        idleState.classList.add('d-none');
        selectedState.classList.remove('d-none');
        btnValidate.disabled = false;
    }

    function resetFileInput() {
        uploadedFile = null;
        if (fileInput) fileInput.value = '';
        if (idleState) idleState.classList.remove('d-none');
        if (selectedState) selectedState.classList.add('d-none');
        if (btnValidate) btnValidate.disabled = true;
    }

    // Step 1 -> Step 2: Validate & Preview
    $('#btn-validate-upload').on('click', function() {
        if (!uploadedFile) return;

        const formData = new FormData();
        formData.append('csv_file', uploadedFile);
        formData.append('csrf_token', '<?php echo $_SESSION['csrf_token'] ?? ''; ?>');
        formData.append('effective_date', $('#bulk_effective_date').val() || '');
        formData.append('revision_reason', $('#bulk_revision_reason').val() || '');

        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Validating CSV...');

        $.ajax({
            url: 'index.php?route=payroll/validate_bulk_structures',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                $btn.prop('disabled', false).html('<i class="fa-solid fa-magnifying-glass-chart me-2"></i>Analyze & Preview CSV');
                if (res.success) {
                    previewData = res;
                    renderPreviewTable(res);
                    switchStep(2);
                } else {
                    alert(res.message || 'Validation failed.');
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html('<i class="fa-solid fa-magnifying-glass-chart me-2"></i>Analyze & Preview CSV');
                let errMsg = 'Failed to analyze CSV.';
                try {
                    const parsed = JSON.parse(xhr.responseText);
                    if (parsed.message) errMsg = parsed.message;
                } catch(e) {}
                alert(errMsg);
            }
        });
    });

    // Render Preview
    function renderPreviewTable(data) {
        $('#stat-total-rows').text(data.total_rows || 0);
        $('#stat-valid-rows').text(data.valid_count || 0);
        $('#stat-update-rows').text(data.update_count || 0);
        $('#stat-error-rows').text(data.error_count || 0);

        $('#pill-all-count').text(data.total_rows || 0);
        $('#pill-valid-count').text(data.valid_count || 0);
        $('#pill-update-count').text(data.update_count || 0);
        $('#pill-error-count').text(data.error_count || 0);

        $('#btn-valid-count').text(data.valid_count || 0);

        if (data.valid_count > 0) {
            $('#btn-confirm-import').prop('disabled', false);
        } else {
            $('#btn-confirm-import').prop('disabled', true);
        }

        if (data.error_count > 0) {
            $('#preview-error-alert').removeClass('d-none');
            $('#preview-error-text').text(`${data.error_count} row(s) contain validation errors and will be skipped. Only valid rows will be imported.`);
        } else {
            $('#preview-error-alert').addClass('d-none');
        }

        renderFilteredRows();
    }

    function renderFilteredRows() {
        if (!previewData || !previewData.rows) return;

        const searchTerm = ($('#preview-search-input').val() || '').toLowerCase().trim();
        const tbody = $('#preview-table-body');
        tbody.empty();

        const filtered = previewData.rows.filter(r => {
            // Filter by status pill
            if (currentFilter === 'valid' && r.status !== 'valid') return false;
            if (currentFilter === 'update' && (r.status !== 'valid' || r.action !== 'update')) return false;
            if (currentFilter === 'error' && r.status !== 'error') return false;

            // Search filter
            if (searchTerm) {
                const name = (r.employee_name || '').toLowerCase();
                const code = (r.employee_code || '').toLowerCase();
                if (!name.includes(searchTerm) && !code.includes(searchTerm)) {
                    return false;
                }
            }
            return true;
        });

        if (filtered.length === 0) {
            tbody.html('<tr><td colspan="11" class="text-center py-4 text-secondary">No rows match the selected filter.</td></tr>');
            return;
        }

        filtered.forEach(r => {
            let statusBadge = '';
            if (r.status === 'valid') {
                if (r.action === 'update') {
                    statusBadge = '<span class="badge bg-info-subtle text-info border border-info-subtle"><i class="fa-solid fa-arrows-rotate me-1"></i>Revision</span>';
                } else {
                    statusBadge = '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa-solid fa-plus me-1"></i>New Baseline</span>';
                }
            } else {
                statusBadge = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="fa-solid fa-triangle-exclamation me-1"></i>Error</span>';
            }

            let remarks = '';
            if (r.status === 'error') {
                const errList = Object.values(r.errors || {});
                remarks = `<span class="text-danger small"><i class="fa-solid fa-circle-xmark me-1"></i>${errList.join('; ')}</span>`;
            } else if (r.action === 'update') {
                const diff = (r.gross_salary || 0) - (r.previous_gross || 0);
                const diffSign = diff >= 0 ? '+' : '';
                remarks = `<span class="text-info small">Prev Gross: ₹${Math.round(r.previous_gross).toLocaleString('en-IN')} (${diffSign}₹${Math.round(diff).toLocaleString('en-IN')})</span>`;
            } else {
                remarks = `<span class="text-success small">Initial configuration</span>`;
            }

            const allowTotal = (r.special_allowance || 0) + (r.other_allowance || 0) + (r.medical_allowance || 0) + (r.travel_allowance || 0);

            const tr = `
                <tr class="${r.status === 'error' ? 'table-danger bg-opacity-25' : ''}">
                    <td class="text-secondary font-monospace">${r.row}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="fw-bold text-white">${escapeHtml(r.employee_name)}</div>
                        <div class="text-secondary font-monospace" style="font-size: 0.75rem;">${escapeHtml(r.employee_code)} ${r.job_title ? '• ' + escapeHtml(r.job_title) : ''}</div>
                    </td>
                    <td class="text-end font-monospace">₹${Math.round(r.basic_salary || 0).toLocaleString('en-IN')}</td>
                    <td class="text-end font-monospace">₹${Math.round(r.hra || 0).toLocaleString('en-IN')}</td>
                    <td class="text-end font-monospace">₹${Math.round(allowTotal).toLocaleString('en-IN')}</td>
                    <td class="text-end font-monospace fw-bold text-white">₹${Math.round(r.gross_salary || 0).toLocaleString('en-IN')}</td>
                    <td class="text-end font-monospace text-warning">₹${Math.round(r.total_deductions || 0).toLocaleString('en-IN')}</td>
                    <td class="text-end font-monospace text-success fw-bold">₹${Math.round(r.net_salary || 0).toLocaleString('en-IN')}</td>
                    <td class="text-end font-monospace text-primary fw-bold">₹${Math.round(r.ctc || 0).toLocaleString('en-IN')}</td>
                    <td>${remarks}</td>
                </tr>
            `;
            tbody.append(tr);
        });
    }

    function escapeHtml(text) {
        if (!text) return '';
        return text.replace(/[&<>"']/g, function(m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
        });
    }

    // Filter pills
    $(document).on('click', '.preview-filter-btn', function() {
        $('.preview-filter-btn').removeClass('active');
        $(this).addClass('active');
        currentFilter = $(this).data('filter');
        renderFilteredRows();
    });

    // Search input
    $('#preview-search-input').on('input', function() {
        renderFilteredRows();
    });

    // Step navigation
    function switchStep(step) {
        $('#bulk-step-upload, #bulk-step-preview, #bulk-step-progress, #bulk-step-result').addClass('d-none');
        $('#footer-step-1, #footer-step-2').addClass('d-none');

        if (step === 1) {
            $('#bulk-step-upload').removeClass('d-none');
            $('#footer-step-1').removeClass('d-none');
        } else if (step === 2) {
            $('#bulk-step-preview').removeClass('d-none');
            $('#footer-step-2').removeClass('d-none');
        } else if (step === 3) {
            $('#bulk-step-progress').removeClass('d-none');
            $('#bulk-modal-close-btn').prop('disabled', true);
        } else if (step === 4) {
            $('#bulk-step-result').removeClass('d-none');
            $('#bulk-modal-close-btn').prop('disabled', false);
        }
    }

    $('#btn-step2-back').on('click', function() {
        switchStep(1);
    });

    // Confirm & Execute Import
    $('#btn-confirm-import').on('click', function() {
        if (!previewData || previewData.valid_count === 0) return;

        const strategy = $('input[name="duplicate_strategy"]:checked').val() || 'update';
        const effectiveDate = $('#bulk_effective_date').val() || '';
        const revisionReason = $('#bulk_revision_reason').val() || '';

        switchStep(3);

        $.ajax({
            url: 'index.php?route=payroll/bulk_import_structures',
            type: 'POST',
            contentType: 'application/json',
            headers: {
                'X-CSRF-Token': '<?php echo $_SESSION['csrf_token'] ?? ''; ?>'
            },
            data: JSON.stringify({
                csrf_token: '<?php echo $_SESSION['csrf_token'] ?? ''; ?>',
                duplicate_strategy: strategy,
                batch_effective_date: effectiveDate,
                batch_revision_reason: revisionReason
            }),
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    $('#result-stat-processed').text(res.total_processed || (res.imported + res.updated) || 0);
                    $('#result-stat-skipped').text(res.skipped || 0);

                    if (res.failed > 0) {
                        $('#result-stat-failed').text(res.failed);
                        $('#result-stat-failed-box').removeClass('d-none');
                        $('#btn-download-import-errors').removeClass('d-none');
                    } else {
                        $('#result-stat-failed-box').addClass('d-none');
                        $('#btn-download-import-errors').addClass('d-none');
                    }

                    switchStep(4);
                } else {
                    alert(res.message || 'Import failed.');
                    switchStep(2);
                }
            },
            error: function(xhr) {
                let errMsg = 'An unexpected error occurred during import.';
                try {
                    const parsed = JSON.parse(xhr.responseText);
                    if (parsed.message) errMsg = parsed.message;
                } catch(e) {}
                alert(errMsg);
                switchStep(2);
            }
        });
    });

    // Reset when modal closed
    $('#bulkSalaryUploadModal').on('hidden.bs.modal', function() {
        switchStep(1);
        resetFileInput();
        previewData = null;
        currentFilter = 'all';
    });
})();
</script>
