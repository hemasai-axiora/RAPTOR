<div class="pulse-card mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="text-white mb-1">Leads Manager</h4>
            <div class="text-secondary" style="font-size: 0.9rem;">Lifecycle pipeline with duplicate checks, ownership, and follow-up dates.</div>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?route=leads/downloadSampleCsv" class="btn btn-outline-info btn-sm px-3 py-2" title="Download Sample CSV Template">
                <i class="fa-solid fa-file-csv me-2"></i>Sample CSV
            </a>
            <button type="button" class="btn btn-outline-success btn-sm px-3 py-2" data-bs-toggle="modal" data-bs-target="#bulkUploadLeadsModal">
                <i class="fa-solid fa-file-import me-2"></i>Bulk Upload CSV
            </button>
            <a href="index.php?route=leads/pipeline" class="btn btn-outline-light btn-sm px-3 py-2">
                <i class="fa-solid fa-grip me-2"></i>Pipeline
            </a>
            <a href="index.php?route=leads/add" class="btn btn-primary btn-sm px-3 py-2" style="background: var(--primary); border: none; border-radius: 8px;">
                <i class="fa-solid fa-user-plus me-2"></i>Capture Lead
            </a>
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

    <!-- Quick Filter Pills (Sprint 2) -->
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3 pb-2 border-bottom border-secondary border-opacity-25">
        <span class="text-secondary small fw-medium me-1"><i class="fa-solid fa-bolt text-warning me-1"></i>Quick Filters:</span>
        <a href="index.php?route=leads/index" class="btn btn-sm <?php echo empty($filters['lead_quality']) && empty($filters['status']) ? 'btn-primary' : 'btn-outline-secondary'; ?> rounded-pill px-3 py-1">
            All Leads
        </a>
        <a href="index.php?route=leads/index&lead_quality=hot" class="btn btn-sm <?php echo ($filters['lead_quality'] ?? '') === 'hot' ? 'btn-danger' : 'btn-outline-danger'; ?> rounded-pill px-3 py-1">
            🔥 Hot Leads
        </a>
        <a href="index.php?route=leads/index&lead_quality=warm" class="btn btn-sm <?php echo ($filters['lead_quality'] ?? '') === 'warm' ? 'btn-warning text-dark fw-semibold' : 'btn-outline-warning'; ?> rounded-pill px-3 py-1">
            ⚡ Warm Leads
        </a>
        <a href="index.php?route=leads/index&lead_quality=cold" class="btn btn-sm <?php echo ($filters['lead_quality'] ?? '') === 'cold' ? 'btn-info' : 'btn-outline-info'; ?> rounded-pill px-3 py-1">
            ❄️ Cold Leads
        </a>
        <a href="index.php?route=leads/index&status=qualified" class="btn btn-sm <?php echo ($filters['status'] ?? '') === 'qualified' ? 'btn-success' : 'btn-outline-success'; ?> rounded-pill px-3 py-1">
            🎯 Qualified
        </a>
        <a href="index.php?route=leads/index&status=proposal" class="btn btn-sm <?php echo ($filters['status'] ?? '') === 'proposal' ? 'btn-primary' : 'btn-outline-primary'; ?> rounded-pill px-3 py-1">
            📄 Proposal
        </a>
        <a href="index.php?route=leads/index&status=converted" class="btn btn-sm <?php echo ($filters['status'] ?? '') === 'converted' ? 'btn-success' : 'btn-outline-success'; ?> rounded-pill px-3 py-1">
            🏆 Converted
        </a>
    </div>

    <form method="GET" action="index.php" class="row g-3 mb-4">
        <input type="hidden" name="route" value="leads/index">
        <div class="col-md-2">
            <label class="form-label text-secondary">Status</label>
            <select name="status" class="form-select bg-dark border-secondary text-white">
                <option value="">All</option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?php echo $status; ?>" <?php echo $filters['status'] === $status ? 'selected' : ''; ?>><?php echo strtoupper($status); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label text-secondary">Quality</label>
            <select name="lead_quality" class="form-select bg-dark border-secondary text-white">
                <option value="">All</option>
                <?php foreach ($qualities as $quality): ?>
                    <option value="<?php echo $quality; ?>" <?php echo $filters['lead_quality'] === $quality ? 'selected' : ''; ?>><?php echo strtoupper($quality); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label text-secondary">Source</label>
            <select name="lead_source" class="form-select bg-dark border-secondary text-white">
                <option value="">All</option>
                <?php foreach ($sources as $source): ?>
                    <option value="<?php echo htmlspecialchars($source->name); ?>" <?php echo $filters['lead_source'] === $source->name ? 'selected' : ''; ?>><?php echo htmlspecialchars($source->name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label text-secondary">Assignee</label>
            <select name="assigned_to_user_id" class="form-select bg-dark border-secondary text-white">
                <option value="">All</option>
                <?php foreach ($assignees as $user): ?>
                    <option value="<?php echo $user->user_id; ?>" <?php echo (string) $filters['assigned_to_user_id'] === (string) $user->user_id ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label text-secondary">Ageing</label>
            <select name="ageing" class="form-select bg-dark border-secondary text-white">
                <option value="">Any</option>
                <option value="7" <?php echo $filters['ageing'] === '7' ? 'selected' : ''; ?>>7+ days</option>
                <option value="30" <?php echo $filters['ageing'] === '30' ? 'selected' : ''; ?>>30+ days</option>
            </select>
        </div>
        <div class="col-md-1 d-flex align-items-end">
            <button class="btn btn-outline-light w-100" type="submit"><i class="fa-solid fa-filter"></i></button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle border-secondary table-stack" id="leads-table">
            <thead>
                <tr class="text-secondary" style="border-bottom: 1px solid var(--border-color);">
                    <th>Lead ID</th>
                    <th>Lead Name</th>
                    <th>Company</th>
                    <th>Source</th>
                    <th class="text-center">Quality</th>
                    <th class="text-center">Priority</th>
                    <th class="text-center">Probability</th>
                    <th class="text-end">Value</th>
                    <th>Owner</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leads)): ?>
                    <tr>
                        <td colspan="11" class="text-center py-4 text-secondary">No leads match the current filters.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($leads as $lead): ?>
                        <?php
                            $qualityClass = [
                                'hot' => 'bg-danger-subtle text-danger border border-danger-subtle',
                                'warm' => 'bg-warning-subtle text-warning border border-warning-subtle',
                                'cold' => 'bg-info-subtle text-info border border-info-subtle',
                            ][$lead->lead_quality] ?? 'bg-secondary-subtle text-secondary';
                            $statusTone = [
                                'new' => 'primary', 'contacted' => 'warning', 'qualified' => 'success',
                                'proposal' => 'info', 'converted' => 'success', 'lost' => 'danger',
                            ][$lead->status] ?? 'secondary';
                            $priorityTone = [
                                'urgent' => 'danger', 'high' => 'warning', 'medium' => 'info', 'low' => 'secondary',
                            ][$lead->priority] ?? 'secondary';
                        ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td data-label="Lead ID">
                                <span class="badge bg-dark border border-secondary text-primary font-monospace"><?php echo htmlspecialchars($lead->lead_code ?: ('LD-' . date('Y') . '-' . sprintf('%05d', $lead->lead_id))); ?></span>
                            </td>
                            <td data-label="Lead Name">
                                <a class="text-white text-decoration-none fw-semibold" href="index.php?route=leads/view/<?php echo $lead->lead_id; ?>">
                                    <?php echo htmlspecialchars(trim($lead->first_name . ' ' . ($lead->last_name ?? ''))); ?>
                                </a>
                                <div class="text-secondary" style="font-size:0.78rem;"><?php echo htmlspecialchars($lead->email ?: $lead->phone ?: 'No contact'); ?></div>
                            </td>
                            <td data-label="Company"><?php echo htmlspecialchars($lead->lead_company_name ?: $lead->client_company_name ?: 'Individual'); ?></td>
                            <td data-label="Source"><span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><?php echo htmlspecialchars($lead->lead_source); ?></span></td>
                            <td data-label="Quality" class="text-center"><span class="badge <?php echo $qualityClass; ?>"><?php echo strtoupper($lead->lead_quality); ?></span></td>
                            <td data-label="Priority" class="text-center"><span class="badge bg-<?php echo $priorityTone; ?>-subtle text-<?php echo $priorityTone; ?> border border-<?php echo $priorityTone; ?>-subtle"><?php echo strtoupper($lead->priority); ?></span></td>
                            <td data-label="Probability" class="text-center fw-semibold text-white"><?php echo number_format((float) ($lead->probability ?? $lead->conversion_probability), 1); ?>%</td>
                            <td data-label="Value" class="text-end fw-semibold text-success">$<?php echo number_format((float) $lead->lead_value, 2); ?></td>
                            <td data-label="Owner" class="text-secondary" style="font-size: 0.85rem;"><?php echo htmlspecialchars($lead->owner_employee_name ?: $lead->assignee_name ?: 'Unassigned'); ?></td>
                            <td data-label="Status"><span class="badge bg-<?php echo $statusTone; ?>-subtle text-<?php echo $statusTone; ?> border border-<?php echo $statusTone; ?>-subtle"><?php echo strtoupper($lead->status); ?></span></td>
                            <td data-label="Actions" class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <button type="button" class="btn btn-outline-info btn-sm btn-lead-preview" data-id="<?php echo $lead->lead_id; ?>" title="Quick Inspect Drawer">
                                        <i class="fa-solid fa-sidebar me-1"></i><i class="fa-solid fa-eye"></i>
                                    </button>
                                    <a href="index.php?route=leads/view/<?php echo $lead->lead_id; ?>" class="btn btn-outline-secondary btn-sm" title="Full Page"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                                    <a href="index.php?route=leads/edit/<?php echo $lead->lead_id; ?>" class="btn btn-outline-light btn-sm" title="Edit"><i class="fa-solid fa-user-pen"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Bulk Upload Leads via CSV -->
<div class="modal fade" id="bulkUploadLeadsModal" tabindex="-1" aria-labelledby="bulkUploadLeadsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="bulkUploadLeadsModalLabel">
                    <i class="fa-solid fa-file-csv text-success me-2"></i>Bulk Upload Leads via CSV
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="index.php?route=leads/uploadCsv" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                
                <div class="modal-body">
                    <p class="text-secondary small mb-3">
                        Upload a CSV spreadsheet containing lead columns: <code>First Name</code>, <code>Company Name</code>, <code>Email Address</code>, <code>Phone Number</code>, <code>Lead Source</code>, <code>Estimated Value ($)</code>, <code>Notes</code>.
                    </p>

                    <div class="mb-4 p-4 rounded-3 text-center" style="border: 2px dashed rgba(255, 255, 255, 0.2); background: rgba(0,0,0,0.15);">
                        <i class="fa-solid fa-cloud-arrow-up text-primary fs-1 mb-2"></i>
                        <div class="text-white fw-semibold mb-1">Select CSV File to Upload</div>
                        <span class="text-secondary small d-block mb-3">Supports .csv files up to 5MB</span>
                        <input type="file" name="csv_file" id="csv_file" class="form-control form-control-sm bg-dark border-secondary text-white" accept=".csv" required>
                    </div>

                    <div class="text-center pt-2 border-top border-secondary border-opacity-25">
                        <a href="index.php?route=leads/downloadSampleCsv" class="small text-info text-decoration-none">
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

<!-- Slide-Over Lead Preview Drawer (Sprint 2) -->
<?php
$this->component('drawer', [
    'id' => 'leadPreviewDrawer',
    'title' => 'Lead Overview',
    'subtitle' => 'Instant slide-over inspection',
    'size' => '540px',
    'body' => '<div id="leadDrawerContent" class="text-center py-5 text-secondary"><i class="fa-solid fa-spinner fa-spin me-2"></i>Loading lead profile...</div>',
    'footer' => '
        <button type="button" class="btn btn-outline-secondary btn-sm" data-drawer-dismiss="leadPreviewDrawer">Close</button>
        <a href="#" id="leadDrawerEditBtn" class="btn btn-outline-light btn-sm"><i class="fa-solid fa-user-pen me-1"></i>Edit Lead</a>
        <a href="#" id="leadDrawerFullBtn" class="btn btn-primary btn-sm"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i>Full Profile</a>
    '
]);
?>

<script>
$(function() {
    // 1. DataTables with HTML5 CSV & Excel Export Buttons
    if ($('#leads-table tbody tr').length > 1) {
        $('#leads-table').DataTable({
            pageLength: 10,
            lengthChange: false,
            info: true,
            searching: true,
            dom: '<"d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"<"d-flex align-items-center gap-2"B><"d-flex align-items-center"f>>rt<"d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3"ip>',
            buttons: [
                {
                    extend: 'csvHtml5',
                    text: '<i class="fa-solid fa-file-csv me-1 text-info"></i> CSV Export',
                    className: 'btn btn-sm btn-outline-secondary',
                    exportOptions: { columns: ':not(:last-child)' }
                },
                {
                    extend: 'excelHtml5',
                    text: '<i class="fa-solid fa-file-excel me-1 text-success"></i> Excel Export',
                    className: 'btn btn-sm btn-outline-secondary',
                    exportOptions: { columns: ':not(:last-child)' }
                }
            ],
            language: { 
                search: '',
                searchPlaceholder: 'Search leads...' 
            }
        });
    }

    // 2. Lead Preview Slide-Over Drawer Controller
    const drawerBackdrop = document.getElementById('leadPreviewDrawer-backdrop');
    const drawerElement = document.getElementById('leadPreviewDrawer');
    const drawerContent = document.getElementById('leadDrawerContent');
    const drawerTitle = document.getElementById('leadPreviewDrawer-title');
    const drawerSubtitle = document.getElementById('leadPreviewDrawer-subtitle');
    const drawerEditBtn = document.getElementById('leadDrawerEditBtn');
    const drawerFullBtn = document.getElementById('leadDrawerFullBtn');

    function openLeadDrawer(leadId) {
        if (!drawerElement || !drawerBackdrop) return;
        
        drawerBackdrop.classList.add('active');
        drawerElement.classList.add('active');
        drawerElement.setAttribute('aria-hidden', 'false');
        
        if (drawerContent) {
            drawerContent.innerHTML = `
                <div class="text-center py-5 text-secondary">
                    <i class="fa-solid fa-circle-notch fa-spin fa-2x text-primary mb-3 d-block"></i>
                    <div>Fetching lead telemetry...</div>
                </div>
            `;
        }

        fetch('index.php?route=leads/preview&id=' + encodeURIComponent(leadId), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success || !data.lead) {
                if (drawerContent) drawerContent.innerHTML = '<div class="alert alert-danger">Failed to load lead details or access denied.</div>';
                return;
            }
            const l = data.lead;
            if (drawerTitle) drawerTitle.textContent = l.name || 'Lead Overview';
            if (drawerSubtitle) drawerSubtitle.textContent = (l.lead_code ? l.lead_code + ' • ' : '') + l.company;
            if (drawerEditBtn) drawerEditBtn.href = l.edit_url;
            if (drawerFullBtn) drawerFullBtn.href = l.view_url;

            const qBadge = l.quality === 'hot' ? 'bg-danger-subtle text-danger border-danger-subtle' :
                          (l.quality === 'warm' ? 'bg-warning-subtle text-warning border-warning-subtle' : 'bg-info-subtle text-info border-info-subtle');

            let html = `
                <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
                    <span class="badge bg-dark border border-secondary text-primary font-monospace">${l.lead_code}</span>
                    <span class="badge border ${qBadge} text-uppercase">${l.quality}</span>
                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle text-uppercase">${l.status}</span>
                    <span class="badge bg-dark border border-secondary text-secondary text-uppercase">${l.priority} Priority</span>
                </div>

                <div class="raptor-glass-card p-3 mb-3">
                    <div class="row g-2 text-center">
                        <div class="col-6 border-end border-secondary border-opacity-25">
                            <span class="text-secondary small d-block">Deal Value</span>
                            <span class="text-success fw-bold fs-5">$${Number(l.value || 0).toLocaleString(undefined, {minimumFractionDigits: 2})}</span>
                        </div>
                        <div class="col-6">
                            <span class="text-secondary small d-block">Win Probability</span>
                            <span class="text-white fw-bold fs-5">${Number(l.probability || 0).toFixed(1)}%</span>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="text-secondary small fw-medium mb-1">Company / Organization</label>
                    <div class="p-2.5 bg-dark border border-secondary rounded text-white fw-medium">
                        <i class="fa-solid fa-building me-2 text-primary"></i>${$('<div>').text(l.company).html()}
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-12 col-md-6">
                        <label class="text-secondary small fw-medium mb-1">Email</label>
                        <div class="p-2 bg-dark border border-secondary rounded text-white small text-truncate">
                            ${l.email ? `<a href="mailto:${$('<div>').text(l.email).html()}" class="text-info text-decoration-none"><i class="fa-solid fa-envelope me-1.5"></i>${$('<div>').text(l.email).html()}</a>` : '<span class="text-muted">Not provided</span>'}
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="text-secondary small fw-medium mb-1">Phone</label>
                        <div class="p-2 bg-dark border border-secondary rounded text-white small">
                            ${l.phone ? `<a href="tel:${$('<div>').text(l.phone).html()}" class="text-success text-decoration-none"><i class="fa-solid fa-phone me-1.5"></i>${$('<div>').text(l.phone).html()}</a>` : '<span class="text-muted">Not provided</span>'}
                        </div>
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="text-secondary small fw-medium mb-1">Lead Source</label>
                        <div class="p-2 bg-dark border border-secondary rounded text-secondary small">${$('<div>').text(l.source).html()}</div>
                    </div>
                    <div class="col-6">
                        <label class="text-secondary small fw-medium mb-1">Account Owner</label>
                        <div class="p-2 bg-dark border border-secondary rounded text-white small text-truncate"><i class="fa-solid fa-user-tie me-1 text-warning"></i>${$('<div>').text(l.owner).html()}</div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="text-secondary small fw-medium mb-1">Notes & Remarks</label>
                    <div class="p-3 bg-dark border border-secondary rounded text-secondary small" style="max-height: 140px; overflow-y: auto;">
                        ${l.notes ? $('<div>').text(l.notes).html().replace(/\n/g, '<br>') : '<em>No remarks recorded yet.</em>'}
                    </div>
                </div>
            `;
            drawerContent.innerHTML = html;
        })
        .catch(() => {
            if (drawerContent) drawerContent.innerHTML = '<div class="alert alert-danger">Error retrieving lead preview.</div>';
        });
    }

    function closeLeadDrawer() {
        if (!drawerElement || !drawerBackdrop) return;
        drawerBackdrop.classList.remove('active');
        drawerElement.classList.remove('active');
        drawerElement.setAttribute('aria-hidden', 'true');
    }

    $(document).on('click', '.btn-lead-preview', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        if (id) openLeadDrawer(id);
    });

    $(document).on('click', '[data-drawer-dismiss="leadPreviewDrawer"]', closeLeadDrawer);
    if (drawerBackdrop) drawerBackdrop.addEventListener('click', closeLeadDrawer);

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && drawerElement && drawerElement.classList.contains('active')) {
            closeLeadDrawer();
        }
    });
});
</script>
