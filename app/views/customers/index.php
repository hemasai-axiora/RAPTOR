<div class="pulse-card mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="text-white mb-1"><i class="fa-solid fa-address-book me-2 text-primary"></i>Customer Directory</h4>
            <div class="text-secondary" style="font-size: 0.9rem;">Lifecycle customer tracking, contract values, account managers, and renewals.</div>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?route=customers/downloadSampleCsv" class="btn btn-outline-info btn-sm px-3 py-2" title="Download Sample CSV Template">
                <i class="fa-solid fa-file-csv me-2"></i>Sample CSV
            </a>
            <button type="button" class="btn btn-outline-success btn-sm px-3 py-2" data-bs-toggle="modal" data-bs-target="#bulkUploadCustomersModal">
                <i class="fa-solid fa-file-import me-2"></i>Bulk Upload CSV
            </button>
            <a href="index.php?route=customers/add" class="btn btn-primary btn-sm px-3 py-2" style="background: var(--primary); border: none; border-radius: 8px;">
                <i class="fa-solid fa-user-plus me-2"></i>Capture Customer
            </a>
        </div>
    </div>

    <?php
    if (file_exists(APPROOT . '/views/components/liquid_gauge.php')) {
        require_once APPROOT . '/views/components/liquid_gauge.php';
    }
    ?>

    <!-- Customer Intelligence Liquid Fill Gauges Standalone Row -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="pulse-card h-100 p-2">
                <?php
                if (function_exists('renderLiquidGauge')) {
                    echo renderLiquidGauge([
                        'value' => 45,
                        'max' => 100,
                        'title' => 'Customer Engagement Score',
                        'description' => 'mixed interest, room to strengthen it',
                        'animate' => true
                    ]);
                }
                ?>
            </div>
        </div>
        <div class="col-md-4">
            <div class="pulse-card h-100 p-2">
                <?php
                if (function_exists('renderLiquidGauge')) {
                    echo renderLiquidGauge([
                        'value' => 84.5,
                        'max' => 100,
                        'title' => 'Account Retention Rate',
                        'description' => 'excellent score — target surpassed',
                        'animate' => true
                    ]);
                }
                ?>
            </div>
        </div>
        <div class="col-md-4">
            <div class="pulse-card h-100 p-2">
                <?php
                if (function_exists('renderLiquidGauge')) {
                    echo renderLiquidGauge([
                        'value' => 68.0,
                        'max' => 100,
                        'title' => 'NPS Loyalty Index',
                        'description' => 'good performance — strong trajectory',
                        'animate' => true
                    ]);
                }
                ?>
            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow mb-4" role="alert" style="background: rgba(25, 135, 84, 0.15); color: #2ec4b6;">
            <i class="fa-solid fa-circle-check me-2"></i> <?php echo $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow mb-4" role="alert" style="background: rgba(220, 53, 69, 0.15); color: #e63946;">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Search & Filter Bar -->
    <form method="GET" action="index.php" class="row g-3 mb-4">
        <input type="hidden" name="route" value="customers/index">

        <div class="col-md-3">
            <label class="form-label text-secondary">Status</label>
            <select name="status" class="form-select bg-dark border-secondary text-white" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <?php foreach ($statuses as $st): ?>
                    <option value="<?php echo $st; ?>" <?php echo ($filters['status'] === $st) ? 'selected' : ''; ?>><?php echo $st; ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label text-secondary">Account Owner</label>
            <select name="owner_employee_id" class="form-select bg-dark border-secondary text-white" onchange="this.form.submit()">
                <option value="">All Account Owners</option>
                <?php foreach ($employees as $emp): ?>
                    <option value="<?php echo $emp->employee_id; ?>" <?php echo ((string)$filters['owner_employee_id'] === (string)$emp->employee_id) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($emp->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label text-secondary">Customer Type</label>
            <select name="customer_type" class="form-select bg-dark border-secondary text-white" onchange="this.form.submit()">
                <option value="">All Types</option>
                <?php foreach ($types as $tp): ?>
                    <option value="<?php echo $tp; ?>" <?php echo ($filters['customer_type'] === $tp) ? 'selected' : ''; ?>><?php echo $tp; ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label text-secondary">Search</label>
            <div class="input-group">
                <input type="text" name="search" class="form-control bg-dark border-secondary text-white" placeholder="Customer ID / Name / Email" value="<?php echo htmlspecialchars($filters['search']); ?>">
                <button type="submit" class="btn btn-outline-secondary"><i class="fa-solid fa-magnifying-glass"></i></button>
            </div>
        </div>
    </form>

    <!-- Customers Registry Table -->
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle border-secondary" id="customers-table">
            <thead>
                <tr class="text-secondary" style="border-bottom: 1px solid var(--border-color);">
                    <th>Customer ID</th>
                    <th>Customer &amp; Company</th>
                    <th>Type &amp; Segment</th>
                    <th>Account Owner</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Contract Value</th>
                    <th>Renewal Date</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-secondary">No customers found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($customers as $cust): ?>
                        <?php 
                            $statusBadge = 'bg-success-subtle text-success border-success-subtle';
                            if ($cust->status === 'On Hold') $statusBadge = 'bg-warning-subtle text-warning border-warning-subtle';
                            elseif ($cust->status === 'Churned') $statusBadge = 'bg-danger-subtle text-danger border-danger-subtle';
                            elseif ($cust->status === 'Renewal Due') $statusBadge = 'bg-info-subtle text-info border-info-subtle';
                        ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td>
                                <span class="badge bg-dark border border-secondary text-primary font-monospace" style="font-size:0.82rem;">
                                    <?php echo htmlspecialchars($cust->customer_code ?: ('CUST-' . date('Y') . '-' . sprintf('%05d', $cust->customer_id))); ?>
                                </span>
                                <?php if (!empty($cust->originating_lead_code)): ?>
                                    <div class="text-secondary small" style="font-size:0.72rem;">
                                        <i class="fa-solid fa-link me-1 text-info"></i>Lead: <?php echo htmlspecialchars($cust->originating_lead_code); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-bold text-white"><?php echo htmlspecialchars($cust->company_name ?: $cust->first_name); ?></div>
                                <div class="text-secondary small">
                                    <?php if (!empty($cust->first_name) && !empty($cust->company_name)): ?>
                                        <i class="fa-solid fa-user me-1"></i><?php echo htmlspecialchars($cust->first_name); ?> • 
                                    <?php endif; ?>
                                    <i class="fa-solid fa-envelope me-1"></i><?php echo htmlspecialchars($cust->email); ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle mb-1">
                                    <?php echo htmlspecialchars($cust->customer_type); ?>
                                </span>
                                <?php if (!empty($cust->tags)): ?>
                                    <div class="text-info small" style="font-size:0.75rem;"><i class="fa-solid fa-tag me-1"></i><?php echo htmlspecialchars($cust->tags); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="text-white small fw-semibold">
                                    <i class="fa-solid fa-user-tie me-1 text-warning"></i><?php echo htmlspecialchars($cust->owner_name ?: 'Unassigned'); ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge border <?php echo $statusBadge; ?>"><?php echo strtoupper($cust->status); ?></span>
                            </td>
                            <td class="text-end fw-bold text-success font-monospace">
                                $<?php echo number_format((float)$cust->contract_value, 2); ?>
                            </td>
                            <td class="text-secondary small">
                                <?php echo $cust->renewal_date ? date('M d, Y', strtotime($cust->renewal_date)) : 'N/A'; ?>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a href="index.php?route=customers/detail/<?php echo $cust->customer_id; ?>" class="btn btn-outline-info btn-sm" title="View Detail Profile"><i class="fa-solid fa-eye"></i></a>
                                    <a href="index.php?route=customers/edit/<?php echo $cust->customer_id; ?>" class="btn btn-outline-light btn-sm" title="Edit Customer"><i class="fa-solid fa-pen-to-square"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Bulk Upload Customers via CSV -->
<div class="modal fade" id="bulkUploadCustomersModal" tabindex="-1" aria-labelledby="bulkUploadCustomersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="bulkUploadCustomersModalLabel">
                    <i class="fa-solid fa-file-csv text-success me-2"></i>Bulk Upload Customers via CSV
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="index.php?route=customers/uploadCsv" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                
                <div class="modal-body">
                    <p class="text-secondary small mb-3">
                        Upload a CSV spreadsheet containing customer columns: <code>Company Name</code>, <code>First Name</code>, <code>Customer Type</code>, <code>Email Address</code>, <code>Phone Number</code>, <code>Contract Value ($)</code>, <code>Billing Address</code>, <code>Payment Terms</code>, <code>Tags/Segment</code>.
                    </p>

                    <div class="mb-4 p-4 rounded-3 text-center" style="border: 2px dashed rgba(255, 255, 255, 0.2); background: rgba(0,0,0,0.15);">
                        <i class="fa-solid fa-cloud-arrow-up text-primary fs-1 mb-2"></i>
                        <div class="text-white fw-semibold mb-1">Select CSV File to Upload</div>
                        <span class="text-secondary small d-block mb-3">Supports .csv files up to 5MB</span>
                        <input type="file" name="csv_file" id="csv_file" class="form-control form-control-sm bg-dark border-secondary text-white" accept=".csv" required>
                    </div>

                    <div class="text-center pt-2 border-top border-secondary border-opacity-25">
                        <a href="index.php?route=customers/downloadSampleCsv" class="small text-info text-decoration-none">
                            <i class="fa-solid fa-download me-1"></i>Download Sample CSV Template (.csv)
                        </a>
                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold px-4"><i class="fa-solid fa-file-import me-1"></i>Import & Process CSV</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(function() {
    if ($('#customers-table tbody tr').length > 1) {
        $('#customers-table').DataTable({
            pageLength: 10,
            lengthChange: false,
            info: false,
            searching: true,
            language: { search: 'Search Customers:' }
        });
    }
});
</script>
