<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="text-white mb-0"><i class="fa-solid fa-chart-line text-primary me-2"></i>Account Sales Dashboard</h4>
        <p class="text-secondary small mb-0">Post-conversion account management, renewals, upsell activity, and churn risk tracking.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#logActivityModal" style="background: var(--primary); border: none;">
            <i class="fa-solid fa-plus me-1"></i>Log Sales Activity
        </button>
        <a href="index.php?route=account_sales/opportunities" class="btn btn-outline-light btn-sm">
            <i class="fa-solid fa-kanban me-1"></i>Growth Pipeline
        </a>
    </div>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow mb-4" role="alert" style="background: rgba(25, 135, 84, 0.15); color: #2ec4b6;">
        <i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow mb-4" role="alert" style="background: rgba(220, 53, 69, 0.15); color: #e63946;">
        <i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Summary Cards (Interactive) -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="pulse-card p-3 d-flex align-items-center justify-content-between cursor-pointer card-hover-highlight" data-bs-toggle="modal" data-bs-target="#managedAccountsModal" style="cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" title="Click to view all 27 Managed Accounts">
            <div class="d-flex align-items-center">
                <div class="rounded-3 bg-primary bg-opacity-20 text-primary me-3 d-flex align-items-center justify-content-center" style="width:48px; height:48px; font-size:1.4rem;">
                    <i class="fa-solid fa-users-gear"></i>
                </div>
                <div>
                    <div class="text-secondary small fw-semibold">MANAGED ACCOUNTS</div>
                    <div class="text-white fw-bold fs-4"><?php echo count($customers); ?></div>
                </div>
            </div>
            <span class="badge bg-primary bg-opacity-15 text-primary border border-primary border-opacity-25 small py-1 px-2" style="font-size:0.68rem;">
                View Accounts <i class="fa-solid fa-chevron-right ms-1"></i>
            </span>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="pulse-card p-3 d-flex align-items-center justify-content-between cursor-pointer card-hover-highlight" data-bs-toggle="modal" data-bs-target="#allActivitiesModal" style="cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" title="Click to view all Logged Activities">
            <div class="d-flex align-items-center">
                <div class="rounded-3 bg-success bg-opacity-20 text-success me-3 d-flex align-items-center justify-content-center" style="width:48px; height:48px; font-size:1.4rem;">
                    <i class="fa-solid fa-phone-volume"></i>
                </div>
                <div>
                    <div class="text-secondary small fw-semibold">ACTIVITIES LOGGED</div>
                    <div class="text-white fw-bold fs-4"><?php echo count($activities); ?></div>
                </div>
            </div>
            <span class="badge bg-success bg-opacity-15 text-success border border-success border-opacity-25 small py-1 px-2" style="font-size:0.68rem;">
                View Log <i class="fa-solid fa-chevron-right ms-1"></i>
            </span>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="pulse-card p-3 d-flex align-items-center justify-content-between cursor-pointer card-hover-highlight" data-bs-toggle="modal" data-bs-target="#churnRiskAccountsModal" style="cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" title="Click to view all Churn Risk Accounts">
            <div class="d-flex align-items-center">
                <div class="rounded-3 bg-warning bg-opacity-20 text-warning me-3 d-flex align-items-center justify-content-center" style="width:48px; height:48px; font-size:1.4rem;">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                <div>
                    <div class="text-secondary small fw-semibold">CHURN RISK ACCOUNTS</div>
                    <div class="text-warning fw-bold fs-4"><?php echo count($churn_risks); ?></div>
                </div>
            </div>
            <span class="badge bg-warning bg-opacity-15 text-warning border border-warning border-opacity-25 small py-1 px-2" style="font-size:0.68rem;">
                View At Risk <i class="fa-solid fa-chevron-right ms-1"></i>
            </span>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="pulse-card p-3 d-flex align-items-center justify-content-between cursor-pointer card-hover-highlight" data-bs-toggle="modal" data-bs-target="#insideRepsModal" style="cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" title="Click to view Inside Sales Reps">
            <div class="d-flex align-items-center">
                <div class="rounded-3 bg-info bg-opacity-20 text-info me-3 d-flex align-items-center justify-content-center" style="width:48px; height:48px; font-size:1.4rem;">
                    <i class="fa-solid fa-user-tie"></i>
                </div>
                <div>
                    <div class="text-secondary small fw-semibold">INSIDE REPS</div>
                    <div class="text-white fw-bold fs-4"><?php echo count($employees); ?></div>
                </div>
            </div>
            <span class="badge bg-info bg-opacity-15 text-info border border-info border-opacity-25 small py-1 px-2" style="font-size:0.68rem;">
                View Reps <i class="fa-solid fa-chevron-right ms-1"></i>
            </span>
        </div>
    </div>
</div>

<!-- Churn Risk Alerts & Recent Activities -->
<div class="row g-4 mb-4">
    <!-- Churn Risk Alert Banner / Table -->
    <div class="col-12 col-lg-6 col-xl-5">
        <div class="pulse-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2 border-secondary">
                <h6 class="text-white mb-0"><i class="fa-solid fa-triangle-exclamation text-warning me-2"></i>Account Churn Risk Radar</h6>
                <span class="badge <?php echo count($churn_risks) > 0 ? 'bg-warning text-dark' : 'bg-success text-white'; ?>">
                    <?php echo count($churn_risks); ?> Flagged
                </span>
            </div>
            <?php if (empty($churn_risks)): ?>
                <div class="text-center py-4 px-3">
                    <i class="fa-solid fa-circle-check text-success display-6 mb-2"></i>
                    <h6 class="text-white fw-bold">No accounts at risk 🎉</h6>
                    <p class="text-secondary small mb-0">All active customer accounts have recent sales outreach and healthy renewal timelines.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
                    <table class="table table-dark table-hover align-middle mb-0" style="font-size:0.85rem;">
                        <thead>
                            <tr class="text-secondary">
                                <th>Customer</th>
                                <th>Account Manager</th>
                                <th>Risk Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($churn_risks as $risk): ?>
                                <?php
                                    if ($risk->days_to_renewal !== null && $risk->days_to_renewal <= 30 && $risk->days_to_renewal >= 0) {
                                        $badgeClass = 'bg-danger-subtle text-danger border border-danger-subtle';
                                        $badgeText = 'Renewal Due in ' . $risk->days_to_renewal . 'd';
                                    } elseif (!empty($risk->last_activity_at) && $risk->days_since_activity <= 30) {
                                        $badgeClass = 'bg-success-subtle text-success border border-success-subtle';
                                        $badgeText = 'Healthy Outreach';
                                    } else {
                                        $badgeClass = 'bg-warning-subtle text-warning border border-warning-subtle';
                                        $badgeText = 'No Recent Outreach';
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <a href="index.php?route=customers/detail/<?php echo $risk->customer_id; ?>" class="text-white fw-semibold text-decoration-none text-truncate d-block" style="max-width: 140px;" title="<?php echo htmlspecialchars($risk->company_name ?: $risk->first_name); ?>">
                                            <?php echo htmlspecialchars($risk->company_name ?: $risk->first_name); ?>
                                        </a>
                                        <span class="badge bg-dark border border-secondary text-primary font-monospace px-1 py-0.5" style="font-size:0.65rem;"><?php echo htmlspecialchars($risk->customer_code); ?></span>
                                    </td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle py-0.5 px-2 font-monospace" type="button" data-bs-toggle="dropdown" style="font-size:0.72rem;">
                                                <i class="fa-solid fa-user-tie me-1 opacity-75"></i><?php echo htmlspecialchars($risk->owner_name ?: 'Assign Rep'); ?>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-dark shadow border-secondary p-2" style="font-size:0.8rem; min-width: 190px;">
                                                <li><h6 class="dropdown-header text-uppercase text-secondary px-2" style="font-size:0.65rem;">Assign Account Rep</h6></li>
                                                <?php foreach ($employees as $emp): ?>
                                                    <li>
                                                        <form action="index.php?route=account_sales/assignAccount" method="POST" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                                            <input type="hidden" name="customer_id" value="<?php echo $risk->customer_id; ?>">
                                                            <input type="hidden" name="owner_employee_id" value="<?php echo $emp->employee_id; ?>">
                                                            <button type="submit" class="dropdown-item py-1 px-2 rounded small <?php echo ($risk->owner_employee_id == $emp->employee_id) ? 'active bg-primary' : 'text-white'; ?>">
                                                                <i class="fa-solid fa-user me-2 opacity-50"></i><?php echo htmlspecialchars($emp->name); ?>
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $badgeClass; ?> px-2 py-1" style="font-size:0.7rem;"><?php echo $badgeText; ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Sales Activity Log -->
    <div class="col-12 col-lg-6 col-xl-7">
        <div class="pulse-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2 border-secondary">
                <h6 class="text-white mb-0"><i class="fa-solid fa-list-check text-primary me-2"></i>Recent Sales Outreach Log</h6>
                <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#logActivityModal">
                    <i class="fa-solid fa-plus me-1"></i>New Entry
                </button>
            </div>
            <?php if (empty($activities)): ?>
                <div class="text-secondary small py-4 text-center">No sales activities logged yet. Click "Log Sales Activity" to record outreach.</div>
            <?php else: ?>
                <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
                    <table class="table table-dark table-hover align-middle mb-0" style="font-size:0.88rem;">
                        <thead>
                            <tr class="text-secondary">
                                <th>Activity Code</th>
                                <th>Customer</th>
                                <th>Type</th>
                                <th>Outcome</th>
                                <th>Sales Rep</th>
                                <th>Logged At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activities as $act): ?>
                                <tr>
                                    <td><span class="badge bg-dark border border-secondary text-primary font-monospace"><?php echo htmlspecialchars($act->activity_code); ?></span></td>
                                    <td>
                                        <a href="index.php?route=customers/detail/<?php echo $act->customer_id; ?>" class="text-white text-decoration-none fw-semibold">
                                            <?php echo htmlspecialchars($act->company_name ?: $act->first_name); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle"><?php echo htmlspecialchars($act->activity_type); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle"><?php echo htmlspecialchars($act->outcome); ?></span>
                                    </td>
                                    <td class="small text-secondary"><?php echo htmlspecialchars($act->rep_name ?: 'Unassigned'); ?></td>
                                    <td class="small text-secondary"><?php echo date('M d, H:i', strtotime($act->created_at)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Log Activity Modal -->
<div class="modal fade" id="logActivityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <form action="index.php?route=account_sales/logActivity" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title"><i class="fa-solid fa-phone-volume me-2 text-primary"></i>Log Inside Sales Activity</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modal_customer_id" class="form-label text-secondary small">Target Customer *</label>
                        <select name="customer_id" id="modal_customer_id" class="form-select bg-dark border-secondary text-white" required>
                            <option value="">-- Select Customer --</option>
                            <?php foreach ($customers as $cust): ?>
                                <option value="<?php echo $cust->customer_id; ?>">
                                    <?php echo htmlspecialchars($cust->company_name ?: $cust->first_name); ?> (<?php echo htmlspecialchars($cust->customer_code); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="modal_activity_type" class="form-label text-secondary small">Activity Type</label>
                            <select name="activity_type" id="modal_activity_type" class="form-select bg-dark border-secondary text-white">
                                <option value="Call">Call</option>
                                <option value="Email">Email</option>
                                <option value="Upsell Pitch">Upsell Pitch</option>
                                <option value="Renewal Check-in">Renewal Check-in</option>
                                <option value="Cross-sell">Cross-sell</option>
                                <option value="QBR">QBR (Quarterly Review)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="modal_outcome" class="form-label text-secondary small">Outcome</label>
                            <select name="outcome" id="modal_outcome" class="form-select bg-dark border-secondary text-white">
                                <option value="Successful">Successful</option>
                                <option value="Follow-up Needed">Follow-up Needed</option>
                                <option value="No Answer">No Answer</option>
                                <option value="Closed Won">Closed Won</option>
                                <option value="Closed Lost">Closed Lost</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="modal_assigned_rep_employee_id" class="form-label text-secondary small">Assigned Sales Rep</label>
                            <select name="assigned_rep_employee_id" id="modal_assigned_rep_employee_id" class="form-select bg-dark border-secondary text-white">
                                <option value="">-- Select Rep --</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp->employee_id; ?>"><?php echo htmlspecialchars($emp->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="modal_next_follow_up_date" class="form-label text-secondary small">Next Follow-up Date</label>
                            <input type="datetime-local" name="next_follow_up_date" id="modal_next_follow_up_date" class="form-control bg-dark border-secondary text-white">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="modal_notes" class="form-label text-secondary small">Activity Notes &amp; Discussion Points</label>
                        <textarea name="notes" id="modal_notes" class="form-control bg-dark border-secondary text-white" rows="3" placeholder="Summary of client discussion, feedback, or follow-up commitments..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold" style="background: var(--primary); border: none;">Save Activity Log</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Managed Accounts Detail Modal -->
<div class="modal fade" id="managedAccountsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fa-solid fa-users-gear text-primary me-2"></i>Managed Accounts Registry (<?php echo count($customers); ?> Accounts)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-3">
                    <input type="text" id="searchManagedAccountsInput" class="form-control bg-dark border-secondary text-white" placeholder="Search accounts by name, code, email..." onkeyup="filterManagedAccountsTable()">
                </div>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0" id="managedAccountsTable" style="font-size: 0.88rem;">
                        <thead>
                            <tr class="text-secondary">
                                <th>Customer Code</th>
                                <th>Company / Client Name</th>
                                <th>Email &amp; Phone</th>
                                <th>Account Manager</th>
                                <th>Onboarding Date</th>
                                <th>Contract Value</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $cust): ?>
                                <tr>
                                    <td><span class="badge bg-dark border border-secondary text-primary font-monospace"><?php echo htmlspecialchars($cust->customer_code); ?></span></td>
                                    <td>
                                        <a href="index.php?route=customers/detail/<?php echo $cust->customer_id; ?>" class="text-white fw-semibold text-decoration-none">
                                            <?php echo htmlspecialchars($cust->company_name ?: ($cust->first_name . ' ' . ($cust->last_name ?? ''))); ?>
                                        </a>
                                    </td>
                                    <td class="small text-secondary">
                                        <div><?php echo htmlspecialchars($cust->email); ?></div>
                                        <div><?php echo htmlspecialchars($cust->phone ?? 'N/A'); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($cust->owner_name ?: 'Unassigned'); ?></span>
                                    </td>
                                    <td class="small text-secondary"><?php echo !empty($cust->onboarding_date) ? date('M d, Y', strtotime($cust->onboarding_date)) : 'N/A'; ?></td>
                                    <td class="fw-semibold text-success">$<?php echo number_format((float)($cust->contract_value ?? 0), 2); ?></td>
                                    <td><span class="badge bg-success-subtle text-success border border-success-subtle"><?php echo htmlspecialchars($cust->status ?? 'Active'); ?></span></td>
                                    <td>
                                        <a href="index.php?route=customers/detail/<?php echo $cust->customer_id; ?>" class="btn btn-outline-primary btn-sm py-0.5 px-2" style="font-size:0.75rem;">
                                            View <i class="fa-solid fa-arrow-right ms-1"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-secondary justify-content-between">
                <span class="text-secondary small">Total Managed Accounts: <?php echo count($customers); ?></span>
                <div>
                    <button type="button" class="btn btn-outline-light btn-sm me-2" data-bs-dismiss="modal">Close</button>
                    <a href="index.php?route=customers/index" class="btn btn-primary btn-sm">Full Customer Directory <i class="fa-solid fa-external-link ms-1"></i></a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- All Activities Detail Modal -->
<div class="modal fade" id="allActivitiesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fa-solid fa-phone-volume text-success me-2"></i>Sales Outreach Log (<?php echo count($activities); ?> Logged Activities)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <?php if (empty($activities)): ?>
                    <div class="text-secondary small py-4 text-center">No sales activities logged yet. Click "Log Sales Activity" to record outreach.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle mb-0" style="font-size: 0.88rem;">
                            <thead>
                                <tr class="text-secondary">
                                    <th>Activity Code</th>
                                    <th>Customer</th>
                                    <th>Type</th>
                                    <th>Outcome</th>
                                    <th>Sales Rep</th>
                                    <th>Logged At</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activities as $act): ?>
                                    <tr>
                                        <td><span class="badge bg-dark border border-secondary text-primary font-monospace"><?php echo htmlspecialchars($act->activity_code); ?></span></td>
                                        <td>
                                            <a href="index.php?route=customers/detail/<?php echo $act->customer_id; ?>" class="text-white text-decoration-none fw-semibold">
                                                <?php echo htmlspecialchars($act->company_name ?: $act->first_name); ?>
                                            </a>
                                        </td>
                                        <td><span class="badge bg-info-subtle text-info border border-info-subtle"><?php echo htmlspecialchars($act->activity_type); ?></span></td>
                                        <td><span class="badge bg-success-subtle text-success border border-success-subtle"><?php echo htmlspecialchars($act->outcome); ?></span></td>
                                        <td class="small text-secondary"><?php echo htmlspecialchars($act->rep_name ?: 'Unassigned'); ?></td>
                                        <td class="small text-secondary"><?php echo date('M d, Y H:i', strtotime($act->created_at)); ?></td>
                                        <td class="small text-secondary text-truncate" style="max-width: 200px;"><?php echo htmlspecialchars($act->notes ?? '—'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#logActivityModal"><i class="fa-solid fa-plus me-1"></i>Log New Activity</button>
            </div>
        </div>
    </div>
</div>

<!-- Churn Risk Accounts Detail Modal -->
<div class="modal fade" id="churnRiskAccountsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fa-solid fa-clock-rotate-left text-warning me-2"></i>Account Churn Risk Radar (<?php echo count($churn_risks); ?> Flagged)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <?php if (empty($churn_risks)): ?>
                    <div class="text-center py-4 px-3">
                        <i class="fa-solid fa-circle-check text-success display-6 mb-2"></i>
                        <h6 class="text-white fw-bold">No accounts at risk 🎉</h6>
                        <p class="text-secondary small mb-0">All active customer accounts have recent sales outreach and healthy renewal timelines.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle mb-0" style="font-size: 0.88rem;">
                            <thead>
                                <tr class="text-secondary">
                                    <th>Customer Code</th>
                                    <th>Company Name</th>
                                    <th>Account Manager</th>
                                    <th>Risk Status</th>
                                    <th>Last Activity</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($churn_risks as $risk): ?>
                                    <?php
                                        if ($risk->days_to_renewal !== null && $risk->days_to_renewal <= 30 && $risk->days_to_renewal >= 0) {
                                            $badgeClass = 'bg-danger-subtle text-danger border border-danger-subtle';
                                            $badgeText = 'Renewal Due in ' . $risk->days_to_renewal . 'd';
                                        } elseif (!empty($risk->last_activity_at) && $risk->days_since_activity <= 30) {
                                            $badgeClass = 'bg-success-subtle text-success border border-success-subtle';
                                            $badgeText = 'Healthy Outreach';
                                        } else {
                                            $badgeClass = 'bg-warning-subtle text-warning border border-warning-subtle';
                                            $badgeText = 'No Recent Outreach';
                                        }
                                    ?>
                                    <tr>
                                        <td><span class="badge bg-dark border border-secondary text-primary font-monospace"><?php echo htmlspecialchars($risk->customer_code); ?></span></td>
                                        <td>
                                            <a href="index.php?route=customers/detail/<?php echo $risk->customer_id; ?>" class="text-white fw-semibold text-decoration-none">
                                                <?php echo htmlspecialchars($risk->company_name ?: $risk->first_name); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-outline-secondary btn-sm dropdown-toggle py-0.5 px-2 font-monospace" type="button" data-bs-toggle="dropdown" style="font-size:0.75rem;">
                                                    <i class="fa-solid fa-user-tie me-1 opacity-75"></i><?php echo htmlspecialchars($risk->owner_name ?: 'Assign Rep'); ?>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-dark shadow border-secondary p-2" style="font-size:0.8rem; min-width: 190px;">
                                                    <li><h6 class="dropdown-header text-uppercase text-secondary px-2" style="font-size:0.65rem;">Assign Account Rep</h6></li>
                                                    <?php foreach ($employees as $emp): ?>
                                                        <li>
                                                            <form action="index.php?route=account_sales/assignAccount" method="POST" class="d-inline">
                                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                                                <input type="hidden" name="customer_id" value="<?php echo $risk->customer_id; ?>">
                                                                <input type="hidden" name="owner_employee_id" value="<?php echo $emp->employee_id; ?>">
                                                                <button type="submit" class="dropdown-item py-1 px-2 rounded small <?php echo ($risk->owner_employee_id == $emp->employee_id) ? 'active bg-primary' : 'text-white'; ?>">
                                                                    <i class="fa-solid fa-user me-2 opacity-50"></i><?php echo htmlspecialchars($emp->name); ?>
                                                                </button>
                                                            </form>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        </td>
                                        <td><span class="badge <?php echo $badgeClass; ?> px-2 py-1"><?php echo $badgeText; ?></span></td>
                                        <td class="small text-secondary"><?php echo !empty($risk->last_activity_at) ? date('M d, Y', strtotime($risk->last_activity_at)) : 'Never'; ?></td>
                                        <td>
                                            <a href="index.php?route=customers/detail/<?php echo $risk->customer_id; ?>" class="btn btn-outline-primary btn-sm py-0.5 px-2" style="font-size:0.75rem;">
                                                View Account <i class="fa-solid fa-arrow-right ms-1"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Inside Reps Detail Modal -->
<div class="modal fade" id="insideRepsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fa-solid fa-user-tie text-info me-2"></i>Inside Sales Team &amp; Account Assignment (<?php echo count($employees); ?> Reps)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0" style="font-size: 0.88rem;">
                        <thead>
                            <tr class="text-secondary">
                                <th>Rep Name</th>
                                <th>Department</th>
                                <th>Job Title</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $emp): ?>
                                <tr>
                                    <td class="fw-semibold text-white">
                                        <i class="fa-solid fa-user-circle me-2 text-info opacity-75"></i><?php echo htmlspecialchars($emp->name); ?>
                                    </td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($emp->department ?? 'Sales'); ?></span></td>
                                    <td class="small text-secondary"><?php echo htmlspecialchars($emp->job_title ?? 'Account Manager'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function filterManagedAccountsTable() {
    const input = document.getElementById('searchManagedAccountsInput');
    if (!input) return;
    const filter = input.value.toLowerCase();
    const table = document.getElementById('managedAccountsTable');
    if (!table) return;
    const tr = table.getElementsByTagName('tr');

    for (let i = 1; i < tr.length; i++) {
        const text = tr[i].textContent || tr[i].innerText;
        if (text.toLowerCase().indexOf(filter) > -1) {
            tr[i].style.display = '';
        } else {
            tr[i].style.display = 'none';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert-dismissible');
        alerts.forEach(function(alert) {
            alert.classList.remove('show');
            setTimeout(function() { alert.remove(); }, 300);
        });
    }, 4000);
});
</script>
