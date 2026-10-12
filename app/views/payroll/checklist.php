<div class="pulse-card">
    <?php if (!empty($_SESSION['payroll_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert" style="background-color: #d1fae5; border-color: #a7f3d0; color: #065f46;">
            <i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($_SESSION['payroll_success']); unset($_SESSION['payroll_success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['payroll_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert" style="background-color: #fee2e2; border-color: #fecaca; color: #991b1b;">
            <i class="fa-solid fa-circle-xmark me-2"></i><?php echo htmlspecialchars($_SESSION['payroll_error']); unset($_SESSION['payroll_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Header & Period Selector -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h4 class="mb-1" style="color: var(--text-primary); font-weight: 700;">
                <i class="fa-solid fa-clipboard-check me-2 text-primary"></i>Pre-Payroll Processing Checklist
            </h4>
            <p class="small mb-0" style="color: var(--text-secondary);">
                Mandatory accounts verification checklist. Confirm and verify all 9 items before executing the monthly salary run.
            </p>
        </div>
        <div>
            <form method="GET" action="index.php" class="d-flex align-items-center gap-2">
                <input type="hidden" name="route" value="payroll/checklist">
                <label for="month_year" class="small font-weight-bold text-nowrap" style="color: var(--text-secondary);">Payroll Period:</label>
                <input type="month" name="month_year" id="month_year" class="form-control form-control-sm font-monospace" style="max-width: 170px;" value="<?php echo htmlspecialchars($month_year); ?>" onchange="this.form.submit()">
                <button type="submit" class="btn btn-outline-primary btn-sm">Load Month</button>
            </form>
        </div>
    </div>

    <!-- Month Progress & Run Execution Banner -->
    <?php 
    $pct = $total_items > 0 ? round(($completed_count / $total_items) * 100) : 0;
    ?>
    <div class="p-4 rounded mb-4" style="background: var(--surface-soft); border: 1px solid var(--border-color); box-shadow: var(--shadow-soft);">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
            <div>
                <span class="small text-uppercase font-weight-bold" style="color: var(--text-secondary); letter-spacing: 0.5px;">Target Payroll Month</span>
                <h3 class="mb-0" style="color: var(--text-primary); font-weight: 700;">
                    <?php echo htmlspecialchars(PayslipHelper::formatMonthYear($month_year)); ?> 
                    <span class="font-monospace fs-6" style="color: var(--text-secondary); font-weight: 500;">(<?php echo htmlspecialchars($month_year); ?>)</span>
                </h3>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="text-end">
                    <span class="small d-block" style="color: var(--text-secondary);">Completion Status</span>
                    <span class="badge <?php echo $is_all_completed ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-warning-subtle text-warning border border-warning-subtle'; ?> px-3 py-2 fs-6">
                        <i class="fa-solid fa-<?php echo $is_all_completed ? 'circle-check' : 'spinner fa-spin-pulse'; ?> me-1"></i>
                        <?php echo $completed_count; ?> of <?php echo $total_items; ?> Completed (<?php echo $pct; ?>%)
                    </span>
                </div>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="progress mt-3 mb-3" style="height: 10px; background-color: var(--border-color);">
            <div class="progress-bar <?php echo $is_all_completed ? 'bg-success' : 'bg-primary'; ?>" role="progressbar" style="width: <?php echo $pct; ?>%;" aria-valuenow="<?php echo $pct; ?>" aria-valuemin="0" aria-valuemax="100"></div>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 pt-2">
            <div>
                <?php if ($is_all_completed): ?>
                    <span class="text-success font-weight-bold small">
                        <i class="fa-solid fa-circle-check me-1"></i> All 9 checklist items are verified and confirmed. Payroll generation is UNLOCKED.
                    </span>
                <?php else: ?>
                    <span class="text-warning font-weight-bold small">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> Payroll generation is locked until all 9 verification items are marked Completed.
                    </span>
                <?php endif; ?>
                <?php if ($existing_run): ?>
                    <div class="mt-1">
                        <span class="badge bg-info-subtle text-info border border-info-subtle">
                            <i class="fa-solid fa-info-circle me-1"></i> Payroll Run #<?php echo $existing_run->payroll_run_id; ?> exists for this month (Status: <?php echo strtoupper($existing_run->status); ?>)
                        </span>
                        <a href="index.php?route=payroll/processing&run_id=<?php echo $existing_run->payroll_run_id; ?>" class="small ms-2 text-primary font-weight-bold">View Run Details &rarr;</a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="d-flex align-items-center gap-2">
                <?php if (!$is_all_completed): ?>
                    <!-- Quick Complete All Action -->
                    <form action="index.php?route=payroll/mark_all_checklist" method="POST" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                        <input type="hidden" name="month_year" value="<?php echo htmlspecialchars($month_year); ?>">
                        <button type="submit" class="btn btn-outline-success btn-sm px-3" onclick="return confirm('Confirm verification and mark all 9 checklist items as Completed for <?php echo htmlspecialchars(PayslipHelper::formatMonthYear($month_year)); ?>?');">
                            <i class="fa-solid fa-check-double me-1"></i> Mark All 9 Items Completed
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($is_all_completed): ?>
                    <form action="index.php?route=payroll/run_generate" method="POST" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                        <input type="hidden" name="month_year" value="<?php echo htmlspecialchars($month_year); ?>">
                        <button type="submit" class="btn btn-success btn-md px-4 py-2 font-weight-bold shadow-sm" onclick="return confirm('Proceed to generate & compute payroll for <?php echo htmlspecialchars(PayslipHelper::formatMonthYear($month_year)); ?>?');">
                            <i class="fa-solid fa-bolt me-1"></i> Run & Generate Payroll
                        </button>
                    </form>
                <?php else: ?>
                    <button class="btn btn-secondary btn-sm px-3" disabled title="Complete all 9 checklist items below to run payroll">
                        <i class="fa-solid fa-lock me-1"></i> Run Payroll (Locked)
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 9 Checklist Items Cards -->
    <div class="row g-3">
        <?php foreach ($items as $item): ?>
            <?php 
                $isItemDone = ($item->status === 'completed');
                $isItemProg = ($item->status === 'in_progress');
                $cardBorderClass = $isItemDone ? 'border-success-subtle' : ($isItemProg ? 'border-warning-subtle' : 'border-secondary-subtle');
                $cardBgStyle = $isItemDone ? 'background: rgba(16, 185, 129, 0.04);' : ($isItemProg ? 'background: rgba(245, 158, 11, 0.04);' : 'background: var(--panel-dark);');
            ?>
            <div class="col-12" id="<?php echo htmlspecialchars($item->item_key); ?>">
                <div class="p-3 rounded border <?php echo $cardBorderClass; ?> transition-all" style="<?php echo $cardBgStyle; ?> box-shadow: var(--shadow-soft);">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                        <div style="flex: 1; min-width: 290px;">
                            <!-- Header & Status Badge -->
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <?php if ($isItemDone): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle text-uppercase font-weight-bold px-2 py-1" style="font-size: 0.72rem;">
                                        <i class="fa-solid fa-circle-check me-1"></i> Completed
                                    </span>
                                <?php elseif ($isItemProg): ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle text-uppercase font-weight-bold px-2 py-1" style="font-size: 0.72rem;">
                                        <i class="fa-solid fa-clock me-1"></i> In Progress
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle text-uppercase font-weight-bold px-2 py-1" style="font-size: 0.72rem;">
                                        <i class="fa-solid fa-hourglass-start me-1"></i> Pending
                                    </span>
                                <?php endif; ?>
                                <h5 class="mb-0 font-weight-bold" style="color: var(--text-primary);">
                                    <?php echo htmlspecialchars($item->item_title); ?>
                                </h5>
                            </div>

                            <p class="small mb-2" style="color: var(--text-secondary);">
                                <?php echo htmlspecialchars($item->item_description); ?>
                            </p>

                            <!-- ========================================== -->
                            <!-- Dynamic Interactive Tools & Option Drawers -->
                            <!-- ========================================== -->

                            <!-- 1. Employee Status Verification Option -->
                            <?php if ($item->item_key === 'employee_status'): ?>
                                <div class="p-3 rounded mt-2" style="background: var(--surface-soft); border: 1px solid var(--border-color); color: var(--text-primary);">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                        <div class="d-flex flex-wrap gap-2 align-items-center">
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                                <i class="fa-solid fa-users me-1"></i> Active Employees: <strong><?php echo $active_count; ?></strong>
                                            </span>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                                <i class="fa-solid fa-user-slash me-1"></i> Inactive/Relieved: <strong><?php echo $inactive_count; ?></strong>
                                            </span>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                <i class="fa-solid fa-user-plus me-1"></i> New Joiners: <strong><?php echo count($new_joiners); ?></strong>
                                            </span>
                                        </div>
                                        <a href="index.php?route=users/index" target="_blank" class="btn btn-outline-primary btn-sm py-1 px-2" style="font-size: 0.78rem;">
                                            <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Open Employee Directory
                                        </a>
                                    </div>
                                    <div class="small" style="color: var(--text-secondary);">
                                        <i class="fa-solid fa-circle-info me-1 text-primary"></i> Only active employees with valid salary structures will be included in the payroll run.
                                    </div>
                                </div>

                            <!-- 2. Salary Processing / Hold Option -->
                            <?php elseif ($item->item_key === 'salary_hold'): ?>
                                <div class="p-3 rounded mt-2" style="background: var(--surface-soft); border: 1px solid var(--border-color); color: var(--text-primary);">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
                                        <span class="font-weight-bold" style="color: var(--text-primary);">
                                            <i class="fa-solid fa-hand me-1 text-warning"></i> Salaries on Hold for <?php echo htmlspecialchars(PayslipHelper::formatMonthYear($month_year)); ?>: 
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle ms-1"><?php echo count($salary_holds); ?></span>
                                        </span>
                                        <button type="button" class="btn btn-warning btn-sm py-1 px-3 font-weight-bold" data-bs-toggle="modal" data-bs-target="#salaryHoldModal">
                                            <i class="fa-solid fa-plus me-1"></i> + Place Salary on Hold
                                        </button>
                                    </div>

                                    <?php if (empty($salary_holds)): ?>
                                        <div class="small fst-italic p-2 rounded" style="background: var(--panel-dark); border: 1px dashed var(--border-color); color: var(--text-secondary);">
                                            <i class="fa-solid fa-check text-success me-1"></i> No employee salaries placed on hold. All eligible employees will be processed.
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive mt-2">
                                            <table class="table table-sm align-middle mb-0" style="background: var(--panel-dark); border: 1px solid var(--border-color); border-radius: 6px;">
                                                <thead style="background: var(--surface-soft);">
                                                    <tr class="small" style="color: var(--text-secondary);">
                                                        <th>Employee</th>
                                                        <th>Employee ID</th>
                                                        <th>Reason for Hold</th>
                                                        <th>Placed By</th>
                                                        <th class="text-end">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($salary_holds as $sh): ?>
                                                        <tr class="small" style="color: var(--text-primary);">
                                                            <td class="font-weight-bold"><?php echo htmlspecialchars($sh->employee_name); ?></td>
                                                            <td class="font-monospace"><?php echo htmlspecialchars($sh->employee_code); ?></td>
                                                            <td><span class="badge bg-warning-subtle text-warning border border-warning-subtle"><?php echo htmlspecialchars($sh->reason); ?></span></td>
                                                            <td style="color: var(--text-secondary);"><?php echo htmlspecialchars($sh->hold_by_name ?? 'System'); ?></td>
                                                            <td class="text-end">
                                                                <a href="index.php?route=payroll/release_salary_hold/<?php echo $sh->hold_id; ?>&month_year=<?php echo htmlspecialchars($month_year); ?>" class="btn btn-outline-danger btn-sm py-0 px-2" style="font-size: 0.72rem;" onclick="return confirm('Release salary hold for <?php echo htmlspecialchars($sh->employee_name); ?>?');">
                                                                    <i class="fa-solid fa-lock-open me-1"></i> Release
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>

                            <!-- 3. New Joiners and Relieved Employees Option -->
                            <?php elseif ($item->item_key === 'joiners_relieved'): ?>
                                <div class="p-3 rounded mt-2" style="background: var(--surface-soft); border: 1px solid var(--border-color); color: var(--text-primary);">
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <div class="p-2 rounded h-100" style="background: var(--panel-dark); border: 1px solid var(--border-color);">
                                                <div class="small font-weight-bold text-success mb-1">
                                                    <i class="fa-solid fa-user-plus me-1"></i> New Joiners in <?php echo htmlspecialchars(PayslipHelper::formatMonthYear($month_year)); ?> (<?php echo count($new_joiners); ?>)
                                                </div>
                                                <?php if (empty($new_joiners)): ?>
                                                    <div class="small fst-italic" style="color: var(--text-secondary);">No new joiners joined during this month.</div>
                                                <?php else: ?>
                                                    <ul class="list-unstyled mb-0 small">
                                                        <?php foreach ($new_joiners as $nj): ?>
                                                            <li class="py-1 border-bottom" style="border-color: var(--border-color) !important;">
                                                                <strong><?php echo htmlspecialchars($nj->name); ?></strong> (<?php echo htmlspecialchars($nj->employee_code); ?>)
                                                                <span class="d-block" style="color: var(--text-secondary);">DOJ: <span class="font-monospace text-success font-weight-bold"><?php echo htmlspecialchars($nj->date_of_joining); ?></span> &bull; Salary will be prorated automatically.</span>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="p-2 rounded h-100" style="background: var(--panel-dark); border: 1px solid var(--border-color);">
                                                <div class="small font-weight-bold text-danger mb-1">
                                                    <i class="fa-solid fa-user-minus me-1"></i> Relieved / Inactive Employees (<?php echo count($relieved_employees); ?>)
                                                </div>
                                                <?php if (empty($relieved_employees)): ?>
                                                    <div class="small fst-italic" style="color: var(--text-secondary);">No inactive/relieved records flagged for this cycle.</div>
                                                <?php else: ?>
                                                    <div class="small" style="color: var(--text-secondary);">
                                                        <?php echo count($relieved_employees); ?> inactive staff detected. Ensure final settlement and last working days are confirmed.
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            <!-- 4. Salary Revisions Option -->
                            <?php elseif ($item->item_key === 'salary_revisions'): ?>
                                <div class="p-3 rounded mt-2" style="background: var(--surface-soft); border: 1px solid var(--border-color); color: var(--text-primary);">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
                                        <span class="font-weight-bold" style="color: var(--text-primary);">
                                            <i class="fa-solid fa-sliders me-1 text-primary"></i> Effective Salary Revisions for <?php echo htmlspecialchars(PayslipHelper::formatMonthYear($month_year)); ?>:
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1"><?php echo count($salary_revisions); ?></span>
                                        </span>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-primary btn-sm py-1 px-3 font-weight-bold" data-bs-toggle="modal" data-bs-target="#salaryRevisionModal">
                                                <i class="fa-solid fa-plus me-1"></i> + Log Salary Revision
                                            </button>
                                            <a href="index.php?route=payroll/structures" target="_blank" class="btn btn-outline-primary btn-sm py-1 px-2" style="font-size: 0.78rem;">
                                                <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Full Structure Editor
                                            </a>
                                        </div>
                                    </div>
                                    <?php if (empty($salary_revisions)): ?>
                                        <div class="small fst-italic p-2 rounded" style="background: var(--panel-dark); border: 1px dashed var(--border-color); color: var(--text-secondary);">
                                            <i class="fa-solid fa-circle-info me-1 text-primary"></i> No salary revisions logged specifically for this month. Click <strong>"+ Log Salary Revision"</strong> to record an increment or revision with remarks.
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive mt-2">
                                            <table class="table table-sm align-middle mb-0" style="background: var(--panel-dark); border: 1px solid var(--border-color); border-radius: 6px;">
                                                <thead style="background: var(--surface-soft);">
                                                    <tr class="small" style="color: var(--text-secondary);">
                                                        <th>Employee</th>
                                                        <th>Employee ID</th>
                                                        <th>New Gross / CTC</th>
                                                        <th>Effective Date</th>
                                                        <th>Remarks / Reason</th>
                                                        <th class="text-end">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($salary_revisions as $sr): ?>
                                                        <tr class="small" style="color: var(--text-primary);">
                                                            <td class="font-weight-bold"><?php echo htmlspecialchars($sr->employee_name); ?></td>
                                                            <td class="font-monospace"><?php echo htmlspecialchars($sr->employee_code); ?></td>
                                                            <td>
                                                                <span class="font-monospace text-primary font-weight-bold">
                                                                    ₹<?php echo number_format($sr->new_gross ?? $sr->new_ctc); ?>
                                                                </span>
                                                            </td>
                                                            <td class="font-monospace"><?php echo htmlspecialchars($sr->effective_date); ?></td>
                                                            <td>
                                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                                                    <i class="fa-solid fa-comment-dots me-1"></i><?php echo htmlspecialchars($sr->reason ?: 'Salary Revision'); ?>
                                                                </span>
                                                            </td>
                                                            <td class="text-end">
                                                                <a href="index.php?route=payroll/delete_salary_revision/<?php echo $sr->revision_id; ?>&month_year=<?php echo htmlspecialchars($month_year); ?>" class="btn btn-outline-danger btn-sm py-0 px-2" style="font-size: 0.72rem;" onclick="return confirm('Remove salary revision entry for <?php echo htmlspecialchars($sr->employee_name); ?>?');">
                                                                    <i class="fa-solid fa-trash me-1"></i> Remove
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>

                            <!-- 5. Loss of Pay (LOP) Option -->
                            <?php elseif ($item->item_key === 'lop_deductions'): ?>
                                <div class="p-3 rounded mt-2" style="background: var(--surface-soft); border: 1px solid var(--border-color); color: var(--text-primary);">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
                                        <span class="font-weight-bold" style="color: var(--text-primary);">
                                            <i class="fa-solid fa-calendar-xmark me-1 text-warning"></i> Loss of Pay (LOP) Adjustments for <?php echo htmlspecialchars(PayslipHelper::formatMonthYear($month_year)); ?>:
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle ms-1"><?php echo count($lops ?? []); ?></span>
                                        </span>
                                        <div class="d-flex flex-wrap gap-2">
                                            <button type="button" class="btn btn-warning btn-sm py-1 px-3 font-weight-bold" data-bs-toggle="modal" data-bs-target="#lopModal">
                                                <i class="fa-solid fa-plus me-1"></i> + Add / Edit LOP
                                            </button>
                                            <a href="index.php?route=attendance/report" target="_blank" class="btn btn-outline-primary btn-sm py-1 px-2" style="font-size: 0.78rem;">
                                                <i class="fa-solid fa-chart-bar me-1"></i> Attendance Report
                                            </a>
                                            <a href="index.php?route=leaves/balances" target="_blank" class="btn btn-outline-secondary btn-sm py-1 px-2" style="font-size: 0.78rem;">
                                                <i class="fa-solid fa-scale-balanced me-1"></i> Leave Balances
                                            </a>
                                        </div>
                                    </div>

                                    <?php if (empty($lops)): ?>
                                        <div class="small fst-italic p-2 rounded" style="background: var(--panel-dark); border: 1px dashed var(--border-color); color: var(--text-secondary);">
                                            <i class="fa-solid fa-circle-info me-1 text-warning"></i> No manual LOP adjustments added for this month. System will compute deductions based on biometric logs or full attendance. Click <strong>"+ Add / Edit LOP"</strong> to manually specify unpaid leave days with remarks.
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive mt-2">
                                            <table class="table table-sm align-middle mb-0" style="background: var(--panel-dark); border: 1px solid var(--border-color); border-radius: 6px;">
                                                <thead style="background: var(--surface-soft);">
                                                    <tr class="small" style="color: var(--text-secondary);">
                                                        <th>Employee</th>
                                                        <th>Employee ID</th>
                                                        <th>LOP Days</th>
                                                        <th>Remarks / Reason</th>
                                                        <th>Added By</th>
                                                        <th class="text-end">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($lops as $lop): ?>
                                                        <tr class="small" style="color: var(--text-primary);">
                                                            <td class="font-weight-bold"><?php echo htmlspecialchars($lop->employee_name); ?></td>
                                                            <td class="font-monospace"><?php echo htmlspecialchars($lop->employee_code); ?></td>
                                                            <td>
                                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle font-monospace px-2 py-1">
                                                                    <i class="fa-solid fa-calendar-minus me-1"></i><?php echo number_format($lop->lop_days, 1); ?> Day<?php echo $lop->lop_days != 1 ? 's' : ''; ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                                                    <i class="fa-solid fa-comment-dots me-1"></i><?php echo htmlspecialchars($lop->reason ?: 'Unpaid Leave'); ?>
                                                                </span>
                                                            </td>
                                                            <td style="color: var(--text-secondary);"><?php echo htmlspecialchars($lop->created_by_name ?? 'Admin'); ?></td>
                                                            <td class="text-end">
                                                                <a href="index.php?route=payroll/delete_lop/<?php echo $lop->lop_id; ?>&month_year=<?php echo htmlspecialchars($month_year); ?>" class="btn btn-outline-danger btn-sm py-0 px-2" style="font-size: 0.72rem;" onclick="return confirm('Remove LOP adjustment for <?php echo htmlspecialchars($lop->employee_name); ?>?');">
                                                                    <i class="fa-solid fa-trash me-1"></i> Remove
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>

                            <!-- 6. Other Deductions Option -->
                            <?php elseif ($item->item_key === 'other_deductions'): ?>
                                <div class="p-3 rounded mt-2" style="background: var(--surface-soft); border: 1px solid var(--border-color); color: var(--text-primary);">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
                                        <span class="font-weight-bold" style="color: var(--text-primary);">
                                            <i class="fa-solid fa-receipt me-1 text-danger"></i> Other Employee Deductions for <?php echo htmlspecialchars(PayslipHelper::formatMonthYear($month_year)); ?>:
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle ms-1"><?php echo count($deductions ?? []); ?></span>
                                        </span>
                                        <div class="d-flex flex-wrap gap-2">
                                            <button type="button" class="btn btn-danger btn-sm py-1 px-3 font-weight-bold" data-bs-toggle="modal" data-bs-target="#otherDeductionModal">
                                                <i class="fa-solid fa-plus me-1"></i> + Add Employee Deduction
                                            </button>
                                        </div>
                                    </div>

                                    <?php if (empty($deductions)): ?>
                                        <div class="small fst-italic p-2 rounded" style="background: var(--panel-dark); border: 1px dashed var(--border-color); color: var(--text-secondary);">
                                            <i class="fa-solid fa-circle-check text-success me-1"></i> Standard statutory deductions (EPF 12%, Professional Tax ₹200) are automatically computed. No custom employee deductions (penalties, loan recovery, advances) recorded yet. Click <strong>"+ Add Employee Deduction"</strong> to log a deduction with remarks.
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive mt-2">
                                            <table class="table table-sm align-middle mb-0" style="background: var(--panel-dark); border: 1px solid var(--border-color); border-radius: 6px;">
                                                <thead style="background: var(--surface-soft);">
                                                    <tr class="small" style="color: var(--text-secondary);">
                                                        <th>Employee</th>
                                                        <th>Employee ID</th>
                                                        <th>Deduction Type</th>
                                                        <th>Amount (₹)</th>
                                                        <th>Remarks / Reason</th>
                                                        <th>Added By</th>
                                                        <th class="text-end">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($deductions as $ded): ?>
                                                        <tr class="small" style="color: var(--text-primary);">
                                                            <td class="font-weight-bold"><?php echo htmlspecialchars($ded->employee_name); ?></td>
                                                            <td class="font-monospace"><?php echo htmlspecialchars($ded->employee_code); ?></td>
                                                            <td>
                                                                <span class="badge bg-secondary-subtle text-light border border-secondary-subtle">
                                                                    <?php echo htmlspecialchars($ded->deduction_type); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="font-monospace text-danger font-weight-bold">
                                                                    -₹<?php echo number_format($ded->amount); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                                                    <i class="fa-solid fa-comment-dots me-1"></i><?php echo htmlspecialchars($ded->reason ?: 'Deduction'); ?>
                                                                </span>
                                                            </td>
                                                            <td style="color: var(--text-secondary);"><?php echo htmlspecialchars($ded->created_by_name ?? 'Admin'); ?></td>
                                                            <td class="text-end">
                                                                <a href="index.php?route=payroll/delete_deduction/<?php echo $ded->deduction_id; ?>&month_year=<?php echo htmlspecialchars($month_year); ?>" class="btn btn-outline-danger btn-sm py-0 px-2" style="font-size: 0.72rem;" onclick="return confirm('Remove deduction of ₹<?php echo number_format($ded->amount); ?> for <?php echo htmlspecialchars($ded->employee_name); ?>?');">
                                                                    <i class="fa-solid fa-trash me-1"></i> Remove
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>

                            <!-- 7. Arrears Option -->
                            <?php elseif ($item->item_key === 'arrears'): ?>
                                <div class="p-3 rounded mt-2" style="background: var(--surface-soft); border: 1px solid var(--border-color); color: var(--text-primary);">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
                                        <span class="font-weight-bold" style="color: var(--text-primary);">
                                            <i class="fa-solid fa-money-bill-transfer me-1 text-info"></i> Pending Arrears for <?php echo htmlspecialchars(PayslipHelper::formatMonthYear($month_year)); ?>: 
                                            <span class="badge bg-info-subtle text-info border border-info-subtle ms-1"><?php echo count($arrears); ?></span>
                                        </span>
                                        <button type="button" class="btn btn-info btn-sm py-1 px-3 font-weight-bold" data-bs-toggle="modal" data-bs-target="#arrearModal">
                                            <i class="fa-solid fa-plus me-1"></i> + Add Arrear Entry
                                        </button>
                                    </div>

                                    <?php if (empty($arrears)): ?>
                                        <div class="small fst-italic p-2 rounded" style="background: var(--panel-dark); border: 1px dashed var(--border-color); color: var(--text-secondary);">
                                            No pending salary arrears registered for this month.
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive mt-2">
                                            <table class="table table-sm align-middle mb-0" style="background: var(--panel-dark); border: 1px solid var(--border-color); border-radius: 6px;">
                                                <thead style="background: var(--surface-soft);">
                                                    <tr class="small" style="color: var(--text-secondary);">
                                                        <th>Employee</th>
                                                        <th>Amount (₹)</th>
                                                        <th>Reason / Description</th>
                                                        <th>Added By</th>
                                                        <th class="text-end">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($arrears as $arr): ?>
                                                        <tr class="small" style="color: var(--text-primary);">
                                                            <td class="font-weight-bold"><?php echo htmlspecialchars($arr->employee_name); ?></td>
                                                            <td class="font-monospace text-success font-weight-bold">+₹<?php echo number_format($arr->amount); ?></td>
                                                            <td><?php echo htmlspecialchars($arr->reason); ?></td>
                                                            <td style="color: var(--text-secondary);"><?php echo htmlspecialchars($arr->created_by_name ?? 'Admin'); ?></td>
                                                            <td class="text-end">
                                                                <a href="index.php?route=payroll/delete_arrear/<?php echo $arr->arrear_id; ?>&month_year=<?php echo htmlspecialchars($month_year); ?>" class="btn btn-outline-danger btn-sm py-0 px-2" style="font-size: 0.72rem;" onclick="return confirm('Remove this arrear entry?');">
                                                                    <i class="fa-solid fa-trash me-1"></i> Remove
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>

                            <!-- 8. Bonus / Incentives Option -->
                            <?php elseif ($item->item_key === 'bonuses_incentives'): ?>
                                <div class="p-3 rounded mt-2" style="background: var(--surface-soft); border: 1px solid var(--border-color); color: var(--text-primary);">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
                                        <span class="font-weight-bold" style="color: var(--text-primary);">
                                            <i class="fa-solid fa-gift me-1 text-warning"></i> Approved Bonuses & Incentives for <?php echo htmlspecialchars(PayslipHelper::formatMonthYear($month_year)); ?>: 
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle ms-1"><?php echo count($approved_bonuses); ?></span>
                                        </span>
                                        <div class="d-flex flex-wrap gap-2">
                                            <button type="button" class="btn btn-warning btn-sm py-1 px-3 font-weight-bold" data-bs-toggle="modal" data-bs-target="#bonusIncentiveModal">
                                                <i class="fa-solid fa-plus me-1"></i> + Add Bonus / Incentive
                                            </button>
                                            <a href="index.php?route=payroll/bonuses" target="_blank" class="btn btn-outline-warning btn-sm py-1 px-2" style="font-size: 0.78rem;">
                                                <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Manage Bonuses Center
                                            </a>
                                        </div>
                                    </div>

                                    <?php if (empty($approved_bonuses)): ?>
                                        <div class="small fst-italic p-2 rounded" style="background: var(--panel-dark); border: 1px dashed var(--border-color); color: var(--text-secondary);">
                                            <i class="fa-solid fa-circle-info me-1 text-warning"></i> No approved bonuses or incentives recorded for this month. Click <strong>"+ Add Bonus / Incentive"</strong> to grant performance, festival, or sales bonuses with remarks.
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive mt-2">
                                            <table class="table table-sm align-middle mb-0" style="background: var(--panel-dark); border: 1px solid var(--border-color); border-radius: 6px;">
                                                <thead style="background: var(--surface-soft);">
                                                    <tr class="small" style="color: var(--text-secondary);">
                                                        <th>Employee</th>
                                                        <th>Employee ID</th>
                                                        <th>Bonus Type</th>
                                                        <th>Amount (₹)</th>
                                                        <th>Remarks / Description</th>
                                                        <th class="text-end">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($approved_bonuses as $bn): ?>
                                                        <tr class="small" style="color: var(--text-primary);">
                                                            <td class="font-weight-bold"><?php echo htmlspecialchars($bn->employee_name); ?></td>
                                                            <td class="font-monospace"><?php echo htmlspecialchars($bn->employee_code); ?></td>
                                                            <td>
                                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                                                    <i class="fa-solid fa-award me-1"></i><?php echo htmlspecialchars($bn->bonus_type ?? 'Bonus'); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="font-monospace text-success font-weight-bold">
                                                                    +₹<?php echo number_format($bn->amount); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <?php if (!empty($bn->description)): ?>
                                                                    <span class="badge bg-secondary-subtle text-light border border-secondary-subtle">
                                                                        <i class="fa-solid fa-comment-dots me-1"></i><?php echo htmlspecialchars($bn->description); ?>
                                                                    </span>
                                                                <?php else: ?>
                                                                    <span class="text-muted small fst-italic">Standard Bonus</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="text-end">
                                                                <a href="index.php?route=payroll/delete_checklist_bonus/<?php echo $bn->bonus_id; ?>&month_year=<?php echo htmlspecialchars($month_year); ?>" class="btn btn-outline-danger btn-sm py-0 px-2" style="font-size: 0.72rem;" onclick="return confirm('Remove bonus of ₹<?php echo number_format($bn->amount); ?> for <?php echo htmlspecialchars($bn->employee_name); ?>?');">
                                                                    <i class="fa-solid fa-trash me-1"></i> Remove
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>

                            <!-- 9. TDS Deductions Option -->
                            <?php elseif ($item->item_key === 'tds_deductions'): ?>
                                <div class="p-3 rounded mt-2" style="background: var(--surface-soft); border: 1px solid var(--border-color); color: var(--text-primary);">
                                    <div class="small" style="color: var(--text-secondary);">
                                        <i class="fa-solid fa-file-invoice me-1 text-info"></i> Review TDS tax exemptions, 80C/80D declarations, and tax slabs. The calculated TDS deductions will be retained and shown on individual monthly payslips.
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Remarks & Auditor Info Footer -->
                            <div class="mt-2 pt-2 border-top small d-flex flex-wrap justify-content-between align-items-center" style="border-color: var(--border-color) !important;">
                                <div>
                                    <span class="font-weight-bold" style="color: var(--text-secondary);">Remarks / Audit Note:</span>
                                    <span class="fst-italic ms-1" style="color: var(--text-primary);">
                                        <?php echo htmlspecialchars($item->remarks ?: 'No remarks added yet.'); ?>
                                    </span>
                                </div>
                                <?php if ($item->status !== 'pending' && !empty($item->verified_by_name)): ?>
                                    <div>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                            <i class="fa-solid fa-user-check me-1 text-success"></i> Verified by <?php echo htmlspecialchars($item->verified_by_name); ?> on <?php echo date('d M Y H:i', strtotime($item->verified_at)); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Update Status Form / Action Controls -->
                        <div class="d-flex flex-column align-items-end gap-2" style="min-width: 230px;">
                            <form action="index.php?route=payroll/save_checklist_item" method="POST" class="d-flex flex-column gap-2 w-100 p-2 rounded" style="background: var(--surface-soft); border: 1px solid var(--border-color);">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                <input type="hidden" name="item_id" value="<?php echo $item->item_id; ?>">
                                <input type="hidden" name="month_year" value="<?php echo htmlspecialchars($month_year); ?>">

                                <label class="small font-weight-bold mb-0" style="color: var(--text-secondary);">Item Status:</label>
                                <div class="input-group input-group-sm">
                                    <select name="status" class="form-select font-weight-bold" style="background-color: var(--panel-dark) !important; color: var(--text-primary) !important;">
                                        <option value="pending" <?php echo $item->status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="in_progress" <?php echo $item->status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                        <option value="completed" <?php echo $item->status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    </select>
                                    <button type="submit" class="btn btn-primary btn-sm px-3" style="background: var(--primary); border: none;">Save</button>
                                </div>

                                <label class="small font-weight-bold mb-0" style="color: var(--text-secondary);">Remarks (Optional):</label>
                                <input type="text" name="remarks" class="form-control form-control-sm" style="background-color: var(--panel-dark) !important; color: var(--text-primary) !important;" placeholder="Enter audit verification remarks..." value="<?php echo htmlspecialchars($item->remarks ?? ''); ?>">
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Bottom Execution Action Bar -->
    <div class="p-3 rounded mt-4 d-flex flex-wrap justify-content-between align-items-center gap-3" style="background: var(--surface-soft); border: 1px solid var(--border-color);">
        <div>
            <h6 class="mb-0" style="color: var(--text-primary); font-weight: 700;">Pre-Payroll Verification Sign-off</h6>
            <div class="small" style="color: var(--text-secondary);">
                <?php if ($is_all_completed): ?>
                    All checklist requirements have been verified. Click below to initiate and generate the complete payroll calculation.
                <?php else: ?>
                    Please mark all remaining items as Completed to execute payroll.
                <?php endif; ?>
            </div>
        </div>
        <div>
            <?php if ($is_all_completed): ?>
                <form action="index.php?route=payroll/run_generate" method="POST" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                    <input type="hidden" name="month_year" value="<?php echo htmlspecialchars($month_year); ?>">
                    <button type="submit" class="btn btn-success btn-md px-4 py-2 font-weight-bold shadow-sm" onclick="return confirm('Proceed to generate & compute payroll for <?php echo htmlspecialchars(PayslipHelper::formatMonthYear($month_year)); ?>?');">
                        <i class="fa-solid fa-calculator me-1"></i> Run & Generate Payroll for <?php echo htmlspecialchars(PayslipHelper::formatMonthYear($month_year)); ?>
                    </button>
                </form>
            <?php else: ?>
                <button class="btn btn-secondary btn-sm px-4" disabled>
                    <i class="fa-solid fa-lock me-1"></i> Run Payroll (Locked)
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal 1: Add Salary Hold -->
<div class="modal fade" id="salaryHoldModal" tabindex="-1" aria-labelledby="salaryHoldModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--panel-dark); color: var(--text-primary); border: 1px solid var(--border-color);">
            <form action="index.php?route=payroll/add_salary_hold" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="month_year" value="<?php echo htmlspecialchars($month_year); ?>">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title" id="salaryHoldModalLabel"><i class="fa-solid fa-hand me-2 text-warning"></i>Place Employee Salary on Hold</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small font-weight-bold" style="color: var(--text-secondary);">Select Employee *</label>
                        <select name="employee_id" class="form-select" required style="background-color: var(--surface-soft); color: var(--text-primary);">
                            <option value="">-- Choose Employee --</option>
                            <?php foreach ($active_employees as $emp): ?>
                                <option value="<?php echo $emp->employee_id; ?>">
                                    <?php echo htmlspecialchars($emp->name) . " (" . htmlspecialchars($emp->employee_code) . ")"; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small font-weight-bold" style="color: var(--text-secondary);">Reason for Holding Salary *</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="e.g. Pending documentation / Unapproved leave enquiry / Clearance pending" required style="background-color: var(--surface-soft); color: var(--text-primary);"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning font-weight-bold"><i class="fa-solid fa-lock me-1"></i>Confirm Salary Hold</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Add Arrear -->
<div class="modal fade" id="arrearModal" tabindex="-1" aria-labelledby="arrearModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--panel-dark); color: var(--text-primary); border: 1px solid var(--border-color);">
            <form action="index.php?route=payroll/add_arrear" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="month_year" value="<?php echo htmlspecialchars($month_year); ?>">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title" id="arrearModalLabel"><i class="fa-solid fa-money-bill-transfer me-2 text-info"></i>Add Salary Arrear</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small font-weight-bold" style="color: var(--text-secondary);">Select Employee *</label>
                        <select name="employee_id" class="form-select" required style="background-color: var(--surface-soft); color: var(--text-primary);">
                            <option value="">-- Choose Employee --</option>
                            <?php foreach ($active_employees as $emp): ?>
                                <option value="<?php echo $emp->employee_id; ?>">
                                    <?php echo htmlspecialchars($emp->name) . " (" . htmlspecialchars($emp->employee_code) . ")"; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small font-weight-bold" style="color: var(--text-secondary);">Arrear Amount (₹) *</label>
                        <input type="number" step="1" name="amount" class="form-control font-monospace" placeholder="e.g. 5000" required style="background-color: var(--surface-soft); color: var(--text-primary);">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small font-weight-bold" style="color: var(--text-secondary);">Reason / Arrear Description *</label>
                        <textarea name="reason" class="form-control" rows="2" placeholder="e.g. Previous month increment adjustment / Unpaid overtime" required style="background-color: var(--surface-soft); color: var(--text-primary);"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info font-weight-bold"><i class="fa-solid fa-plus me-1"></i>Add Arrear</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 3: Add Salary Revision -->
<div class="modal fade" id="salaryRevisionModal" tabindex="-1" aria-labelledby="salaryRevisionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--panel-dark); color: var(--text-primary); border: 1px solid var(--border-color);">
            <form action="index.php?route=payroll/add_salary_revision" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="month_year" value="<?php echo htmlspecialchars($month_year); ?>">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title" id="salaryRevisionModalLabel"><i class="fa-solid fa-sliders me-2 text-primary"></i>Record Employee Salary Revision</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small font-weight-bold" style="color: var(--text-secondary);">Select Employee *</label>
                        <select name="employee_id" class="form-select" required style="background-color: var(--surface-soft); color: var(--text-primary);">
                            <option value="">-- Choose Employee --</option>
                            <?php foreach ($active_employees as $emp): ?>
                                <option value="<?php echo $emp->employee_id; ?>">
                                    <?php echo htmlspecialchars($emp->name) . " (" . htmlspecialchars($emp->employee_code) . ")"; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small font-weight-bold" style="color: var(--text-secondary);">New Monthly Gross Salary (₹) *</label>
                        <input type="number" step="1" name="new_gross" class="form-control font-monospace" placeholder="e.g. 50000" required style="background-color: var(--surface-soft); color: var(--text-primary);">
                        <span class="form-text small" style="color: var(--text-secondary);">Salary components (Basic 50%, HRA 30%, Special) will be automatically computed.</span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small font-weight-bold" style="color: var(--text-secondary);">Effective Date *</label>
                        <input type="date" name="effective_date" class="form-control font-monospace" value="<?php echo htmlspecialchars($month_year . '-01'); ?>" required style="background-color: var(--surface-soft); color: var(--text-primary);">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small font-weight-bold" style="color: var(--text-secondary);">Revision Remarks / Reason *</label>
                        <textarea name="reason" class="form-control" rows="2" placeholder="e.g. Annual appraisal increment / Promoted to Lead Architect / Market adjustment" required style="background-color: var(--surface-soft); color: var(--text-primary);"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary font-weight-bold"><i class="fa-solid fa-check me-1"></i>Save Salary Revision</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 4: Add / Edit LOP -->
<div class="modal fade" id="lopModal" tabindex="-1" aria-labelledby="lopModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--panel-dark); color: var(--text-primary); border: 1px solid var(--border-color);">
            <form action="index.php?route=payroll/add_lop" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="month_year" value="<?php echo htmlspecialchars($month_year); ?>">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title" id="lopModalLabel"><i class="fa-solid fa-calendar-xmark me-2 text-warning"></i>Record Loss of Pay (LOP)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small font-weight-bold" style="color: var(--text-secondary);">Select Employee *</label>
                        <select name="employee_id" class="form-select" required style="background-color: var(--surface-soft); color: var(--text-primary);">
                            <option value="">-- Choose Employee --</option>
                            <?php foreach ($active_employees as $emp): ?>
                                <option value="<?php echo $emp->employee_id; ?>">
                                    <?php echo htmlspecialchars($emp->name) . " (" . htmlspecialchars($emp->employee_code) . ")"; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small font-weight-bold" style="color: var(--text-secondary);">LOP Days (Unpaid Leave) *</label>
                        <input type="number" step="0.5" min="0.5" max="31" name="lop_days" class="form-control font-monospace" placeholder="e.g. 1.0 or 2.5" required style="background-color: var(--surface-soft); color: var(--text-primary);">
                        <span class="form-text small" style="color: var(--text-secondary);">Salary will be pro-rated based on (Working Days - LOP Days).</span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small font-weight-bold" style="color: var(--text-secondary);">Remarks / Reason for LOP *</label>
                        <textarea name="reason" class="form-control" rows="2" placeholder="e.g. Unapproved absence / Leave balance exhausted / Late reporting penalty" required style="background-color: var(--surface-soft); color: var(--text-primary);"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning font-weight-bold"><i class="fa-solid fa-check me-1"></i>Save LOP Adjustment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 5: Add Employee Deduction -->
<div class="modal fade" id="otherDeductionModal" tabindex="-1" aria-labelledby="otherDeductionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--panel-dark); color: var(--text-primary); border: 1px solid var(--border-color);">
            <form action="index.php?route=payroll/add_deduction" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="month_year" value="<?php echo htmlspecialchars($month_year); ?>">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title" id="otherDeductionModalLabel"><i class="fa-solid fa-receipt me-2 text-danger"></i>Record Employee Deduction</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small font-weight-bold" style="color: var(--text-secondary);">Select Employee *</label>
                        <select name="employee_id" class="form-select" required style="background-color: var(--surface-soft); color: var(--text-primary);">
                            <option value="">-- Choose Employee --</option>
                            <?php foreach ($active_employees as $emp): ?>
                                <option value="<?php echo $emp->employee_id; ?>">
                                    <?php echo htmlspecialchars($emp->name) . " (" . htmlspecialchars($emp->employee_code) . ")"; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small font-weight-bold" style="color: var(--text-secondary);">Deduction Type *</label>
                        <select name="deduction_type" class="form-select" required style="background-color: var(--surface-soft); color: var(--text-primary);">
                            <option value="Penalty / Disciplinary">Penalty / Disciplinary</option>
                            <option value="Salary Advance Recovery">Salary Advance Recovery</option>
                            <option value="Loan Repayment">Loan Repayment</option>
                            <option value="Asset / Equipment Damage">Asset / Equipment Damage</option>
                            <option value="TDS / Additional Tax">TDS / Additional Tax</option>
                            <option value="Other Deduction" selected>Other Deduction</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small font-weight-bold" style="color: var(--text-secondary);">Deduction Amount (₹) *</label>
                        <input type="number" step="1" min="1" name="amount" class="form-control font-monospace" placeholder="e.g. 1500" required style="background-color: var(--surface-soft); color: var(--text-primary);">
                        <span class="form-text small" style="color: var(--text-secondary);">This amount will be deducted from Net Salary for this payroll month.</span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small font-weight-bold" style="color: var(--text-secondary);">Remarks / Reason for Deduction *</label>
                        <textarea name="reason" class="form-control" rows="2" placeholder="e.g. Advance recovery installment 1 / Disciplinary penalty / Asset damage recovery" required style="background-color: var(--surface-soft); color: var(--text-primary);"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger font-weight-bold"><i class="fa-solid fa-check me-1"></i>Save Deduction</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 6: Add Bonus / Incentive -->
<div class="modal fade" id="bonusIncentiveModal" tabindex="-1" aria-labelledby="bonusIncentiveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--panel-dark); color: var(--text-primary); border: 1px solid var(--border-color);">
            <form action="index.php?route=payroll/add_checklist_bonus" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="month_year" value="<?php echo htmlspecialchars($month_year); ?>">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title" id="bonusIncentiveModalLabel"><i class="fa-solid fa-gift me-2 text-warning"></i>Record Bonus / Incentive</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small font-weight-bold" style="color: var(--text-secondary);">Select Employee *</label>
                        <select name="employee_id" class="form-select" required style="background-color: var(--surface-soft); color: var(--text-primary);">
                            <option value="">-- Choose Employee --</option>
                            <?php foreach ($active_employees as $emp): ?>
                                <option value="<?php echo $emp->employee_id; ?>">
                                    <?php echo htmlspecialchars($emp->name) . " (" . htmlspecialchars($emp->employee_code) . ")"; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small font-weight-bold" style="color: var(--text-secondary);">Bonus / Incentive Type *</label>
                        <select name="bonus_type" class="form-select" required style="background-color: var(--surface-soft); color: var(--text-primary);">
                            <option value="Performance" selected>Performance Bonus</option>
                            <option value="Festival">Festival Bonus</option>
                            <option value="Sales Incentives">Sales Incentive</option>
                            <option value="Referral">Referral Bonus</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small font-weight-bold" style="color: var(--text-secondary);">Amount (₹) *</label>
                        <input type="number" step="1" min="1" name="amount" class="form-control font-monospace" placeholder="e.g. 5000" required style="background-color: var(--surface-soft); color: var(--text-primary);">
                        <span class="form-text small" style="color: var(--text-secondary);">This approved amount will be added to Gross Earnings in the payroll calculation.</span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small font-weight-bold" style="color: var(--text-secondary);">Remarks / Description *</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="e.g. Q3 Sales target overachievement incentive / Festival celebration bonus" required style="background-color: var(--surface-soft); color: var(--text-primary);"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning font-weight-bold"><i class="fa-solid fa-check me-1"></i>Save Bonus & Approve</button>
                </div>
            </form>
        </div>
    </div>
</div>



